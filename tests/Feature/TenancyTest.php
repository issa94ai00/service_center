<?php

namespace Tests\Feature;

use App\Models\Central\Admin;
use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    /** Requests switch the default connection; keep the test transaction on the suite's own. */
    protected array $connectionsToTransact = ['sqlite'];

    private string $tenantFile;

    protected function setUp(): void
    {
        parent::setUp();

        // The registry lives on its own in-memory SQLite connection, standing in
        // for the central MySQL database.
        config([
            'tenancy.enabled' => true,
            'tenancy.central_domain' => 'repair.test',
            'tenancy.central_connection' => 'central_test',
            'database.connections.central_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $this->artisan('migrate', [
            '--database' => 'central_test',
            '--path' => 'database/migrations/central',
        ])->assertSuccessful();

        // A shop database the tenant connection can actually open.
        $this->tenantFile = tempnam(sys_get_temp_dir(), 'tenant').'.sqlite';
        touch($this->tenantFile);
        config(['database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => null,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        @unlink($this->tenantFile);
        parent::tearDown();
    }

    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::query()->create($attributes + [
            'slug' => 'alnour',
            'name' => 'متجر النور',
            'database' => $this->tenantFile,
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    public function test_hosts_are_classified(): void
    {
        $tenancy = app(Tenancy::class);

        $this->assertSame([Tenancy::CENTRAL, null], $tenancy->resolveHost('repair.test'));
        $this->assertSame([Tenancy::CENTRAL, null], $tenancy->resolveHost('www.repair.test'));
        $this->assertSame([Tenancy::ADMIN, null], $tenancy->resolveHost('ADMIN.repair.test'));
        $this->assertSame([Tenancy::TENANT, 'alnour'], $tenancy->resolveHost('alnour.repair.test'));
        $this->assertSame([Tenancy::TENANT, 'al-nour2'], $tenancy->resolveHost('al-nour2.repair.test.'));
        $this->assertSame([Tenancy::CENTRAL, null], $tenancy->resolveHost('a.b.repair.test'));
        $this->assertSame([Tenancy::CENTRAL, null], $tenancy->resolveHost('-bad.repair.test'));
        $this->assertSame([Tenancy::SINGLE, null], $tenancy->resolveHost('evilrepair.test'));
        $this->assertSame([Tenancy::SINGLE, null], $tenancy->resolveHost('localhost'));
    }

    public function test_slug_rules(): void
    {
        $this->assertTrue(Tenancy::isValidSlug('shop-1'));
        $this->assertFalse(Tenancy::isValidSlug('Shop'));
        $this->assertFalse(Tenancy::isValidSlug('shop-'));
        $this->assertFalse(Tenancy::isValidSlug('sh_op'));
        $this->assertTrue(Tenancy::isReservedSlug('admin'));
        $this->assertSame('repair_t_al_nour', Tenancy::databaseFor('al-nour'));
    }

    public function test_central_host_serves_no_shop_api(): void
    {
        $this->getJson('http://repair.test/api/user')->assertNotFound();
        $this->getJson('http://repair.test/api/admin/me')->assertNotFound();
    }

    public function test_central_host_page_carries_the_central_context(): void
    {
        $this->get('http://repair.test/')
            ->assertOk()
            ->assertSee('"mode":"central"', false);
    }

    public function test_unknown_and_archived_shops_are_not_found(): void
    {
        $this->getJson('http://nobody.repair.test/api/user')->assertNotFound();

        $this->tenant(['status' => Tenant::STATUS_ARCHIVED]);
        $this->getJson('http://alnour.repair.test/api/user')->assertNotFound();
        $this->get('http://alnour.repair.test/login')->assertNotFound()->assertSee('لا يوجد متجر بهذا العنوان');
    }

    public function test_suspended_and_expired_shops_are_closed(): void
    {
        $tenant = $this->tenant(['status' => Tenant::STATUS_SUSPENDED]);
        $this->getJson('http://alnour.repair.test/api/user')->assertForbidden();

        $tenant->update(['status' => Tenant::STATUS_ACTIVE, 'expires_at' => now()->subDay()]);
        $this->getJson('http://alnour.repair.test/api/user')->assertStatus(402);

        // The last day of the subscription still works.
        $tenant->update(['expires_at' => now()->toDateString()]);
        $this->getJson('http://alnour.repair.test/api/user')->assertUnauthorized();
    }

    public function test_an_active_shop_runs_on_its_own_database(): void
    {
        $this->tenant();

        $this->get('http://alnour.repair.test/login')
            ->assertOk()
            ->assertSee('"mode":"tenant"', false)
            ->assertSee('متجر النور');

        $this->assertSame('tenant', DB::getDefaultConnection());
        $this->assertSame($this->tenantFile, config('database.connections.tenant.database'));
    }

    public function test_shop_hosts_do_not_expose_the_admin_api(): void
    {
        $this->tenant();

        $this->getJson('http://alnour.repair.test/api/admin/me')->assertNotFound();
    }

    public function test_admin_api_requires_a_platform_admin(): void
    {
        $this->getJson('http://admin.repair.test/api/admin/tenants')->assertUnauthorized();
        $this->getJson('http://admin.repair.test/api/user')->assertNotFound();
    }

    public function test_admin_lists_tenants(): void
    {
        $this->tenant();
        $admin = Admin::query()->create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'secret-pass-1']);

        $this->actingAs($admin, 'admin')
            ->getJson('http://admin.repair.test/api/admin/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'alnour')
            ->assertJsonPath('data.0.host', 'alnour.repair.test')
            ->assertJsonPath('data.0.is_accessible', true);
    }

    public function test_admin_cannot_create_a_reserved_or_malformed_subdomain(): void
    {
        $admin = Admin::query()->create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'secret-pass-1']);
        $payload = [
            'name' => 'Shop',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'password-123',
        ];

        foreach (['admin', 'Bad_Slug', '-x'] as $slug) {
            $this->actingAs($admin, 'admin')
                ->postJson('http://admin.repair.test/api/admin/tenants', $payload + ['slug' => $slug])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('slug');
        }

        $this->tenant();
        $this->actingAs($admin, 'admin')
            ->postJson('http://admin.repair.test/api/admin/tenants', $payload + ['slug' => 'alnour'])
            ->assertJsonValidationErrors('slug');
    }

    public function test_admin_suspends_and_archives_before_deleting(): void
    {
        $tenant = $this->tenant();
        $admin = Admin::query()->create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'secret-pass-1']);

        $this->actingAs($admin, 'admin')
            ->deleteJson("http://admin.repair.test/api/admin/tenants/{$tenant->id}", ['confirm_slug' => 'alnour'])
            ->assertUnprocessable();

        $tenant->update(['status' => Tenant::STATUS_ARCHIVED]);

        $this->actingAs($admin, 'admin')
            ->deleteJson("http://admin.repair.test/api/admin/tenants/{$tenant->id}", ['confirm_slug' => 'wrong'])
            ->assertJsonValidationErrors('confirm_slug');

        $this->actingAs($admin, 'admin')
            ->deleteJson("http://admin.repair.test/api/admin/tenants/{$tenant->id}", ['confirm_slug' => 'alnour'])
            ->assertOk();

        $this->assertNull(Tenant::query()->find($tenant->id));
    }

    private function signupPayload(array $overrides = []): array
    {
        return $overrides + [
            'shop_name' => 'Al Nour Phones',
            'slug' => 'nour-phones',
            'owner_name' => 'Owner',
            'phone' => '0999123456',
            'email' => 'owner@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'terms' => true,
        ];
    }

    /** Provisioning needs MySQL; record the call and register the shop instead. */
    private function fakeProvisioning(): void
    {
        $this->partialMock(TenantManager::class, function ($mock) {
            $mock->shouldReceive('create')->andReturnUsing(fn (array $data) => Tenant::query()->create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'database' => Tenancy::databaseFor($data['slug']),
                'status' => Tenant::STATUS_ACTIVE,
                'source' => $data['source'],
                'signup_ip' => $data['signup_ip'],
                'plan' => $data['plan'],
                'expires_at' => $data['expires_at'],
                'owner_name' => $data['owner_name'],
                'owner_email' => $data['owner_email'],
                'contact_phone' => $data['contact_phone'],
                'ssl_status' => 'issued',
            ]));
        });
    }

    public function test_slug_check_reports_availability(): void
    {
        $this->tenant();

        $this->getJson('http://repair.test/api/public/slug-check?slug=fresh')->assertJson(['available' => true]);
        $this->getJson('http://repair.test/api/public/slug-check?slug=alnour')->assertJson(['available' => false, 'reason' => 'taken']);
        $this->getJson('http://repair.test/api/public/slug-check?slug=admin')->assertJson(['reason' => 'reserved']);
        $this->getJson('http://repair.test/api/public/slug-check?slug=Bad_')->assertJson(['reason' => 'invalid']);
    }

    public function test_public_api_is_only_on_the_central_domain(): void
    {
        $this->tenant();

        $this->getJson('http://alnour.repair.test/api/public/slug-check?slug=x')->assertNotFound();
        $this->getJson('http://admin.repair.test/api/public/slug-check?slug=x')->assertNotFound();
    }

    public function test_signup_creates_a_trial_shop(): void
    {
        config(['tenancy.signup.trial_days' => 14]);
        $this->fakeProvisioning();

        $this->postJson('http://repair.test/api/public/register', $this->signupPayload())
            ->assertCreated()
            ->assertJsonPath('data.host', 'nour-phones.repair.test')
            ->assertJsonPath('data.login_url', 'https://nour-phones.repair.test/login')
            ->assertJsonPath('data.expires_at', now()->addDays(14)->toDateString());

        $tenant = Tenant::query()->where('slug', 'nour-phones')->firstOrFail();
        $this->assertSame('signup', $tenant->source);
        $this->assertSame('owner@example.com', $tenant->owner_email);
        $this->assertNotNull($tenant->signup_ip);
    }

    public function test_signup_validates_its_input(): void
    {
        $this->fakeProvisioning();
        $this->tenant(['source' => 'signup', 'owner_email' => 'taken@example.com']);

        $this->postJson('http://repair.test/api/public/register', $this->signupPayload([
            'slug' => 'admin',
            'email' => 'taken@example.com',
            'password_confirmation' => 'different',
            'terms' => false,
        ]))->assertJsonValidationErrors(['slug', 'email', 'password', 'terms']);

        $this->postJson('http://repair.test/api/public/register', $this->signupPayload(['slug' => 'alnour']))
            ->assertJsonValidationErrors('slug');
    }

    public function test_signup_rejects_bots_filling_the_honeypot(): void
    {
        $this->fakeProvisioning();

        $this->postJson('http://repair.test/api/public/register', $this->signupPayload(['website' => 'http://spam.example']))
            ->assertJsonValidationErrors('website');

        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_signup_stops_at_the_daily_limit(): void
    {
        config(['tenancy.signup.daily_limit' => 1]);
        $this->fakeProvisioning();
        $this->tenant(['source' => 'signup']);

        $this->postJson('http://repair.test/api/public/register', $this->signupPayload())->assertStatus(429);
    }

    public function test_signup_can_be_switched_off(): void
    {
        config(['tenancy.signup.enabled' => false]);

        $this->postJson('http://repair.test/api/public/register', $this->signupPayload())->assertNotFound();
        $this->get('http://repair.test/')->assertSee('"signup":null', false);
    }

    public function test_central_page_offers_signup_when_enabled(): void
    {
        config(['tenancy.signup.enabled' => true, 'tenancy.signup.trial_days' => 14]);

        $this->get('http://repair.test/')->assertSee('"signup":{"trialDays":14}', false);
    }
}
