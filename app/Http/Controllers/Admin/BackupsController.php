<?php

namespace App\Http\Controllers\Admin;

use App\Backups\BackupService;
use App\Http\Controllers\Controller;
use App\Models\Central\Backup;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Database backups for the platform admin (admin.<central domain>).
 */
class BackupsController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    /** Health of every database, plus schedule, storage and offsite status. */
    public function overview(): JsonResponse
    {
        return response()->json([
            'data' => [
                'summary' => $this->backups->summary(),
                'databases' => $this->backups->databases(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'database' => ['nullable', 'string', 'max:64'],
            'tenant_id' => ['nullable', 'integer'],
            'trigger' => ['nullable', Rule::in($this->triggers())],
            'status' => ['nullable', Rule::in([Backup::STATUS_RUNNING, Backup::STATUS_COMPLETED, Backup::STATUS_FAILED])],
        ]);

        $page = Backup::query()
            ->with(['tenant:id,slug,name', 'creator:id,name'])
            ->when($filters['database'] ?? null, fn ($q, $v) => $q->where('database', $v))
            ->when($filters['tenant_id'] ?? null, fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($filters['trigger'] ?? null, fn ($q, $v) => $q->where('trigger', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Backup $b) => $this->present($b)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Back up one database now: a shop by tenant_id, or the central registry. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['nullable', 'integer', Rule::exists(Tenant::class, 'id')],
            'central' => ['boolean'],
        ]);

        if (empty($data['tenant_id']) && empty($data['central'])) {
            return response()->json(['message' => 'حدّد قاعدة البيانات المطلوب نسخها.'], 422);
        }

        @set_time_limit((int) config('backups.timeout'));
        $adminId = $request->user('admin')?->id;

        $backup = ! empty($data['tenant_id'])
            ? $this->backups->backupTenant(Tenant::query()->findOrFail($data['tenant_id']), Backup::TRIGGER_MANUAL, $adminId)
            : $this->backups->backupCentral(Backup::TRIGGER_MANUAL, $adminId);

        if ($backup->status !== Backup::STATUS_COMPLETED) {
            return response()->json(['message' => 'فشل النسخ الاحتياطي: '.$backup->error, 'data' => $this->present($backup)], 500);
        }

        return response()->json(['message' => 'تم أخذ النسخة الاحتياطية.', 'data' => $this->present($backup)], 201);
    }

    public function download(Backup $backup): StreamedResponse|JsonResponse
    {
        if ($backup->status !== Backup::STATUS_COMPLETED || ! $backup->path) {
            return response()->json(['message' => 'هذه النسخة غير متاحة للتنزيل.'], 404);
        }

        $disk = $this->backups->disk();
        if (! $disk->exists($backup->path)) {
            $offsite = $this->backups->offsite();
            if ($offsite && $backup->offsite_status === Backup::OFFSITE_UPLOADED && $offsite->exists($backup->path)) {
                return $offsite->download($backup->path, basename($backup->path));
            }

            return response()->json(['message' => 'ملف النسخة غير موجود.'], 404);
        }

        return $disk->download($backup->path, basename($backup->path), ['Content-Type' => 'application/gzip']);
    }

    public function restore(Request $request, Backup $backup): JsonResponse
    {
        $tenant = $backup->tenant;
        if (! $backup->isRestorable() || ! $tenant) {
            return response()->json(['message' => 'هذه النسخة لا يمكن استعادتها من هنا.'], 422);
        }

        $request->validate([
            'confirm_slug' => ['required', 'string', Rule::in([$tenant->slug])],
        ], [
            'confirm_slug.in' => 'اكتب النطاق الفرعي للمتجر تماماً لتأكيد الاستعادة.',
        ]);

        @set_time_limit((int) config('backups.timeout') * 2);

        try {
            $safety = $this->backups->restore($backup, $request->user('admin')?->id);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'تمت استعادة '.$tenant->name.' إلى نسخة '.$backup->created_at->format('Y-m-d H:i').'. حُفظت البيانات السابقة في النسخة رقم '.$safety->id.'.',
            'data' => $this->present($backup->refresh()),
        ]);
    }

    public function retryOffsite(Backup $backup): JsonResponse
    {
        if ($backup->status !== Backup::STATUS_COMPLETED || ! $this->backups->offsite()) {
            return response()->json(['message' => 'لا يمكن رفع هذه النسخة.'], 422);
        }

        $backup = $this->backups->retryOffsite($backup);

        return $backup->offsite_status === Backup::OFFSITE_UPLOADED
            ? response()->json(['message' => 'رُفعت النسخة إلى التخزين الخارجي.', 'data' => $this->present($backup)])
            : response()->json(['message' => 'فشل الرفع: '.$backup->offsite_error, 'data' => $this->present($backup)], 500);
    }

    public function destroy(Backup $backup): JsonResponse
    {
        if ($backup->status === Backup::STATUS_RUNNING) {
            return response()->json(['message' => 'لا يمكن حذف نسخة قيد التنفيذ.'], 422);
        }

        $this->backups->delete($backup);

        return response()->json(['message' => 'تم حذف النسخة.']);
    }

    private function triggers(): array
    {
        return [
            Backup::TRIGGER_SCHEDULED, Backup::TRIGGER_MANUAL, Backup::TRIGGER_PRE_RESTORE,
            Backup::TRIGGER_PRE_MIGRATE, Backup::TRIGGER_PRE_DELETE,
        ];
    }

    private function present(Backup $backup): array
    {
        return [
            'id' => $backup->id,
            'database' => $backup->database,
            'label' => $backup->label,
            'is_central' => $backup->isCentral(),
            'tenant' => $backup->tenant ? ['id' => $backup->tenant->id, 'slug' => $backup->tenant->slug, 'name' => $backup->tenant->name] : null,
            'trigger' => $backup->trigger,
            'status' => $backup->status,
            'size_bytes' => $backup->size_bytes,
            'checksum' => $backup->checksum,
            'offsite_status' => $backup->offsite_status,
            'offsite_error' => $backup->offsite_error,
            'error' => $backup->error,
            'created_by' => $backup->creator?->name,
            'created_at' => $backup->created_at?->toIso8601String(),
            'duration_seconds' => $backup->started_at && $backup->finished_at
                ? $backup->started_at->diffInSeconds($backup->finished_at)
                : null,
            'restored_at' => $backup->restored_at?->toIso8601String(),
            'is_restorable' => $backup->isRestorable(),
        ];
    }
}
