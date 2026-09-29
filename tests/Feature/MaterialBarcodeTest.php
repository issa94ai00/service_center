<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الباركود ومطابقته عند الصندوق.
 *
 * الماسح يرسل رمزاً واحداً وينتظر مادة واحدة؛ كل ما يلي يدور حول أن تكون تلك
 * المادة هي الصحيحة، وأن يكون «لا نتيجة» جواباً مفهوماً لا عطلاً.
 */
class MaterialBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_material_can_be_created_with_a_barcode(): void
    {
        $this->actingAs($this->userWith(['materials.create']));

        $unit = \App\Models\ServiceUnit::factory()->create();

        $this->postJson('/api/materials', [
            'name' => 'شاحن سريع',
            'sku' => 'CHG-1',
            'barcode' => '6291041500213',
            'unit_id' => $unit->id,
            'cost_price' => '5000',
            'sale_price' => '8000',
        ])->assertCreated()->assertJsonPath('data.barcode', '6291041500213');
    }

    public function test_two_materials_may_not_share_a_barcode(): void
    {
        Material::factory()->create(['barcode' => '6291041500213']);
        $this->actingAs($this->userWith(['materials.create']));

        $unit = \App\Models\ServiceUnit::factory()->create();

        $this->postJson('/api/materials', [
            'name' => 'آخر',
            'sku' => 'CHG-2',
            'barcode' => '6291041500213',
            'unit_id' => $unit->id,
            'cost_price' => '1', 'sale_price' => '2',
        ])->assertStatus(422)->assertJsonValidationErrors('barcode');
    }

    public function test_many_materials_may_have_no_barcode_at_all(): void
    {
        $this->actingAs($this->userWith(['materials.create']));
        $unit = \App\Models\ServiceUnit::factory()->create();

        foreach (['A', 'B', 'C'] as $sku) {
            $this->postJson('/api/materials', [
                'name' => "مادة {$sku}",
                'sku' => $sku,
                // سلسلة فارغة لا NULL: هكذا يرسلها النموذج حين يترك الحقل فارغاً.
                'barcode' => '',
                'unit_id' => $unit->id,
                'cost_price' => '1', 'sale_price' => '2',
            ])->assertCreated()->assertJsonPath('data.barcode', null);
        }

        $this->assertSame(3, Material::query()->whereNull('barcode')->count());
    }

    public function test_lookup_finds_a_material_by_its_barcode(): void
    {
        $material = Material::factory()->create(['barcode' => '6291041500213', 'name' => 'شاحن']);
        $this->actingAs($this->userWith(['materials.view']));

        $this->getJson('/api/materials/lookup?code=6291041500213')
            ->assertOk()
            ->assertJsonPath('data.id', $material->id)
            ->assertJsonPath('data.name', 'شاحن');
    }

    public function test_lookup_falls_back_to_the_sku(): void
    {
        // المحل الذي يطبع رمزه الداخلي باركوداً يجب أن يعمل بلا إدخال مزدوج.
        $material = Material::factory()->create(['sku' => 'INTERNAL-9', 'barcode' => null]);
        $this->actingAs($this->userWith(['materials.view']));

        $this->getJson('/api/materials/lookup?code=INTERNAL-9')
            ->assertOk()
            ->assertJsonPath('data.id', $material->id);
    }

    public function test_a_barcode_wins_over_another_materials_sku(): void
    {
        $bySku = Material::factory()->create(['sku' => '12345', 'barcode' => null]);
        $byBarcode = Material::factory()->create(['sku' => 'OTHER', 'barcode' => '12345']);

        $this->actingAs($this->userWith(['materials.view']));

        $this->getJson('/api/materials/lookup?code=12345')
            ->assertOk()
            ->assertJsonPath('data.id', $byBarcode->id);

        $this->assertNotSame($bySku->id, $byBarcode->id);
    }

    public function test_lookup_ignores_inactive_materials(): void
    {
        Material::factory()->create(['barcode' => '999', 'is_active' => false]);
        $this->actingAs($this->userWith(['materials.view']));

        $this->getJson('/api/materials/lookup?code=999')->assertNotFound();
    }

    public function test_an_unknown_code_is_a_plain_not_found(): void
    {
        $this->actingAs($this->userWith(['materials.view']));

        $this->getJson('/api/materials/lookup?code=nothing-here')
            ->assertNotFound()
            ->assertJsonPath('data', null);
    }

    public function test_an_empty_code_is_rejected(): void
    {
        $this->actingAs($this->userWith(['materials.view']));

        $this->getJson('/api/materials/lookup?code=')->assertStatus(422);
    }

    public function test_lookup_requires_permission_to_see_materials(): void
    {
        Material::factory()->create(['barcode' => '111']);
        $this->actingAs($this->userWith([]));

        $this->getJson('/api/materials/lookup?code=111')->assertForbidden();
    }

    public function test_the_materials_search_also_matches_a_barcode(): void
    {
        $material = Material::factory()->create(['barcode' => '6291041500213', 'name' => 'شاحن']);
        $this->actingAs($this->userWith(['materials.view']));

        $this->getJson('/api/materials?search=629104')
            ->assertOk()
            ->assertJsonPath('data.0.id', $material->id);
    }

    /**
     * الإضافة السريعة من فاتورة الشراء.
     *
     * البضاعة تصل بلا رمز أحياناً، والموظّف واقف أمام المورد: إلزامه باختراع
     * رمز فريد في تلك اللحظة هو ما يُنتج مواد اسمها «1» و«جديد».
     */
    public function test_a_material_may_be_created_without_a_sku(): void
    {
        $this->actingAs($this->userWith(['materials.create']));
        $unit = \App\Models\ServiceUnit::factory()->create();

        $response = $this->postJson('/api/materials', [
            'name' => 'كفر شفاف',
            'unit_id' => $unit->id,
            'cost_price' => '1500',
            'sale_price' => '2500',
        ])->assertCreated();

        $this->assertSame('M-000001', $response->json('data.sku'));
    }

    public function test_generated_skus_do_not_collide(): void
    {
        $this->actingAs($this->userWith(['materials.create']));
        $unit = \App\Models\ServiceUnit::factory()->create();

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/materials', [
                'name' => "مادة {$i}",
                'sku' => '',
                'unit_id' => $unit->id,
                'cost_price' => '1', 'sale_price' => '2',
            ])->assertCreated();
        }

        $this->assertSame(3, Material::query()->distinct()->count('sku'));
    }

    /** رمز مكتوب باليد يقع على الرقم التالي في السلسلة لا يجوز أن يُفشل الحفظ. */
    public function test_a_generated_sku_steps_over_one_typed_by_hand(): void
    {
        Material::factory()->create(['sku' => 'M-000001']);
        $this->actingAs($this->userWith(['materials.create']));
        $unit = \App\Models\ServiceUnit::factory()->create();

        $this->postJson('/api/materials', [
            'name' => 'مادة تالية',
            'unit_id' => $unit->id,
            'cost_price' => '1', 'sale_price' => '2',
        ])->assertCreated()->assertJsonPath('data.sku', 'M-000002');
    }

    public function test_a_typed_sku_is_still_honoured(): void
    {
        $this->actingAs($this->userWith(['materials.create']));
        $unit = \App\Models\ServiceUnit::factory()->create();

        $this->postJson('/api/materials', [
            'name' => 'مادة مرقّمة يدوياً',
            'sku' => 'HOUSE-7',
            'unit_id' => $unit->id,
            'cost_price' => '1', 'sale_price' => '2',
        ])->assertCreated()->assertJsonPath('data.sku', 'HOUSE-7');
    }

    /** التحديث الجزئي لا يعني «رقّمها من جديد»: الرمز القديم هو ما يعرفه المحل. */
    public function test_an_update_that_omits_the_sku_keeps_the_old_one(): void
    {
        $material = Material::factory()->create(['sku' => 'OLD-1']);
        $this->actingAs($this->userWith(['materials.edit']));

        $this->putJson("/api/materials/{$material->id}", ['name' => 'اسم جديد'])
            ->assertOk()
            ->assertJsonPath('data.sku', 'OLD-1');
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        if ($permissions === []) {
            return $user;
        }

        $role = Role::query()->create(['name' => 'دور '.$user->id, 'slug' => 'role-'.$user->id]);

        foreach ($permissions as $slug) {
            $role->permissions()->attach(
                Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $slug, 'group' => explode('.', $slug)[0]]
                )
            );
        }

        $user->roles()->attach($role);

        return $user;
    }
}
