<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $masterUser;
    protected User $regularUser;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemConfigSeeder::class);

        // Master User
        $this->masterUser = User::factory()->create();
        $masterRole = Role::firstOrCreate(['slug' => 'master'], ['name' => 'المدير العام']);
        $this->masterUser->roles()->attach($masterRole);

        // Admin User (Not Master)
        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'مدير النظام']);
        $this->adminUser->roles()->attach($adminRole);

        // Regular User
        $this->regularUser = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_roles_or_permissions(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
        $this->postJson('/api/roles', ['name' => 'Test'])->assertUnauthorized();
        $this->getJson('/api/permissions')->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_roles_endpoints(): void
    {
        $this->actingAs($this->regularUser);

        $this->getJson('/api/roles')->assertForbidden();
        $this->postJson('/api/roles', ['name' => 'New Role'])->assertForbidden();
        $this->getJson('/api/permissions')->assertForbidden();
    }

    public function test_admin_user_without_roles_manage_is_forbidden(): void
    {
        $this->actingAs($this->adminUser);

        $this->getJson('/api/roles')->assertForbidden();

        $this->postJson('/api/roles', ['name' => 'New Role'])->assertForbidden();
        $this->getJson('/api/permissions')->assertForbidden();
    }

    public function test_master_user_can_list_roles_with_counts(): void
    {
        $this->actingAs($this->masterUser);

        $response = $this->getJson('/api/roles')->assertOk();
        $roles = $response->json('data');

        $this->assertNotEmpty($roles);
        $masterItem = collect($roles)->firstWhere('slug', 'master');
        $this->assertNotNull($masterItem);
        $this->assertArrayHasKey('users_count', $masterItem);
        $this->assertArrayHasKey('permissions_count', $masterItem);
        $this->assertTrue($masterItem['is_protected']);
    }

    public function test_master_user_can_search_roles(): void
    {
        $this->actingAs($this->masterUser);

        $response = $this->getJson('/api/roles?search=master')->assertOk();
        $roles = $response->json('data');

        $this->assertCount(1, $roles);
        $this->assertSame('master', $roles[0]['slug']);
    }

    public function test_master_user_can_list_grouped_permissions(): void
    {
        $this->actingAs($this->masterUser);

        $response = $this->getJson('/api/permissions')->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('grouped', $data);
        $this->assertNotEmpty($data['grouped']);

        $firstGroup = $data['grouped'][0];
        $this->assertArrayHasKey('key', $firstGroup);
        $this->assertArrayHasKey('title', $firstGroup);
        $this->assertArrayHasKey('permissions', $firstGroup);
    }

    public function test_master_user_can_create_new_role_with_permissions(): void
    {
        $this->actingAs($this->masterUser);

        $permIds = Permission::take(3)->pluck('id')->all();

        $response = $this->postJson('/api/roles', [
            'name' => 'مشرف فرع',
            'slug' => 'branch_supervisor',
            'description' => 'إدارة الفرع والعمليات اليومية',
            'permission_ids' => $permIds,
        ])->assertCreated();

        $roleData = $response->json('data');
        $this->assertSame('مشرف فرع', $roleData['name']);
        $this->assertSame('branch_supervisor', $roleData['slug']);
        $this->assertSame('إدارة الفرع والعمليات اليومية', $roleData['description']);
        $this->assertCount(3, $roleData['permissions']);

        $this->assertDatabaseHas('roles', [
            'name' => 'مشرف فرع',
            'slug' => 'branch_supervisor',
        ]);
    }

    public function test_master_user_can_view_role_details(): void
    {
        $this->actingAs($this->masterUser);

        $role = Role::firstOrCreate(['slug' => 'cashier'], ['name' => 'أمين الصندوق']);
        $role->users()->attach($this->regularUser);

        $response = $this->getJson("/api/roles/{$role->id}")->assertOk();
        $data = $response->json('data');

        $this->assertSame('cashier', $data['slug']);
        $this->assertCount(1, $data['users']);
        $this->assertSame($this->regularUser->id, $data['users'][0]['id']);
    }

    public function test_master_user_can_update_role_and_permissions(): void
    {
        $this->actingAs($this->masterUser);

        $role = Role::create([
            'name' => 'دور تجريبي',
            'slug' => 'test-role',
            'description' => 'وصف قديم',
        ]);

        $permIds = Permission::take(2)->pluck('id')->all();

        $response = $this->putJson("/api/roles/{$role->id}", [
            'name' => 'دور معدل',
            'description' => 'وصف جديد',
            'permission_ids' => $permIds,
        ])->assertOk();

        $updated = $response->json('data');
        $this->assertSame('دور معدل', $updated['name']);
        $this->assertSame('وصف جديد', $updated['description']);
        $this->assertCount(2, $updated['permissions']);
    }

    public function test_master_role_retains_all_permissions_on_update(): void
    {
        $this->actingAs($this->masterUser);

        $masterRole = Role::where('slug', 'master')->firstOrFail();
        $totalPermissions = Permission::count();

        // Attempt to pass an empty array of permissions to master role
        $this->putJson("/api/roles/{$masterRole->id}", [
            'name' => 'المدير العام المطور',
            'permission_ids' => [],
        ])->assertOk();

        $masterRole->refresh();
        $this->assertSame($totalPermissions, $masterRole->permissions()->count());
    }

    public function test_protected_role_slug_cannot_be_changed(): void
    {
        $this->actingAs($this->masterUser);

        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $this->putJson("/api/roles/{$adminRole->id}", [
            'name' => 'مدير النظام المطور',
            'slug' => 'super_admin',
        ])->assertOk();

        $adminRole->refresh();
        $this->assertSame('admin', $adminRole->slug);
    }

    public function test_cannot_delete_master_or_admin_role(): void
    {
        $this->actingAs($this->masterUser);

        $masterRole = Role::where('slug', 'master')->firstOrFail();
        $this->deleteJson("/api/roles/{$masterRole->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'لا يمكن حذف دور المدير العام (Master) أبداً.');

        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $this->deleteJson("/api/roles/{$adminRole->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'لا يمكن حذف دور مدير النظام الأساسي (Admin).');
    }

    public function test_cannot_delete_role_with_assigned_users(): void
    {
        $this->actingAs($this->masterUser);

        $role = Role::create([
            'name' => 'دور به مستخدمين',
            'slug' => 'role-with-users',
        ]);
        $role->users()->attach($this->regularUser);

        $this->deleteJson("/api/roles/{$role->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_can_delete_custom_role_without_users(): void
    {
        $this->actingAs($this->masterUser);

        $role = Role::create([
            'name' => 'دور قابل للحذف',
            'slug' => 'deletable-role',
        ]);

        $this->deleteJson("/api/roles/{$role->id}")->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_master_can_assign_users_to_role(): void
    {
        $this->actingAs($this->masterUser);

        $role = Role::create([
            'name' => 'دور مبيعات فرعي',
            'slug' => 'sub-sales',
        ]);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->postJson("/api/roles/{$role->id}/users", [
            'user_ids' => [$u1->id, $u2->id],
        ])->assertOk();

        $this->assertDatabaseHas('user_roles', [
            'role_id' => $role->id,
            'user_id' => $u1->id,
        ]);
        $this->assertDatabaseHas('user_roles', [
            'role_id' => $role->id,
            'user_id' => $u2->id,
        ]);
    }
}
