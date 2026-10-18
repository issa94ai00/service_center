<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Creates, maintains and removes shops: their database, schema, first user,
 * module switches and HTTPS certificate.
 */
class TenantManager
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * Provision a new shop. Rolls back (database dropped, registry row removed)
     * if any step before the certificate fails; a certificate failure only marks
     * the shop's ssl_status so it can be retried.
     *
     * @param  array{slug: string, name: string, owner_name: string, owner_email: string, owner_password: string,
     *     plan?: ?string, expires_at?: ?string, contact_phone?: ?string, notes?: ?string, features?: array<string, bool>,
     *     source?: string, signup_ip?: ?string}  $data
     */
    public function create(array $data): Tenant
    {
        $slug = strtolower($data['slug']);
        $this->assertSlugAvailable($slug);

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'name' => $data['name'],
            'database' => Tenancy::databaseFor($slug),
            'status' => Tenant::STATUS_ACTIVE,
            'source' => $data['source'] ?? 'admin',
            'signup_ip' => $data['signup_ip'] ?? null,
            'plan' => $data['plan'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'contact_phone' => $data['contact_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'ssl_status' => 'pending',
        ]);

        $databaseCreated = false;

        try {
            $this->createDatabase($tenant->database);
            $databaseCreated = true;

            $this->migrate($tenant);

            $this->tenancy->run($tenant, function () use ($tenant, $data) {
                Artisan::call('db:seed', [
                    '--class' => SystemConfigSeeder::class,
                    '--database' => 'tenant',
                    '--force' => true,
                ]);

                DB::table('settings')->where('key', 'company_name')->update(['value' => $tenant->name]);

                $owner = User::query()->create([
                    'name' => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'password' => Hash::make($data['owner_password']),
                    'is_active' => true,
                    'locale' => 'ar',
                ]);

                $adminRole = Role::query()->where('slug', 'admin')->first();
                if ($adminRole) {
                    $owner->roles()->attach($adminRole->id);
                }
            });

            if (! empty($data['features'])) {
                $this->setFeatures($tenant, $data['features']);
            }
        } catch (Throwable $e) {
            if ($databaseCreated) {
                $this->dropDatabase($tenant->database);
            }
            $tenant->delete();

            throw $e;
        }

        $this->issueCertificate($tenant);

        return $tenant->refresh();
    }

    /**
     * Register a database that already holds a shop (the original single-shop
     * install) and bring its schema up to date.
     */
    public function adopt(string $slug, string $database, string $name, array $attributes = []): Tenant
    {
        $slug = strtolower($slug);
        $this->assertSlugAvailable($slug);

        if (! $this->databaseExists($database)) {
            throw new RuntimeException("Database `{$database}` does not exist.");
        }

        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'name' => $name,
            'database' => $database,
            'status' => Tenant::STATUS_ACTIVE,
            'ssl_status' => 'pending',
        ] + $attributes);

        $this->migrate($tenant);
        $this->issueCertificate($tenant);

        return $tenant->refresh();
    }

    /** Bring one shop's schema up to date. Returns the migrator's output. */
    public function migrate(Tenant $tenant): string
    {
        return $this->tenancy->run($tenant, function () {
            $code = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations',
                '--force' => true,
            ]);

            $output = Artisan::output();
            if ($code !== 0) {
                throw new RuntimeException("Migration failed:\n{$output}");
            }

            return $output;
        });
    }

    /**
     * Migrations the code ships that this shop's database has not run yet.
     *
     * @return list<string>
     */
    public function pendingMigrations(Tenant $tenant): array
    {
        $files = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($path) => basename($path, '.php'));

        $ran = $this->tenancy->run($tenant, fn () => Schema::hasTable('migrations')
            ? DB::table('migrations')->pluck('migration')
            : collect());

        return $files->diff($ran)->sort()->values()->all();
    }

    /** @return array<string, bool> feature key => on */
    public function features(Tenant $tenant): array
    {
        $keys = array_keys((array) config('tenancy.features'));

        return $this->tenancy->run($tenant, function () use ($keys) {
            $stored = DB::table('settings')
                ->whereIn('key', array_map(fn ($k) => "feature_{$k}", $keys))
                ->pluck('value', 'key');

            $features = [];
            foreach ($keys as $key) {
                $features[$key] = filter_var($stored["feature_{$key}"] ?? '1', FILTER_VALIDATE_BOOLEAN);
            }

            return $features;
        });
    }

    /** @param  array<string, bool>  $features */
    public function setFeatures(Tenant $tenant, array $features): void
    {
        $known = array_keys((array) config('tenancy.features'));

        $this->tenancy->run($tenant, function () use ($features, $known) {
            foreach ($features as $key => $on) {
                if (! in_array($key, $known, true)) {
                    continue;
                }

                DB::table('settings')->updateOrInsert(
                    ['key' => "feature_{$key}"],
                    ['value' => $on ? '1' : '0', 'group' => 'features', 'type' => 'bool', 'updated_at' => now()]
                );
            }
        });
    }

    /**
     * Set a new password for the shop's owner (by email), re-activating the
     * account. Returns the owner's email.
     */
    public function resetOwnerPassword(Tenant $tenant, string $password, ?string $email = null): string
    {
        $email ??= $tenant->owner_email;

        return $this->tenancy->run($tenant, function () use ($email, $password) {
            $user = $email ? User::query()->where('email', $email)->first() : null;

            // Fall back to the oldest admin when the recorded owner email no longer matches.
            $user ??= User::query()
                ->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
                ->orderBy('id')
                ->first();

            if (! $user) {
                throw new RuntimeException('No owner account found in this shop.');
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'is_active' => true,
            ])->save();

            // Sign the account out everywhere.
            DB::table('sessions')->where('user_id', $user->id)->delete();

            return $user->email;
        });
    }

    /** Headline numbers for the admin panel. */
    public function stats(Tenant $tenant): array
    {
        $counts = $this->tenancy->run($tenant, fn () => [
            'users' => DB::table('users')->count(),
            'customers' => DB::table('customers')->count(),
            'sales_invoices' => DB::table('sales_invoices')->count(),
            'repairs' => DB::table('repairs')->count(),
            'last_activity' => ($ts = DB::table('sessions')->whereNotNull('user_id')->max('last_activity'))
                ? date(DATE_ATOM, (int) $ts)
                : null,
        ]);

        $bytes = DB::connection(config('tenancy.central_connection'))
            ->table('information_schema.tables')
            ->where('table_schema', $tenant->database)
            ->sum(DB::raw('data_length + index_length'));

        return $counts + ['database_mb' => round(((int) $bytes) / 1048576, 2)];
    }

    /**
     * Issue the shop's HTTPS certificate and nginx site through the configured
     * privileged helper. Never throws: the outcome is recorded on the tenant.
     */
    public function issueCertificate(Tenant $tenant): Tenant
    {
        $command = trim((string) config('tenancy.ssl_command'));

        if ($command === '') {
            $tenant->update(['ssl_status' => 'skipped', 'ssl_message' => 'TENANCY_SSL_COMMAND is not configured.']);

            return $tenant;
        }

        try {
            $result = Process::timeout(240)->run([...preg_split('/\s+/', $command), $tenant->host()]);
            $output = Str::limit(trim($result->output()."\n".$result->errorOutput()), 2000);

            $tenant->update($result->successful()
                ? ['ssl_status' => 'issued', 'ssl_message' => null]
                : ['ssl_status' => 'failed', 'ssl_message' => $output ?: "Exit code {$result->exitCode()}"]);
        } catch (Throwable $e) {
            $tenant->update(['ssl_status' => 'failed', 'ssl_message' => Str::limit($e->getMessage(), 2000)]);
        }

        return $tenant;
    }

    /** Remove a shop from the registry, optionally dropping its database for good. */
    public function delete(Tenant $tenant, bool $dropDatabase): void
    {
        if ($dropDatabase) {
            $this->dropDatabase($tenant->database);
        }

        $tenant->delete();
    }

    /** Whether a new shop could take $slug right now. */
    public function slugAvailable(string $slug): bool
    {
        return Tenancy::isValidSlug($slug)
            && ! Tenancy::isReservedSlug($slug)
            && ! Tenant::query()->where('slug', $slug)->exists();
    }

    private function assertSlugAvailable(string $slug): void
    {
        if (! Tenancy::isValidSlug($slug)) {
            throw new RuntimeException("Invalid subdomain `{$slug}`.");
        }
        if (Tenancy::isReservedSlug($slug)) {
            throw new RuntimeException("Subdomain `{$slug}` is reserved.");
        }
        if (Tenant::query()->where('slug', $slug)->exists()) {
            throw new RuntimeException("Subdomain `{$slug}` is already taken.");
        }
    }

    private function central(): \Illuminate\Database\Connection
    {
        return DB::connection(config('tenancy.central_connection'));
    }

    private function databaseExists(string $database): bool
    {
        return $this->central()
            ->table('information_schema.schemata')
            ->where('schema_name', $database)
            ->exists();
    }

    private function createDatabase(string $database): void
    {
        $this->assertDatabaseName($database);

        if ($this->databaseExists($database)) {
            throw new RuntimeException("Database `{$database}` already exists.");
        }

        $this->central()->statement(
            "CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    private function dropDatabase(string $database): void
    {
        $this->assertDatabaseName($database);
        $this->central()->statement("DROP DATABASE IF EXISTS `{$database}`");
    }

    /** Only ever create or drop databases under the tenant prefix. */
    private function assertDatabaseName(string $database): void
    {
        $prefix = (string) config('tenancy.database_prefix');

        if ($prefix === '' || ! str_starts_with($database, $prefix) || ! preg_match('/^[a-z0-9_]+$/', $database)) {
            throw new RuntimeException("Refusing to create or drop database `{$database}`.");
        }
    }
}
