<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * The deploy-time migration: the central registry first, then every shop.
 */
class TenantsMigrate extends Command
{
    protected $signature = 'tenants:migrate
        {--tenant=* : Only these shop subdomains}
        {--skip-central : Do not migrate the central database}';

    protected $description = 'Run migrations on the central database and every shop database';

    public function handle(TenantManager $manager): int
    {
        if (! $this->option('skip-central') && ! $this->option('tenant')) {
            $this->components->task('central', fn () => Artisan::call('migrate', [
                '--database' => config('tenancy.central_connection'),
                '--path' => 'database/migrations/central',
                '--force' => true,
            ]) === 0);
        }

        $tenants = Tenant::query()
            ->where('status', '!=', Tenant::STATUS_ARCHIVED)
            ->when($this->option('tenant'), fn ($q, $slugs) => $q->whereIn('slug', $slugs))
            ->orderBy('id')
            ->get();

        $failed = 0;
        foreach ($tenants as $tenant) {
            try {
                $output = $manager->migrate($tenant);
                $this->components->twoColumnDetail($tenant->slug, str_contains($output, 'Nothing to migrate') ? 'up to date' : '<fg=green>migrated</>');
            } catch (Throwable $e) {
                $failed++;
                $this->components->twoColumnDetail($tenant->slug, '<fg=red>FAILED</>');
                $this->line($e->getMessage());
            }
        }

        $this->newLine();
        $this->info("{$tenants->count()} shop(s), {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
