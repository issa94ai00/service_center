<?php

namespace App\Http\Requests;

use App\Enums\RepairStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepairStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('repairs.create') ?? false;
    }

    protected function baseRules(): array
    {
        $statuses = array_map(fn ($case) => $case->value, RepairStatus::cases());

        return [
            'customer_id' => ['required', 'exists:customers,id'],
            // الخبير يُسنَد عند الاستلام ويُعدَّل ما دام البلاغ مفتوحاً؛ نسبته
            // لا تُلتقط إلّا عند التسوية.
            'technician_id' => ['nullable', 'exists:technicians,id'],
            'device_type' => ['required', 'string', 'max:60'],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:40'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'accessories' => ['nullable', 'string', 'max:500'],
            'reported_issue' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'final_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'status' => ['nullable', Rule::in($statuses)],
            'received_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'delivered_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after_or_equal:received_at'],
        ];
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            // The deposit belongs to the intake moment only. Editing a repair
            // later must not be able to conjure a second one; money added after
            // the fact goes through the settlement endpoints.
            'deposit_amount' => ['nullable', 'numeric', 'gt:0', 'max:99999999999'],
            'deposit_method' => ['nullable', 'in:CASH,BANK,OTHER'],
        ];
    }

    public function messages(): array
    {
        return [
            'deposit_amount.gt' => 'العربون يجب أن يكون أكبر من صفر، أو اتركه فارغاً.',
        ];
    }
}
