<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ExpenseCategory */
class ExpenseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            // يُحمَّل عند الحاجة فقط: الشاشة تحتاجه لترتيب الأبواب الأكثر
            // استعمالاً أولاً، وقائمة الاختيار لا تحتاجه.
            'expenses_count' => $this->whenCounted('expenses'),
        ];
    }
}
