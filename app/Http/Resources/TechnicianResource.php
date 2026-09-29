<?php

namespace App\Http\Resources;

use App\Models\Technician;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Technician */
class TechnicianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'commission_rate' => $this->commission_rate,
            'opening_balance' => $this->opening_balance,
            // الرصيد بإشارة الخبير: الموجب يعني أن المحل مدين له، تماماً كما
            // يقلب تقرير الموردين إشارته.
            'balance' => bcmul(
                bcadd((string) ($this->opening_balance ?? 0), (string) ($this->tx_net ?? 0), 2),
                '-1',
                2
            ),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
