<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Exceptions\DocumentLockedException;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryAdjustment;
use App\Models\Material;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventory;

    protected User $user;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'key' => 'adjustment_prefix', 'value' => 'ADJ-', 'group' => 'numbers', 'type' => 'string',
        ]);

        $this->inventory = app(InventoryService::class);
        $this->user = $this->userWith(['inventory.view', 'inventory.adjust']);
        $this->material = Material::factory()->inStock('100.000')->withMinimum('20.000')->create([
            'name' => 'كابل كهربائي', 'sku' => 'CBL-1', 'cost_price' => '10.00',
        ]);

        $this->actingAs($this->user);
    }

    // ------------------------------------------------------------ stock listing

    public function test_it_lists_stock_levels_with_value_on_hand(): void
    {
        $body = $this->getJson('/api/inventory')->assertOk()->json();

        $row = $body['data'][0];
        $this->assertSame('كابل كهربائي', $row['name']);
        $this->assertSame('100.000', $row['stock_quantity']);
        // 100 units at 10.00 cost.
        $this->assertSame('1000.00', $row['stock_value']);
        $this->assertFalse($row['is_low']);
        $this->assertSame('1000.00', $body['totals']['stock_value']);
    }

    public function test_it_filters_by_stock_status(): void
    {
        Material::factory()->inStock('5.000')->withMinimum('20.000')->create(['name' => 'قليل']);
        Material::factory()->inStock('0.000')->withMinimum('10.000')->create(['name' => 'نافد']);

        $this->assertCount(1, $this->getJson('/api/inventory?status=low')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/inventory?status=out')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/inventory?status=available')->assertOk()->json('data'));

        $totals = $this->getJson('/api/inventory')->assertOk()->json('totals');
        $this->assertSame('1', $totals['low']);
        $this->assertSame('1', $totals['out_of_stock']);
    }

    public function test_it_searches_by_name_and_sku(): void
    {
        Material::factory()->inStock('5.000')->create(['name' => 'إسمنت', 'sku' => 'CEM-9']);

        $this->assertCount(1, $this->getJson('/api/inventory?search=CBL')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/inventory?search=إسمنت')->assertOk()->json('data'));
    }

    // -------------------------------------------------------------- adjustments

    public function test_an_inward_adjustment_raises_stock_and_records_a_movement(): void
    {
        $response = $this->postJson('/api/inventory/adjustments', [
            'reason' => 'جرد فعلي',
            'items' => [
                ['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '25'],
            ],
        ])->assertCreated();

        $this->assertSame('ADJ-000001', $response->json('data.adjustment_no'));
        $this->assertSame('125.000', $this->material->fresh()->stock_quantity);

        $movement = StockMovement::query()->sole();
        $this->assertSame(MovementType::ADJUSTMENT, $movement->type);
        $this->assertSame(MovementDirection::IN, $movement->direction);
        $this->assertSame('25.000', $movement->quantity);
        $this->assertSame('ADJ-000001', $movement->reference_no);
    }

    public function test_an_outward_adjustment_lowers_stock(): void
    {
        $this->postJson('/api/inventory/adjustments', [
            'reason' => 'تلف',
            'items' => [
                ['material_id' => $this->material->id, 'direction' => 'OUT', 'quantity' => '30'],
            ],
        ])->assertCreated();

        $this->assertSame('70.000', $this->material->fresh()->stock_quantity);
    }

    public function test_an_adjustment_defaults_its_unit_cost_to_the_material_cost(): void
    {
        $this->inventory->adjust([
            'reason' => 'جرد',
            'items' => [['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '5']],
        ], $this->user->id);

        $this->assertSame('10.00', StockMovement::query()->sole()->unit_price);
    }

    public function test_an_adjustment_may_cover_several_materials_at_once(): void
    {
        $second = Material::factory()->inStock('50.000')->create();

        $this->postJson('/api/inventory/adjustments', [
            'reason' => 'جرد سنوي',
            'items' => [
                ['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '10'],
                ['material_id' => $second->id, 'direction' => 'OUT', 'quantity' => '5'],
            ],
        ])->assertCreated();

        $this->assertSame('110.000', $this->material->fresh()->stock_quantity);
        $this->assertSame('45.000', $second->fresh()->stock_quantity);
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_an_outward_adjustment_cannot_exceed_stock_on_hand(): void
    {
        $this->expectException(InsufficientStockException::class);

        try {
            $this->inventory->adjust([
                'reason' => 'تلف',
                'items' => [['material_id' => $this->material->id, 'direction' => 'OUT', 'quantity' => '500']],
            ], $this->user->id);
        } finally {
            // The whole adjustment rolls back — no document, no movement, no change.
            $this->assertSame('100.000', $this->material->fresh()->stock_quantity);
            $this->assertSame(0, InventoryAdjustment::query()->count());
            $this->assertSame(0, StockMovement::query()->count());
        }
    }

    public function test_a_permissive_policy_allows_stock_to_go_negative(): void
    {
        Setting::query()->create([
            'key' => 'inventory_sale_policy', 'value' => 'permissive', 'group' => 'financial', 'type' => 'string',
        ]);

        $this->inventory->adjust([
            'reason' => 'تصحيح',
            'items' => [['material_id' => $this->material->id, 'direction' => 'OUT', 'quantity' => '150']],
        ], $this->user->id);

        $this->assertSame('-50.000', $this->material->fresh()->stock_quantity);
    }

    public function test_an_adjustment_is_rejected_without_a_reason_or_lines(): void
    {
        $this->postJson('/api/inventory/adjustments', ['items' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason', 'items']);

        $this->postJson('/api/inventory/adjustments', [
            'reason' => 'x',
            'items' => [['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '0']],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
    }

    // --------------------------------------------------------------- cancelling

    public function test_cancelling_an_adjustment_puts_the_stock_back(): void
    {
        $id = $this->postJson('/api/inventory/adjustments', [
            'reason' => 'جرد',
            'items' => [['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '25']],
        ])->json('data.id');

        $this->assertSame('125.000', $this->material->fresh()->stock_quantity);

        $this->postJson("/api/inventory/adjustments/{$id}/cancel", ['reason' => 'خطأ في الجرد'])->assertOk();

        $this->assertSame('100.000', $this->material->fresh()->stock_quantity);
        $this->assertSame(DocumentStatus::CANCELLED, InventoryAdjustment::query()->find($id)->status);
    }

    public function test_cancelling_appends_a_reversal_and_keeps_the_original(): void
    {
        $adjustment = $this->inventory->adjust([
            'reason' => 'جرد',
            'items' => [['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '25']],
        ], $this->user->id);

        $this->inventory->cancel($adjustment, $this->user->id, 'خطأ');

        $this->assertSame(2, StockMovement::query()->count());

        $reversal = StockMovement::query()->whereNotNull('cancels_id')->sole();
        $this->assertSame(MovementDirection::OUT, $reversal->direction);
        $this->assertSame('25.000', $reversal->quantity);
        $this->assertSame('خطأ', $reversal->cancelled_reason);
    }

    public function test_an_adjustment_cannot_be_cancelled_twice(): void
    {
        $adjustment = $this->inventory->adjust([
            'reason' => 'جرد',
            'items' => [['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '5']],
        ], $this->user->id);

        $this->inventory->cancel($adjustment, $this->user->id, 'خطأ');

        $this->expectException(DocumentLockedException::class);

        $this->inventory->cancel($adjustment->fresh(), $this->user->id, 'مرة أخرى');
    }

    // ------------------------------------------------------------ material view

    public function test_a_material_shows_its_movement_history_with_a_running_quantity(): void
    {
        $this->inventory->adjust([
            'reason' => 'أول',
            'items' => [['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '10']],
        ], $this->user->id);

        $this->inventory->adjust([
            'reason' => 'ثانٍ',
            'items' => [['material_id' => $this->material->id, 'direction' => 'OUT', 'quantity' => '4']],
        ], $this->user->id);

        $body = $this->getJson("/api/inventory/materials/{$this->material->id}")->assertOk()->json();

        $this->assertSame('106.000', $body['data']['material']['stock_quantity']);
        $this->assertCount(2, $body['data']['movements']);
        $this->assertSame('10.000', $body['data']['movements'][0]['running_quantity']);
        $this->assertSame('6.000', $body['data']['movements'][1]['running_quantity']);
    }

    public function test_adjustment_history_is_listed_newest_first(): void
    {
        foreach (['أول', 'ثانٍ'] as $reason) {
            $this->inventory->adjust([
                'reason' => $reason,
                'items' => [['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '1']],
            ], $this->user->id);
        }

        $rows = $this->getJson('/api/inventory/adjustments')->assertOk()->json('data');

        $this->assertCount(2, $rows);
        $this->assertSame('ثانٍ', $rows[0]['reason']);
        $this->assertSame('ADJ-000002', $rows[0]['adjustment_no']);
    }

    // ---------------------------------------------------------------- security

    public function test_viewing_requires_the_inventory_view_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/inventory')->assertForbidden();
        $this->getJson('/api/inventory/adjustments')->assertForbidden();
    }

    public function test_adjusting_requires_its_own_permission(): void
    {
        // A viewer may read stock but must not be able to change it.
        $this->actingAs($this->userWith(['inventory.view']));

        $this->getJson('/api/inventory')->assertOk();

        $this->postJson('/api/inventory/adjustments', [
            'reason' => 'محاولة',
            'items' => [['material_id' => $this->material->id, 'direction' => 'IN', 'quantity' => '1']],
        ])->assertForbidden();
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param  array<int, string>  $permissions
     */
    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'دور', 'slug' => 'role-'.uniqid()]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'inventory']
            ));
        }

        $user->roles()->attach($role);

        return $user;
    }
}
