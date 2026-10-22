<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\LineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sales_invoice_id', 'line_type', 'service_id', 'material_id', 'description',
        'unit_id', 'quantity', 'unit_price', 'discount_amount', 'line_total', 'cost_amount', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'line_type' => LineType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'cost_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class, 'unit_id');
    }

    /** Return lines against this line on returns that still stand. */
    public function activeReturnItems(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class, 'sales_invoice_item_id')
            ->whereHas('salesReturn', fn ($q) => $q->where('status', DocumentStatus::POSTED));
    }
}
