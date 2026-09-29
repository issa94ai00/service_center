<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Money taken against a repair: the deposit at the counter, or the balance
 * paid when the customer comes back for the device.
 */
class RepairDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('repairs.settle') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'payment_method' => ['sometimes', 'in:CASH,BANK,OTHER'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'أدخل المبلغ المقبوض.',
            'amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.',
        ];
    }
}
