<?php

namespace App\Backups;

use App\Models\Central\Backup;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Decides which backups of one database to delete.
 *
 * Scheduled backups are thinned grandfather-father-son style: the newest
 * completed backup of each of the last N days, weeks and months survives.
 * Anything else — taken by hand, before a restore or a migration — lives a
 * fixed number of days, as do failed attempts. A running backup is never
 * touched, and the single newest good backup always survives so a database
 * is never left with nothing to restore.
 */
class RetentionPolicy
{
    public function __construct(
        private readonly int $daily = 7,
        private readonly int $weekly = 4,
        private readonly int $monthly = 3,
        private readonly int $adhocDays = 30,
        private readonly int $failedDays = 30,
    ) {}

    public static function fromConfig(): self
    {
        $r = config('backups.retention');

        return new self($r['daily'], $r['weekly'], $r['monthly'], $r['adhoc_days'], $r['failed_days']);
    }

    /**
     * @param  Collection<int, Backup>  $backups  every backup of one database
     * @return Collection<int, Backup> the ones to delete
     */
    public function expired(Collection $backups, CarbonInterface $now, string $timezone = 'UTC'): Collection
    {
        $completed = $backups->where('status', Backup::STATUS_COMPLETED)->sortByDesc('created_at')->values();
        $keep = [];

        if ($newest = $completed->first()) {
            $keep[$newest->id] = true;
        }

        $scheduled = $completed->where('trigger', Backup::TRIGGER_SCHEDULED);
        // Buckets are counted in local time, so "a day" is the shop owner's day.
        $local = fn (Backup $b) => $b->created_at->copy()->setTimezone($timezone);

        foreach ([
            [$this->daily, fn ($b) => $local($b)->format('Y-m-d')],
            [$this->weekly, fn ($b) => $local($b)->format('o-W')],
            [$this->monthly, fn ($b) => $local($b)->format('Y-m')],
        ] as [$count, $bucket]) {
            // Newest first, so the first backup seen in a bucket is that bucket's newest.
            $scheduled->groupBy($bucket)->take($count)->each(function (Collection $group) use (&$keep) {
                $keep[$group->first()->id] = true;
            });
        }

        return $backups->filter(function (Backup $backup) use ($keep, $now) {
            if (isset($keep[$backup->id]) || $backup->status === Backup::STATUS_RUNNING) {
                return false;
            }

            $ageDays = $backup->created_at->diffInDays($now);

            return match (true) {
                $backup->status === Backup::STATUS_FAILED => $ageDays >= $this->failedDays,
                $backup->trigger === Backup::TRIGGER_SCHEDULED => true,
                default => $ageDays >= $this->adhocDays,
            };
        })->values();
    }
}
