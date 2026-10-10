<?php

namespace App\Http\Requests;

use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Adding a managed currency or changing one (the route carries it on update). */
class CurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('currencies.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        /** @var Currency|null $currency */
        $currency = $this->route('currency');
        $updating = $currency !== null;

        return [
            'code' => [
                $updating ? 'prohibited' : 'required',
                'string', 'size:3', 'regex:/^[A-Z]{3}$/',
                Rule::unique('currencies', 'code'),
            ],
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:50'],
            'symbol' => [$updating ? 'sometimes' : 'required', 'string', 'max:10'],
            'exchange_rate' => [$updating ? 'sometimes' : 'required', 'numeric', 'gt:0', 'max:999999999999'],
            'decimals' => ['sometimes', 'integer', 'between:0,2'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_invoices' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'رمز العملة مطلوب (ثلاثة أحرف لاتينية مثل SYP).',
            'code.size' => 'رمز العملة ثلاثة أحرف لاتينية مثل SYP.',
            'code.regex' => 'رمز العملة ثلاثة أحرف لاتينية مثل SYP.',
            'code.unique' => 'هذه العملة معرّفة مسبقاً.',
            'code.prohibited' => 'رمز العملة لا يُغيَّر بعد إضافتها.',
            'name.required' => 'اسم العملة مطلوب.',
            'symbol.required' => 'رمز العرض مطلوب (مثل ل.س).',
            'exchange_rate.required' => 'سعر الصرف مطلوب.',
            'exchange_rate.gt' => 'سعر الصرف يجب أن يكون أكبر من صفر.',
            'decimals.between' => 'عدد المنازل العشرية بين 0 و2.',
        ];
    }
}
