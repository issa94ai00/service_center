<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->allows($user, 'customers.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->allows($user, 'customers.edit');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->allows($user, 'customers.delete');
    }
}
