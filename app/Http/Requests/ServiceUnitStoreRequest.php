<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceUnitStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('services.create') ?? false;
    }

    protected function baseRules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'category' => ['required', 'in:LENGTH,AREA,VOLUME,COUNT,TIME,OTHER'],
            'is_measurable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function rules(): array
    {
        return $this->baseRules();
    }
}
