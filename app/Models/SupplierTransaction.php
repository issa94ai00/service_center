<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SupplierTransaction extends Model
{
    protected $fillable = [
        'supplier_id', 'transaction_date', 'type', 'reference_type', 'reference_id',
        'debit', 'credit', 'description', 'created_by', 'ledger_reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'type' => TransactionType::class,
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'ledger_reconciled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
