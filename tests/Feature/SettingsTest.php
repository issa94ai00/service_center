<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['company_name', 'شركة الإكساء', 'general', 'string'],
            ['currency_symbol', 'ل.س', 'general', 'string'],
            ['currency_name', 'ليرة سورية', 'general', 'string'],
            ['allow_overpayment', '0', 'financial', 'bool'],
            ['inventory_sale_policy', 'strict', 'financial', 'string'],
            ['invoice_prefix', 'SL-', 'numbers', 'string'],
            ['repair_invoice_prefix', 'RS-', 'numbers', 'string'],
            ['repair_payment_prefix', 'RCV-', 'numbers', 'string'],
        ] as [$key, $value, $group, $type]) {
            Setting::query()->updateOrCreate(['key' => $key], compact('value', 'group', 'type'));
        }

        $this->actingAs($this->userWith(['settings.manage']));
    }

    public function test_it_lists_settings_grouped_and_typed(): void
    {
        $rows = $this->getJson('/api/settings')->assertOk()->json('data');

        $byKey = collect($rows)->keyBy('key');

        // كل مفتاح مزروع يعود في القائمة. العدد نفسه ليس ثابتاً: الهجرات تزرع
        // مفاتيحها أيضاً — بادئة سندات المصاريف مثلاً — وتثبيته هنا يكسر
        // الاختبار كلما أُضيف إعداد لا علاقة له به.
        $this->assertEmpty(array_diff([
            'company_name', 'currency_symbol', 'currency_name', 'allow_overpayment',
            'inventory_sale_policy', 'invoice_prefix', 'repair_invoice_prefix', 'repair_payment_prefix',
        ], $byKey->keys()->all()));
        $this->assertSame('general', $byKey['company_name']['group']);
        $this->assertSame('bool', $byKey['allow_overpayment']['type']);
        // The bool accessor casts, so the UI gets a real boolean.
        $this->assertFalse($byKey['allow_overpayment']['value']);
    }

    public function test_it_updates_values(): void
    {
        $this->putJson('/api/settings', ['values' => [
            'company_name' => 'شركة جديدة',
            'invoice_prefix' => 'INV-',
        ]])->assertOk();

        $this->assertSame('شركة جديدة', Setting::get('company_name'));
        $this->assertSame('INV-', Setting::get('invoice_prefix'));
    }

    public function test_a_boolean_is_stored_as_zero_not_an_empty_string(): void
    {
        $this->putJson('/api/settings', ['values' => ['allow_overpayment' => 1]])->assertOk();
        $this->assertSame('1', Setting::query()->where('key', 'allow_overpayment')->value('value'));

        $this->putJson('/api/settings', ['values' => ['allow_overpayment' => 0]])->assertOk();

        // (string) false would have written "", which reads back as a blank.
        $this->assertSame('0', Setting::query()->where('key', 'allow_overpayment')->value('value'));
        $this->assertFalse(Setting::get('allow_overpayment'));
    }

    public function test_the_inventory_policy_only_accepts_known_values(): void
    {
        // A typo here would read as "not strict" and silently disable the
        // negative-stock guard on every sale and adjustment.
        $this->putJson('/api/settings', ['values' => ['inventory_sale_policy' => 'strictt']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('values.inventory_sale_policy');

        $this->assertSame('strict', Setting::get('inventory_sale_policy'));

        $this->putJson('/api/settings', ['values' => ['inventory_sale_policy' => 'permissive']])->assertOk();
        $this->assertSame('permissive', Setting::get('inventory_sale_policy'));
    }

    public function test_essential_settings_cannot_be_blanked(): void
    {
        foreach (['company_name', 'currency_symbol', 'currency_name'] as $key) {
            $this->putJson('/api/settings', ['values' => [$key => '']])
                ->assertStatus(422)
                ->assertJsonValidationErrors("values.{$key}");
        }

        $this->assertSame('شركة الإكساء', Setting::get('company_name'));
    }

    public function test_an_optional_setting_may_be_cleared(): void
    {
        Setting::query()->create([
            'key' => 'company_phone', 'value' => '0111', 'group' => 'general', 'type' => 'string',
        ]);

        $this->putJson('/api/settings', ['values' => ['company_phone' => '']])->assertOk();

        $this->assertSame('', Setting::get('company_phone'));
    }

    public function test_unknown_keys_are_ignored_rather_than_created(): void
    {
        $this->putJson('/api/settings', ['values' => [
            'company_name' => 'شركة الإكساء',
            'not_a_real_setting' => 'x',
        ]])->assertOk();

        $this->assertNull(Setting::query()->where('key', 'not_a_real_setting')->first());
    }

    public function test_each_document_kind_accepts_only_the_papers_it_has_a_layout_for(): void
    {
        $this->putJson('/api/settings', ['values' => [
            'print_paper_repair' => '58mm',
            'print_paper_invoice' => 'a5',
        ]])->assertOk();

        $this->assertSame('58mm', Setting::get('print_paper_repair'));
        $this->assertSame('a5', Setting::get('print_paper_invoice'));

        $this->putJson('/api/settings', ['values' => ['print_paper_statement' => '80mm']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.print_paper_statement');

        $this->putJson('/api/settings', ['values' => ['print_paper_payment' => 'letter']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.print_paper_payment');

        $this->putJson('/api/settings', ['values' => ['print_paper_invoice' => '']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.print_paper_invoice');
    }

    public function test_printed_text_is_kept_short_enough_for_a_receipt(): void
    {
        $this->putJson('/api/settings', ['values' => ['print_footer_note' => str_repeat('ش', 301)]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('values.print_footer_note');

        $this->putJson('/api/settings', ['values' => [
            'print_footer_note' => 'شكرًا لزيارتكم',
            'print_show_signatures' => false,
        ]])->assertOk();

        $this->assertSame('شكرًا لزيارتكم', Setting::get('print_footer_note'));
        $this->assertFalse(Setting::get('print_show_signatures'));
    }

    public function test_master_user_can_login_with_credentials_and_has_master_role(): void
    {
        (new \Database\Seeders\SystemConfigSeeder)->run();
        (new \Database\Seeders\AdminUserSeeder)->run();

        $response = $this->postJson('/api/login', [
            'email' => 'master@damatech.sy',
            'password' => 'master123',
        ])->assertOk();

        $user = $response->json('user');
        $this->assertSame('master@damatech.sy', $user['email']);
        $roleSlugs = collect($user['roles'])->pluck('slug')->all();
        $this->assertContains('master', $roleSlugs);
    }

    public function test_features_can_be_listed_and_toggled_on_and_off_by_master(): void
    {
        $this->actingAs($this->masterUser());

        $features = $this->getJson('/api/features')->assertOk()->json('data');
        $this->assertArrayHasKey('feature_repairs', $features);
        $this->assertTrue($features['feature_repairs']);

        // Toggle repairs off
        $this->putJson('/api/settings', ['values' => [
            'feature_repairs' => 0,
        ]])->assertOk();

        $this->assertFalse((bool) Setting::get('feature_repairs'));

        $updated = $this->getJson('/api/features')->assertOk()->json('data');
        $this->assertFalse($updated['feature_repairs']);

        // Toggle repairs back on
        $this->putJson('/api/settings', ['values' => [
            'feature_repairs' => 1,
        ]])->assertOk();

        $this->assertTrue((bool) Setting::get('feature_repairs'));
    }

    public function test_admin_cannot_toggle_features_and_cannot_see_feature_settings(): void
    {
        Setting::updateOrCreate(['key' => 'feature_repairs'], [
            'value' => '1',
            'group' => 'features',
            'type' => 'bool',
        ]);

        $admin = $this->userWith(['settings.manage']);
        $this->actingAs($admin);

        // Feature settings must not be included in the settings list for admin
        $response = $this->getJson('/api/settings')->assertOk()->json('data');
        $groups = collect($response)->pluck('group')->unique()->all();
        $this->assertNotContains('features', $groups);

        // Attempting to toggle a feature as admin fails with 422
        $this->putJson('/api/settings', ['values' => [
            'feature_repairs' => 0,
        ]])->assertUnprocessable()
            ->assertJsonValidationErrors('values');

        $this->assertTrue((bool) Setting::get('feature_repairs'));
    }

    public function test_it_requires_the_settings_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/settings')->assertForbidden();
        $this->putJson('/api/settings', ['values' => ['company_name' => 'x']])->assertForbidden();
    }

    public function test_disabled_feature_blocks_regular_user_but_master_can_access(): void
    {
        Setting::updateOrCreate(['key' => 'feature_repairs'], [
            'value' => '0',
            'group' => 'features',
            'type' => 'bool',
        ]);

        $regularUser = $this->userWith(['repairs.view']);
        $this->actingAs($regularUser);
        $this->getJson('/api/repairs')->assertForbidden();

        // Master user can bypass feature restriction
        $masterUser = User::factory()->create();
        $masterRole = Role::firstOrCreate(['slug' => 'master'], ['name' => 'المدير العام']);
        $masterUser->roles()->attach($masterRole);
        $this->actingAs($masterUser);
        $this->getJson('/api/repairs')->assertOk();
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
                ['name' => $slug, 'group' => 'access']
            ));
        }

        $user->roles()->attach($role);

        return $user;
    }

    protected function masterUser(): User
    {
        $user = User::factory()->create();
        $masterRole = Role::firstOrCreate(['slug' => 'master'], ['name' => 'المدير العام']);
        $user->roles()->attach($masterRole);

        return $user;
    }
}
