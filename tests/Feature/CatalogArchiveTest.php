<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\Customer;
use App\Models\Material;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\Service;
use App\Models\ServiceUnit;
use App\Models\Setting;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Archiving materials and services: the everyday "delete" that keeps history
 * readable and can be undone, with permanent deletion kept for mistakes.
 */
class CatalogArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create(['key' => 'adjustment_prefix', 'value' => 'ADJ-', 'group' => 'numbers', 'type' => 'string']);

        $this->user = $this->userWith([
            'materials.view', 'materials.create', 'materials.edit', 'materials.delete',
            'services.view', 'services.delete', 'sales.create', 'inventory.adjust',
        ]);
        $this->actingAs($this->user);
    }

    // ---------------------------------------------------------------- archive

    public function test_a_material_with_sales_history_can_be_archived_and_restored(): void
    {
        $material = Material::factory()->create(['name' => 'شاشة', 'stock_quantity' => 0]);
        $this->invoiceWith($material, DocumentStatus::POSTED);

        $this->deleteJson("/api/materials/{$material->id}")->assertOk();

        $this->assertSoftDeleted($material);
        $this->getJson('/api/materials')->assertJsonMissing(['id' => $material->id])->assertJsonPath('counts.archived', 1);
        $this->getJson('/api/materials?status=archived')
            ->assertJsonPath('data.0.id', $material->id)
            ->assertJsonPath('data.0.is_referenced', true);

        $this->postJson("/api/materials/{$material->id}/restore")->assertOk()->assertJsonPath('data.archived_at', null);

        $this->assertNotSoftDeleted($material);
    }

    public function test_a_material_still_in_stock_cannot_be_archived(): void
    {
        $material = Material::factory()->create(['stock_quantity' => '3.000']);

        $this->deleteJson("/api/materials/{$material->id}")
            ->assertConflict()
            ->assertJsonFragment(['message' => "لا يمكن أرشفة «{$material->name}» وفي المخزون منها 3 — صفّر الرصيد بتسوية مخزنية أولاً، حتى لا تختفي قيمتها من الجرد."]);

        $this->assertNotSoftDeleted($material);
    }

    public function test_an_item_on_a_draft_invoice_cannot_be_archived(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $this->invoiceWith($material, DocumentStatus::DRAFT);

        $this->deleteJson("/api/materials/{$material->id}")->assertConflict();

        $this->assertNotSoftDeleted($material);
    }

    public function test_a_service_used_on_invoices_can_be_archived(): void
    {
        $service = Service::factory()->create();
        $this->invoiceWith($service, DocumentStatus::POSTED);

        $this->deleteJson("/api/services/{$service->id}")->assertOk();

        $this->assertSoftDeleted($service);
        $this->getJson('/api/services?status=archived')->assertJsonPath('data.0.is_referenced', true);
    }

    // ---------------------------------------------------------- force delete

    public function test_only_an_archived_unused_item_can_be_deleted_for_good(): void
    {
        $unused = Material::factory()->create(['stock_quantity' => 0]);
        $used = Material::factory()->create(['stock_quantity' => 0]);
        $this->invoiceWith($used, DocumentStatus::POSTED);

        // Not archived yet.
        $this->deleteJson("/api/materials/{$unused->id}/force")->assertConflict();

        $unused->delete();
        $used->delete();

        $this->deleteJson("/api/materials/{$used->id}/force")->assertConflict();
        $this->deleteJson("/api/materials/{$unused->id}/force")->assertOk();

        $this->assertDatabaseMissing('materials', ['id' => $unused->id]);
        $this->assertSoftDeleted($used);
    }

    public function test_archiving_requires_the_delete_permission(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $material->delete();
        $this->actingAs($this->userWith(['materials.view']));

        $this->postJson("/api/materials/{$material->id}/restore")->assertForbidden();
        $this->deleteJson("/api/materials/{$material->id}/force")->assertForbidden();
    }

    // ------------------------------------------------------ history and reuse

    public function test_an_archived_item_cannot_go_on_a_new_invoice_but_old_ones_still_name_it(): void
    {
        $material = Material::factory()->create(['name' => 'بطارية', 'stock_quantity' => 0]);
        $invoice = $this->invoiceWith($material, DocumentStatus::POSTED);
        $material->delete();

        $this->assertSame('بطارية', $invoice->items()->first()->material->name);

        $this->postJson('/api/sales', [
            'customer_id' => $invoice->customer_id,
            'items' => [[
                'line_type' => 'MATERIAL', 'material_id' => $material->id, 'unit_id' => $material->unit_id,
                'quantity' => 1, 'unit_price' => 10,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.material_id');
    }

    public function test_cancelling_an_old_adjustment_still_moves_an_archived_materials_stock(): void
    {
        $material = Material::factory()->create(['stock_quantity' => 0]);
        $inventory = app(InventoryService::class);

        $inventory->adjust(['reason' => 'جرد', 'items' => [['material_id' => $material->id, 'direction' => 'IN', 'quantity' => '5']]], $this->user->id);
        $out = $inventory->adjust(['reason' => 'تالف', 'items' => [['material_id' => $material->id, 'direction' => 'OUT', 'quantity' => '5']]], $this->user->id);

        $this->deleteJson("/api/materials/{$material->id}")->assertOk();

        $inventory->cancel($out, $this->user->id, 'خطأ');

        $this->assertSame('5.000', Material::withTrashed()->find($material->id)->stock_quantity);
    }

    public function test_a_code_held_by_an_archived_material_points_to_restoring_it(): void
    {
        $archived = Material::factory()->create(['name' => 'كابل قديم', 'barcode' => '6291041500213', 'stock_quantity' => 0]);
        $archived->delete();

        $this->postJson('/api/materials', [
            'name' => 'كابل جديد', 'barcode' => '6291041500213', 'unit_id' => $archived->unit_id,
            'cost_price' => 1, 'sale_price' => 2,
        ])->assertStatus(422)->assertJsonFragment([
            'barcode' => ['الباركود مسجَّل على المادة المؤرشفة «كابل قديم» — استعدها من الأرشيف بدل إنشاء مادة جديدة.'],
        ]);
    }

    public function test_scanning_an_archived_material_says_it_is_archived(): void
    {
        $material = Material::factory()->create(['barcode' => '111222333', 'stock_quantity' => 0]);
        $material->delete();

        $this->getJson('/api/materials/lookup?code=111222333')
            ->assertNotFound()
            ->assertJsonPath('archived', true);
    }

    // ---------------------------------------------------------------- helpers

    protected function invoiceWith(Material|Service $item, DocumentStatus $status): SalesInvoice
    {
        $invoice = SalesInvoice::query()->create([
            'invoice_no' => $status === DocumentStatus::DRAFT ? null : 'SL-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id,
            'invoice_date' => now()->toDateString(),
            'status' => $status,
            'subtotal' => '10.00', 'total' => '10.00', 'remaining' => '10.00',
            'created_by' => $this->user->id,
        ]);

        $invoice->items()->create([
            'line_type' => $item instanceof Material ? 'MATERIAL' : 'SERVICE',
            'material_id' => $item instanceof Material ? $item->id : null,
            'service_id' => $item instanceof Service ? $item->id : null,
            'unit_id' => $item->unit_id ?? ServiceUnit::factory()->create()->id,
            'description' => $item->name,
            'quantity' => 1, 'unit_price' => '10.00', 'line_total' => '10.00',
        ]);

        return $invoice;
    }

    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'دور '.$user->id, 'slug' => 'role-'.$user->id]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => explode('.', $slug)[0]],
            ));
        }

        $user->roles()->attach($role);

        return $user->fresh();
    }
}
