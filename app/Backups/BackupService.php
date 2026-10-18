<?php

namespace App\Backups;

use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Takes, verifies, ships, restores and prunes database backups.
 *
 * A backup is `mysqldump | gzip` into the local backups disk. It only counts as
 * completed once gzip can read it back and the dump ends with mysqldump's
 * "Dump completed" trailer, so a dump cut short by a full disk or a killed
 * process is recorded as failed rather than offered for restore.
 */
class BackupService
{
    /** A running row older than this is a process that died without saying so. */
    private const ABANDONED_AFTER_MINUTES = 120;

    public function __construct(private readonly TenantManager $tenants) {}

    // ------------------------------------------------------------------ backup

    public function backupTenant(Tenant $tenant, string $trigger = Backup::TRIGGER_MANUAL, ?int $adminId = null): Backup
    {
        return $this->backup($tenant->database, $tenant->name, $trigger, $adminId, $tenant, $tenant->slug);
    }

    public function backupCentral(string $trigger = Backup::TRIGGER_MANUAL, ?int $adminId = null): Backup
    {
        return $this->backup($this->centralDatabase(), 'قاعدة البيانات المركزية', $trigger, $adminId, null, '_central');
    }

    private function backup(string $database, string $label, string $trigger, ?int $adminId, ?Tenant $tenant, string $folder): Backup
    {
        $startedAt = now();
        $path = sprintf('%s/%s/%s_%s_%s.sql.gz', $folder, $startedAt->format('Y/m'), $database, $startedAt->format('Ymd-His'), $trigger);

        $backup = Backup::query()->create([
            'tenant_id' => $tenant?->id,
            'database' => $database,
            'label' => $label,
            'trigger' => $trigger,
            'status' => Backup::STATUS_RUNNING,
            'path' => $path,
            'created_by' => $adminId,
            'started_at' => $startedAt,
            'offsite_status' => Backup::OFFSITE_DISABLED,
        ]);

        $absolute = $this->disk()->path($path);

        try {
            $this->withLock($database, function () use ($database, $absolute) {
                @mkdir(dirname($absolute), 0770, true);
                $this->dump($database, $absolute);
                $this->verify($absolute);
            });

            $backup->forceFill([
                'status' => Backup::STATUS_COMPLETED,
                'size_bytes' => filesize($absolute),
                'checksum' => hash_file('sha256', $absolute),
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            @unlink($absolute);
            Log::error("Backup of {$database} failed", ['error' => $e->getMessage()]);

            $backup->forceFill([
                'status' => Backup::STATUS_FAILED,
                'path' => null,
                'error' => Str::limit($this->scrub($e->getMessage()), 2000),
                'finished_at' => now(),
            ])->save();

            return $backup;
        }

        $this->shipOffsite($backup);

        return $backup;
    }

    private function dump(string $database, string $target): void
    {
        $this->withCredentials(function (string $credentials) use ($database, $target) {
            $command = sprintf(
                '%s --defaults-extra-file=%s --single-transaction --quick --skip-lock-tables --no-tablespaces '
                .'--set-gtid-purged=OFF --default-character-set=utf8mb4 --hex-blob --triggers %s | gzip -6 > %s',
                escapeshellcmd(config('backups.binaries.mysqldump')),
                escapeshellarg($credentials),
                escapeshellarg($database),
                escapeshellarg($target),
            );

            $this->shell($command);
        });
    }

    /** The file decompresses cleanly and mysqldump finished writing it. */
    private function verify(string $file): void
    {
        $this->shell(sprintf('gzip -t %s', escapeshellarg($file)));

        $tail = $this->shell(sprintf('gzip -dc %s | tail -c 512', escapeshellarg($file)));
        if (! str_contains($tail, 'Dump completed')) {
            throw new RuntimeException('النسخة غير مكتملة: لم يكتمل التصدير حتى نهايته.');
        }
    }

    private function shipOffsite(Backup $backup): void
    {
        $offsite = $this->offsite();
        if (! $offsite) {
            return;
        }

        try {
            $stream = $this->disk()->readStream($backup->path);
            $offsite->writeStream($backup->path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            $backup->forceFill(['offsite_status' => Backup::OFFSITE_UPLOADED, 'offsite_error' => null])->save();
        } catch (Throwable $e) {
            Log::error("Offsite upload of backup {$backup->id} failed", ['error' => $e->getMessage()]);
            $backup->forceFill([
                'offsite_status' => Backup::OFFSITE_FAILED,
                'offsite_error' => Str::limit($e->getMessage(), 1000),
            ])->save();
        }
    }

    /** Retry the offsite copy of a backup that failed to upload. */
    public function retryOffsite(Backup $backup): Backup
    {
        $this->shipOffsite($backup);

        return $backup->refresh();
    }

    // ----------------------------------------------------------------- restore

    /**
     * Replace a shop's database with a backup.
     *
     * The current data is backed up first; if that fails nothing is touched.
     * The backup is then loaded into a scratch database, so a damaged file is
     * caught before the live tables are dropped. Only then are the live tables
     * swapped for the scratch ones (a rename, not a second import) and the
     * schema migrated forward to the code that is running now. The shop is
     * closed to its users for the duration.
     *
     * @return Backup the safety backup taken of the data that was replaced
     */
    public function restore(Backup $backup, ?int $adminId = null): Backup
    {
        $tenant = $backup->tenant;
        if (! $backup->isRestorable() || ! $tenant) {
            throw new RuntimeException('هذه النسخة لا يمكن استعادتها.');
        }

        $file = $this->localCopy($backup);

        $safety = $this->backupTenant($tenant, Backup::TRIGGER_PRE_RESTORE, $adminId);
        if ($safety->status !== Backup::STATUS_COMPLETED) {
            throw new RuntimeException('تعذّر أخذ نسخة أمان من البيانات الحالية، لذلك أُلغيت الاستعادة: '.$safety->error);
        }

        $scratch = $this->scratchDatabase($tenant);
        $this->setMaintenance($tenant, 'جارٍ استعادة نسخة احتياطية');
        $liveTouched = false;

        try {
            $this->withLock($tenant->database, function () use ($file, $scratch, $tenant, &$liveTouched) {
                $this->connection()->statement("DROP DATABASE IF EXISTS `{$scratch}`");
                $this->connection()->statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                try {
                    $this->import($file, $scratch);
                    if (! $this->tables($scratch)->contains('name', 'migrations')) {
                        throw new RuntimeException('محتوى النسخة لا يشبه قاعدة بيانات متجر.');
                    }
                    $liveTouched = true;
                    $this->swap($scratch, $tenant->database);
                } finally {
                    $this->connection()->statement("DROP DATABASE IF EXISTS `{$scratch}`");
                }
            });
        } catch (Throwable $e) {
            // Until swap() starts the live tables are untouched and the shop can
            // reopen. After that it stays closed until someone restores the
            // safety backup.
            if (! $liveTouched) {
                $this->clearMaintenance($tenant);
            }
            throw new RuntimeException('فشلت الاستعادة: '.$this->scrub($e->getMessage()).' — نسخة الأمان رقم '.$safety->id.' محفوظة.', 0, $e);
        }

        // The backup may predate migrations the running code expects.
        try {
            $this->tenants->migrate($tenant);
        } catch (Throwable $e) {
            throw new RuntimeException('استُعيدت البيانات لكن تحديث بنيتها فشل، فبقي المتجر في وضع الصيانة: '.$this->scrub($e->getMessage()), 0, $e);
        }

        $backup->forceFill(['restored_at' => now()])->save();
        $this->clearMaintenance($tenant);

        return $safety;
    }

    private function import(string $file, string $database): void
    {
        $this->withCredentials(function (string $credentials) use ($file, $database) {
            $this->shell(sprintf(
                'gzip -dc %s | %s --defaults-extra-file=%s --default-character-set=utf8mb4 %s',
                escapeshellarg($file),
                escapeshellcmd(config('backups.binaries.mysql')),
                escapeshellarg($credentials),
                escapeshellarg($database),
            ));
        });
    }

    /** Drop the live tables and move the restored ones in, in one RENAME. */
    private function swap(string $from, string $to): void
    {
        $db = $this->connection();
        $restored = $this->tables($from);

        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($this->tables($to) as $table) {
                $kind = $table['type'] === 'VIEW' ? 'VIEW' : 'TABLE';
                $db->statement("DROP {$kind} IF EXISTS `{$to}`.`{$table['name']}`");
            }

            $renames = $restored->where('type', '!=', 'VIEW')
                ->map(fn ($t) => "`{$from}`.`{$t['name']}` TO `{$to}`.`{$t['name']}`")
                ->implode(', ');
            if ($renames !== '') {
                $db->statement("RENAME TABLE {$renames}");
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** The backup file on local disk, fetched back from offsite if it was pruned locally. */
    private function localCopy(Backup $backup): string
    {
        if (! $backup->path) {
            throw new RuntimeException('ملف النسخة غير موجود.');
        }

        if (! $this->disk()->exists($backup->path)) {
            $offsite = $this->offsite();
            if (! $offsite || $backup->offsite_status !== Backup::OFFSITE_UPLOADED || ! $offsite->exists($backup->path)) {
                throw new RuntimeException('ملف النسخة غير موجود على الخادم ولا في التخزين الخارجي.');
            }
            $this->disk()->writeStream($backup->path, $offsite->readStream($backup->path));
        }

        $absolute = $this->disk()->path($backup->path);
        if ($backup->checksum && hash_file('sha256', $absolute) !== $backup->checksum) {
            throw new RuntimeException('ملف النسخة تالف (لا يطابق بصمته المسجّلة).');
        }

        return $absolute;
    }

    // ------------------------------------------------------------ maintenance

    public function setMaintenance(Tenant $tenant, string $reason): void
    {
        $tenant->forceFill(['maintenance_at' => now(), 'maintenance_reason' => $reason])->save();
    }

    public function clearMaintenance(Tenant $tenant): void
    {
        $tenant->forceFill(['maintenance_at' => null, 'maintenance_reason' => null])->save();
    }

    // ------------------------------------------------------------------- prune

    /** @return int how many backups were deleted */
    public function prune(?Carbon $now = null): int
    {
        $now ??= now();

        // A process killed mid-dump leaves its row "running" forever.
        Backup::query()
            ->where('status', Backup::STATUS_RUNNING)
            ->where('started_at', '<', $now->copy()->subMinutes(self::ABANDONED_AFTER_MINUTES))
            ->update(['status' => Backup::STATUS_FAILED, 'error' => 'انقطعت العملية قبل اكتمالها.', 'finished_at' => $now]);

        $policy = RetentionPolicy::fromConfig();
        $timezone = config('backups.schedule.timezone');
        $deleted = 0;

        Backup::query()->get()->groupBy('database')->each(function (Collection $backups) use ($policy, $now, $timezone, &$deleted) {
            foreach ($policy->expired($backups, $now, $timezone) as $backup) {
                $this->delete($backup);
                $deleted++;
            }
        });

        return $deleted;
    }

    public function delete(Backup $backup): void
    {
        if ($backup->path) {
            $this->disk()->delete($backup->path);
            if ($backup->offsite_status === Backup::OFFSITE_UPLOADED && ($offsite = $this->offsite())) {
                try {
                    $offsite->delete($backup->path);
                } catch (Throwable $e) {
                    Log::warning("Could not delete offsite copy of backup {$backup->id}", ['error' => $e->getMessage()]);
                }
            }
        }

        $backup->delete();
    }

    // ------------------------------------------------------------------ status

    /**
     * Every database on the platform with its size and the state of its backups.
     *
     * @return array<int, array<string, mixed>>
     */
    public function databases(): array
    {
        $sizes = $this->sizes();
        $latest = Backup::query()
            ->whereIn('id', Backup::query()->selectRaw('MAX(id)')->groupBy('database'))
            ->get()->keyBy('database');
        $latestGood = Backup::query()
            ->whereIn('id', Backup::query()->selectRaw('MAX(id)')->where('status', Backup::STATUS_COMPLETED)->groupBy('database'))
            ->get()->keyBy('database');
        $counts = Backup::query()->where('status', Backup::STATUS_COMPLETED)
            ->selectRaw('`database`, COUNT(*) as n, COALESCE(SUM(size_bytes), 0) as bytes')
            ->groupBy('database')->get()->keyBy('database');

        $staleBefore = now()->subHours(config('backups.stale_after_hours'));

        $row = function (string $database, string $label, ?Tenant $tenant) use ($sizes, $latest, $latestGood, $counts, $staleBefore) {
            $good = $latestGood->get($database);
            $last = $latest->get($database);

            return [
                'database' => $database,
                'label' => $label,
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,
                    'maintenance' => $tenant->maintenance_at !== null,
                ] : null,
                'size_bytes' => $sizes[$database] ?? null,
                'backups_count' => (int) ($counts->get($database)?->n ?? 0),
                'backups_bytes' => (int) ($counts->get($database)?->bytes ?? 0),
                'last_backup_at' => $good?->created_at?->toIso8601String(),
                'last_attempt_failed' => $last?->status === Backup::STATUS_FAILED,
                'is_stale' => ! $good || $good->created_at->lt($staleBefore),
            ];
        };

        $rows = [$row($this->centralDatabase(), 'قاعدة البيانات المركزية', null)];
        foreach (Tenant::query()->where('status', '!=', Tenant::STATUS_ARCHIVED)->orderBy('name')->get() as $tenant) {
            $rows[] = $row($tenant->database, $tenant->name, $tenant);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $root = $this->disk()->path('');
        $lastScheduled = Backup::query()->where('trigger', Backup::TRIGGER_SCHEDULED)->latest('id')->first();

        return [
            'offsite' => [
                'configured' => $this->offsite() !== null,
                'failed' => Backup::query()->where('offsite_status', Backup::OFFSITE_FAILED)->count(),
            ],
            'schedule' => [
                'enabled' => (bool) config('backups.schedule.enabled'),
                'time' => config('backups.schedule.time'),
                'timezone' => config('backups.schedule.timezone'),
                'next_run_at' => $this->nextRun()?->toIso8601String(),
                'last_run_at' => $lastScheduled?->created_at?->toIso8601String(),
            ],
            'retention' => config('backups.retention'),
            'stale_after_hours' => (int) config('backups.stale_after_hours'),
            'storage' => [
                'used_bytes' => (int) Backup::query()->where('status', Backup::STATUS_COMPLETED)->sum('size_bytes'),
                'free_bytes' => is_dir($root) ? (int) @disk_free_space($root) : null,
            ],
            'recent_failures' => Backup::query()->where('status', Backup::STATUS_FAILED)
                ->where('created_at', '>=', now()->subDay())->count(),
        ];
    }

    public function nextRun(): ?Carbon
    {
        if (! config('backups.schedule.enabled')) {
            return null;
        }

        $tz = config('backups.schedule.timezone');
        [$hour, $minute] = array_map('intval', explode(':', config('backups.schedule.time')));
        $next = now($tz)->setTime($hour, $minute);

        return ($next->isPast() ? $next->addDay() : $next)->utc();
    }

    // ----------------------------------------------------------------- helpers

    public function disk(): Filesystem
    {
        return Storage::disk(config('backups.disk'));
    }

    public function offsite(): ?Filesystem
    {
        $name = config('backups.offsite_disk');

        return $name ? Storage::disk($name) : null;
    }

    public function centralDatabase(): string
    {
        return (string) config('database.connections.'.config('tenancy.central_connection').'.database');
    }

    private function connection()
    {
        return DB::connection(config('tenancy.central_connection'));
    }

    /** @return Collection<int, array{name: string, type: string}> */
    private function tables(string $database): Collection
    {
        return collect($this->connection()->select(
            'SELECT TABLE_NAME AS name, TABLE_TYPE AS type FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
            [$database],
        ))->map(fn ($r) => ['name' => $r->name, 'type' => $r->type === 'VIEW' ? 'VIEW' : 'TABLE']);
    }

    /** @return array<string, int> database => bytes */
    private function sizes(): array
    {
        try {
            return collect($this->connection()->select(
                'SELECT TABLE_SCHEMA AS db, SUM(DATA_LENGTH + INDEX_LENGTH) AS bytes FROM information_schema.TABLES GROUP BY TABLE_SCHEMA'
            ))->mapWithKeys(fn ($r) => [$r->db => (int) $r->bytes])->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** Shop databases share the prefix the DB user is granted on, so the scratch one does too. */
    private function scratchDatabase(Tenant $tenant): string
    {
        return config('tenancy.database_prefix').'_restore_'.$tenant->id;
    }

    /** One dump or restore per database at a time, across web requests and the scheduler. */
    private function withLock(string $database, callable $callback): mixed
    {
        $dir = $this->disk()->path('.locks');
        @mkdir($dir, 0770, true);
        $handle = fopen("{$dir}/{$database}.lock", 'c');

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('عملية أخرى تعمل على قاعدة البيانات هذه الآن. أعد المحاولة بعد قليل.');
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Hands the MySQL credentials over in a private file, never on the command line. */
    private function withCredentials(callable $callback): mixed
    {
        $config = config('database.connections.'.config('tenancy.central_connection'));
        $quote = fn ($v) => '"'.addcslashes((string) $v, "\\\"").'"';

        $file = tempnam(sys_get_temp_dir(), 'bkp');
        chmod($file, 0600);
        file_put_contents($file, implode("\n", array_filter([
            '[client]',
            'user='.$quote($config['username']),
            'password='.$quote($config['password']),
            ! empty($config['unix_socket']) ? 'socket='.$quote($config['unix_socket']) : 'host='.$quote($config['host']),
            empty($config['unix_socket']) ? 'port='.(int) $config['port'] : null,
        ]))."\n");

        try {
            return $callback($file);
        } finally {
            @unlink($file);
        }
    }

    private function shell(string $command): string
    {
        $result = Process::timeout(config('backups.timeout'))->run(['bash', '-o', 'pipefail', '-c', $command]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'فشل الأمر بالرمز '.$result->exitCode());
        }

        return $result->output();
    }

    /** Keep the database password out of anything shown or stored. */
    private function scrub(string $message): string
    {
        $password = (string) config('database.connections.'.config('tenancy.central_connection').'.password');

        return $password !== '' ? str_replace($password, '••••', $message) : $message;
    }
}
