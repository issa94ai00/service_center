<?php

namespace App\Models;

use App\Enums\MovementDirection;
use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'material_id', 'type', 'direction', 'quantity', 'unit_price',
        'source_type', 'source_id', 'reference_no', 'movement_date',
        'description', 'created_by', 'cancels_id', 'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'direction' => MovementDirection::class,
            'source_type' => MovementSourceType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'movement_date' => 'date',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
