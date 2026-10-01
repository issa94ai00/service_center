import { computed, reactive, ref } from 'vue';
import { printApi } from '../services/api';

/**
 * Which paper each document prints on, and how.
 *
 * Two layers:
 *  - the shop default per document kind, kept on the server (Settings page);
 *  - an override for this browser only, kept in localStorage — the repair
 *    counter has a thermal printer, the back office has an A4 laser, and both
 *    log in to the same system.
 *
 * State is module-level so every print button on screen agrees, and the
 * server config is fetched once per session.
 */

export const DOCUMENT_KINDS = [
    { key: 'invoice', label: 'الفواتير', hint: 'فواتير البيع والشراء والصيانة' },
    { key: 'payment', label: 'سندات القبض والدفع', hint: 'بما فيها عرابين الصيانة' },
    { key: 'repair', label: 'إيصالات استلام الأجهزة', hint: 'تُسلَّم للعميل عند الاستلام' },
    { key: 'statement', label: 'كشوف الحساب', hint: 'على الورق العادي فقط' },
];

// Used until /print/config answers, and if it never does: printing must not
// depend on a second request succeeding.
const FALLBACK = {
    papers: [
        { value: 'a4', label: 'A4', thermal: false, width: 210 },
        { value: 'a5', label: 'A5', thermal: false, width: 148 },
        { value: '80mm', label: 'حراري 80 مم', thermal: true, width: 80 },
        { value: '58mm', label: 'حراري 58 مم', thermal: true, width: 58 },
    ],
    supported: {
        invoice: ['a4', 'a5', '80mm', '58mm'],
        payment: ['a4', 'a5', '80mm', '58mm'],
        repair: ['a4', 'a5', '80mm', '58mm'],
        statement: ['a4', 'a5'],
    },
    defaults: { invoice: 'a4', payment: 'a4', repair: 'a4', statement: 'a4' },
};

const STORAGE_KEY = 'print.device.v1';

const config = ref(FALLBACK);
let loading = null;

const device = reactive({
    /** kind → paper value, or null for "use the shop default". */
    papers: {},
    /** 'preview' opens the PDF in a tab; 'direct' goes straight to the print dialog. */
    mode: 'preview',
});

function readDevice() {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        device.papers = stored.papers && typeof stored.papers === 'object' ? stored.papers : {};
        device.mode = stored.mode === 'direct' ? 'direct' : 'preview';
    } catch {
        // Private window or blocked storage: run on shop defaults.
    }
}

function writeDevice() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ papers: device.papers, mode: device.mode }));
    } catch {
        // Not fatal: the choice holds for this session.
    }
}

readDevice();

export function loadPrintConfig(force = false) {
    if (loading && !force) return loading;

    loading = printApi.config()
        .then((data) => {
            if (data?.papers?.length) config.value = data;
        })
        .catch(() => {
            loading = null;
        });

    return loading;
}

// ---------- the device preferences dialog, opened from anywhere --------------

const dialogOpen = ref(false);

export function openPrintPreferences() {
    dialogOpen.value = true;
}

export function usePrintPreferences() {
    loadPrintConfig();

    const papers = computed(() => config.value.papers);

    function paperInfo(value) {
        return papers.value.find((p) => p.value === value) || { value, label: value, thermal: false };
    }

    function supports(kind, paper) {
        return (config.value.supported[kind] || []).includes(paper);
    }

    function papersFor(kind) {
        return (config.value.supported[kind] || ['a4']).map(paperInfo);
    }

    function shopDefault(kind) {
        return config.value.defaults[kind] || 'a4';
    }

    /** This device's override, if it is still one the document supports. */
    function deviceOverride(kind) {
        const paper = device.papers[kind];
        return paper && supports(kind, paper) ? paper : null;
    }

    function paperFor(kind) {
        return deviceOverride(kind) || shopDefault(kind);
    }

    function setDevicePaper(kind, paper) {
        device.papers = { ...device.papers, [kind]: paper || null };
        writeDevice();
    }

    function setMode(mode) {
        device.mode = mode === 'direct' ? 'direct' : 'preview';
        writeDevice();
    }

    function resetDevice() {
        device.papers = {};
        device.mode = 'preview';
        writeDevice();
    }

    const hasDeviceOverrides = computed(() =>
        DOCUMENT_KINDS.some((k) => deviceOverride(k.key)) || device.mode !== 'preview'
    );

    return {
        papers,
        paperInfo,
        papersFor,
        shopDefault,
        deviceOverride,
        paperFor,
        setDevicePaper,
        setMode,
        resetDevice,
        hasDeviceOverrides,
        mode: computed(() => device.mode),
        dialogOpen,
        refresh: () => loadPrintConfig(true),
    };
}
