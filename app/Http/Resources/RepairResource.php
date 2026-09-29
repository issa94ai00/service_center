<?php

namespace App\Http\Resources;

use App\Models\Repair;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Repair */
class RepairResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Present only where the caller asked for it: the list adds a
        // withSum() so a row can show what is still owed without a query per
        // device, while a bare show() stays a single row read.
        $paid = $this->paid_total === null ? null : Money::fromDecimal($this->paid_total)->toDecimal();

        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'technician_id' => $this->technician_id,
            'technician' => $this->whenLoaded('technician', fn () => $this->technician?->name),
            'commission_rate' => $this->commission_rate,
            'commission_amount' => $this->commission_amount,
            'device_type' => $this->device_type,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'serial_number' => $this->serial_number,
            'accessories' => $this->accessories,
            'reported_issue' => $this->reported_issue,
            'notes' => $this->notes,
            'estimated_cost' => $this->estimated_cost,
            'final_cost' => $this->final_cost,
            'chargeable_cost' => $this->chargeableCost(),
            'paid' => $paid,
            'due' => $paid === null
                ? null
                : Money::fromDecimal($this->chargeableCost())->subtract(Money::fromDecimal($paid))->toDecimal(),
            'is_settled' => $this->isSettled(),
            'settled_at' => $this->settled_at?->format('Y-m-d H:i'),
            'sales_invoice_id' => $this->sales_invoice_id,
            'invoice_no' => $this->whenLoaded('salesInvoice', fn () => $this->salesInvoice?->invoice_no),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'received_at' => $this->received_at?->format('Y-m-d H:i'),
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i'),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
