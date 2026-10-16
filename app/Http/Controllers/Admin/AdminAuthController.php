<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            return response()->json(['message' => __('Invalid credentials.')], 422);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $admin = Auth::guard('admin')->user();
        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json(['admin' => $admin]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('admin')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('Logged out successfully.')]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['admin' => $request->user('admin')]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $request->user('admin')->forceFill(['password' => Hash::make($data['password'])])->save();

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }
}
