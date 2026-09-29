/**
 * The repair lifecycle, in one place.
 *
 * The list, the form and the status badge all used to carry their own copy of
 * these labels and colours, so a new status meant editing three files and the
 * tones drifted apart. Everything now reads from here.
 */

export const REPAIR_STATUSES = [
    {
        value: 'RECEIVED',
        label: 'مستلَم',
        short: 'مستلَم',
        icon: '📥',
        tone: 'bg-blue-50 text-blue-700 ring-blue-200',
        dot: 'bg-blue-500',
        open: true,
    },
    {
        value: 'DIAGNOSING',
        label: 'قيد الفحص',
        short: 'فحص',
        icon: '🔎',
        tone: 'bg-violet-50 text-violet-700 ring-violet-200',
        dot: 'bg-violet-500',
        open: true,
    },
    {
        value: 'UNDER_REPAIR',
        label: 'قيد الإصلاح',
        short: 'إصلاح',
        icon: '🛠️',
        tone: 'bg-amber-50 text-amber-800 ring-amber-200',
        dot: 'bg-amber-500',
        open: true,
    },
    {
        value: 'WAITING_APPROVAL',
        label: 'بانتظار موافقة العميل',
        short: 'موافقة',
        icon: '⏳',
        tone: 'bg-orange-50 text-orange-700 ring-orange-200',
        dot: 'bg-orange-500',
        open: true,
    },
    {
        value: 'READY',
        label: 'جاهز للتسليم',
        short: 'جاهز',
        icon: '✅',
        tone: 'bg-cyan-50 text-cyan-700 ring-cyan-200',
        dot: 'bg-cyan-500',
        open: true,
    },
    {
        value: 'DELIVERED',
        label: 'تم التسليم',
        short: 'مُسلَّم',
        icon: '📦',
        tone: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        dot: 'bg-emerald-500',
        open: false,
    },
    {
        value: 'CANCELLED',
        label: 'ملغي',
        short: 'ملغي',
        icon: '✖️',
        tone: 'bg-red-50 text-red-600 ring-red-200',
        dot: 'bg-red-500',
        open: false,
    },
];

const BY_VALUE = Object.fromEntries(REPAIR_STATUSES.map((s) => [s.value, s]));

const FALLBACK = {
    value: '',
    label: '—',
    short: '—',
    icon: '•',
    tone: 'bg-slate-100 text-slate-600 ring-slate-200',
    dot: 'bg-slate-400',
    open: false,
};

export function repairStatus(value) {
    return BY_VALUE[value] ?? FALLBACK;
}

/** Statuses that still mean the device is on the bench. */
export const OPEN_STATUSES = REPAIR_STATUSES.filter((s) => s.open).map((s) => s.value);

export function isOpenStatus(value) {
    return OPEN_STATUSES.includes(value);
}

/**
 * The one status a counter clerk most often moves a device to next. Drives the
 * single-click advance button in the list, which saves opening the whole form
 * just to say "the screen is fitted, it's ready".
 */
const NEXT_STATUS = {
    RECEIVED: 'DIAGNOSING',
    DIAGNOSING: 'UNDER_REPAIR',
    UNDER_REPAIR: 'READY',
    WAITING_APPROVAL: 'UNDER_REPAIR',
    READY: 'DELIVERED',
};

export function nextStatus(value) {
    const next = NEXT_STATUS[value];
    return next ? repairStatus(next) : null;
}
