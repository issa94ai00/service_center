<?php

namespace App\Http\Requests;

class MaterialUpdateRequest extends MaterialStoreRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('materials.edit') ?? false;
    }

    protected function generatesMissingSku(): bool
    {
        return false;
    }

    public function rules(): array
    {
        $rules = $this->baseRules();
        $rules['sku'] = ['sometimes', 'string', 'max:50', $this->uniqueCode('sku')];

        return array_combine(
            array_keys($rules),
            array_map(fn ($rule) => ['sometimes', ...$rule], array_values($rules))
        );
    }
}
