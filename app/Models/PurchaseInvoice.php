<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    protected $fillable = [
        'invoice_no', 'supplier_id', 'purchase_date', 'due_date', 'status',
        'discount_type', 'discount_value', 'discount_amount', 'subtotal', 'total',
        'paid_amount', 'remaining', 'notes', 'created_by', 'posted_by', 'posted_at',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'discount_type' => DiscountType::class,
            'purchase_date' => 'date',
            'due_date' => 'date',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining' => 'decimal:2',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class)->orderBy('sort');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class, 'reference_id')
            ->where('reference_type', PurchaseInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
