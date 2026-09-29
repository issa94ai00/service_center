<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        // `remember` is valid in the request but must never reach the user
        // query — Laravel's attempt() builds a WHERE clause from the
        // credentials, and users has no such column.
        $credentials = Arr::except($request->validated(), ['remember']);

        if (! auth()->attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'message' => __('Invalid credentials.'),
            ], 422);
        }

        /** @var User $user */
        $user = auth()->user();

        if (! $user->is_active) {
            auth()->logout();
            $this->flushSession($request);

            return response()->json([
                'message' => __('Your account is disabled.'),
            ], 403);
        }

        // Issue a fresh session id now that the session is privileged, so a
        // session id planted before login cannot be reused after it. Guarded:
        // a request from outside the stateful domains carries no session store,
        // and touching it there throws.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->load('roles.permissions');

        return response()->json([
            'message' => __('Logged in successfully.'),
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        auth()->guard('web')->logout();

        $this->flushSession($request);

        return response()->json([
            'message' => __('Logged out successfully.'),
        ]);
    }

    /**
     * Drop the session and issue a fresh CSRF token, when there is one to drop.
     */
    protected function flushSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles.permissions');

        return response()->json([
            'user' => $user,
        ]);
    }
}
