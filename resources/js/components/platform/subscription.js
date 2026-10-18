/** Within this many days of expiry a shop is flagged as "expiring soon". */
export const EXPIRING_SOON_DAYS = 14;

/** Whole days until `expiresAt` (YYYY-MM-DD), negative once past; null for an open subscription. */
export function daysLeft(expiresAt) {
    if (!expiresAt) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const end = new Date(`${expiresAt}T00:00:00`);
    return Math.round((end - today) / 864e5);
}

export function isExpiringSoon(tenant) {
    const days = daysLeft(tenant.expires_at);
    return tenant.status === 'active' && days !== null && days >= 0 && days <= EXPIRING_SOON_DAYS;
}

/** Suspended, or past its end date while still marked active. */
export function needsAttention(tenant) {
    return tenant.status === 'suspended' || (tenant.status === 'active' && tenant.is_expired);
}

/** Short Arabic phrase for the time left, with a tone for the badge. */
export function subscriptionView(expiresAt) {
    const days = daysLeft(expiresAt);
    if (days === null) return { label: 'مفتوح', tone: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300', days };
    if (days < 0) return { label: `منتهٍ منذ ${-days} يوم`, tone: 'bg-red-50 dark:bg-red-950/50 text-red-700 dark:text-red-400', days };
    if (days === 0) return { label: 'ينتهي اليوم', tone: 'bg-red-50 dark:bg-red-950/50 text-red-700 dark:text-red-400', days };
    if (days <= EXPIRING_SOON_DAYS) return { label: `باقٍ ${days} يوم`, tone: 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400', days };
    return { label: `باقٍ ${days} يوم`, tone: 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400', days };
}

/** Initials for a shop's avatar tile: the first letter of up to two words. */
export function initials(name) {
    return (name || '؟')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase();
}

const AVATAR_TONES = [
    'from-blue-500 to-indigo-600',
    'from-emerald-500 to-teal-600',
    'from-violet-500 to-purple-600',
    'from-amber-500 to-orange-600',
    'from-rose-500 to-pink-600',
    'from-cyan-500 to-sky-600',
];

/** A stable gradient per shop, so the same shop keeps its colour between visits. */
export function avatarTone(seed) {
    const hash = String(seed ?? '').split('').reduce((acc, c) => (acc * 31 + c.charCodeAt(0)) >>> 0, 0);
    return AVATAR_TONES[hash % AVATAR_TONES.length];
}
