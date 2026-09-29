<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'tax_number' => $this->tax_number,
            'opening_balance' => $this->opening_balance,
            'balance' => bcadd(
                (string) ($this->opening_balance ?? 0),
                (string) ($this->tx_net ?? 0),
                2
            ),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
