<?php

namespace App\Console\Commands;

use App\Backups\BackupService;
use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;

/**
 * Back up the central registry and every shop that is not archived.
 */
class BackupsRun extends Command
{
    protected $signature = 'backups:run
        {--tenant=* : Only these shop subdomains}
        {--skip-central : Do not back up the central database}
        {--trigger=manual : scheduled | manual}';

    protected $description = 'Back up the central database and every shop database';

    public function handle(BackupService $backups): int
    {
        $trigger = $this->option('trigger') === Backup::TRIGGER_SCHEDULED ? Backup::TRIGGER_SCHEDULED : Backup::TRIGGER_MANUAL;
        $results = collect();

        if (! $this->option('skip-central') && ! $this->option('tenant')) {
            $results->push($this->report($backups->backupCentral($trigger)));
        }

        Tenant::query()
            ->where('status', '!=', Tenant::STATUS_ARCHIVED)
            ->when($this->option('tenant'), fn ($q, $slugs) => $q->whereIn('slug', $slugs))
            ->orderBy('id')
            ->each(fn (Tenant $tenant) => $results->push($this->report($backups->backupTenant($tenant, $trigger))));

        $failed = $results->where('status', Backup::STATUS_FAILED)->count();
        $this->newLine();
        $this->info("{$results->count()} database(s), {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function report(Backup $backup): Backup
    {
        $detail = $backup->status === Backup::STATUS_COMPLETED
            ? sprintf('<fg=green>%s KB</>%s', number_format($backup->size_bytes / 1024, 1), $backup->offsite_status === Backup::OFFSITE_FAILED ? ' <fg=yellow>(offsite failed)</>' : '')
            : '<fg=red>FAILED</> '.$backup->error;

        $this->components->twoColumnDetail($backup->database, $detail);

        return $backup;
    }
}
