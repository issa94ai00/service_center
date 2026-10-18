<?php

namespace App\Http\Resources;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Material */
class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => MaterialCategoryResource::make($this->whenLoaded('category')),
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'unit_id' => $this->unit_id,
            'unit' => ServiceUnitResource::make($this->whenLoaded('unit')),
            'cost_price' => $this->cost_price,
            'cost_method' => $this->cost_method?->value,
            'cost_method_label' => $this->cost_method?->label(),
            'sale_price' => $this->sale_price,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
            'stock_quantity' => $this->stock_quantity,
            'is_low' => $this->isLow,
            'archived_at' => $this->deleted_at?->toIso8601String(),
            // Only set on archive listings, where it decides whether a
            // permanent delete is offered.
            'is_referenced' => $this->when(
                array_key_exists('is_referenced', $this->getAttributes()),
                fn () => (bool) $this->getAttributes()['is_referenced'],
            ),
        ];
    }
}
