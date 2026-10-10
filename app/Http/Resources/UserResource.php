<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'locale' => $this->locale,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'is_master' => $this->whenLoaded('roles', fn () => $this->isMaster()),
            // Whether the requesting user may edit/deactivate/delete this one (see AccessControlService).
            'can_manage' => $this->whenLoaded('roles', fn () => $request->user()
                && app(AccessControlService::class)->canManageUser($request->user(), $this->resource)),
            'created_at' => $this->created_at,
        ];
    }
}
