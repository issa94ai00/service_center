<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'unit_id' => $this->unit_id,
            'unit' => ServiceUnitResource::make($this->whenLoaded('unit')),
            'default_price' => $this->default_price,
            'is_active' => $this->is_active,
            'archived_at' => $this->deleted_at?->toIso8601String(),
            // Only set on archive listings, where it decides whether a
            // permanent delete is offered.
            'is_referenced' => $this->when(
                array_key_exists('is_referenced', $this->getAttributes()),
                fn () => (bool) $this->getAttributes()['is_referenced'],
            ),
        ];
    }
}
