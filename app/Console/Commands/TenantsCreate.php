<?php

namespace App\Console\Commands;

use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TenantsCreate extends Command
{
    protected $signature = 'tenants:create
        {slug : Subdomain, e.g. "alnour" for alnour.<central domain>}
        {name : Shop name}
        {owner_email : Owner sign-in email}
        {--owner-name=مدير المتجر}
        {--password= : Owner password (generated when omitted)}
        {--expires= : Subscription end date, YYYY-MM-DD}';

    protected $description = 'Provision a new shop: database, schema, owner account and HTTPS certificate';

    public function handle(TenantManager $manager): int
    {
        $password = $this->option('password') ?: Str::password(14, symbols: false);

        $tenant = $manager->create([
            'slug' => $this->argument('slug'),
            'name' => $this->argument('name'),
            'owner_name' => $this->option('owner-name'),
            'owner_email' => $this->argument('owner_email'),
            'owner_password' => $password,
            'expires_at' => $this->option('expires'),
        ]);

        $this->info("Created {$tenant->url()} (database {$tenant->database}, SSL: {$tenant->ssl_status})");
        if (! $this->option('password')) {
            $this->warn("Owner password: {$password}");
        }
        if ($tenant->ssl_message) {
            $this->line($tenant->ssl_message);
        }

        return self::SUCCESS;
    }
}
