<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfilePasswordRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in user's own account: profile card, password, and the
 * browsers currently holding a session for it.
 *
 * Everything here is scoped to request()->user() — there is no {user}
 * parameter, so there is nothing to tamper with.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles.permissions');

        return response()->json([
            'user' => $user,
            'sessions' => $this->sessionsFor($request),
            'activity' => $this->recentActivity($user),
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $before = $this->snapshot($user);

        $user->update($request->validated());
        $user->load('roles.permissions');

        $this->audit->record('user.updated', $user, $before, $this->snapshot($user));

        return response()->json([
            'message' => 'تم حفظ بياناتك الشخصية.',
            'user' => $user,
        ]);
    }

    public function updatePassword(ProfilePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => Hash::make($request->validated('password'))]);

        $this->audit->record('user.updated', $user, [], ['password_changed' => true]);

        $ended = 0;
        if ($request->boolean('logout_others')) {
            $ended = $this->endOtherSessions($request);
        }

        return response()->json([
            'message' => $ended
                ? "تم تغيير كلمة المرور وإنهاء {$ended} من الجلسات الأخرى."
                : 'تم تغيير كلمة المرور.',
            'ended_sessions' => $ended,
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->sessionsFor($request),
        ]);
    }

    /**
     * Sign the account out everywhere but here. Re-asks for the password:
     * this is the lever a user reaches for when they suspect a stolen session,
     * so the stolen session itself must not be able to pull it.
     */
    public function destroyOtherSessions(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [], ['password' => 'كلمة المرور']);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'كلمة المرور غير صحيحة.',
            ]);
        }

        $ended = $this->endOtherSessions($request);

        return response()->json([
            'message' => $ended
                ? "تم إنهاء {$ended} من الجلسات الأخرى."
                : 'لا توجد جلسات أخرى مفتوحة.',
            'ended_sessions' => $ended,
            'data' => $this->sessionsFor($request),
        ]);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Whether sessions live in a table we can read. The suite runs on the array
     * driver and some deployments may use files; both simply show nothing.
     */
    private function sessionsReadable(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable('sessions');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sessionsFor(Request $request): array
    {
        if (! $this->sessionsReadable()) {
            return [];
        }

        $currentId = $request->hasSession() ? $request->session()->getId() : null;

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($row) => [
                // The raw id is a bearer credential; a hash is enough to key the list.
                'id' => substr(hash('sha256', $row->id), 0, 16),
                'is_current' => $row->id === $currentId,
                'ip_address' => $row->ip_address,
                'last_activity' => Carbon::createFromTimestamp($row->last_activity)->toIso8601String(),
            ] + $this->describeAgent((string) $row->user_agent))
            ->sortByDesc(fn (array $s) => [$s['is_current'], $s['last_activity']])
            ->values()
            ->all();
    }

    private function endOtherSessions(Request $request): int
    {
        if (! $this->sessionsReadable() || ! $request->hasSession()) {
            return 0;
        }

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    /**
     * A readable "Chrome on Windows" from a user-agent string, without a
     * parser dependency. Unknowns fall back to generic labels rather than
     * failing; this is a hint for the user, not a security control.
     *
     * @return array{browser: string, platform: string, device: string}
     */
    private function describeAgent(string $ua): array
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            $ua === '' => 'متصفح غير معروف',
            default => 'متصفح آخر',
        };

        $platform = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'نظام غير معروف',
        };

        $device = match (true) {
            str_contains($ua, 'iPad') || (str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile')) => 'tablet',
            str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone') => 'mobile',
            default => 'desktop',
        };

        return compact('browser', 'platform', 'device');
    }

    /**
     * The last few access-control changes made *by* this user, so the profile
     * doubles as a "what did I touch lately" view.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(User $user): array
    {
        return AuditLog::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(8)
            ->get(['id', 'event', 'auditable_type', 'auditable_id', 'ip_address', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'subject_type' => class_basename($log->auditable_type),
                'subject_id' => $log->auditable_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];
    }
}
