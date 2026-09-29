<?php

namespace App\Http\Resources;

use App\Models\PurchaseInvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseInvoiceItem */
class PurchaseInvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'material' => MaterialResource::make($this->whenLoaded('material')),
            'description' => $this->description,
            'unit_id' => $this->unit_id,
            'unit' => ServiceUnitResource::make($this->whenLoaded('unit')),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'line_total' => $this->line_total,
        ];
    }
}
