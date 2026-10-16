<?php

namespace App\Console\Commands;

use App\Tenancy\TenantManager;
use Illuminate\Console\Command;

class TenantsAdopt extends Command
{
    protected $signature = 'tenants:adopt
        {slug : Subdomain for the shop}
        {database : Existing database holding the shop\'s data}
        {name : Shop name}
        {--owner-email=}';

    protected $description = 'Register an existing shop database (e.g. the original single-shop install) as a tenant';

    public function handle(TenantManager $manager): int
    {
        $tenant = $manager->adopt(
            $this->argument('slug'),
            $this->argument('database'),
            $this->argument('name'),
            array_filter(['owner_email' => $this->option('owner-email')]),
        );

        $this->info("Registered {$tenant->url()} on database {$tenant->database} (SSL: {$tenant->ssl_status})");
        if ($tenant->ssl_message) {
            $this->line($tenant->ssl_message);
        }

        return self::SUCCESS;
    }
}
