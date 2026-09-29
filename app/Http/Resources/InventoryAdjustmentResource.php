<?php

namespace App\Http\Resources;

use App\Models\InventoryAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryAdjustment */
class InventoryAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjustment_no' => $this->adjustment_no,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'adjusted_by' => $this->whenLoaded('adjuster', fn () => $this->adjuster?->name),
            'posted_at' => $this->posted_at?->format('Y-m-d H:i'),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i'),
            'cancellation_reason' => $this->cancellation_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'material' => $item->material?->name,
                'unit' => $item->material?->unit?->name_ar,
                'direction' => $item->direction->value,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'reason' => $item->reason,
            ])),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
