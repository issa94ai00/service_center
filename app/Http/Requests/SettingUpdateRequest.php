<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('settings.manage') ?? false;
    }

    /**
     * Keys whose value is not free text. Without this, a typo in
     * inventory_sale_policy silently reads as "not strict" and disables the
     * negative-stock guard on every sale and adjustment.
     *
     * @var array<string, array<int, string>>
     */
    protected const ALLOWED_VALUES = [
        'inventory_sale_policy' => ['strict', 'permissive'],
    ];

    /**
     * Keys that must not be blanked, because something downstream depends on
     * them being present.
     *
     * @var array<int, string>
     */
    protected const REQUIRED_KEYS = [
        'company_name', 'currency_symbol', 'currency_name', 'inventory_sale_policy',
    ];

    public function rules(): array
    {
        $rules = [];
        $existing = Setting::all();

        foreach ($existing as $setting) {
            $field = 'values.'.$setting->key;

            $presence = in_array($setting->key, self::REQUIRED_KEYS, true)
                ? ['sometimes', 'required']
                : ['nullable'];

            if (isset(self::ALLOWED_VALUES[$setting->key])) {
                $rules[$field] = [...$presence, Rule::in(self::ALLOWED_VALUES[$setting->key])];

                continue;
            }

            $rules[$field] = match ($setting->type) {
                'int' => [...$presence, 'integer'],
                'bool' => ['nullable', Rule::in([0, 1, '0', '1', true, false])],
                'decimal' => [...$presence, 'numeric'],
                default => [...$presence, 'string', 'max:65535'],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.inventory_sale_policy.in' => 'سياسة المخزون يجب أن تكون "strict" أو "permissive".',
            'values.company_name.required' => 'اسم الشركة مطلوب.',
            'values.currency_symbol.required' => 'رمز العملة مطلوب.',
            'values.currency_name.required' => 'اسم العملة مطلوب.',
        ];
    }

    public function validatedValues(): array
    {
        return $this->validated()['values'] ?? [];
    }
}
