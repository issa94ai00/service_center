<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseInvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('purchases.create') ?? false;
    }

    protected function baseRules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'discount_type' => ['sometimes', 'in:NONE,PERCENT,FIXED'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'exists:materials,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.unit_id' => ['required', 'exists:service_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function rules(): array
    {
        return $this->baseRules();
    }
}
