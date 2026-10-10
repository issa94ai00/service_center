<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('roles.permissions')
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
        $roleIds = $this->roleIds($request->validated('role_ids'));

        $this->access->assertCanChangeRoles($request->user(), [], $roleIds);

        /** @var User $user */
        $user = User::query()->create($data + ['password' => Hash::make($request->password)]);

        if ($roleIds) {
            $user->roles()->attach($roleIds);
        }

        $user->load('roles.permissions');

        $this->audit->record('user.created', $user, [], $this->snapshot($user));

        return response()->json([
            'message' => __('User created successfully.'),
            'data' => new UserResource($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles.permissions');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $this->access->assertCanManageUser($actor, $user);

        $data = $request->safe()->except(['role_ids', 'password']);
        $currentRoleIds = $user->roles()->pluck('roles.id')->all();
        $newRoleIds = $request->has('role_ids') ? $this->roleIds($request->validated('role_ids')) : $currentRoleIds;
        $deactivating = array_key_exists('is_active', $data) && ! $data['is_active'];

        if ($deactivating && $user->id === $actor->id) {
            return response()->json([
                'message' => 'لا يمكنك تعطيل حسابك الشخصي.',
                'errors' => ['is_active' => ['لا يمكنك تعطيل حسابك الشخصي.']],
            ], 422);
        }

        $this->access->assertCanChangeRoles($actor, $currentRoleIds, $newRoleIds);

        if ($user->isMaster()) {
            $masterRoleId = $user->roles->firstWhere('slug', AccessControlService::MASTER)->id;
            $staysMaster = in_array($masterRoleId, $newRoleIds) && ! $deactivating;

            $this->access->assertKeepsAnActiveMaster(
                fn (User $master) => $master->id !== $user->id || $staysMaster,
                $deactivating ? 'is_active' : 'role_ids',
            );
        }

        $before = $this->snapshot($user);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        if ($request->has('role_ids')) {
            $user->roles()->sync($newRoleIds);
        }

        $user->load('roles.permissions');

        $after = $this->snapshot($user);
        if ($request->filled('password')) {
            $after['password_changed'] = true;
        }
        $this->audit->record('user.updated', $user, $before, $after);

        return response()->json([
            'message' => __('User updated successfully.'),
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->access->assertCanManageUser($request->user(), $user);

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

        if ($user->isMaster()) {
            $this->access->assertKeepsAnActiveMaster(fn (User $master) => $master->id !== $user->id);
        }

        $snapshot = $this->snapshot($user);
        $user->delete();
        $this->audit->record('user.deleted', $user, $snapshot);

        return response()->json([
            'message' => __('User deleted successfully.'),
        ]);
    }

    /**
     * @param  array<int|string>|null  $ids
     * @return array<int>
     */
    private function roleIds(?array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids ?? [])));
    }

    /**
     * The audited view of a user: profile fields and role names (never the password).
     *
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'roles' => $user->roles->pluck('name')->sort()->values()->all(),
        ];
    }
}
