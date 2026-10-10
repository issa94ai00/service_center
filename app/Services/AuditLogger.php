<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes access-control changes (roles, permissions, users) to audit_logs.
 */
class AuditLogger
{
    /**
     * Records $event against $subject. Keys whose old and new values are equal are
     * dropped, and nothing is written when no values changed (for *.updated events).
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(string $event, Model $subject, array $old = [], array $new = []): ?AuditLog
    {
        if (str_ends_with($event, '.updated')) {
            foreach (array_keys($old + $new) as $key) {
                if (($old[$key] ?? null) == ($new[$key] ?? null)) {
                    unset($old[$key], $new[$key]);
                }
            }

            if (! $old && ! $new) {
                return null;
            }
        }

        $request = request();

        return AuditLog::query()->create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
        ]);
    }
}
