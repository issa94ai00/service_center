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
}
