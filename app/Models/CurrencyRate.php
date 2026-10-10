<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سطر في سجلّ تغيّرات سعر الصرف: من غيّره، ومن كم إلى كم. */
class CurrencyRate extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['currency_id', 'previous_rate', 'exchange_rate', 'changed_by'];

    protected function casts(): array
    {
        return [
            'previous_rate' => 'decimal:6',
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
