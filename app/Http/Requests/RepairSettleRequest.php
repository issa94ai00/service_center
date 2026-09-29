<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The hand-over: agree the final price, collect what is still owed and close
 * the repair — one request instead of an invoice screen plus a payment screen.
 *
 * The bill arrives one of two ways. A bare `total` closes the repair on a
 * single agreed figure, the way the counter has always done it. An `items`
 * breakdown bills labour and the parts fitted separately, so the parts leave
 * the shelf with the device. Where `items` is sent it is the bill: the total is
 * priced server-side from the lines and any `total` in the payload is ignored.
 */
class RepairSettleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('repairs.settle') ?? false;
    }

    public function rules(): array
    {
        return [
            'total' => ['required_without:items', 'nullable', 'numeric', 'min:0', 'max:99999999999'],
            'collect_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'payment_method' => ['sometimes', 'in:CASH,BANK,OTHER'],
            'notes' => ['nullable', 'string', 'max:255'],
            'delivered_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            // تجاوز نسبة الخبير لهذا البلاغ وحده؛ الفراغ يعني نسبته الافتراضية.
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // بنود الفاتورة التفصيلية: أجرة الورشة وقطع الغيار المركّبة.
            'items' => ['sometimes', 'array', 'min:1', 'max:50'],
            'items.*.line_type' => ['required', 'in:SERVICE,MATERIAL'],
            // سطر الخدمة قد يكون أجرة حرّة بلا خدمة معرَّفة، فالخدمة اختيارية
            // هنا خلافاً لفاتورة البيع؛ أمّا المادة فبلا صنف لا مخزون يُخصم.
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.material_id' => ['required_if:items.*.line_type,MATERIAL', 'nullable', 'exists:materials,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.unit_id' => ['nullable', 'exists:service_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * An empty `items` array is the quick hand-over sending an unused field,
     * not a request to bill nothing — drop it so `total` stays in charge.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('items') && $this->input('items') === []) {
            $this->request->remove('items');
        }
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('items', []) as $index => $row) {
                if (($row['line_type'] ?? null) !== 'MATERIAL') {
                    continue;
                }

                // كمية قطعة الغيار تُخصم من المخزون، والمخزون يُمسك بثلاث
                // خانات عشرية؛ كسر أدقّ منها يضيع في التقريب لا في الدفتر.
                if (round((float) ($row['quantity'] ?? 0), 3) !== (float) ($row['quantity'] ?? 0)) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        __('كمية قطعة الغيار تُقبل بثلاث خانات عشرية على الأكثر.')
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'total.required_without' => 'أدخل التكلفة النهائية للصيانة، أو أضف بنوداً للفاتورة.',
            'total.min' => 'التكلفة النهائية لا يمكن أن تكون سالبة.',
            'collect_amount.min' => 'المبلغ المحصَّل لا يمكن أن يكون سالباً.',
            'commission_rate.min' => 'نسبة الخبير يجب أن تكون بين ٠ و ١٠٠.',
            'commission_rate.max' => 'نسبة الخبير يجب أن تكون بين ٠ و ١٠٠.',
            'items.*.material_id.required_if' => 'اختر قطعة الغيار من المخزون.',
            'items.*.quantity.required' => 'أدخل كمية السطر.',
            'items.*.quantity.gt' => 'كمية السطر يجب أن تكون أكبر من صفر.',
            'items.*.unit_price.required' => 'أدخل سعر السطر.',
            'items.*.unit_price.min' => 'سعر السطر لا يمكن أن يكون سالباً.',
        ];
    }
}
