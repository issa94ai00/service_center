<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes each request to its part of the platform by host (config/tenancy.php).
 *
 * Global and prepended, so the database is switched before the session starts:
 * a shop's sessions live in the shop's own database.
 */
class IdentifyTenant
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenancy->enabled()) {
            return $next($request);
        }

        return $this->forgetLegacyCookies($this->route($request, $next));
    }

    private function route(Request $request, Closure $next): Response
    {
        [$context, $slug] = $this->tenancy->resolveHost($request->getHost());

        // The health check answers on any host.
        if ($request->is('up')) {
            return $next($request);
        }

        if ($context === Tenancy::TENANT) {
            return $this->serveTenant($request, $next, $slug);
        }

        // Anything that is not a shop runs on the central database. A host
        // outside the platform (the bare IP, say) is treated as the public site.
        $context = $context === Tenancy::ADMIN ? Tenancy::ADMIN : Tenancy::CENTRAL;
        $this->tenancy->useCentral($context);

        // Each central host answers only its own API: sign-up on the public
        // site, tenant management on admin.*.
        $allowedApi = $context === Tenancy::ADMIN
            ? $request->is('api/admin', 'api/admin/*')
            : $request->is('api/public/*');
        if ($request->is('api/*') && ! $allowedApi) {
            return $this->deny($request, Response::HTTP_NOT_FOUND, 'هذا العنوان لا يخدم واجهة المتجر.');
        }

        return $next($request);
    }

    private function serveTenant(Request $request, Closure $next, string $slug): Response
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant || $tenant->status === Tenant::STATUS_ARCHIVED) {
            return $this->deny($request, Response::HTTP_NOT_FOUND, 'لا يوجد متجر بهذا العنوان.');
        }

        if ($tenant->status === Tenant::STATUS_SUSPENDED) {
            return $this->deny($request, Response::HTTP_FORBIDDEN, 'حساب هذا المتجر موقوف مؤقتاً. يرجى التواصل مع الدعم الفني.', $tenant);
        }

        if ($tenant->isExpired()) {
            return $this->deny($request, Response::HTTP_PAYMENT_REQUIRED, 'انتهى اشتراك هذا المتجر. يرجى التواصل مع الدعم الفني للتجديد.', $tenant);
        }

        if ($request->is('api/admin', 'api/admin/*', 'api/public/*')) {
            return $this->deny($request, Response::HTTP_NOT_FOUND, 'Not found.');
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }

    private function deny(Request $request, int $status, string $message, ?Tenant $tenant = null): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $message, 'tenant_status' => $status], $status);
        }

        return response()->view('tenancy.unavailable', [
            'message' => $message,
            'tenant' => $tenant,
            'centralUrl' => 'https://'.config('tenancy.central_domain'),
        ], $status);
    }

    /**
     * Before tenancy the session and CSRF cookies were scoped to the whole
     * central domain, so browsers would keep sending them to every shop host
     * next to the new host-only ones. Expire those old domain-wide copies.
     */
    private function forgetLegacyCookies(Response $response): Response
    {
        $domain = config('tenancy.legacy_cookies.domain');
        if (! $domain) {
            return $response;
        }

        $names = array_filter([
            ...(array) config('tenancy.legacy_cookies.names'),
            'XSRF-TOKEN',
            Auth::guard('web')->getRecallerName(),
        ]);

        foreach (array_unique($names) as $name) {
            $response->headers->setCookie(Cookie::create($name, '', 1, '/', $domain));
        }

        return $response;
    }
}
