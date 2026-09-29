<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('payments.create') ?? false;
    }

    public function rules(): array
    {
        $rules = (new PaymentStoreRequest())->rules();

        return array_combine(
            array_keys($rules),
            array_map(fn ($rule) => ['sometimes', ...$rule], array_values($rules))
        );
    }
}