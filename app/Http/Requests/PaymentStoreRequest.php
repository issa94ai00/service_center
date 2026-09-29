<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('payments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'payment_type' => ['required', 'in:CUSTOMER,SUPPLIER,TECHNICIAN'],
            'customer_id' => ['required_if:payment_type,CUSTOMER', 'exists:customers,id'],
            'supplier_id' => ['required_if:payment_type,SUPPLIER', 'exists:suppliers,id'],
            'technician_id' => ['required_if:payment_type,TECHNICIAN', 'exists:technicians,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['sometimes', 'in:CASH,BANK,OTHER'],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            // سند صرف لخبير لا يُخصَّص على فاتورة: ليس هناك ما يُسدَّد، بل
            // رصيد يُنقص. بقية الأنواع تظل ملزَمة بتسمية ما تُسدِّده.
            'allocations' => [
                'required_unless:payment_type,TECHNICIAN',
                'prohibited_if:payment_type,TECHNICIAN',
                'array',
                'min:1',
            ],
            'allocations.*.invoice_type' => ['required', 'in:SALES,PURCHASE'],
            'allocations.*.sales_invoice_id' => ['required_if:allocations.*.invoice_type,SALES', 'exists:sales_invoices,id'],
            'allocations.*.purchase_invoice_id' => ['required_if:allocations.*.invoice_type,PURCHASE', 'exists:purchase_invoices,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'technician_id.required_if' => 'اختيار خبير الصيانة مطلوب لسند الصرف.',
            'allocations.prohibited_if' => 'سندات خبراء الصيانة لا تُخصَّص على فواتير.',
        ];
    }
}