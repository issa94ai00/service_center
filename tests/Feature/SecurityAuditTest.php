<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Regression coverage for the phase 16 security audit. Each test pins one
 * finding so it cannot silently come back.
 */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Role $admin;

    protected Role $employee;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('login');

        // Seeded in this order in production too — admin is role id 1, which is
        // what made the role-assignment bug grant administrator.
        $this->admin = Role::query()->create(['name' => 'مدير النظام', 'slug' => 'admin']);
        Role::query()->create(['name' => 'محاسب', 'slug' => 'accountant']);
        $this->employee = Role::query()->create(['name' => 'موظف', 'slug' => 'employee']);

        $this->admin->permissions()->attach($this->permission('users.manage'));
    }

    // ------------------------------------------- privilege escalation (role_ids)

    public function test_creating_a_user_assigns_only_the_requested_role(): void
    {
        $this->actingAs($this->userWithRole($this->admin));

        $this->postJson('/api/users', [
            'name' => 'موظف جديد',
            'email' => 'employee@example.test',
            'password' => 'correct-horse-battery',
            'role_ids' => [$this->employee->id],
        ])->assertCreated();

        $created = User::query()->where('email', 'employee@example.test')->firstOrFail();

        // Previously this returned ['admin']: integer('role_ids') cast the array
        // [3] to int 1, and role 1 is the administrator.
        $this->assertSame(['employee'], $created->roles->pluck('slug')->all());
    }

    public function test_updating_a_user_syncs_only_the_requested_roles(): void
    {
        $this->actingAs($this->userWithRole($this->admin));
        $target = User::factory()->create();

        $this->putJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role_ids' => [$this->employee->id],
        ])->assertOk();

        $this->assertSame(['employee'], $target->fresh()->roles->pluck('slug')->all());
    }

    public function test_a_user_created_without_roles_gets_none(): void
    {
        $this->actingAs($this->userWithRole($this->admin));

        $this->postJson('/api/users', [
            'name' => 'بدون دور',
            'email' => 'norole@example.test',
            'password' => 'correct-horse-battery',
        ])->assertCreated();

        $this->assertCount(0, User::query()->where('email', 'norole@example.test')->firstOrFail()->roles);
    }

    // --------------------------------------------------- broken access control

    public function test_the_customer_picker_is_not_readable_without_sales_permission(): void
    {
        Customer::query()->create(['name' => 'عميل سري', 'opening_balance' => '5000.00', 'is_active' => true]);

        $this->actingAs(User::factory()->create());

        // This leaked every customer's balance and contact details to any
        // signed-in user, regardless of permissions.
        $this->getJson('/api/sales/customer-options')->assertForbidden();
    }

    public function test_the_supplier_picker_is_not_readable_without_purchase_permission(): void
    {
        Supplier::query()->create(['name' => 'مورد سري', 'opening_balance' => '0.00', 'is_active' => true]);

        $this->actingAs(User::factory()->create());

        $this->getJson('/api/purchases/supplier-options')->assertForbidden();
    }

    public function test_the_pickers_remain_available_to_a_permitted_user(): void
    {
        Customer::query()->create(['name' => 'عميل', 'opening_balance' => '0.00', 'is_active' => true]);

        $role = Role::query()->create(['name' => 'بائع', 'slug' => 'seller']);
        $role->permissions()->attach($this->permission('sales.create'));

        $this->actingAs($this->userWithRole($role));

        $this->getJson('/api/sales/customer-options')->assertOk();
    }

    public function test_the_sales_customer_picker_searches_instead_of_shipping_the_whole_book(): void
    {
        $role = Role::query()->create(['name' => 'بائع', 'slug' => 'seller-search']);
        $role->permissions()->attach($this->permission('sales.create'));
        $this->actingAs($this->userWithRole($role));

        Customer::query()->create(['name' => 'أحمد الحلبي', 'mobile' => '0999123456', 'opening_balance' => '0.00', 'is_active' => true]);
        Customer::query()->create(['name' => 'سامر', 'mobile' => '0988777666', 'opening_balance' => '0.00', 'is_active' => true]);

        $this->getJson('/api/sales/customer-options?search=الحلبي')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'أحمد الحلبي');

        // Typed with spaces at the counter, matched on the bare digits.
        $this->getJson('/api/sales/customer-options?search=0999 123 456')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mobile', '0999123456');

        // Never unbounded, whatever the caller asks for.
        for ($i = 0; $i < 8; $i++) {
            Customer::query()->create(['name' => "عميل {$i}", 'opening_balance' => '0.00', 'is_active' => true]);
        }

        $this->getJson('/api/sales/customer-options?limit=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.truncated', true)
            ->assertJsonPath('meta.total', 10);
    }

    public function test_the_sales_customer_picker_does_not_leak_balances_or_contact_details(): void
    {
        $role = Role::query()->create(['name' => 'بائع', 'slug' => 'seller-leak']);
        $role->permissions()->attach($this->permission('sales.create'));
        $this->actingAs($this->userWithRole($role));

        Customer::query()->create([
            'name' => 'عميل',
            'email' => 'secret@example.com',
            'address' => 'شارع سري',
            'tax_number' => '9999',
            'opening_balance' => '500.00',
            'is_active' => true,
        ]);

        $row = $this->getJson('/api/sales/customer-options')->assertOk()->json('data.0');

        $this->assertSame(
            ['id', 'name', 'company', 'phone', 'mobile', 'is_active'],
            array_keys($row)
        );
    }

    public function test_the_sales_customer_picker_pins_an_invoices_own_customer_into_the_slice(): void
    {
        $role = Role::query()->create(['name' => 'بائع', 'slug' => 'seller-pin']);
        $role->permissions()->attach($this->permission('sales.create'));
        $this->actingAs($this->userWithRole($role));

        // Deactivated, so a plain search would never return them — but an
        // invoice already issued to them must still render their name.
        $retired = Customer::query()->create([
            'name' => 'عميل قديم', 'opening_balance' => '0.00', 'is_active' => false,
        ]);

        $this->getJson('/api/sales/customer-options')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/sales/customer-options?include={$retired->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $retired->id);
    }

    public function test_the_supplier_picker_searches_instead_of_shipping_the_whole_book(): void
    {
        $role = Role::query()->create(['name' => 'أمين مستودع', 'slug' => 'storekeeper-search']);
        $role->permissions()->attach($this->permission('purchases.create'));
        $this->actingAs($this->userWithRole($role));

        Supplier::query()->create(['name' => 'مؤسسة النور', 'mobile' => '0999123456', 'opening_balance' => '0.00', 'is_active' => true]);
        Supplier::query()->create(['name' => 'شركة الأمل', 'mobile' => '0988777666', 'opening_balance' => '0.00', 'is_active' => true]);

        $this->getJson('/api/purchases/supplier-options?search=النور')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'مؤسسة النور');

        // Typed with spaces off an invoice header, matched on the bare digits.
        $this->getJson('/api/purchases/supplier-options?search=0999 123 456')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mobile', '0999123456');

        for ($i = 0; $i < 8; $i++) {
            Supplier::query()->create(['name' => "مورد {$i}", 'opening_balance' => '0.00', 'is_active' => true]);
        }

        $this->getJson('/api/purchases/supplier-options?limit=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.truncated', true)
            ->assertJsonPath('meta.total', 10);
    }

    public function test_the_supplier_picker_does_not_leak_balances_or_contact_details(): void
    {
        $role = Role::query()->create(['name' => 'أمين مستودع', 'slug' => 'storekeeper-leak']);
        $role->permissions()->attach($this->permission('purchases.create'));
        $this->actingAs($this->userWithRole($role));

        Supplier::query()->create([
            'name' => 'مورد',
            'email' => 'secret@example.com',
            'address' => 'شارع سري',
            'tax_number' => '9999',
            'opening_balance' => '500.00',
            'is_active' => true,
        ]);

        $row = $this->getJson('/api/purchases/supplier-options')->assertOk()->json('data.0');

        $this->assertSame(
            ['id', 'name', 'company', 'phone', 'mobile', 'is_active'],
            array_keys($row)
        );
    }

    public function test_the_supplier_picker_pins_an_invoices_own_supplier_into_the_slice(): void
    {
        $role = Role::query()->create(['name' => 'أمين مستودع', 'slug' => 'storekeeper-pin']);
        $role->permissions()->attach($this->permission('purchases.create'));
        $this->actingAs($this->userWithRole($role));

        // Deactivated, so a plain search would never return them — but an
        // invoice already booked against them must still render their name.
        $retired = Supplier::query()->create([
            'name' => 'مورد قديم', 'opening_balance' => '0.00', 'is_active' => false,
        ]);

        $this->getJson('/api/purchases/supplier-options')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/purchases/supplier-options?include={$retired->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $retired->id);
    }

    // ----------------------------------------------------------- session safety

    public function test_login_rotates_the_session_id(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test']);

        $this->get('/');
        $before = session()->getId();

        $this->postJson('/api/login', [
            'email' => 'user@example.test',
            'password' => 'password',
        ])->assertOk();

        // A session id fixed before login must not survive it.
        $this->assertNotSame($before, session()->getId());
    }

    public function test_login_accepts_a_remember_flag(): void
    {
        // The remember flag is a session concern, not a credential: it must be
        // kept out of the user query, which otherwise fails on the unknown
        // `remember` column.
        User::factory()->create(['email' => 'remember@example.test']);

        $this->postJson('/api/login', [
            'email' => 'remember@example.test',
            'password' => 'password',
            'remember' => true,
        ])->assertOk();
    }

    public function test_a_disabled_account_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'disabled@example.test']);

        $this->postJson('/api/login', [
            'email' => 'disabled@example.test',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_disabling_an_account_ends_its_existing_session(): void
    {
        $user = $this->userWithRole($this->admin);
        $this->actingAs($user);

        $this->getJson('/api/user')->assertOk();

        $user->update(['is_active' => false]);

        // Revoking access previously did nothing until the session expired.
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_accepts_a_short_existing_password(): void
    {
        User::factory()->create([
            'email' => 'legacy@example.test',
            'password' => bcrypt('short7'),
        ]);

        // A length rule on login only locks out passwords that predate it; it
        // must not be mistaken for a password policy.
        $this->postJson('/api/login', [
            'email' => 'legacy@example.test',
            'password' => 'short7',
        ])->assertOk();
    }

    // ------------------------------------------------------------ rate limiting

    public function test_repeated_failed_logins_are_throttled(): void
    {
        User::factory()->create(['email' => 'target@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'target@example.test',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => 'target@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_the_api_declares_a_baseline_rate_limit(): void
    {
        $this->actingAs($this->userWithRole($this->admin));

        $response = $this->getJson('/api/user')->assertOk();

        // Every API route now carries a ceiling; only /login had one before.
        $this->assertNotNull($response->headers->get('X-RateLimit-Limit'));
    }

    // ------------------------------------------------------------ mass assignment

    public function test_a_user_cannot_be_created_already_holding_extra_columns(): void
    {
        $this->actingAs($this->userWithRole($this->admin));

        $this->postJson('/api/users', [
            'name' => 'محاولة',
            'email' => 'attempt@example.test',
            'password' => 'correct-horse-battery',
            'remember_token' => 'planted-token',
        ])->assertCreated();

        $created = User::query()->where('email', 'attempt@example.test')->firstOrFail();

        $this->assertNotSame('planted-token', $created->remember_token);
        $this->assertNotSame('correct-horse-battery', $created->password);
    }

    // ------------------------------------------------------------------ helpers

    protected function permission(string $slug): Permission
    {
        return Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug, 'group' => explode('.', $slug)[0]]
        );
    }

    protected function userWithRole(Role $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
