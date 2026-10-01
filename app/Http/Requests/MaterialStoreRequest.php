<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Services\NumberingService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class MaterialStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasPermission('materials.create') ?? false;
    }

    protected function baseRules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:material_categories,id'],
            'name' => ['required', 'string', 'max:150'],
            // اختياري: يولَّد رمز تسلسلي حين يُترك فارغاً، حتى تُضاف مادة
            // مجهولة من داخل فاتورة الشراء بلا وقفة عند اختراع رمز.
            'sku' => ['nullable', 'string', 'max:50', $this->uniqueCode('sku')],
            // فراغ الباركود يُخزَّن NULL لا سلسلة فارغة، وإلّا اصطدمت كل مادة
            // بلا باركود بالقيد الفريد بعد الأولى.
            'barcode' => ['nullable', 'string', 'max:64', $this->uniqueCode('barcode')],
            'unit_id' => ['required', 'exists:service_units,id'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function rules(): array
    {
        return $this->baseRules();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('barcode')) {
            $barcode = trim((string) $this->input('barcode'));
            $this->merge(['barcode' => $barcode === '' ? null : $barcode]);
        }

        // يولَّد قبل التحقّق لا بعده، فيمرّ الرمز المولَّد على قيد التفرّد نفسه
        // الذي يمرّ عليه الرمز المكتوب باليد.
        if ($this->generatesMissingSku() && trim((string) $this->input('sku')) === '') {
            $this->merge(['sku' => app(NumberingService::class)->nextMaterialSku()]);
        }
    }

    /**
     * Only a create invents a code. A partial update that simply does not
     * mention `sku` means "leave it alone" — generating one there would
     * silently renumber a material the shop already knows by its old code.
     */
    protected function generatesMissingSku(): bool
    {
        return true;
    }

    /**
     * A SKU or barcode must be free across the whole table, archive included —
     * the database index says so too, so a restore can never collide. When the
     * holder is archived, say which one: restoring it is usually what the
     * clerk actually wants.
     */
    protected function uniqueCode(string $column): Closure
    {
        $label = $column === 'sku' ? 'الرمز' : 'الباركود';

        return function (string $attribute, mixed $value, Closure $fail) use ($column, $label) {
            $holder = Material::withTrashed()
                ->where($column, $value)
                ->when($this->route('material'), fn ($q, $self) => $q->whereKeyNot($self->getKey()))
                ->first(['id', 'name', 'deleted_at']);

            if (! $holder) {
                return;
            }

            $fail($holder->trashed()
                ? "{$label} مسجَّل على المادة المؤرشفة «{$holder->name}» — استعدها من الأرشيف بدل إنشاء مادة جديدة."
                : "{$label} مسجَّل على مادة أخرى: «{$holder->name}».");
        };
    }
}
