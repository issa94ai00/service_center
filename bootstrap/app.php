<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureUserIsActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'permission' => CheckPermission::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // A baseline request ceiling for the whole API. Only /login was
        // throttled before, leaving every other endpoint — including the
        // CPU-bound PDF renderer — open to unbounded automated traffic.
        $middleware->api(append: [
            'throttle:api',
        ]);

        // Laravel registers a default guest redirect that calls route('login').
        // The SPA owns /login client-side, so that route does not exist and the
        // call throws inside the middleware, before the exception handler can
        // turn it into a 401 — see shouldRenderJsonWhen() below, which handles
        // the other half.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/login'
        );

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The SPA owns /login client-side, so there is no named login route for
        // the handler to redirect a guest to. Without this, any unauthenticated
        // browser navigation to an API URL — a bookmarked PDF link, say — died
        // with "Route [login] not defined" (500) instead of a clean 401, because
        // a navigation does not send an Accept: application/json header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'field' => $e->field,
                ], Response::HTTP_CONFLICT);
            }

            throw $e;
        });
    })->create();