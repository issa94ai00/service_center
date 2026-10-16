<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

/**
 * Issue HTTPS certificates from the shell — for retries, and for servers where
 * the web process cannot run the SSL helper itself.
 */
class TenantsCertificate extends Command
{
    protected $signature = 'tenants:certificate
        {slug?* : Shop subdomains}
        {--pending : Every shop whose certificate is not issued yet}';

    protected $description = 'Issue (or retry) the HTTPS certificate and nginx site for shops';

    public function handle(TenantManager $manager): int
    {
        $tenants = Tenant::query()
            ->where('status', '!=', Tenant::STATUS_ARCHIVED)
            ->when($this->argument('slug'), fn ($q, $slugs) => $q->whereIn('slug', $slugs))
            ->when($this->option('pending'), fn ($q) => $q->where('ssl_status', '!=', 'issued'))
            ->get();

        if (! $this->argument('slug') && ! $this->option('pending')) {
            $this->error('Name the shop(s), or pass --pending.');

            return self::INVALID;
        }

        $failed = 0;
        foreach ($tenants as $tenant) {
            $manager->issueCertificate($tenant);
            $ok = $tenant->ssl_status === 'issued';
            $failed += $ok ? 0 : 1;
            $this->components->twoColumnDetail($tenant->host(), $ok ? '<fg=green>issued</>' : "<fg=red>{$tenant->ssl_status}</>");
            if (! $ok && $tenant->ssl_message) {
                $this->line($tenant->ssl_message);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
