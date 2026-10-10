<?php

namespace App\Policies;

use App\Models\MaterialCategory;
use App\Models\User;

class MaterialCategoryPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $this->allows($user, 'materials.view')
            || $this->allows($user, 'sales.create')
            || $this->allows($user, 'sales.view')
            || $this->allows($user, 'purchases.create')
            || $this->allows($user, 'purchases.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'materials.create');
    }

    public function update(User $user, MaterialCategory $category): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'materials.edit');
    }

    public function delete(User $user, MaterialCategory $category): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'materials.delete');
    }
}
