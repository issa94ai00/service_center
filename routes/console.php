<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retry HTTPS certificates for shops that do not have one yet. Hourly keeps a
// host that keeps failing under Let's Encrypt's failed-validation limit.
// Needs the scheduler cron: * * * * * php artisan schedule:run
if (config('tenancy.enabled')) {
    Schedule::command('tenants:certificate --pending')->hourly()->withoutOverlapping();

    // Nightly backups of every database, then thin them out (config/backups.php).
    if (config('backups.schedule.enabled')) {
        Schedule::command('backups:run --trigger=scheduled')
            ->dailyAt(config('backups.schedule.time'))
            ->timezone(config('backups.schedule.timezone'))
            ->withoutOverlapping(120)
            // Prune even when one shop failed: the others still got a new backup.
            ->after(fn () => Artisan::call('backups:prune'));
    }
}
