<?php

namespace App\Policies;

use App\Models\User;

class CatalogPolicy
{
    use ChecksPermission;

    public function viewServices(User $user): bool
    {
        return $user->hasRole('admin')
            || $this->allows($user, 'services.view')
            || $this->allows($user, 'sales.create')
            || $this->allows($user, 'sales.view');
    }

    public function manageServices(User $user): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'services.create');
    }

    public function viewMaterials(User $user): bool
    {
        return $user->hasRole('admin')
            || $this->allows($user, 'materials.view')
            || $this->allows($user, 'sales.create')
            || $this->allows($user, 'sales.view')
            || $this->allows($user, 'purchases.create')
            || $this->allows($user, 'purchases.view');
    }

    public function manageMaterials(User $user): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'materials.create');
    }

    public function viewUnits(User $user): bool
    {
        return $user->hasRole('admin')
            || $this->allows($user, 'services.view')
            || $this->allows($user, 'materials.view')
            || $this->allows($user, 'sales.create')
            || $this->allows($user, 'sales.view')
            || $this->allows($user, 'purchases.create')
            || $this->allows($user, 'purchases.view');
    }
}
