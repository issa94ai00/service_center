<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                });
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', (bool) $request->is_active);
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return response()->json(UserResource::collection($users));
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['role_ids']);

        /** @var User $user */
        $user = User::query()->create($data + ['password' => Hash::make($request->password)]);

        if ($request->filled('role_ids')) {
            $user->roles()->attach($request->validated('role_ids'));
        }

        $user->load('roles');

        return response()->json([
            'message' => __('User created successfully.'),
            'data' => new UserResource($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        $data = $request->safe()->except(['role_ids', 'password']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($request->has('role_ids')) {
            $user->roles()->sync($request->validated('role_ids') ?: []);
        }

        $user->load('roles');

        return response()->json([
            'message' => __('User updated successfully.'),
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => __('You cannot delete your own account.'),
            ], 422);
        }

        $referenced = \DB::table('sales_invoices')->where('created_by', $user->id)->exists()
            || \DB::table('purchase_invoices')->where('created_by', $user->id)->exists()
            || \DB::table('payments')->where('recorded_by', $user->id)->exists();

        if ($referenced) {
            return response()->json([
                'message' => __('This user has historical documents and cannot be deleted; deactivate instead.'),
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => __('User deleted successfully.'),
        ]);
    }
}
