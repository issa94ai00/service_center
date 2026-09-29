<?php

namespace App\Http\Resources;

use App\Models\ServiceUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceUnit */
class ServiceUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'symbol' => $this->symbol,
            'category' => $this->category,
            'is_measurable' => $this->is_measurable,
            'is_active' => $this->is_active,
            'services_count' => $this->services_count ?? null,
            'materials_count' => $this->materials_count ?? null,
        ];
    }
}
