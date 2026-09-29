<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'invoice_type', 'sales_invoice_id', 'purchase_invoice_id', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * The id of whichever invoice type is set for this allocation.
     */
    public function invoiceId(): ?int
    {
        return $this->invoice_type === 'SALES' ? $this->sales_invoice_id : $this->purchase_invoice_id;
    }
}
