<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * عملة مُدارة.
 *
 * الدفاتر كلّها بالدولار (العملة الأساسية). بقية العملات تُستعمل عند قبض المال
 * أو دفعه أو صرفه بها: يُحفظ المبلغ كما سُلِّم، وسعر الصرف وقتها، وما يعادله
 * بالدولار — وهذا الأخير وحده يدخل الدفاتر والتقارير.
 *
 * exchange_rate = كم وحدة من هذه العملة تساوي دولاراً واحداً (ل.س 13000).
 */
class Currency extends Model
{
    public const BASE = 'USD';

    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate', 'decimals', 'is_base',
        'is_active', 'show_on_invoices', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'decimals' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'show_on_invoices' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(CurrencyRate::class)->latest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('is_base')->orderBy('sort_order')->orderBy('code');
    }
}
