<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The supplier picker's payload.
 *
 * Deliberately narrower than SupplierResource: a picker needs enough to tell
 * two wholesalers apart at the counter and nothing more. Balances, addresses,
 * tax numbers and e-mail stay behind the suppliers module's own permissions —
 * what a supplier is owed is not something every storekeeper entering a
 * delivery note needs to see.
 *
 * @mixin Supplier
 */
class SupplierOptionResource extends JsonResource
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
