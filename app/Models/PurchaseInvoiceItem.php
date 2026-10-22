<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_invoice_id', 'material_id', 'description', 'unit_id',
        'quantity', 'unit_price', 'discount_amount', 'line_total', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
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
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_invoice_item_id')
            ->whereHas('purchaseReturn', fn ($q) => $q->where('status', DocumentStatus::POSTED));
    }
}
