<?php

namespace App\Http\Requests;

class SupplierUpdateRequest extends SupplierStoreRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('suppliers.edit') ?? false;
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
