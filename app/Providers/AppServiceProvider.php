<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Throttle by account as well as by address: an IP-only limit lets a
        // botnet spread a password-spraying run thinly enough to never trip it.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        // Baseline ceiling for the rest of the API, which had none at all.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // PDF rendering is CPU-bound, so it gets a tighter budget of its own.
        RateLimiter::for('documents', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
