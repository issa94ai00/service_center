<?php

namespace App\Providers;

use App\Tenancy\Tenancy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenancy::class);
    }

    public function boot(): void
    {
        $this->guardUntargetedMigrations();

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

    /**
     * With tenancy on, the default connection is the central database, so a bare
     * `php artisan migrate` would build every shop table there. Database
     * commands must name their target: --database=central for the registry,
     * or `tenants:migrate` for the shops.
     */
    private function guardUntargetedMigrations(): void
    {
        if (! config('tenancy.enabled') || ! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            $command = (string) $event->command;
            $touchesSchema = str_starts_with($command, 'migrate') || in_array($command, ['db:seed', 'db:wipe'], true);

            if ($touchesSchema && ! $event->input->hasParameterOption('--database')) {
                throw new \RuntimeException(
                    "Tenancy is enabled: run `{$command}` with --database=central "
                    .'(and --path=database/migrations/central), or use `php artisan tenants:migrate` for the shops.'
                );
            }
        });
    }
}
