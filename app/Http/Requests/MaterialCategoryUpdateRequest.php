<?php

namespace App\Http\Requests;

class MaterialCategoryUpdateRequest extends MaterialCategoryStoreRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('materials.edit') ?? false;
    }

    public function rules(): array
    {
        $rules = $this->baseRules();

        return array_combine(
            array_keys($rules),
            array_map(fn ($rule) => ['sometimes', ...$rule], array_values($rules))
        );
    }
}
