<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->allows($user, 'suppliers.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->allows($user, 'suppliers.edit');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->allows($user, 'suppliers.delete');
    }
}
