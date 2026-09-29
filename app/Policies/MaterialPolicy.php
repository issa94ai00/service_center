<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

class MaterialPolicy
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

    public function update(User $user, Material $material): bool
    {
        return $this->allows($user, 'materials.edit');
    }

    public function delete(User $user, Material $material): bool
    {
        return $this->allows($user, 'materials.delete');
    }
}
