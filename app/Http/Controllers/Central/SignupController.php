<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Self-service sign-up on the public site: a visitor creates their own shop on
 * a free trial (config/tenancy.php → signup).
 */
class SignupController extends Controller
{
    public function __construct(private readonly TenantManager $manager) {}

    public function checkSlug(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $slug = strtolower(trim((string) $request->query('slug')));

        $reason = match (true) {
            ! Tenancy::isValidSlug($slug) => 'invalid',
            Tenancy::isReservedSlug($slug) => 'reserved',
            ! $this->manager->slugAvailable($slug) => 'taken',
            default => null,
        };

        return response()->json(['slug' => $slug, 'available' => $reason === null, 'reason' => $reason]);
    }

    public function register(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $data = $request->validate([
            'shop_name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => [
                'required', 'string', 'max:40',
                function ($attribute, $value, $fail) {
                    $value = strtolower((string) $value);
                    if (! Tenancy::isValidSlug($value)) {
                        $fail('عنوان المتجر يقبل الأحرف الإنجليزية الصغيرة والأرقام والشرطة فقط، ولا يبدأ أو ينتهي بشرطة.');
                    } elseif (Tenancy::isReservedSlug($value)) {
                        $fail('هذا العنوان محجوز، اختر عنواناً آخر.');
                    }
                },
                Rule::unique(Tenant::class, 'slug'),
            ],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique(Tenant::class, 'owner_email')->where('source', 'signup'),
            ],
            'phone' => ['required', 'string', 'min:6', 'max:40'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
            // Honeypot: hidden from people, filled in by form bots.
            'website' => ['prohibited'],
        ], [
            'slug.unique' => 'هذا العنوان مستخدم لمتجر آخر، اختر عنواناً آخر.',
            'email.unique' => 'هذا البريد مسجّل مسبقاً لمتجر. سجّل الدخول إلى متجرك أو تواصل معنا.',
            'terms.accepted' => 'يجب الموافقة على شروط الاستخدام.',
        ]);

        $recent = Tenant::query()
            ->where('source', 'signup')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($recent >= (int) config('tenancy.signup.daily_limit')) {
            return response()->json([
                'message' => 'التسجيل مزدحم حالياً. حاول لاحقاً أو تواصل معنا لإنشاء متجرك.',
            ], 429);
        }

        $trialDays = (int) config('tenancy.signup.trial_days');

        try {
            $tenant = $this->manager->create([
                'slug' => strtolower($data['slug']),
                'name' => $data['shop_name'],
                'owner_name' => $data['owner_name'],
                'owner_email' => $data['email'],
                'owner_password' => $data['password'],
                'contact_phone' => $data['phone'],
                'plan' => 'تجريبية',
                'expires_at' => now()->addDays($trialDays)->toDateString(),
                'notes' => 'تسجيل ذاتي من الموقع.',
                'source' => 'signup',
                'signup_ip' => $request->ip(),
            ]);
        } catch (Throwable $e) {
            Log::error('Self sign-up failed', ['slug' => $data['slug'], 'error' => $e->getMessage()]);

            return response()->json(['message' => 'تعذّر إنشاء المتجر الآن. حاول مرة أخرى بعد قليل.'], 500);
        }

        return response()->json([
            'data' => [
                'name' => $tenant->name,
                'host' => $tenant->host(),
                'login_url' => $tenant->url().'/login',
                'expires_at' => $tenant->expires_at?->toDateString(),
                'secure' => $tenant->ssl_status === 'issued',
            ],
        ], 201);
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('tenancy.signup.enabled'), 404);
    }
}
