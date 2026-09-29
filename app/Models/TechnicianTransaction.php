<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TechnicianTransaction extends Model
{
    protected $fillable = [
        'technician_id', 'transaction_date', 'type', 'reference_type', 'reference_id',
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

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
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
