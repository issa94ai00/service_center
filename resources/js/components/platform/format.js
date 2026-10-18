import { formatDate } from '../../utils/format';

/** "منذ 3 ساعات" — callers keep the exact time in a tooltip. */
export function timeAgo(value) {
    if (!value) return '—';
    const minutes = Math.round((Date.now() - new Date(value).getTime()) / 6e4);
    if (!Number.isFinite(minutes)) return '—';
    if (minutes < 1) return 'الآن';
    if (minutes < 60) return `منذ ${minutes} دقيقة`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `منذ ${hours} ساعة`;
    const days = Math.round(hours / 24);
    return days < 30 ? `منذ ${days} يوم` : formatDate(value);
}

/** "in 5 hours" for times ahead, e.g. the next scheduled backup. */
export function timeUntil(value) {
    if (!value) return '—';
    const minutes = Math.round((new Date(value).getTime() - Date.now()) / 6e4);
    if (!Number.isFinite(minutes) || minutes <= 0) return 'الآن';
    if (minutes < 60) return `بعد ${minutes} دقيقة`;
    return `بعد ${Math.round(minutes / 60)} ساعة`;
}

export function formatBytes(bytes) {
    const n = Number(bytes);
    if (bytes === null || bytes === undefined || !Number.isFinite(n)) return '—';
    if (n < 1024) return `${n} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = n / 1024;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    return `${value.toFixed(value < 10 ? 1 : 0)} ${units[unit]}`;
}

export function formatDuration(seconds) {
    const s = Number(seconds);
    if (!Number.isFinite(s)) return '—';
    if (s < 60) return `${Math.max(1, Math.round(s))} ث`;
    return `${Math.floor(s / 60)} د ${Math.round(s % 60)} ث`;
}

export const BACKUP_TRIGGERS = {
    scheduled: { label: 'تلقائية', tone: 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300' },
    manual: { label: 'يدوية', tone: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' },
    pre_restore: { label: 'قبل استعادة', tone: 'bg-violet-50 dark:bg-violet-950/50 text-violet-700 dark:text-violet-300' },
    pre_migrate: { label: 'قبل تحديث', tone: 'bg-cyan-50 dark:bg-cyan-950/50 text-cyan-700 dark:text-cyan-300' },
    pre_delete: { label: 'قبل حذف', tone: 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300' },
};
