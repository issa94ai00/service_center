<?php

namespace App\Models;

use App\Enums\MovementDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['inventory_adjustment_id', 'material_id', 'direction', 'quantity', 'unit_cost', 'reason'];

    protected function casts(): array
    {
        return [
            'direction' => MovementDirection::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }
}
