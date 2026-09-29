<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PaymentAllocation */
class PaymentAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoice = $this->invoice_type === 'SALES'
            ? ($this->relationLoaded('salesInvoice') ? $this->salesInvoice : null)
            : ($this->relationLoaded('purchaseInvoice') ? $this->purchaseInvoice : null);

        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'invoice_type' => $this->invoice_type,
            'sales_invoice_id' => $this->sales_invoice_id,
            'purchase_invoice_id' => $this->purchase_invoice_id,
            'amount' => $this->amount,
            'invoice_no' => $invoice?->invoice_no ?? null,
            'invoice_date' => $invoice ? ($invoice->invoice_date ?? $invoice->purchase_date)?->format('Y-m-d') : null,
            'total' => $invoice?->total ?? null,
            'remaining' => $invoice?->remaining ?? null,
        ];
    }
}