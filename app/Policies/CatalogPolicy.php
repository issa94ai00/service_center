<?php

namespace App\Policies;

use App\Models\User;

class CatalogPolicy
{
    use ChecksPermission;

    public function viewServices(User $user): bool
    {
        return $this->allows($user, 'services.view');
    }

    public function manageServices(User $user): bool
    {
        return $this->allows($user, 'services.create');
    }

    public function viewMaterials(User $user): bool
    {
        return $this->allows($user, 'materials.view');
    }

    public function manageMaterials(User $user): bool
    {
        return $this->allows($user, 'materials.create');
    }

    public function viewUnits(User $user): bool
    {
        return $this->allows($user, 'services.view') || $this->allows($user, 'materials.view');
    }
}
