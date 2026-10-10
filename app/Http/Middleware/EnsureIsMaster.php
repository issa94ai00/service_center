<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, __('Unauthenticated.'));
        }

        if (! $user->hasRole('master')) {
            abort(Response::HTTP_FORBIDDEN, __('هذه الميزة وإدارتها مخصصة حصرياً للمدير العام (Master).'));
        }

        return $next($request);
    }
}
