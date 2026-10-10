<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * العملات المُدارة وتحويل المبالغ إلى العملة الأساسية (الدولار).
 *
 * كل ما يدخل الدفاتر بالدولار. السند المقبوض أو المدفوع بعملة أخرى يحمل معه
 * المبلغ كما سُلِّم وسعر الصرف الذي حُوِّل به، فيُعرف دائماً من أين جاء رقمه.
 */
class CurrencyService
{
    /**
     * The currency columns for a payment or expense, worked out from what the
     * form sent.
     *
     * Returns [] when the request said nothing about currency: an edit that
     * only fixes the notes must not reset a SYP receipt to USD.
     *
     * The rate the form showed is checked rather than trusted. It may be the
     * current managed rate, or — on an edit that keeps the same currency — the
     * rate the record was already converted at, so correcting a typo on last
     * week's expense does not quietly re-price it at today's rate. Anything
     * else means the rate moved while the form was open, and the user must see
     * the new figure before it reaches the books.
     *
     * @param  array<string, mixed>  $data
     * @return array{amount?: string, currency_code?: string, exchange_rate?: string, currency_amount?: ?string}
     */
    public function resolve(array $data, ?Model $existing = null): array
    {
        if (! array_key_exists('currency_code', $data) || blank($data['currency_code'])) {
            return [];
        }

        $code = strtoupper((string) $data['currency_code']);

        if ($code === Currency::BASE) {
            $usd = blank($data['amount'] ?? null) && filled($data['currency_amount'] ?? null)
                ? ['amount' => $this->round((string) $data['currency_amount'], 2)]
                : [];

            return $usd + [
                'currency_code' => Currency::BASE,
                'exchange_rate' => '1.000000',
                'currency_amount' => null,
            ];
        }

        $currency = Currency::query()->where('code', $code)->first();
        $keepsCurrency = $existing !== null && $existing->getAttribute('currency_code') === $code;

        if (! $currency || (! $currency->is_active && ! $keepsCurrency)) {
            throw new BusinessRuleException(__('العملة :code غير مفعّلة.', ['code' => $code]), 'currency_code');
        }

        if (blank($data['currency_amount'] ?? null)) {
            throw new BusinessRuleException(__('أدخل المبلغ بعملة :name.', ['name' => $currency->name]), 'currency_amount');
        }

        $rate = $this->acceptedRate($currency, $data['exchange_rate'] ?? null, $keepsCurrency ? $existing : null);
        $foreign = $this->round((string) $data['currency_amount'], $currency->decimals);
        $amount = $this->toBase($foreign, $rate);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new BusinessRuleException(__('المبلغ أصغر من أن يعادل سنتاً واحداً.'), 'currency_amount');
        }

        return [
            'amount' => $amount,
            'currency_code' => $code,
            'exchange_rate' => $rate,
            'currency_amount' => $foreign,
        ];
    }

    /** currency_amount ÷ rate, to the cent. */
    public function toBase(string $foreign, string $rate): string
    {
        return $this->round(bcdiv($foreign, $rate, 8), 2);
    }

    /**
     * Change a currency's rate and log the change. A no-op when the rate is
     * the same, so saving the form untouched does not fill the history.
     */
    public function updateRate(Currency $currency, string $rate, ?int $userId): void
    {
        $rate = $this->round($rate, 6);
        $previous = (string) $currency->exchange_rate;

        if (bccomp($previous, $rate, 6) === 0) {
            return;
        }

        DB::transaction(function () use ($currency, $rate, $previous, $userId) {
            $currency->forceFill(['exchange_rate' => $rate])->save();

            CurrencyRate::query()->create([
                'currency_id' => $currency->id,
                'previous_rate' => $previous,
                'exchange_rate' => $rate,
                'changed_by' => $userId,
            ]);
        });
    }

    /**
     * The currencies printed under an invoice total ("ما يعادل"), at today's rate.
     *
     * @return Collection<int, array{code: string, name: string, symbol: string, rate: string, amount: string, decimals: int}>
     */
    public function equivalents(string $baseAmount): Collection
    {
        return Currency::query()
            ->active()
            ->where('is_base', false)
            ->where('show_on_invoices', true)
            ->ordered()
            ->get()
            ->map(fn (Currency $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
                'rate' => (string) $c->exchange_rate,
                'amount' => $this->round(bcmul($baseAmount, (string) $c->exchange_rate, 8), $c->decimals),
                'decimals' => $c->decimals,
            ]);
    }

    private function acceptedRate(Currency $currency, mixed $sent, ?Model $existing): string
    {
        $current = $this->round((string) $currency->exchange_rate, 6);

        if (blank($sent)) {
            return $current;
        }

        $sent = $this->round((string) $sent, 6);

        if (bccomp($sent, $current, 6) === 0) {
            return $current;
        }

        if ($existing !== null && bccomp($sent, $this->round((string) $existing->getAttribute('exchange_rate'), 6), 6) === 0) {
            return $sent;
        }

        throw new BusinessRuleException(__('تغيّر سعر صرف :name إلى :rate منذ فتح الشاشة — راجع المبلغ واحفظ مجدداً.', [
            'name' => $currency->name,
            'rate' => rtrim(rtrim($current, '0'), '.'),
        ]), 'exchange_rate');
    }

    /** Half-up rounding on decimal strings; bcmath alone only truncates. */
    private function round(string $value, int $scale): string
    {
        // "1e5" passes Laravel's numeric rule but is not a bcmath number.
        if (! preg_match('/^\s*-?\d*(\.\d*)?\s*$/', $value)) {
            $value = sprintf('%.8F', (float) $value);
        }

        $value = trim($value);
        $offset = '0.'.str_repeat('0', $scale).'5';

        return str_starts_with(ltrim($value), '-')
            ? bcsub($value, $offset, $scale)
            : bcadd($value, $offset, $scale);
    }
}
