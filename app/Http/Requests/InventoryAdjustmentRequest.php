<?php

namespace App\Http\Requests;

use App\Enums\MovementDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('inventory.adjust');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.direction' => ['required', Rule::enum(MovementDirection::class)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'سبب التسوية مطلوب.',
            'items.required' => 'يجب إضافة بند واحد على الأقل.',
            'items.*.quantity.gt' => 'الكمية يجب أن تكون أكبر من صفر.',
        ];
    }
}
