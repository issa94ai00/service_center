<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * خبير صيانة يعمل بنسبة من قيمة البلاغ.
 *
 * يُمسك له دفتر حسابات كامل مثل المورد: عمولاته دائنة ودفعاته مدينة، والرصيد
 * يُشتقّ دائماً من الدفتر لا من عمود محفوظ.
 */
class Technician extends Model
{
    use HasFactory;

    public const DELETED_AT = 'soft_deleted_at';

    use SoftDeletes;

    protected $fillable = [
        'name', 'specialty', 'phone', 'mobile', 'email', 'address', 'city',
        'commission_rate', 'opening_balance', 'is_active', 'notes', 'soft_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'soft_deleted_at' => 'datetime',
        ];
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(Repair::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TechnicianTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * نصيب الخبير من مبلغ، بالنسبة المعطاة أو بنسبته الافتراضية.
     *
     * الحساب يمرّ عبر Money كاملاً حتى لا يتسرّب انحراف عشري إلى دفتر حسابات.
     */
    public function commissionOn(string|float|null $amount, string|float|null $rate = null): Money
    {
        $rate = $rate ?? $this->commission_rate;

        return Money::fromDecimal($amount)->percentage((float) $rate);
    }
}
