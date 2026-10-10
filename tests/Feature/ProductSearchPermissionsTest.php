<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceUnit;
use App\Models\User;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected Material $material;
    protected Service $service;
    protected ServiceUnit $unit;
    protected MaterialCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemConfigSeeder::class);

        $this->unit = ServiceUnit::firstOrCreate(['name_ar' => 'قطعة'], [
            'name_en' => 'Piece',
            'symbol' => 'قطعة',
            'category' => 'COUNT',
            'is_measurable' => true,
        ]);

        $this->category = MaterialCategory::firstOrCreate(['name' => 'شاشات'], ['description' => null]);

        $this->material = Material::create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'شاشة آيفون 13 برو',
            'sku' => 'IPH13P-SCR-12345',
            'barcode' => '6901234567890',
            'sale_price' => '150.00',
            'cost_price' => '100.00',
            'stock_quantity' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'unit_id' => $this->unit->id,
            'name' => 'تبديل شاشة وضمان',
            'type' => 'MANUAL',
            'default_price' => '30.00',
            'is_active' => true,
        ]);
    }

    public function test_admin_role_can_search_and_lookup_products(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'مدير النظام']);
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin);

        // Materials index search
        $res = $this->getJson('/api/materials?search=آيفون')->assertOk();
        $this->assertNotEmpty($res->json('data'));
        $this->assertSame($this->material->id, $res->json('data.0.id'));

        // Materials lookup by barcode
        $lookup = $this->getJson('/api/materials/lookup?code=6901234567890')->assertOk();
        $this->assertSame($this->material->id, $lookup->json('data.id'));

        // Services index search
        $srvRes = $this->getJson('/api/services?search=تبديل')->assertOk();
        $this->assertNotEmpty($srvRes->json('data'));
        $this->assertSame($this->service->id, $srvRes->json('data.0.id'));
    }

    public function test_sales_user_without_explicit_materials_view_can_search_products(): void
    {
        $salesUser = User::factory()->create();
        $salesRole = Role::create(['name' => 'بائع', 'slug' => 'sales-clerk-' . uniqid()]);
        $salesRole->permissions()->attach(Permission::where('slug', 'sales.create')->firstOrFail());
        $salesUser->roles()->attach($salesRole);

        $this->actingAs($salesUser);

        // Can search materials for sale order
        $res = $this->getJson('/api/materials?search=آيفون')->assertOk();
        $this->assertNotEmpty($res->json('data'));

        // Can lookup material barcode
        $lookup = $this->getJson('/api/materials/lookup?code=6901234567890')->assertOk();
        $this->assertSame($this->material->id, $lookup->json('data.id'));

        // Can search services for sale order
        $srvRes = $this->getJson('/api/services?search=تبديل')->assertOk();
        $this->assertNotEmpty($srvRes->json('data'));
    }

    public function test_purchase_user_without_explicit_materials_view_can_search_materials(): void
    {
        $purchaseUser = User::factory()->create();
        $purchaseRole = Role::create(['name' => 'مشتريات', 'slug' => 'purchase-clerk-' . uniqid()]);
        $purchaseRole->permissions()->attach(Permission::where('slug', 'purchases.create')->firstOrFail());
        $purchaseUser->roles()->attach($purchaseRole);

        $this->actingAs($purchaseUser);

        // Can search materials for purchase order
        $res = $this->getJson('/api/materials?search=IPH13P')->assertOk();
        $this->assertNotEmpty($res->json('data'));

        // Can lookup material by SKU
        $lookup = $this->getJson('/api/materials/lookup?code=IPH13P-SCR-12345')->assertOk();
        $this->assertSame($this->material->id, $lookup->json('data.id'));
    }

    public function test_search_and_lookup_supports_arabic_digits(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'مدير النظام']);
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin);

        // Barcode with Eastern Arabic numerals: 6901234567890 -> ٦٩٠١٢٣٤٥٦٧٨٩٠
        $arabicBarcode = '٦٩٠١٢٣٤٥٦٧٨٩٠';
        $lookup = $this->getJson("/api/materials/lookup?code={$arabicBarcode}")->assertOk();
        $this->assertSame($this->material->id, $lookup->json('data.id'));

        // Search with Arabic numerals: 12345 -> ١٢٣٤٥
        $searchRes = $this->getJson('/api/materials?search=١٢٣٤٥')->assertOk();
        $this->assertNotEmpty($searchRes->json('data'));
        $this->assertSame($this->material->id, $searchRes->json('data.0.id'));
    }

    public function test_user_without_any_relevant_permission_is_forbidden(): void
    {
        $unauthorizedUser = User::factory()->create();

        $this->actingAs($unauthorizedUser);

        $this->getJson('/api/materials?search=آيفون')->assertForbidden();
        $this->getJson('/api/materials/lookup?code=6901234567890')->assertForbidden();
        $this->getJson('/api/services?search=تبديل')->assertForbidden();
    }
}
