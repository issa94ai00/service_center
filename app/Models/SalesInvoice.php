<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    protected $fillable = [
        'invoice_no', 'customer_id', 'repair_id', 'invoice_date', 'due_date', 'status',
        'discount_type', 'discount_value', 'discount_amount', 'subtotal', 'total',
        'paid_amount', 'returned_amount', 'remaining', 'notes', 'created_by', 'posted_by', 'posted_at',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'discount_type' => DiscountType::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'remaining' => 'decimal:2',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The repair this invoice settled, when it was raised at a hand-over rather
     * than at the sales counter. Mirror of repairs.sales_invoice_id.
     */
    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    /** Whether this document belongs to the maintenance series, not sales. */
    public function isRepairInvoice(): bool
    {
        return $this->repair_id !== null;
    }

    /**
     * Limit a listing to one side of the counter: maintenance settlements, or
     * plain sales. Anything else returns the query untouched.
     */
    public function scopeSource(Builder $query, ?string $source): Builder
    {
        return match (strtoupper((string) $source)) {
            'REPAIR' => $query->whereNotNull('repair_id'),
            'SALES' => $query->whereNull('repair_id'),
            default => $query,
        };
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class)->orderBy('sort');
    }

    /** Every return taken against this invoice, cancelled ones included. */
    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class)->orderBy('id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerTransaction::class, 'reference_id')
            ->where('reference_type', SalesInvoice::class);
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
