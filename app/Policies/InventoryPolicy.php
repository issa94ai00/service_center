<?php

namespace App\Policies;

use App\Models\User;

class InventoryPolicy
{
    use ChecksPermission;

    public function view(User $user): bool
    {
        return $this->allows($user, 'inventory.view');
    }

    public function viewMovements(User $user): bool
    {
        return $this->view($user);
    }

    public function adjust(User $user): bool
    {
        return $this->allows($user, 'inventory.adjust');
    }
}
