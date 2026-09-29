<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;

    public const DELETED_AT = 'soft_deleted_at';

    use SoftDeletes;

    protected $fillable = [
        'name', 'company', 'phone', 'mobile', 'email', 'address', 'city',
        'tax_number', 'opening_balance', 'is_active', 'notes', 'soft_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'soft_deleted_at' => 'datetime',
        ];
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
