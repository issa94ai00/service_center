import { computed, ref } from 'vue';
import { currenciesApi } from '../services/api';
import { BASE_CURRENCY } from '../constants/currency';

/**
 * Active currencies and their rates against USD, shared by every money form.
 *
 * Loaded once per session; `refresh()` re-reads them (after a rate changed
 * under an open form, or after the currencies screen saved).
 */
const list = ref([BASE_CURRENCY]);
let pending = null;

async function refresh() {
    pending = currenciesApi.options()
        .then((rows) => {
            if (rows.length) list.value = rows;
            return list.value;
        })
        .catch(() => list.value)
        .finally(() => { pending = null; });
    return pending;
}

let loaded = false;

/** Units of the currency per 1 USD, as a Number. */
export function rateOf(currency) {
    return Number(currency?.exchange_rate) || 1;
}

/** Round half-up to `decimals` places, returned as a fixed string. */
export function roundTo(value, decimals = 2) {
    const n = Number(value);
    if (!Number.isFinite(n)) return '';
    const factor = 10 ** decimals;
    return (Math.round((n + Number.EPSILON) * factor) / factor).toFixed(decimals);
}

/** What the server books for `foreign` at `rate`: foreign ÷ rate, to the cent. */
export function toBase(foreign, rate) {
    const amount = Number(foreign);
    const r = Number(rate);
    if (!Number.isFinite(amount) || !r) return '';
    return roundTo(amount / r, 2);
}

/** USD → the currency, at its decimals. */
export function fromBase(usd, currency) {
    const amount = Number(usd);
    if (!Number.isFinite(amount)) return '';
    return roundTo(amount * rateOf(currency), currency?.decimals ?? 2);
}

/** 13000.000000 → "13,000"; 0.920000 → "0.92". */
export function formatRate(rate) {
    const n = Number(rate);
    if (!Number.isFinite(n)) return '—';
    return new Intl.NumberFormat('ar-SY-u-nu-latn', { maximumFractionDigits: 6 }).format(n);
}

/** "1,300,000 ل.س" in the currency's own decimals. */
export function formatIn(amount, currency) {
    const n = Number(amount);
    if (amount === null || amount === undefined || amount === '' || !Number.isFinite(n)) return '—';
    const decimals = currency?.decimals ?? 2;
    const text = new Intl.NumberFormat('ar-SY-u-nu-latn', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(n);
    return `${text} ${currency?.symbol ?? currency?.code ?? ''}`.trim();
}

/** True when a payment/expense row was handled in something other than USD. */
export function isForeign(row) {
    return Boolean(row?.currency_code) && row.currency_code !== BASE_CURRENCY.code;
}

export function useCurrencies() {
    if (!loaded) {
        loaded = true;
        refresh();
    }

    const currencies = computed(() => list.value);
    const hasForeign = computed(() => list.value.some((c) => !c.is_base));

    function find(code) {
        return list.value.find((c) => c.code === code)
            ?? (code === BASE_CURRENCY.code ? BASE_CURRENCY : null);
    }

    return {
        currencies,
        hasForeign,
        find,
        refresh,
        ready: () => pending ?? Promise.resolve(list.value),
    };
}
