<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Which part of the platform the current request belongs to, and — for a shop —
 * switching the default database connection to that shop's database.
 *
 * Every model uses the default connection, so once a tenant is initialized the
 * whole app (sessions and the database cache included) reads and writes that
 * shop's database only.
 */
class Tenancy
{
    /** Tenancy disabled, or a host outside the platform: plain single-shop app. */
    public const SINGLE = 'single';

    /** The bare central domain: landing page and shop finder. */
    public const CENTRAL = 'central';

    /** admin.<central domain>: tenant management. */
    public const ADMIN = 'admin';

    /** <slug>.<central domain>: one shop. */
    public const TENANT = 'tenant';

    private string $context = self::SINGLE;

    private ?Tenant $tenant = null;

    public function enabled(): bool
    {
        return (bool) config('tenancy.enabled');
    }

    public function context(): string
    {
        return $this->context;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Classify a host. Returns [context, slug]; slug is set only for TENANT.
     *
     * @return array{0: string, 1: ?string}
     */
    public function resolveHost(string $host): array
    {
        $host = strtolower(rtrim($host, '.'));
        $central = strtolower((string) config('tenancy.central_domain'));

        if ($host === $central || $host === "www.{$central}") {
            return [self::CENTRAL, null];
        }

        if ($host === config('tenancy.admin_subdomain').".{$central}") {
            return [self::ADMIN, null];
        }

        $suffix = ".{$central}";
        if (str_ends_with($host, $suffix)) {
            $slug = substr($host, 0, -strlen($suffix));

            // Only a single, well-formed label is a shop; "a.b.<central>" is not.
            if (self::isValidSlug($slug)) {
                return [self::TENANT, $slug];
            }

            return [self::CENTRAL, null];
        }

        return [self::SINGLE, null];
    }

    public static function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $slug);
    }

    public static function isReservedSlug(string $slug): bool
    {
        return in_array($slug, (array) config('tenancy.reserved'), true);
    }

    public static function databaseFor(string $slug): string
    {
        return config('tenancy.database_prefix').str_replace('-', '_', $slug);
    }

    /** Serve this request from the central database (public site or admin). */
    public function useCentral(string $context): void
    {
        $this->context = $context;
        $this->tenant = null;
        DB::setDefaultConnection(config('tenancy.central_connection'));
    }

    /** Point the default connection at $tenant's database. */
    public function initialize(Tenant $tenant): void
    {
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');

        $this->tenant = $tenant;
        $this->context = self::TENANT;
    }

    /**
     * Run $callback against $tenant's database, then put the previous connection
     * and tenant back — for admin actions and console commands.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = [DB::getDefaultConnection(), $this->context, $this->tenant];
        $previousDatabase = config('database.connections.tenant.database');

        $this->initialize($tenant);

        try {
            return $callback($tenant);
        } finally {
            [$connection, $this->context, $this->tenant] = $previous;
            config(['database.connections.tenant.database' => $previousDatabase]);
            DB::purge('tenant');
            DB::setDefaultConnection($connection);
        }
    }

    /** What the SPA needs to know to pick its routes (injected into app.blade.php). */
    public function frontendContext(): array
    {
        return [
            'mode' => $this->context,
            'centralDomain' => config('tenancy.central_domain'),
            'tenant' => $this->tenant ? [
                'name' => $this->tenant->name,
                'slug' => $this->tenant->slug,
            ] : null,
        ];
    }
}
