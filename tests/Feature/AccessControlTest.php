<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Privilege-escalation guards (AccessControlService), the delegated roles.manage and
 * features.manage permissions, payments.view, and the access-change audit trail.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected Role $masterRole;

    protected User $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemConfigSeeder::class);

        $this->masterRole = Role::query()->where('slug', 'master')->firstOrFail();
        $this->master = User::factory()->create();
        $this->master->roles()->attach($this->masterRole);
    }

    // ------------------------------------------------------------ users.manage escalation

    public function test_a_user_manager_cannot_grant_themselves_master(): void
    {
        $manager = $this->userWith(['users.manage']);

        $this->actingAs($manager)
            ->putJson("/api/users/{$manager->id}", ['role_ids' => [$this->masterRole->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');

        $this->assertFalse($manager->fresh()->isMaster());
    }

    public function test_a_user_manager_cannot_create_a_master(): void
    {
        $this->actingAs($this->userWith(['users.manage']))
            ->postJson('/api/users', $this->newUser(['role_ids' => [$this->masterRole->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_a_user_manager_cannot_reset_deactivate_or_delete_a_master(): void
    {
        $this->actingAs($this->userWith(['users.manage']));

        $this->putJson("/api/users/{$this->master->id}", ['password' => 'taken-over-123'])->assertForbidden();
        $this->putJson("/api/users/{$this->master->id}", ['is_active' => false])->assertForbidden();
        $this->deleteJson("/api/users/{$this->master->id}")->assertForbidden();

        $this->assertTrue($this->master->fresh()->is_active);
    }

    public function test_a_user_manager_cannot_grant_a_role_holding_permissions_they_lack(): void
    {
        $powerful = $this->roleWith('powerful', ['users.manage', 'settings.manage']);

        $this->actingAs($this->userWith(['users.manage']))
            ->postJson('/api/users', $this->newUser(['role_ids' => [$powerful->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');
    }

    public function test_a_user_manager_cannot_edit_a_user_more_powerful_than_themselves(): void
    {
        $boss = $this->userWith(['users.manage', 'settings.manage']);

        $this->actingAs($this->userWith(['users.manage']))
            ->putJson("/api/users/{$boss->id}", ['password' => 'taken-over-123'])
            ->assertForbidden();
    }

    public function test_a_user_manager_can_grant_a_role_within_their_own_permissions(): void
    {
        $clerk = $this->roleWith('clerk', ['customers.view']);

        $this->actingAs($this->userWith(['users.manage', 'customers.view']))
            ->postJson('/api/users', $this->newUser(['role_ids' => [$clerk->id]]))
            ->assertCreated()
            ->assertJsonPath('data.roles.0.slug', 'clerk');
    }

    public function test_role_options_flag_which_roles_the_user_may_grant(): void
    {
        $this->roleWith('clerk', ['customers.view']);

        $options = collect($this->actingAs($this->userWith(['users.manage', 'customers.view']))
            ->getJson('/api/roles/options')->assertOk()->json('data'))->keyBy('slug');

        $this->assertTrue($options['clerk']['grantable']);
        $this->assertFalse($options['master']['grantable']);
        $this->assertFalse($options['admin']['grantable']);
    }

    public function test_nobody_can_deactivate_their_own_account(): void
    {
        $this->actingAs($this->master)
            ->putJson("/api/users/{$this->master->id}", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_active');
    }

    // ------------------------------------------------------------ last Master

    public function test_the_last_master_cannot_drop_the_master_role(): void
    {
        $this->actingAs($this->master)
            ->putJson("/api/users/{$this->master->id}", ['role_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role_ids');

        $this->assertTrue($this->master->fresh()->isMaster());
    }

    public function test_a_master_can_step_down_when_another_active_master_remains(): void
    {
        User::factory()->create()->roles()->attach($this->masterRole);

        $this->actingAs($this->master)
            ->putJson("/api/users/{$this->master->id}", ['role_ids' => []])
            ->assertOk();

        $this->assertFalse($this->master->fresh()->isMaster());
    }

    public function test_an_inactive_master_does_not_count_as_the_remaining_master(): void
    {
        User::factory()->create(['is_active' => false])->roles()->attach($this->masterRole);

        $this->actingAs($this->master)
            ->putJson("/api/users/{$this->master->id}", ['role_ids' => []])
            ->assertUnprocessable();
    }

    public function test_the_master_role_cannot_be_emptied_through_role_assignment(): void
    {
        $this->actingAs($this->master)
            ->postJson("/api/roles/{$this->masterRole->id}/users", ['user_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids');

        $this->assertTrue($this->master->fresh()->isMaster());
    }

    // ------------------------------------------------------------ delegated roles.manage

    public function test_a_role_manager_can_create_a_role_within_their_permissions(): void
    {
        $customersView = Permission::query()->where('slug', 'customers.view')->value('id');

        $this->actingAs($this->userWith(['roles.manage', 'customers.view']))
            ->postJson('/api/roles', ['name' => 'Clerk', 'permission_ids' => [$customersView]])
            ->assertCreated();
    }

    public function test_a_role_manager_cannot_grant_permissions_they_lack(): void
    {
        $settings = Permission::query()->where('slug', 'settings.manage')->value('id');
        $manager = $this->userWith(['roles.manage']);
        $ownRole = $manager->roles->first();

        $this->actingAs($manager);

        $this->postJson('/api/roles', ['name' => 'Sneaky', 'permission_ids' => [$settings]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permission_ids');

        // ... including onto their own role.
        $this->putJson("/api/roles/{$ownRole->id}", ['name' => $ownRole->name, 'permission_ids' => [$settings]])
            ->assertUnprocessable();
    }

    public function test_a_role_manager_cannot_touch_the_master_role_or_a_stronger_role(): void
    {
        $stronger = $this->roleWith('stronger', ['roles.manage', 'settings.manage']);

        $this->actingAs($this->userWith(['roles.manage']));

        $this->putJson("/api/roles/{$this->masterRole->id}", ['name' => 'x'])->assertForbidden();
        $this->postJson("/api/roles/{$this->masterRole->id}/users", ['user_ids' => [$this->master->id]])->assertForbidden();
        $this->putJson("/api/roles/{$stronger->id}", ['name' => 'x', 'permission_ids' => []])->assertForbidden();
        $this->deleteJson("/api/roles/{$stronger->id}")->assertForbidden();
    }

    // ------------------------------------------------------------ features.manage / settings.manage

    public function test_features_manage_alone_toggles_features_but_not_general_settings(): void
    {
        $this->actingAs($this->userWith(['features.manage']));

        $keys = collect($this->getJson('/api/settings')->assertOk()->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('feature_repairs'));
        $this->assertFalse($keys->contains('company_name'));

        $this->putJson('/api/settings', ['values' => ['feature_repairs' => false]])->assertOk();
        $this->assertSame('0', Setting::query()->where('key', 'feature_repairs')->value('value'));

        $this->putJson('/api/settings', ['values' => ['company_name' => 'X']])->assertUnprocessable();
    }

    public function test_settings_manage_alone_cannot_toggle_features(): void
    {
        $this->actingAs($this->userWith(['settings.manage']))
            ->putJson('/api/settings', ['values' => ['feature_repairs' => false]])
            ->assertUnprocessable();

        $this->assertSame('1', Setting::query()->where('key', 'feature_repairs')->value('value'));
    }

    // ------------------------------------------------------------ payments.view

    public function test_payments_view_lists_payments_and_create_alone_does_not(): void
    {
        $this->actingAs($this->userWith(['payments.view']))->getJson('/api/payments')->assertOk();
        $this->actingAs($this->userWith(['payments.create']))->getJson('/api/payments')->assertForbidden();
    }

    // ------------------------------------------------------------ permission cache

    public function test_permission_checks_follow_a_role_change_once_roles_are_reloaded(): void
    {
        $user = $this->userWith(['customers.view']);
        $this->assertTrue($user->hasPermission('customers.view'));

        $user->roles()->sync([]);
        $user->load('roles');

        $this->assertFalse($user->hasPermission('customers.view'));
    }

    // ------------------------------------------------------------ audit trail

    public function test_role_permission_changes_are_audited(): void
    {
        $role = $this->roleWith('clerk', ['customers.view']);
        $suppliersView = Permission::query()->where('slug', 'suppliers.view')->value('id');

        $this->actingAs($this->master)
            ->putJson("/api/roles/{$role->id}", ['name' => 'clerk', 'permission_ids' => [$suppliersView]])
            ->assertOk();

        $log = AuditLog::query()->where('event', 'role.updated')->sole();
        $this->assertSame($this->master->id, $log->user_id);
        $this->assertSame(['permissions' => ['customers.view']], $log->old_values);
        $this->assertSame(['permissions' => ['suppliers.view']], $log->new_values);
    }

    public function test_a_password_reset_is_audited_without_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->master)
            ->putJson("/api/users/{$user->id}", ['password' => 'a-new-password-1'])
            ->assertOk();

        $log = AuditLog::query()->where('event', 'user.updated')->sole();
        $this->assertSame(['password_changed' => true], $log->new_values);
        $this->assertStringNotContainsString('a-new-password-1', json_encode($log->getAttributes()));
    }

    public function test_an_unchanged_save_writes_no_audit_entry(): void
    {
        $role = $this->roleWith('clerk', ['customers.view']);
        $customersView = Permission::query()->where('slug', 'customers.view')->value('id');

        $this->actingAs($this->master)
            ->putJson("/api/roles/{$role->id}", ['name' => 'clerk', 'permission_ids' => [$customersView]])
            ->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'role.updated']);
    }

    public function test_the_access_log_only_shows_what_the_reader_manages(): void
    {
        $this->actingAs($this->master);
        $this->postJson('/api/roles', ['name' => 'Clerk'])->assertCreated();
        $this->postJson('/api/users', $this->newUser())->assertCreated();

        $this->actingAs($this->userWith(['users.manage']));

        $events = collect($this->getJson('/api/access-log')->assertOk()->json('data'))->pluck('event');
        $this->assertContains('user.created', $events);
        $this->assertNotContains('role.created', $events);

        $this->getJson('/api/access-log?subject=role')->assertForbidden();

        $this->actingAs($this->userWith(['customers.view']))->getJson('/api/access-log')->assertForbidden();
    }

    // ------------------------------------------------------------ helpers

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->roleWith('role-'.$user->id, $permissions));

        return $user;
    }

    private function roleWith(string $slug, array $permissions): Role
    {
        $role = Role::query()->create(['name' => $slug, 'slug' => $slug]);
        $role->permissions()->attach(Permission::query()->whereIn('slug', $permissions)->pluck('id'));

        return $role;
    }

    private function newUser(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'a-strong-password-1',
        ];
    }
}
