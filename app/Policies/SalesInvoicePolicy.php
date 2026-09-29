<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\SalesInvoice;
use App\Models\User;

class SalesInvoicePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'sales.view');
    }

    public function view(User $user, SalesInvoice $invoice): bool
    {
        return $this->allows($user, 'sales.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'sales.create');
    }

    public function update(User $user, SalesInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::DRAFT
            && ($user->hasPermission('sales.create') || $invoice->created_by === $user->id);
    }

    public function delete(User $user, SalesInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::DRAFT
            && ($invoice->created_by === $user->id || $this->allows($user, 'sales.create'));
    }

    public function post(User $user, SalesInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::DRAFT && $this->allows($user, 'sales.post');
    }

    public function cancel(User $user, SalesInvoice $invoice): bool
    {
        return $invoice->status === DocumentStatus::POSTED && $this->allows($user, 'sales.cancel');
    }
}
