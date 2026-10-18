<?php

namespace Tests\Feature;

use App\Enums\CostMethod;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Permission;
use App\Models\PurchaseInvoice;
use App\Models\Role;
use App\Models\ServiceUnit;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostMethodTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoices;

    protected InventoryService $inventory;

    protected User $user;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['key' => 'purchase_prefix', 'value' => 'PR-', 'group' => 'numbers', 'type' => 'string'],
            ['key' => 'adjustment_prefix', 'value' => 'ADJ-', 'group' => 'numbers', 'type' => 'string'],
        ] as $setting) {
            Setting::query()->create($setting);
        }

        $this->invoices = app(InvoiceService::class);
        $this->inventory = app(InventoryService::class);
        $this->user = $this->userWith(['materials.view', 'materials.create', 'inventory.adjust']);
        $this->supplier = Supplier::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_a_new_material_inherits_the_shop_default_cost_method(): void
    {
        Setting::query()->create([
            'key' => 'inventory_cost_method', 'value' => 'WEIGHTED_AVERAGE', 'group' => 'financial', 'type' => 'string',
        ]);

        $this->postJson('/api/materials', $this->materialPayload())
            ->assertCreated()
            ->assertJsonPath('data.cost_method', 'WEIGHTED_AVERAGE');
    }

    public function test_a_new_material_is_manual_without_a_shop_default(): void
    {
        $this->postJson('/api/materials', $this->materialPayload())
            ->assertCreated()
            ->assertJsonPath('data.cost_method', 'MANUAL');
    }

    public function test_an_unknown_cost_method_is_rejected(): void
    {
        $this->postJson('/api/materials', $this->materialPayload(['cost_method' => 'LIFO']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cost_method');
    }

    public function test_weighted_average_blends_the_cost_of_a_purchase(): void
    {
        $material = $this->material('WEIGHTED_AVERAGE', cost: '10.00', stock: '10.000');

        $this->postPurchase($material, '10', '20.00');

        $this->assertSame('15.00', $material->fresh()->cost_price);
    }

    public function test_weighted_average_uses_the_price_net_of_line_discount(): void
    {
        $material = $this->material('WEIGHTED_AVERAGE', cost: '10.00', stock: '10.000');

        $this->postPurchase($material, '10', '20.00', discount: '20.00');

        // (10 × 10.00 + 180.00) ÷ 20 = 14.00
        $this->assertSame('14.00', $material->fresh()->cost_price);
    }

    public function test_last_purchase_replaces_the_cost(): void
    {
        $material = $this->material('LAST_PURCHASE', cost: '10.00', stock: '10.000');

        $this->postPurchase($material, '5', '22.50');

        $this->assertSame('22.50', $material->fresh()->cost_price);
    }

    public function test_a_manual_material_keeps_its_cost_when_bought(): void
    {
        $material = $this->material('MANUAL', cost: '10.00', stock: '10.000');

        $this->postPurchase($material, '10', '20.00');

        $this->assertSame('10.00', $material->fresh()->cost_price);
    }

    public function test_weighted_average_blends_the_cost_of_an_inbound_adjustment(): void
    {
        $material = $this->material('WEIGHTED_AVERAGE', cost: '10.00', stock: '100.000');

        $this->inventory->adjust([
            'reason' => 'جرد فعلي',
            'items' => [[
                'material_id' => $material->id,
                'direction' => 'IN',
                'quantity' => '100',
                'unit_cost' => '20.00',
            ]],
        ], $this->user->id);

        $this->assertSame('15.00', $material->fresh()->cost_price);
    }

    // ------------------------------------------------------------------ helpers

    protected function material(string $method, string $cost, string $stock): Material
    {
        return Material::factory()->inStock($stock)->create([
            'cost_price' => $cost,
            'cost_method' => CostMethod::from($method),
        ]);
    }

    protected function postPurchase(Material $material, string $quantity, string $price, ?string $discount = null): void
    {
        $line = [
            'material_id' => $material->id,
            'quantity' => $quantity,
            'unit_price' => $price,
        ];

        if ($discount !== null) {
            $line['discount_amount'] = $discount;
        }

        $invoice = $this->invoices->savePurchaseDraft(new PurchaseInvoice, [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => '2026-05-10',
            'items' => [$line],
        ], $this->user->id);

        $this->invoices->postPurchase($invoice, $this->user->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function materialPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'شاحن سريع',
            'unit_id' => ServiceUnit::factory()->create()->id,
            'category_id' => MaterialCategory::factory()->create()->id,
            'cost_price' => '10.00',
            'sale_price' => '20.00',
        ], $overrides);
    }

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
