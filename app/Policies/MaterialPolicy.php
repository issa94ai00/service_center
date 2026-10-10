<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

class MaterialPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $this->allows($user, 'materials.view')
            || $this->allows($user, 'sales.create')
            || $this->allows($user, 'sales.view')
            || $this->allows($user, 'purchases.create')
            || $this->allows($user, 'purchases.view')
            || $this->allows($user, 'repairs.create')
            || $this->allows($user, 'repairs.view')
            || $this->allows($user, 'repairs.settle');
    }

    public function view(User $user, Material $material): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'materials.create');
    }

    public function update(User $user, Material $material): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'materials.edit');
    }

    public function delete(User $user, Material $material): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'materials.delete');
    }
}
