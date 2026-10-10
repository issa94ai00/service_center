<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Currency */
class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            // Units of this currency per 1 USD.
            'exchange_rate' => $this->exchange_rate,
            'decimals' => $this->decimals,
            'is_base' => $this->is_base,
            'is_active' => $this->is_active,
            'show_on_invoices' => $this->is_base ? false : $this->show_on_invoices,
            'sort_order' => $this->sort_order,
            'rate_updated_at' => $this->whenLoaded('rates', fn () => $this->rates->first()?->created_at?->toISOString()),
            'usage_count' => $this->whenHas('usage_count'),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
