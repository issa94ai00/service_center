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
            'sale_price' => $this->sale_price,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
            'stock_quantity' => $this->stock_quantity,
            'is_low' => $this->isLow,
        ];
    }
}
