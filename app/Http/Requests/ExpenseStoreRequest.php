<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('expenses.create') ?? false;
    }

    public function rules(): array
    {
        return [
            // الصنف المعطَّل لا يُصرف عليه من جديد، وإن بقيت سنداته القديمة.
            'expense_category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('is_active', true),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['sometimes', 'in:CASH,BANK,OTHER'],
            'expense_date' => ['nullable', 'date'],
            'payee' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'اختيار باب الصرف مطلوب.',
            'expense_category_id.exists' => 'باب الصرف غير موجود أو معطَّل.',
            'amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.',
        ];
    }
}
