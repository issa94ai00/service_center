<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مصروف صُرف من الصندوق.
 *
 * لا مسودة له: المال خرج قبل أن يُكتب السند، فيُسجَّل معتمداً ويُلغى بسبب
 * مكتوب إن أُخطئ فيه. الملغى يبقى في القائمة ولا يدخل في أي مجموع.
 */
class Expense extends Model
{
    /** الحالتان الوحيدتان: ما صُرف، وما أُلغي بعد صرفه. */
    public const POSTED = 'POSTED';

    public const CANCELLED = 'CANCELLED';

    protected $fillable = [
        'expense_no', 'expense_category_id', 'amount', 'payment_method', 'expense_date',
        'payee', 'notes', 'status', 'recorded_by', 'cancelled_by', 'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** ما يُحتسب فعلاً من الصندوق — كل مجموع في النظام يمرّ من هنا. */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->where('status', self::POSTED);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }
}
