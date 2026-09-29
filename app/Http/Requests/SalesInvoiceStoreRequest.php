<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesInvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('sales.create') ?? false;
    }

    protected function baseRules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'discount_type' => ['sometimes', 'in:NONE,PERCENT,FIXED'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.line_type' => ['required', 'in:SERVICE,MATERIAL'],
            'items.*.service_id' => ['required_if:items.*.line_type,SERVICE', 'exists:services,id'],
            'items.*.material_id' => ['required_if:items.*.line_type,MATERIAL', 'exists:materials,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.unit_id' => ['nullable', 'exists:service_units,id'],
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
