<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\RepairStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repair extends Model
{
    use HasFactory;

    protected $appends = ['receipt_no'];

    protected $fillable = [
        'customer_id', 'technician_id', 'device_type', 'brand', 'model', 'color', 'serial_number',
        'accessories', 'reported_issue', 'notes', 'estimated_cost', 'final_cost',
        'commission_rate', 'commission_amount',
        'status', 'received_at', 'delivered_at', 'sales_invoice_id', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RepairStatus::class,
            'estimated_cost' => 'decimal:2',
            'final_cost' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'received_at' => 'datetime',
            'delivered_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** خبير الصيانة المسند إليه البلاغ، إن وُجد. */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    /**
     * Every payment taken against this repair — the deposit at intake and
     * whatever is collected on hand-over. Drafts and cancelled receipts are
     * included; callers filter to POSTED when they want money that is real.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** The invoice this repair was settled with, once it has been handed over. */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function getReceiptNoAttribute(): string
    {
        return Setting::get('repair_receipt_prefix', 'RPR-').$this->id;
    }

    public function isOpen(): bool
    {
        return $this->status !== RepairStatus::DELIVERED && $this->status !== RepairStatus::CANCELLED;
    }

    /**
     * What the customer is being charged: the price agreed after diagnosis,
     * falling back to the counter estimate while none has been agreed.
     */
    public function chargeableCost(): ?string
    {
        return $this->final_cost ?? $this->estimated_cost;
    }

    /**
     * Whether the money side of this repair is closed.
     *
     * A settlement with nothing to charge (warranty, goodwill) raises no
     * invoice and is closed on settled_at alone. Where an invoice was raised,
     * cancelling it reopens the repair for settlement rather than leaving it
     * stuck pointing at a void document.
     */
    public function isSettled(): bool
    {
        if ($this->settled_at === null) {
            return false;
        }

        if ($this->sales_invoice_id === null) {
            return true;
        }

        return $this->salesInvoice?->status === DocumentStatus::POSTED;
    }
}
