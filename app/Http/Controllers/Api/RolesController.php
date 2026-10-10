<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RolesController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Display a listing of roles with counts and permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::query()
            ->withCount(['users', 'permissions'])
            ->with('permissions')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        return response()->json([
            'data' => RoleResource::collection($query->get()),
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(RoleStoreRequest $request): JsonResponse
    {
        $permissionIds = $this->permissionIds($request->input('permission_ids'));
        $this->access->assertCanGrantPermissions($request->user(), $permissionIds);

        $slug = $request->input('slug');
        if (empty($slug)) {
            $slug = Str::slug($request->input('name'));
            if (empty($slug)) {
                $slug = 'role-' . uniqid();
            }
            $baseSlug = $slug;
            $counter = 1;
            while (Role::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
        }

        $role = Role::create([
            'name' => $request->input('name'),
            'slug' => $slug,
            'description' => $request->input('description'),
        ]);

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($permissionIds);
        }

        $role->load(['permissions', 'users:id,name,email,is_active']);
        $role->loadCount(['users', 'permissions']);

        $this->audit->record('role.created', $role, [], $this->snapshot($role));

        return response()->json([
            'message' => 'تم إنشاء الدور بنجاح.',
            'data' => new RoleResource($role),
        ], 201);
    }

    /**
     * Display the specified role with its permissions and users.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'users:id,name,email,is_active']);
        $role->loadCount(['users', 'permissions']);

        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Update the specified role and sync permissions.
     */
    public function update(RoleUpdateRequest $request, Role $role): JsonResponse
    {
        $this->access->assertCanManageRole($request->user(), $role);

        $permissionIds = $this->permissionIds($request->input('permission_ids'));
        if ($request->has('permission_ids') && $role->slug !== AccessControlService::MASTER) {
            $this->access->assertCanGrantPermissions($request->user(), $permissionIds);
        }

        $before = $this->snapshot($role->load('permissions'));

        $data = [
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ];

        // Slug can only be changed for custom roles, never for master or admin
        if (! in_array($role->slug, ['master', 'admin']) && $request->filled('slug')) {
            $data['slug'] = $request->input('slug');
        }

        $role->update($data);

        // If master role, master permanently keeps all permissions
        if ($role->slug === 'master') {
            $allPermissionIds = Permission::pluck('id')->all();
            $role->permissions()->sync($allPermissionIds);
        } elseif ($request->has('permission_ids')) {
            $role->permissions()->sync($permissionIds);
        }

        $role->load(['permissions', 'users:id,name,email,is_active']);
        $role->loadCount(['users', 'permissions']);

        $this->audit->record('role.updated', $role, $before, $this->snapshot($role));

        return response()->json([
            'message' => 'تم تحديث الدور والصلاحيات بنجاح.',
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Remove the specified role with protection for master, admin, and active users.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if ($role->slug === 'master') {
            return response()->json([
                'message' => 'لا يمكن حذف دور المدير العام (Master) أبداً.',
            ], 422);
        }

        if ($role->slug === 'admin') {
            return response()->json([
                'message' => 'لا يمكن حذف دور مدير النظام الأساسي (Admin).',
            ], 422);
        }

        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return response()->json([
                'message' => "لا يمكن حذف هذا الدور لوجود {$usersCount} مستخدم مرتبط به. يُرجى تغيير أو نقل أدوار المستخدمين أولاً.",
            ], 422);
        }

        $this->access->assertCanManageRole($request->user(), $role);

        $snapshot = $this->snapshot($role->load('permissions'));
        $role->permissions()->detach();
        $role->delete();
        $this->audit->record('role.deleted', $role, $snapshot);

        return response()->json([
            'message' => 'تم حذف الدور بنجاح.',
        ]);
    }

    /**
     * Assign or update users linked to a specific role.
     */
    public function assignUsers(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $actor = $request->user();
        $this->access->assertCanManageRole($actor, $role);

        $currentIds = $role->users()->pluck('users.id')->all();
        $newIds = array_values(array_unique(array_map('intval', $request->input('user_ids'))));
        $changedIds = array_merge(array_diff($newIds, $currentIds), array_diff($currentIds, $newIds));

        User::query()->with('roles.permissions')->whereIn('id', $changedIds)->get()
            ->each(fn (User $user) => $this->access->assertCanManageUser($actor, $user));

        if ($role->slug === AccessControlService::MASTER) {
            $this->access->assertKeepsAnActiveMaster(
                fn (User $master) => in_array($master->id, $newIds),
                'user_ids',
            );
        }

        $before = $role->users()->orderBy('name')->pluck('name')->all();
        $role->users()->sync($newIds);
        $this->audit->record('role.members_changed', $role,
            ['users' => $before],
            ['users' => $role->users()->orderBy('name')->pluck('name')->all()],
        );

        $role->load(['permissions', 'users:id,name,email,is_active']);
        $role->loadCount(['users', 'permissions']);

        return response()->json([
            'message' => 'تم تحديث المستخدمين المسندين لهذا الدور بنجاح.',
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Return all permissions grouped by logical module with Arabic labels.
     */
    public function permissionsIndex(): JsonResponse
    {
        $permissions = Permission::query()->orderBy('group')->orderBy('name')->get();

        $groupLabels = [
            'access' => 'إدارة الوصول والمستخدمين والأدوار',
            'dashboard' => 'لوحة التحكم والمؤشرات',
            'sales' => 'المبيعات وفواتير البيع',
            'purchases' => 'المشتريات وفواتير الشراء',
            'repairs' => 'قسم الصيانة والأجهزة',
            'technicians' => 'خبراء وفنيو الصيانة',
            'materials' => 'المواد والقطع والمستودع',
            'services' => 'دليل الخدمات ووحدات القياس',
            'inventory' => 'المخزون والجرد الدوري',
            'payments' => 'سندات الصندوق (القبض والدفع)',
            'expenses' => 'سندات المصاريف والنثريات',
            'customers' => 'بيانات وسجلات العملاء',
            'suppliers' => 'بيانات وسجلات الموردين',
            'statements' => 'كشوف الحسابات المالية',
            'reports' => 'التقارير المالية والأرباح',
            'settings' => 'إعدادات النظام العامة',
        ];

        $grouped = $permissions->groupBy('group')->map(function ($items, $groupKey) use ($groupLabels) {
            return [
                'key' => $groupKey,
                'title' => $groupLabels[$groupKey] ?? $groupKey,
                'permissions' => PermissionResource::collection($items),
            ];
        })->values();

        return response()->json([
            'data' => PermissionResource::collection($permissions),
            'grouped' => $grouped,
            'group_labels' => $groupLabels,
        ]);
    }

    /**
     * Lightweight role list for dropdown selections.
     */
    public function options(Request $request): JsonResponse
    {
        $actor = $request->user();

        return response()->json([
            'data' => Role::query()->with('permissions:id,slug')->orderBy('name')->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    // Roles the requesting user may assign or remove (see AccessControlService).
                    'grantable' => $this->access->canGrantRole($actor, $role),
                ]),
        ]);
    }

    /**
     * @param  array<int|string>|null  $ids
     * @return array<int>
     */
    private function permissionIds(?array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids ?? [])));
    }

    /**
     * The audited view of a role: its fields and permission slugs.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Role $role): array
    {
        return [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permissions' => $role->permissions->pluck('slug')->sort()->values()->all(),
        ];
    }
}
