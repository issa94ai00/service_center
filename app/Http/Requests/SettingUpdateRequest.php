<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Support\PrintSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasPermission('settings.manage') || $user->hasPermission('features.manage');
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
        'currency_code' => ['USD'],
        'currency_symbol' => ['$'],
        'currency_name' => ['دولار', 'دولار أمريكي', 'USD'],
    ];

    /**
     * Fixed choices plus each document kind's paper, which may only be one the
     * kind has a layout for — a statement saved as "80mm" would otherwise
     * quietly print on A4 and leave the setting lying about it.
     *
     * @return array<string, array<int, string>>
     */
    protected function allowedValues(): array
    {
        $allowed = self::ALLOWED_VALUES;

        foreach (array_keys(PrintSettings::supported()) as $kind) {
            $allowed[PrintSettings::settingKey($kind)] = PrintSettings::supportedValues($kind);
        }

        return $allowed;
    }

    /**
     * Keys that must not be blanked, because something downstream depends on
     * them being present.
     *
     * @var array<int, string>
     */
    protected const REQUIRED_KEYS = [
        'company_name', 'currency_symbol', 'currency_name', 'inventory_sale_policy',
        'print_paper_invoice', 'print_paper_payment', 'print_paper_repair', 'print_paper_statement',
    ];

    /**
     * Free-text keys printed onto documents; long enough for a short terms
     * paragraph, short enough that it cannot push a receipt onto a second page.
     *
     * @var array<string, int>
     */
    protected const MAX_LENGTH = [
        'print_footer_note' => 300,
        'print_repair_terms' => 1000,
    ];

    public function rules(): array
    {
        $rules = [];
        $existing = Setting::all();
        $allowed = $this->allowedValues();

        foreach ($existing as $setting) {
            $field = 'values.'.$setting->key;

            $presence = in_array($setting->key, self::REQUIRED_KEYS, true)
                ? ['sometimes', 'required']
                : ['nullable'];

            if (isset($allowed[$setting->key])) {
                $rules[$field] = [...$presence, Rule::in($allowed[$setting->key])];

                continue;
            }

            $rules[$field] = match ($setting->type) {
                'int' => [...$presence, 'integer'],
                'bool' => ['nullable', Rule::in([0, 1, '0', '1', true, false])],
                'decimal' => [...$presence, 'numeric'],
                default => [...$presence, 'string', 'max:'.(self::MAX_LENGTH[$setting->key] ?? 65535)],
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
            'values.currency_symbol.in' => 'العملة المعتمدة في النظام هي الدولار ($) فقط.',
            'values.currency_name.required' => 'اسم العملة مطلوب.',
            'values.currency_name.in' => 'اسم العملة المعتمد في النظام هو دولار فقط.',
            'values.currency_code.in' => 'كود العملة المعتمد في النظام هو USD فقط.',
            'values.print_paper_*.in' => 'حجم الورق غير مدعوم لهذا النوع من المستندات.',
            'values.print_paper_*.required' => 'اختر حجم الورق الافتراضي.',
            'values.print_footer_note.max' => 'ملاحظة التذييل طويلة — الحد الأقصى :max حرفًا.',
            'values.print_repair_terms.max' => 'شروط الصيانة طويلة — الحد الأقصى :max حرف.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if (! $user) {
                return;
            }

            $featureKeys = Setting::where('group', 'features')->pluck('key')->all();
            $submittedKeys = array_keys($this->input('values', []));

            if (! $user->hasPermission('features.manage') && array_intersect($featureKeys, $submittedKeys)) {
                $validator->errors()->add('values', __('تشغيل وتعطيل ميزات النظام يتطلب صلاحية "إدارة وتفعيل ميزات النظام".'));
            }

            if (! $user->hasPermission('settings.manage') && array_diff($submittedKeys, $featureKeys)) {
                $validator->errors()->add('values', __('تعديل الإعدادات العامة يتطلب صلاحية "إدارة الإعدادات".'));
            }
        });
    }

    public function validatedValues(): array
    {
        return $this->validated()['values'] ?? [];
    }
}
