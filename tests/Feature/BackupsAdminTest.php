<?php

namespace Tests\Feature;

use App\Models\Central\Admin;
use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin side of backups. Taking and restoring a dump needs MySQL, so these
 * cover access, validation and bookkeeping on the SQLite stand-in.
 */
class BackupsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = ['sqlite'];

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

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

        Storage::fake('backups');
        $this->admin = Admin::query()->create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'secret-pass-1']);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection('sqlite');
        parent::tearDown();
    }

    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::query()->create($attributes + [
            'slug' => 'alnour',
            'name' => 'متجر النور',
            'database' => 'repair_t_alnour',
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    private function backup(Tenant $tenant, array $attributes = []): Backup
    {
        Storage::disk('backups')->put('alnour/dump.sql.gz', 'x');

        return Backup::query()->create($attributes + [
            'tenant_id' => $tenant->id,
            'database' => $tenant->database,
            'label' => $tenant->name,
            'trigger' => Backup::TRIGGER_MANUAL,
            'status' => Backup::STATUS_COMPLETED,
            'path' => 'alnour/dump.sql.gz',
            'size_bytes' => 1,
        ]);
    }

    public function test_backups_need_a_platform_admin(): void
    {
        $this->getJson('http://admin.repair.test/api/admin/backups')->assertUnauthorized();
        $this->getJson('http://admin.repair.test/api/admin/backups/overview')->assertUnauthorized();
        $this->tenant();
        $this->getJson('http://alnour.repair.test/api/admin/backups')->assertNotFound();
    }

    public function test_admin_lists_and_filters_backups(): void
    {
        $tenant = $this->tenant();
        $this->backup($tenant);
        $this->backup($tenant, ['status' => Backup::STATUS_FAILED, 'path' => null, 'error' => 'boom']);

        $this->actingAs($this->admin, 'admin')
            ->getJson('http://admin.repair.test/api/admin/backups?status=failed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.error', 'boom')
            ->assertJsonPath('data.0.is_restorable', false);
    }

    public function test_restore_requires_typing_the_shop_subdomain(): void
    {
        $backup = $this->backup($this->tenant());

        $this->actingAs($this->admin, 'admin')
            ->postJson("http://admin.repair.test/api/admin/backups/{$backup->id}/restore", ['confirm_slug' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('confirm_slug');
    }

    public function test_failed_and_central_backups_cannot_be_restored(): void
    {
        $tenant = $this->tenant();
        $failed = $this->backup($tenant, ['status' => Backup::STATUS_FAILED]);
        $central = Backup::query()->create([
            'database' => ':memory:', 'label' => 'central', 'trigger' => Backup::TRIGGER_MANUAL,
            'status' => Backup::STATUS_COMPLETED, 'path' => 'x.sql.gz',
        ]);

        foreach ([$failed, $central] as $backup) {
            $this->actingAs($this->admin, 'admin')
                ->postJson("http://admin.repair.test/api/admin/backups/{$backup->id}/restore", ['confirm_slug' => 'alnour'])
                ->assertUnprocessable();
        }
        $this->assertTrue($central->isCentral());
    }

    public function test_deleting_a_backup_removes_its_file(): void
    {
        $backup = $this->backup($this->tenant());

        $this->actingAs($this->admin, 'admin')
            ->deleteJson("http://admin.repair.test/api/admin/backups/{$backup->id}")
            ->assertOk();

        $this->assertModelMissing($backup);
        Storage::disk('backups')->assertMissing('alnour/dump.sql.gz');
    }

    public function test_download_streams_the_dump(): void
    {
        $backup = $this->backup($this->tenant());

        $this->actingAs($this->admin, 'admin')
            ->get("http://admin.repair.test/api/admin/backups/{$backup->id}/download")
            ->assertOk()
            ->assertDownload('dump.sql.gz');
    }

    public function test_a_shop_in_maintenance_is_closed_and_can_be_reopened(): void
    {
        $tenant = $this->tenant();

        $this->actingAs($this->admin, 'admin')
            ->postJson("http://admin.repair.test/api/admin/tenants/{$tenant->id}/maintenance", ['on' => true])
            ->assertOk()
            ->assertJsonPath('data.is_accessible', false);

        $this->getJson('http://alnour.repair.test/api/user')->assertStatus(503);

        $this->actingAs($this->admin, 'admin')
            ->postJson("http://admin.repair.test/api/admin/tenants/{$tenant->id}/maintenance", ['on' => false])
            ->assertJsonPath('data.maintenance_at', null);
    }

    public function test_pruning_marks_abandoned_runs_failed(): void
    {
        $tenant = $this->tenant();
        $stuck = $this->backup($tenant, ['status' => Backup::STATUS_RUNNING, 'started_at' => now()->subHours(5)]);

        $this->artisan('backups:prune')->assertSuccessful();

        $this->assertSame(Backup::STATUS_FAILED, $stuck->refresh()->status);
    }
}
