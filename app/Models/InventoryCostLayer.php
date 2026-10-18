<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCostLayer extends Model
{
    protected $fillable = [
        'material_id', 'quantity', 'remaining_quantity', 'unit_cost',
        'received_at', 'source_type', 'source_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'remaining_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'received_at' => 'date',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
