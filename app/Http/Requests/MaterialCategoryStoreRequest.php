<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaterialCategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('materials.create') ?? false;
    }

    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:material_categories,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function rules(): array
    {
        return $this->baseRules();
    }
}
