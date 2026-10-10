import { BASE_SYMBOL } from '../constants/currency';

/**
 * Formatting and input-normalisation helpers shared by the repair screens.
 */

const ARABIC_INDIC = {
    '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
    '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
    '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
    '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
};

/**
 * Arabic keyboards produce ٠١٢…; the database stores Latin digits. Without this
 * a customer saved as "0999123456" is unfindable by someone who typed the same
 * number on an Arabic layout.
 */
export function toLatinDigits(value) {
    return String(value ?? '').replace(/[٠-٩۰-۹]/g, (d) => ARABIC_INDIC[d] ?? d);
}

/** Bare digits, for comparing two phone numbers written with different spacing. */
export function phoneDigits(value) {
    return toLatinDigits(value).replace(/\D+/g, '');
}

/** Tidies a typed phone for storage: Latin digits, single spaces, keeps a leading +. */
export function normalisePhone(value) {
    const text = toLatinDigits(value).trim();
    if (!text) return '';
    const plus = text.startsWith('+') ? '+' : '';
    return plus + text.replace(/[^\d]/g, '');
}

/**
 * True when what the user typed into the customer search is a phone number
 * rather than a name — so the quick-add form can prefill the right field.
 */
export function looksLikePhone(value) {
    const text = toLatinDigits(value).trim();
    if (!text) return false;
    return /^[+\d][\d\s\-()]*$/.test(text) && phoneDigits(text).length >= 4;
}

export function formatMoney(value, { fraction = 2 } = {}) {
    if (value === null || value === undefined || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return new Intl.NumberFormat('ar-SY-u-nu-latn', {
        minimumFractionDigits: fraction,
        maximumFractionDigits: fraction,
    }).format(n);
}

/**
 * A book amount (always USD) with its symbol: "1,250.00 $". The space is
 * non-breaking so the symbol never wraps away from a narrow table cell.
 */
export function formatUsd(value, options) {
    const text = formatMoney(value, options);
    return text === '—' ? text : `${text} ${BASE_SYMBOL}`;
}

/** "2026-09-10 14:30" -> a Date, without the Safari/iOS NaN on the space form. */
export function parseDateTime(value) {
    if (!value) return null;
    const iso = String(value).trim().replace(' ', 'T');
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
}

export function formatDate(value) {
    const d = parseDateTime(value);
    if (!d) return '—';
    return new Intl.DateTimeFormat('ar-SY-u-nu-latn', { dateStyle: 'medium' }).format(d);
}

export function formatDateTime(value) {
    const d = parseDateTime(value);
    if (!d) return '—';
    return new Intl.DateTimeFormat('ar-SY-u-nu-latn', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(d);
}

/** Whole days since a timestamp, or null when it cannot be read. */
export function daysSince(value) {
    const d = parseDateTime(value);
    if (!d) return null;
    return Math.floor((Date.now() - d.getTime()) / 86400000);
}

/** "اليوم" / "أمس" / "منذ ٥ أيام" — how long the device has been sitting here. */
export function relativeDays(value) {
    const days = daysSince(value);
    if (days === null) return '—';
    if (days <= 0) return 'اليوم';
    if (days === 1) return 'أمس';
    if (days === 2) return 'منذ يومين';
    if (days <= 10) return `منذ ${days} أيام`;
    if (days < 30) return `منذ ${days} يوماً`;
    const months = Math.floor(days / 30);
    return months === 1 ? 'منذ شهر' : `منذ ${months} أشهر`;
}

/** Today's date as the datetime-local input wants it. */
export function nowLocalInput() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** "2026-09-10 14:30" (API) -> "2026-09-10T14:30" (datetime-local input). */
export function toDateTimeInput(value) {
    if (!value) return '';
    const text = String(value);
    return text.includes(' ') ? text.slice(0, 16).replace(' ', 'T') : text.slice(0, 16);
}

/**
 * Pulls a readable message out of an axios error, including Laravel's
 * field-keyed 422 bag.
 */
export function apiMessage(error, fallback = 'حدث خطأ غير متوقع') {
    const data = error?.response?.data;
    if (!data) return fallback;
    if (typeof data.message === 'string' && data.message) return data.message;
    if (data.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors).flat()[0];
        if (first) return first;
    }
    if (data.message && typeof data.message === 'object') {
        return Object.values(data.message).flat().join('، ');
    }
    return fallback;
}

/** Laravel's 422 `errors` bag flattened to `{ field: 'first message' }`. */
export function apiFieldErrors(error) {
    const errors = error?.response?.data?.errors;
    if (!errors || typeof errors !== 'object') return {};
    return Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => [field, [].concat(messages)[0]])
    );
}

/**
 * Local calendar date as `YYYY-MM-DD`.
 *
 * `toISOString()` converts to UTC first, so east of Greenwich it hands back
 * yesterday for most of the evening — long enough for a date filter defaulted
 * from "today" to quietly drop the day the user actually meant.
 */
export function isoDate(date = new Date()) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Quantities carry up to 3 decimals in the schema, but trailing zeros only add noise. */
export function formatQty(value) {
    const n = Number(value);
    if (value === null || value === undefined || value === '' || Number.isNaN(n)) return '—';
    return new Intl.NumberFormat('ar-SY-u-nu-latn', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    }).format(n);
}

/** Whole-number percentage, for ratios shown beside a bar. */
export function formatPercent(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return '—';
    return `${new Intl.NumberFormat('ar-SY-u-nu-latn', { maximumFractionDigits: 0 }).format(n * 100)}٪`;
}
