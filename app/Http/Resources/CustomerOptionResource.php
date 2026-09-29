<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer picker's payload.
 *
 * Deliberately narrower than CustomerResource: a picker needs enough to tell
 * two people apart at the counter and nothing more. Balances, addresses, tax
 * numbers and e-mail stay behind the customers module's own permissions.
 *
 * @mixin Customer
 */
class CustomerOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
