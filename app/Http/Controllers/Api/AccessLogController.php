<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read side of the access-control audit trail written by AuditLogger.
 * roles.manage holders see role events, users.manage holders see user events.
 */
class AccessLogController extends Controller
{
    private const SUBJECTS = [
        'role' => Role::class,
        'user' => User::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => ['nullable', 'in:role,user'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        $actor = $request->user();
        $visible = array_filter([
            'role' => $actor->hasPermission('roles.manage'),
            'user' => $actor->hasPermission('users.manage'),
        ]);

        $subjects = $request->filled('subject')
            ? array_intersect_key($visible, [$request->subject => true])
            : $visible;

        if (! $subjects) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        // morph type => subject key, for the subjects this request may see
        $types = [];
        foreach (array_keys($subjects) as $key) {
            $types[(new (self::SUBJECTS[$key]))->getMorphClass()] = $key;
        }

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->whereIn('auditable_type', array_keys($types))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('auditable_id', $request->integer('subject_id')))
            ->latest('created_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 30));

        // Current names, for entries whose logged values don't carry one (unchanged on update).
        $currentNames = [];
        foreach ($logs->getCollection()->groupBy('auditable_type') as $type => $entries) {
            $currentNames[$type] = self::SUBJECTS[$types[$type]]::query()
                ->whereIn('id', $entries->pluck('auditable_id'))
                ->pluck('name', 'id');
        }

        $logs->getCollection()->transform(fn (AuditLog $log) => [
            'id' => $log->id,
            'event' => $log->event,
            'subject' => $types[$log->auditable_type],
            'subject_id' => $log->auditable_id,
            // Deleted subjects keep their name in the logged values.
            'subject_name' => $log->new_values['name'] ?? $log->old_values['name']
                ?? $currentNames[$log->auditable_type][$log->auditable_id] ?? null,
            'actor' => $log->user?->only(['id', 'name']),
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at,
        ]);

        return response()->json($logs->toArray() + [
            'permission_labels' => Permission::query()->pluck('name', 'slug'),
        ]);
    }
}
