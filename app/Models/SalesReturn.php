<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Goods (or a service charge) a customer handed back against a posted sales
 * invoice. Posted on creation; cancelling reverses it.
 */
class SalesReturn extends Model
{
    protected $fillable = [
        'return_no', 'sales_invoice_id', 'customer_id', 'return_date', 'status', 'reason', 'notes',
        'subtotal', 'discount_amount', 'total', 'applied_amount', 'refunded_amount', 'refund_method',
        'cost_amount', 'created_by', 'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'return_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
