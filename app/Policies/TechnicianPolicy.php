<?php

namespace App\Policies;

use App\Models\Technician;
use App\Models\User;

class TechnicianPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'technicians.view');
    }

    public function view(User $user, Technician $technician): bool
    {
        return $this->allows($user, 'technicians.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'technicians.create');
    }

    public function update(User $user, Technician $technician): bool
    {
        return $this->allows($user, 'technicians.edit');
    }

    public function delete(User $user, Technician $technician): bool
    {
        return $this->allows($user, 'technicians.delete');
    }
}
