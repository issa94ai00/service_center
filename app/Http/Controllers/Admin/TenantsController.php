<?php

namespace App\Http\Controllers\Admin;

use App\Backups\BackupService;
use App\Http\Controllers\Controller;
use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use App\Tenancy\Tenancy;
use App\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class TenantsController extends Controller
{
    public function __construct(
        private readonly TenantManager $manager,
        private readonly BackupService $backups,
    ) {}

    public function meta(): JsonResponse
    {
        return response()->json([
            'central_domain' => config('tenancy.central_domain'),
            'features' => config('tenancy.features'),
            'reserved' => config('tenancy.reserved'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $term) {
                $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('owner_email', 'like', "%{$term}%")
                    ->orWhere('contact_phone', 'like', "%{$term}%"));
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (Tenant $t) => $this->present($t));

        return response()->json(['data' => $tenants]);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $payload = $this->present($tenant);

        try {
            $payload['features'] = $this->manager->features($tenant);
            $payload['stats'] = $this->manager->stats($tenant);
            $payload['pending_migrations'] = $this->manager->pendingMigrations($tenant);
        } catch (Throwable $e) {
            Log::warning("Tenant {$tenant->slug}: could not read its database", ['error' => $e->getMessage()]);
            $payload['database_error'] = 'تعذّر الوصول إلى قاعدة بيانات المتجر.';
        }

        $last = Backup::query()->where('tenant_id', $tenant->id)->where('status', Backup::STATUS_COMPLETED)->latest('id')->first();
        $payload['backups'] = [
            'count' => Backup::query()->where('tenant_id', $tenant->id)->where('status', Backup::STATUS_COMPLETED)->count(),
            'last_at' => $last?->created_at?->toIso8601String(),
            'last_size_bytes' => $last?->size_bytes,
        ];

        return response()->json(['data' => $payload]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => [
                'required', 'string', 'max:40',
                function ($attribute, $value, $fail) {
                    $value = strtolower((string) $value);
                    if (! Tenancy::isValidSlug($value)) {
                        $fail('النطاق الفرعي يقبل الأحرف الإنجليزية الصغيرة والأرقام والشرطة فقط، ولا يبدأ أو ينتهي بشرطة.');
                    } elseif (Tenancy::isReservedSlug($value)) {
                        $fail('هذا النطاق الفرعي محجوز.');
                    }
                },
                Rule::unique(Tenant::class, 'slug'),
            ],
            'name' => ['required', 'string', 'max:120'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
            'owner_password' => ['required', 'string', Password::min(8)],
            'plan' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['boolean'],
        ]);

        try {
            $tenant = $this->manager->create($data);
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed', ['slug' => $data['slug'], 'error' => $e->getMessage()]);

            return response()->json(['message' => 'تعذّر إنشاء المتجر: '.$e->getMessage()], 500);
        }

        return response()->json(['data' => $this->present($tenant)], 201);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in([Tenant::STATUS_ACTIVE, Tenant::STATUS_SUSPENDED, Tenant::STATUS_ARCHIVED])],
            'plan' => ['sometimes', 'nullable', 'string', 'max:40'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'owner_email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['boolean'],
        ]);

        if (array_key_exists('features', $data)) {
            $this->manager->setFeatures($tenant, $data['features']);
            unset($data['features']);
        }

        $tenant->update($data);

        return $this->show($tenant->refresh());
    }

    public function resetPassword(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        try {
            $email = $this->manager->resetOwnerPassword($tenant, $data['password'], $data['email'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => "تم تعيين كلمة مرور جديدة للحساب {$email}."]);
    }

    public function issueCertificate(Tenant $tenant): JsonResponse
    {
        $this->manager->issueCertificate($tenant);

        return response()->json(['data' => $this->present($tenant->refresh())]);
    }

    /** Bring the shop's schema up to date, backing it up first when there is anything to run. */
    public function migrate(Request $request, Tenant $tenant): JsonResponse
    {
        @set_time_limit((int) config('backups.timeout'));

        try {
            $pending = $this->manager->pendingMigrations($tenant);
        } catch (Throwable $e) {
            return response()->json(['message' => 'تعذّر قراءة حالة قاعدة البيانات: '.$e->getMessage()], 500);
        }

        if (! $pending) {
            return response()->json(['message' => 'قاعدة البيانات محدّثة، لا توجد تحديثات معلّقة.', 'pending_migrations' => []]);
        }

        $backup = $this->backups->backupTenant($tenant, Backup::TRIGGER_PRE_MIGRATE, $request->user('admin')?->id);
        if ($backup->status !== Backup::STATUS_COMPLETED) {
            return response()->json(['message' => 'أُلغي التحديث لأن النسخة الاحتياطية قبله فشلت: '.$backup->error], 500);
        }

        try {
            $this->manager->migrate($tenant);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()."\nنسخة ما قبل التحديث رقم {$backup->id} محفوظة."], 500);
        }

        return response()->json([
            'message' => 'طُبّق '.count($pending).' تحديث على قاعدة البيانات بعد أخذ نسخة احتياطية.',
            'pending_migrations' => [],
        ]);
    }

    /** Close or reopen the shop to its users without touching its status. */
    public function maintenance(Request $request, Tenant $tenant): JsonResponse
    {
        $on = $request->validate(['on' => ['required', 'boolean']])['on'];

        $on
            ? $this->backups->setMaintenance($tenant, 'صيانة يدوية من إدارة المنصة')
            : $this->backups->clearMaintenance($tenant);

        return response()->json(['data' => $this->present($tenant->refresh())]);
    }

    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'confirm_slug' => ['required', 'string', Rule::in([$tenant->slug])],
            'drop_database' => ['boolean'],
        ], [
            'confirm_slug.in' => 'اكتب النطاق الفرعي للمتجر تماماً لتأكيد الحذف.',
        ]);

        if ($tenant->status !== Tenant::STATUS_ARCHIVED) {
            return response()->json(['message' => 'أرشف المتجر أولاً قبل حذفه نهائياً.'], 422);
        }

        // Dropping the database is the one step nothing else can undo; keep a last copy.
        if ($data['drop_database'] ?? false) {
            @set_time_limit((int) config('backups.timeout'));
            $backup = $this->backups->backupTenant($tenant, Backup::TRIGGER_PRE_DELETE, $request->user('admin')?->id);
            if ($backup->status !== Backup::STATUS_COMPLETED) {
                return response()->json(['message' => 'أُلغي الحذف لأن النسخة الاحتياطية الأخيرة فشلت: '.$backup->error], 422);
            }
        }

        try {
            $this->manager->delete($tenant, (bool) ($data['drop_database'] ?? false));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'تم حذف المتجر.']);
    }

    private function present(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'url' => $tenant->url(),
            'host' => $tenant->host(),
            'database' => $tenant->database,
            'status' => $tenant->status,
            'source' => $tenant->source,
            'is_expired' => $tenant->isExpired(),
            'is_accessible' => $tenant->isAccessible(),
            'plan' => $tenant->plan,
            'expires_at' => $tenant->expires_at?->toDateString(),
            'owner_name' => $tenant->owner_name,
            'owner_email' => $tenant->owner_email,
            'contact_phone' => $tenant->contact_phone,
            'notes' => $tenant->notes,
            'ssl_status' => $tenant->ssl_status,
            'ssl_message' => $tenant->ssl_message,
            'maintenance_at' => $tenant->maintenance_at?->toIso8601String(),
            'maintenance_reason' => $tenant->maintenance_reason,
            'created_at' => $tenant->created_at?->toIso8601String(),
        ];
    }
}
