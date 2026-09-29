<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('services.create') ?? false;
    }

    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:METER_BASED,MANUAL'],
            'unit_id' => ['nullable', 'exists:service_units,id'],
            'default_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'is_active' => ['boolean'],
        ];
    }

    public function rules(): array
    {
        return $this->baseRules();
    }
}
