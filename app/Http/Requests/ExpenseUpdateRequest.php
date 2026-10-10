<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('expenses.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            // التعديل قد يُبقي صنفاً عُطِّل بعد التسجيل، فالشرط هنا الوجود فقط:
            // إعادة حفظ سند قديم يجب ألّا تُجبر على تغيير بابه.
            'expense_category_id' => ['sometimes', 'required', Rule::exists('expense_categories', 'id')],
            'amount' => ['sometimes', 'required_without:currency_amount', 'nullable', 'numeric', 'gt:0'],
            'currency_code' => ['sometimes', 'nullable', 'string', 'size:3', 'exists:currencies,code'],
            'currency_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'payment_method' => ['sometimes', 'in:CASH,BANK,OTHER'],
            'expense_date' => ['sometimes', 'required', 'date'],
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
            'expense_category_id.exists' => 'باب الصرف غير موجود.',
            'amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'currency_amount.gt' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'currency_code.exists' => 'العملة غير معرّفة في النظام.',
        ];
    }
}
