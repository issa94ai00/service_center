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

    public function test_it_requires_the_settings_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/settings')->assertForbidden();
        $this->putJson('/api/settings', ['values' => ['company_name' => 'x']])->assertForbidden();
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
}
