<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
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

    public function update(User $user, Service $service): bool
    {
        return $this->allows($user, 'services.edit');
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->allows($user, 'services.delete');
    }
}
