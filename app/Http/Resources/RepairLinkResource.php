<?php

namespace App\Http\Resources;

use App\Models\Repair;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The pointer a maintenance document carries back to its device.
 *
 * Invoices and receipts raised at the repair counter are listed side by side
 * with counter sales, and a number alone does not say which device a line is
 * about. This is the minimum a list row needs to say so — never the whole
 * repair, which would drag the customer and technician into every payment row.
 *
 * @mixin Repair
 */
class RepairLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_no' => $this->receipt_no,
            'device' => collect([$this->brand, $this->model])->filter()->join(' ') ?: $this->device_type,
            'status' => $this->status->value,
        ];
    }
}
