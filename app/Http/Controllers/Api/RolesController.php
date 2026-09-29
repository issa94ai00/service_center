<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RolesController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RoleResource::collection(Role::query()->with('permissions')->orderBy('name')->get()),
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }

    public function permissionsIndex(): JsonResponse
    {
        return response()->json([
            'data' => PermissionResource::collection(
                Permission::query()->orderBy('group')->orderBy('name')->get()
            ),
        ]);
    }
}
