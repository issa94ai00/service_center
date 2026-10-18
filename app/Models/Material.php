<?php

namespace App\Models;

use App\Enums\CostMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'sku', 'barcode', 'unit_id', 'cost_price', 'cost_method', 'sale_price',
        'min_stock', 'is_active', 'stock_quantity',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'cost_method' => CostMethod::class,
            'sale_price' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'stock_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class, 'unit_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function currentStock(): string
    {
        return $this->stock_quantity;
    }

    public function getIsLowAttribute(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->min_stock;
    }
}
