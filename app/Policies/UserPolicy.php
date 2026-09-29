<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'users.manage');
    }

    public function view(User $user, User $target): bool
    {
        return $this->allows($user, 'users.manage');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $this->allows($user, 'users.manage');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->allows($user, 'users.manage');
    }
}
