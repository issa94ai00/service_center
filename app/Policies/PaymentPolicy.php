<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'payments.create') || $user->hasPermission('statements.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'payments.create');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $payment->status === DocumentStatus::DRAFT && $this->allows($user, 'payments.create');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $payment->status === DocumentStatus::DRAFT
            && ($payment->recorded_by === $user->id || $this->allows($user, 'payments.create'));
    }

    public function post(User $user, Payment $payment): bool
    {
        return $payment->status === DocumentStatus::DRAFT && $this->allows($user, 'payments.confirm');
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $payment->status === DocumentStatus::POSTED && $this->allows($user, 'payments.cancel');
    }
}
