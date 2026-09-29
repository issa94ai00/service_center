<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_type' => $this->payment_type,
            'reference_no' => $this->reference_no,
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'supplier_id' => $this->supplier_id,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'technician_id' => $this->technician_id,
            'technician' => TechnicianResource::make($this->whenLoaded('technician')),
            // Money taken at the maintenance counter carries its own series and
            // points back at the device it was collected against.
            'repair_id' => $this->repair_id,
            'is_repair' => $this->isRepairPayment(),
            'repair' => RepairLinkResource::make($this->whenLoaded('repair')),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'posted_by' => $this->posted_by,
            'posted_at' => $this->posted_at?->toISOString(),
            'cancelled_by' => $this->cancelled_by,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'allocations' => PaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'created_at' => $this->created_at,
        ];
    }
}