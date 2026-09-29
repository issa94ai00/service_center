<?php

namespace App\Policies;

use App\Models\MaterialCategory;
use App\Models\User;

class MaterialCategoryPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'materials.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'materials.create');
    }

    public function update(User $user, MaterialCategory $category): bool
    {
        return $this->allows($user, 'materials.edit');
    }

    public function delete(User $user, MaterialCategory $category): bool
    {
        return $this->allows($user, 'materials.delete');
    }
}
