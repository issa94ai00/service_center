<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\PurchaseInvoice;
use App\Models\User;

class PurchaseInvoicePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'purchases.view');
    }

    public function view(User $user, PurchaseInvoice $invoice): bool
    {
        return $this->allows($user, 'purchases.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'purchases.create');
    }

    public function update(User $user, PurchaseInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::DRAFT
            && ($user->hasPermission('purchases.create') || $invoice->created_by === $user->id);
    }

    public function delete(User $user, PurchaseInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::DRAFT
            && ($invoice->created_by === $user->id || $this->allows($user, 'purchases.create'));
    }

    public function post(User $user, PurchaseInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::DRAFT && $this->allows($user, 'purchases.post');
    }

    public function cancel(User $user, PurchaseInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::POSTED && $this->allows($user, 'purchases.cancel');
    }
}
