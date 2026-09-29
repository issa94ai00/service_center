<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'expenses.view');
    }

    /** باب جديد يُفتح من شاشة الصرف نفسها، فمن يسجّل مصروفاً يفتحه. */
    public function create(User $user): bool
    {
        return $this->allows($user, 'expenses.create');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $this->allows($user, 'expenses.edit');
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $this->allows($user, 'expenses.edit');
    }
}
