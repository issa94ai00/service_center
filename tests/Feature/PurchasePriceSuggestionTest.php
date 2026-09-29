<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Permission;
use App\Models\PurchaseInvoice;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اقتراح أفضل مورد يقارن آخر كلفة فعلية من كل مورد في الفواتير المُرحَّلة.
 */
class PurchasePriceSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoices;

    protected User $user;

    protected Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'key' => 'purchase_prefix', 'value' => 'PR-', 'group' => 'numbers', 'type' => 'string',
        ]);

        $this->invoices = app(InvoiceService::class);
        $this->user = $this->userWith(['purchases.create']);
        $this->material = Material::factory()->create();
    }

    public function test_it_suggests_the_supplier_with_the_lowest_latest_price(): void
    {
        $cheap = Supplier::factory()->create(['name' => 'رخيص']);
        $dear = Supplier::factory()->create(['name' => 'غالي']);

        // كان الأرخص قديماً، لكن آخر سعر منه ارتفع: العبرة بالأحدث.
        $this->purchase($dear, '5.00', now()->subDays(20));
        $this->purchase($dear, '12.00', now()->subDays(2));
        $this->purchase($cheap, '10.00', now()->subDays(5));

        $row = $this->suggest(['supplier_id' => $dear->id])->json('data.0');

        $this->assertSame($cheap->id, $row['best']['supplier_id']);
        $this->assertSame('10.00', $row['best']['unit_cost']);
        $this->assertSame('12.00', $row['selected_supplier']['unit_cost']);
        $this->assertCount(2, $row['suppliers']);
    }

    public function test_invoice_level_discount_counts_toward_the_unit_cost(): void
    {
        $a = Supplier::factory()->create();
        $b = Supplier::factory()->create();

        $this->purchase($a, '10.00', now()->subDay());
        // 11 قبل الخصم، لكن خصم 20% على الفاتورة يجعلها 8.80.
        $this->purchase($b, '11.00', now()->subDay(), 'PERCENT', '20');

        $row = $this->suggest()->json('data.0');

        $this->assertSame($b->id, $row['best']['supplier_id']);
        $this->assertSame('8.80', $row['best']['unit_cost']);
    }

    public function test_drafts_and_old_invoices_are_ignored(): void
    {
        $supplier = Supplier::factory()->create();

        $this->purchase($supplier, '3.00', now()->subDays(400));
        $this->draft($supplier, '1.00', now());

        $this->suggest()->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_it_requires_permission_to_create_purchases(): void
    {
        $this->actingAs($this->userWith([]))
            ->getJson('/api/purchases/price-suggestions?material_ids[]='.$this->material->id)
            ->assertForbidden();
    }

    private function suggest(array $params = [])
    {
        return $this->actingAs($this->user)->getJson('/api/purchases/price-suggestions?'.http_build_query(
            ['material_ids' => [$this->material->id]] + $params
        ))->assertOk();
    }

    private function draft(Supplier $supplier, string $price, $date, string $type = 'NONE', string $value = '0'): PurchaseInvoice
    {
        return $this->invoices->savePurchaseDraft(new PurchaseInvoice, [
            'supplier_id' => $supplier->id,
            'purchase_date' => $date->toDateString(),
            'discount_type' => $type,
            'discount_value' => $value,
            'items' => [['material_id' => $this->material->id, 'quantity' => '10', 'unit_price' => $price]],
        ], $this->user->id);
    }

    private function purchase(Supplier $supplier, string $price, $date, string $type = 'NONE', string $value = '0'): PurchaseInvoice
    {
        return $this->invoices->postPurchase($this->draft($supplier, $price, $date, $type, $value), $this->user->id);
    }

    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        if ($permissions === []) {
            return $user;
        }

        $role = Role::query()->create(['name' => 'دور '.$user->id, 'slug' => 'role-'.$user->id]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(
                Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => explode('.', $slug)[0]])
            );
        }

        $user->roles()->attach($role);

        return $user;
    }
}
