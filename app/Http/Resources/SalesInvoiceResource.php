<?php

namespace App\Http\Resources;

use App\Models\SalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesInvoice */
class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            // Set when this invoice settled a repair rather than a counter
            // sale; the screens read it to badge the row and link the device.
            'repair_id' => $this->repair_id,
            'is_repair' => $this->isRepairInvoice(),
            'repair' => RepairLinkResource::make($this->whenLoaded('repair')),
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'status' => $this->status,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'returned_amount' => $this->returned_amount,
            'remaining' => $this->remaining,
            'notes' => $this->notes,
            'posted_by' => $this->posted_by,
            'posted_at' => $this->posted_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'items' => SalesInvoiceItemResource::collection($this->whenLoaded('items')),
            // Returns standing against the invoice; the list badges the row and
            // the detail lists them, so neither has to ask the returns screen.
            'active_returns_count' => $this->whenCounted('active_returns'),
            'returns' => $this->whenLoaded('returns', fn () => $this->returns->map(fn ($return) => [
                'id' => $return->id,
                'return_no' => $return->return_no,
                'return_date' => $return->return_date?->format('Y-m-d'),
                'status' => $return->status,
                'total' => $return->total,
                'reason' => $return->reason,
            ])),
            'created_by' => $this->created_by,
        ];
    }
}
