<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('expenses.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:150',
                Rule::unique('expense_categories', 'name')->ignore($this->route('expense_category')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'هذا الباب موجود مسبقاً.',
        ];
    }
}
