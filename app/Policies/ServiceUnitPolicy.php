<?php

namespace App\Policies;

use App\Models\ServiceUnit;
use App\Models\User;

class ServiceUnitPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'services.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'services.create');
    }

    public function update(User $user, ServiceUnit $unit): bool
    {
        return $this->allows($user, 'services.edit');
    }

    public function delete(User $user, ServiceUnit $unit): bool
    {
        return $this->allows($user, 'services.delete');
    }
}
