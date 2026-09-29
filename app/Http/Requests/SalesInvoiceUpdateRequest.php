<?php

namespace App\Http\Requests;

class SalesInvoiceUpdateRequest extends SalesInvoiceStoreRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('sale');

        return $invoice && ($invoice->created_by === auth()->id() || auth()->user()?->hasPermission('sales.create'));
    }

    public function rules(): array
    {
        $rules = $this->baseRules();
        $rules['customer_id'] = ['sometimes', 'exists:customers,id'];

        return array_combine(
            array_keys($rules),
            array_map(fn ($rule) => ['sometimes', ...$rule], array_values($rules))
        );
    }
}
