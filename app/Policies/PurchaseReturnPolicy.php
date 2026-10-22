<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\PurchaseReturn;
use App\Models\User;

class PurchaseReturnPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'purchases.view');
    }

    public function view(User $user, PurchaseReturn $return): bool
    {
        return $this->allows($user, 'purchases.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'purchases.return');
    }

    public function cancel(User $user, PurchaseReturn $return): bool
    {
        return $return->status === DocumentStatus::POSTED && $this->allows($user, 'purchases.return');
    }
}
