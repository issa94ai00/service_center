<?php

namespace App\Policies;

use App\Models\Repair;
use App\Models\User;

class RepairPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'repairs.view');
    }

    public function view(User $user, Repair $repair): bool
    {
        return $this->allows($user, 'repairs.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'repairs.create');
    }

    public function update(User $user, Repair $repair): bool
    {
        return $this->allows($user, 'repairs.edit');
    }

    /**
     * Taking money and settling a repair posts to the customer ledger and
     * raises an invoice, so it is its own grant rather than a side effect of
     * being allowed to register devices at the counter.
     */
    public function settle(User $user, Repair $repair): bool
    {
        return $this->allows($user, 'repairs.settle');
    }

    public function delete(User $user, Repair $repair): bool
    {
        return $this->allows($user, 'repairs.delete');
    }
}
