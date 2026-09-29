<?php

namespace App\Policies;

use App\Models\User;

trait ChecksPermission
{
    protected function allows(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
