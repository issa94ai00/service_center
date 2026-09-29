<?php

namespace App\Http\Requests;

class TechnicianUpdateRequest extends TechnicianStoreRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('technicians.edit') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->baseRules();

        return array_combine(
            array_keys($rules),
            array_map(fn ($rule) => ['sometimes', ...$rule], array_values($rules))
        );
    }
}
