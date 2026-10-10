<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $this->allows($user, 'services.view')
            || $this->allows($user, 'sales.create')
            || $this->allows($user, 'sales.view')
            || $this->allows($user, 'repairs.create')
            || $this->allows($user, 'repairs.view')
            || $this->allows($user, 'repairs.settle');
    }

    public function view(User $user, Service $service): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'services.create');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'services.edit');
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->hasRole('admin') || $this->allows($user, 'services.delete');
    }
}
