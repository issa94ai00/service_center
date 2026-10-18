<?php

namespace Tests\Unit;

use App\Backups\RetentionPolicy;
use App\Models\Central\Backup;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackupRetentionTest extends TestCase
{
    private int $id = 0;

    private function backup(string $createdAt, string $trigger = Backup::TRIGGER_SCHEDULED, string $status = Backup::STATUS_COMPLETED): Backup
    {
        $backup = new Backup;
        $backup->forceFill([
            'id' => ++$this->id,
            'trigger' => $trigger,
            'status' => $status,
            'created_at' => Carbon::parse($createdAt, 'UTC'),
        ]);

        return $backup;
    }

    public function test_nightly_backups_thin_to_seven_days_four_weeks_three_months(): void
    {
        $now = Carbon::parse('2026-10-18 03:00', 'UTC');
        $backups = collect(range(0, 119))->map(fn ($d) => $this->backup($now->copy()->subDays($d)->toDateTimeString()));

        $kept = $backups->diff((new RetentionPolicy(7, 4, 3))->expired($backups, $now));
        $keptDays = $kept->map(fn ($b) => $b->created_at->toDateString())->sort()->values()->all();

        // The last 7 days...
        foreach (range(0, 6) as $d) {
            $this->assertContains($now->copy()->subDays($d)->toDateString(), $keptDays);
        }
        // ...the newest of each of the last 4 ISO weeks and 3 months, overlapping where they coincide.
        $this->assertContains('2026-10-04', $keptDays); // Sunday closing week 40
        $this->assertContains('2026-09-30', $keptDays); // end of September
        $this->assertContains('2026-08-31', $keptDays); // end of August
        $this->assertNotContains('2026-07-31', $keptDays); // a fourth month is past retention
        $this->assertLessThanOrEqual(14, count($keptDays));
    }

    public function test_adhoc_backups_live_a_fixed_number_of_days(): void
    {
        $now = Carbon::parse('2026-10-18', 'UTC');
        $recent = $this->backup('2026-10-01', Backup::TRIGGER_MANUAL);
        $old = $this->backup('2026-09-01', Backup::TRIGGER_PRE_RESTORE);
        $newest = $this->backup('2026-10-17');

        $expired = (new RetentionPolicy(7, 4, 3, 30))->expired(collect([$recent, $old, $newest]), $now);

        $this->assertSame([$old->id], $expired->pluck('id')->all());
    }

    public function test_failed_and_running_backups(): void
    {
        $now = Carbon::parse('2026-10-18', 'UTC');
        $oldFailure = $this->backup('2026-08-01', status: Backup::STATUS_FAILED);
        $freshFailure = $this->backup('2026-10-17', status: Backup::STATUS_FAILED);
        $running = $this->backup('2026-01-01', status: Backup::STATUS_RUNNING);

        $expired = (new RetentionPolicy(failedDays: 30))->expired(collect([$oldFailure, $freshFailure, $running]), $now);

        $this->assertSame([$oldFailure->id], $expired->pluck('id')->all());
    }

    public function test_the_only_good_backup_is_never_deleted(): void
    {
        $now = Carbon::parse('2026-10-18', 'UTC');
        // A database whose nightly job has been failing for a year: its one old
        // manual backup is all there is to restore from.
        $lone = $this->backup('2025-10-18', Backup::TRIGGER_MANUAL);

        $this->assertTrue((new RetentionPolicy)->expired(collect([$lone]), $now)->isEmpty());
    }

    public function test_days_are_counted_in_the_shops_timezone(): void
    {
        // 22:30 UTC on the 17th is already the 18th in Damascus (UTC+3): both
        // backups fall on the same local day, so only the newer one is the day's.
        $now = Carbon::parse('2026-10-18 06:00', 'UTC');
        $earlier = $this->backup('2026-10-17 22:30');
        $later = $this->backup('2026-10-18 05:00');

        $expired = (new RetentionPolicy(daily: 7, weekly: 0, monthly: 0))
            ->expired(collect([$earlier, $later]), $now, 'Asia/Damascus');

        $this->assertSame([$earlier->id], $expired->pluck('id')->all());
    }
}
