<?php

namespace App\Http\Resources;

use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseInvoice */
class PurchaseInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'supplier_id' => $this->supplier_id,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'remaining' => $this->remaining,
            'notes' => $this->notes,
            'posted_by' => $this->posted_by,
            'posted_at' => $this->posted_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'items' => PurchaseInvoiceItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->created_by,
        ];
    }
}
