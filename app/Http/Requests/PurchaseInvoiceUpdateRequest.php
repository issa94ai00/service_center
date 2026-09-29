<?php

namespace App\Http\Requests;

class PurchaseInvoiceUpdateRequest extends PurchaseInvoiceStoreRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('purchase');

        return $invoice && ($invoice->created_by === auth()->id() || auth()->user()?->hasPermission('purchases.create'));
    }

    public function rules(): array
    {
        $rules = $this->baseRules();
        $rules['supplier_id'] = ['sometimes', 'exists:suppliers,id'];

        return array_combine(
            array_keys($rules),
            array_map(fn ($rule) => ['sometimes', ...$rule], array_values($rules))
        );
    }
}
