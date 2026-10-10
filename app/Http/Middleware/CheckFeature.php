<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // Master users bypass all feature restrictions
        if (! Features::enabled($feature, $request->user())) {
            abort(Response::HTTP_FORBIDDEN, __('هذه الميزة غير مفعلة في النظام حالياً.'));
        }

        return $next($request);
    }
}
