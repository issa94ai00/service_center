<?php

namespace App\Http\Resources;

use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesReturn */
class SalesReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_no' => $this->return_no,
            'sales_invoice_id' => $this->sales_invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn () => [
                'id' => $this->invoice->id,
                'invoice_no' => $this->invoice->invoice_no,
                'invoice_date' => $this->invoice->invoice_date?->format('Y-m-d'),
                'total' => $this->invoice->total,
                'remaining' => $this->invoice->remaining,
            ]),
            'customer_id' => $this->customer_id,
            'customer' => CustomerOptionResource::make($this->whenLoaded('customer')),
            'return_date' => $this->return_date?->format('Y-m-d'),
            'status' => $this->status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'applied_amount' => $this->applied_amount,
            'refunded_amount' => $this->refunded_amount,
            'refund_method' => $this->refund_method,
            // What neither closed the invoice nor went back in cash: it sits on
            // the customer's account for the next invoice.
            'credited_amount' => bcsub(bcsub((string) $this->total, (string) $this->applied_amount, 2), (string) $this->refunded_amount, 2),
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'sales_invoice_item_id' => $item->sales_invoice_item_id,
                'line_type' => $item->line_type,
                'name' => $item->service?->name ?? $item->material?->name ?? $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
        ];
    }
}
