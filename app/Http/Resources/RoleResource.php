<?php

namespace App\Http\Resources;

use App\Models\Role;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_protected' => in_array($this->slug, ['master', 'admin']),
            // Whether the requesting user may edit, delete or reassign this role (see AccessControlService).
            'can_manage' => $this->when($request->user() !== null && $this->relationLoaded('permissions'),
                fn () => app(AccessControlService::class)->canGrantRole($request->user(), $this->resource)),
            'users_count' => $this->users_count ?? ($this->relationLoaded('users') ? $this->users->count() : 0),
            'permissions_count' => $this->permissions_count ?? ($this->relationLoaded('permissions') ? $this->permissions->count() : 0),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => $u->is_active,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
