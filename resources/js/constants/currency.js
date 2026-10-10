/**
 * The books are kept in US dollars. Every amount the API returns in an
 * `amount`/`total`/`balance` field is USD; only `currency_amount` on a payment
 * or expense is in that record's own `currency_code`.
 */
export const BASE_CURRENCY = Object.freeze({
    code: 'USD',
    name: 'دولار أمريكي',
    symbol: '$',
    exchange_rate: '1',
    decimals: 2,
    is_base: true,
});

export const BASE_SYMBOL = BASE_CURRENCY.symbol;
