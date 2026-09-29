<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'payment_type', 'customer_id', 'supplier_id', 'technician_id', 'repair_id', 'amount', 'payment_method',
        'reference_no', 'payment_date', 'status', 'notes', 'recorded_by',
        'posted_by', 'posted_at', 'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'payment_method' => PaymentMethod::class,
            'status' => DocumentStatus::class,
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** خبير الصيانة الذي صُرفت له هذه الدفعة، في سندات صرف المستحقات. */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    /**
     * The repair this money was taken against, when it came in through the
     * maintenance counter rather than the payments screen.
     */
    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    /** Whether this receipt came off the maintenance counter, not the till. */
    public function isRepairPayment(): bool
    {
        return $this->repair_id !== null;
    }

    /**
     * Limit a listing to money taken against devices, or to everything booked
     * through the payments screen. Anything else returns the query untouched.
     */
    public function scopeSource(Builder $query, ?string $source): Builder
    {
        return match (strtoupper((string) $source)) {
            'REPAIR' => $query->whereNotNull('repair_id'),
            'DIRECT' => $query->whereNull('repair_id'),
            default => $query,
        };
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
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
