<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'expenses.create');
    }

    /** الملغى لا يُعدَّل: سجلّه هو الإفادة الوحيدة عمّا جرى. */
    public function update(User $user, Expense $expense): bool
    {
        return ! $expense->isCancelled() && $this->allows($user, 'expenses.edit');
    }

    public function cancel(User $user, Expense $expense): bool
    {
        return ! $expense->isCancelled() && $this->allows($user, 'expenses.cancel');
    }
}
