<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['password' => Hash::make('old-secret-1')]);
        $this->actingAs($this->user);
    }

    public function test_it_shows_the_signed_in_user_with_roles_sessions_and_activity(): void
    {
        $role = Role::query()->create(['name' => 'كاشير', 'slug' => 'cashier']);
        $permission = Permission::query()->firstOrCreate(['slug' => 'sales.view'], ['name' => 'عرض المبيعات', 'group' => 'sales']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        $response = $this->getJson('/api/profile')->assertOk();

        $response->assertJsonPath('user.id', $this->user->id)
            ->assertJsonPath('user.roles.0.slug', 'cashier')
            ->assertJsonPath('user.roles.0.permissions.0.slug', 'sales.view')
            ->assertJsonMissingPath('user.password');

        // The array session driver has no table to list; the shape is still there.
        $this->assertSame([], $response->json('sessions'));
        $this->assertSame([], $response->json('activity'));
    }

    public function test_it_updates_name_email_and_phone_and_audits_the_change(): void
    {
        $this->putJson('/api/profile', [
            'name' => 'سامر',
            'email' => 'samer@example.com',
            'phone' => '0999 000 000',
        ])->assertOk()
            ->assertJsonPath('user.name', 'سامر')
            ->assertJsonPath('user.email', 'samer@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'سامر',
            'email' => 'samer@example.com',
            'phone' => '0999 000 000',
        ]);

        $log = AuditLog::query()->where('event', 'user.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('سامر', $log->new_values['name']);
    }

    public function test_a_profile_save_cannot_widen_access(): void
    {
        $master = Role::query()->create(['name' => 'المدير العام', 'slug' => 'master']);

        $this->putJson('/api/profile', [
            'name' => 'سامر',
            'email' => $this->user->email,
            'is_active' => false,
            'role_ids' => [$master->id],
            'password' => 'sneaky-password',
        ])->assertOk();

        $this->user->refresh();
        $this->assertTrue($this->user->is_active);
        $this->assertCount(0, $this->user->roles);
        $this->assertTrue(Hash::check('old-secret-1', $this->user->password));
    }

    public function test_the_email_must_be_unique_but_may_stay_the_same(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->putJson('/api/profile', ['name' => 'x', 'email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->putJson('/api/profile', ['name' => 'x', 'email' => $this->user->email])
            ->assertOk();
    }

    public function test_it_changes_the_password_after_checking_the_current_one(): void
    {
        $this->putJson('/api/profile/password', [
            'current_password' => 'old-secret-1',
            'password' => 'new-secret-22',
            'password_confirmation' => 'new-secret-22',
        ])->assertOk()->assertJsonPath('ended_sessions', 0);

        $this->assertTrue(Hash::check('new-secret-22', $this->user->fresh()->password));

        $log = AuditLog::query()->where('event', 'user.updated')->latest('id')->first();
        $this->assertTrue($log->new_values['password_changed']);
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $this->putJson('/api/profile/password', [
            'current_password' => 'not-it',
            'password' => 'new-secret-22',
            'password_confirmation' => 'new-secret-22',
        ])->assertUnprocessable()->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('old-secret-1', $this->user->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed_long_enough_and_different(): void
    {
        $this->putJson('/api/profile/password', [
            'current_password' => 'old-secret-1',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->putJson('/api/profile/password', [
            'current_password' => 'old-secret-1',
            'password' => 'new-secret-22',
            'password_confirmation' => 'new-secret-23',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->putJson('/api/profile/password', [
            'current_password' => 'old-secret-1',
            'password' => 'old-secret-1',
            'password_confirmation' => 'old-secret-1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_ending_other_sessions_requires_the_password(): void
    {
        $this->deleteJson('/api/profile/sessions', ['password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);

        $this->deleteJson('/api/profile/sessions', ['password' => 'old-secret-1'])
            ->assertOk()->assertJsonPath('ended_sessions', 0);
    }

    public function test_a_guest_gets_401(): void
    {
        auth()->guard('web')->logout();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/profile')->assertUnauthorized();
    }
}
