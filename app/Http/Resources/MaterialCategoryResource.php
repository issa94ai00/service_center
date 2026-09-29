<?php

namespace App\Http\Resources;

use App\Models\MaterialCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaterialCategory */
class MaterialCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'materials_count' => $this->materials_count ?? null,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
