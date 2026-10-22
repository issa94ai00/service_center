<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\SalesReturn;
use App\Models\User;

class SalesReturnPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sales.view');
    }

    public function view(User $user, SalesReturn $return): bool
    {
        return $this->allows($user, 'sales.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'sales.return');
    }

    public function cancel(User $user, SalesReturn $return): bool
    {
        return $return->status === DocumentStatus::POSTED && $this->allows($user, 'sales.return');
    }
}
