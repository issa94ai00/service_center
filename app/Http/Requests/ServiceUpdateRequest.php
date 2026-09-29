<?php

namespace App\Http\Requests;

class ServiceUpdateRequest extends ServiceStoreRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('services.edit') ?? false;
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
