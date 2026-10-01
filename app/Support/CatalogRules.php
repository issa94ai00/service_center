<?php

namespace App\Support;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rule;

/**
 * Validation for a material or service chosen onto a new document line.
 *
 * `exists:materials,id` reads the raw table and would happily accept an
 * archived item; a new invoice must only offer what is still in the catalogue.
 */
class CatalogRules
{
    public static function material(): Exists
    {
        return Rule::exists('materials', 'id')->withoutTrashed();
    }

    public static function service(): Exists
    {
        return Rule::exists('services', 'id')->withoutTrashed();
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'items.*.material_id.exists' => 'المادة غير موجودة أو مؤرشفة — استعدها من أرشيف المواد أو اختر غيرها.',
            'items.*.service_id.exists' => 'الخدمة غير موجودة أو مؤرشفة — استعدها من أرشيف الخدمات أو اختر غيرها.',
        ];
    }
}
