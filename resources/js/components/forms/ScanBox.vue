<template>
    <div class="relative">
        <div class="flex flex-wrap items-stretch gap-2">
            <div class="relative min-w-0 flex-1">
                <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400" aria-hidden="true">
                    🔍
                </span>
                <input
                    ref="input"
                    v-model="term"
                    type="text"
                    role="combobox"
                    :aria-expanded="open"
                    aria-autocomplete="list"
                    :aria-controls="listboxId"
                    autocomplete="off"
                    :placeholder="placeholder"
                    class="w-full rounded-xl border-2 border-slate-300 bg-white py-3 pe-9 ps-10 text-sm transition focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100"
                    @keydown.enter.prevent="onEnter"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.esc="close"
                    @input="onInput"
                    @focus="term && search()"
                />
                <button
                    v-if="term"
                    type="button"
                    aria-label="مسح الحقل"
                    class="absolute inset-y-0 end-1 flex w-8 items-center justify-center text-slate-400 transition hover:text-slate-700"
                    @click="reset(true)"
                >
                    ✕
                </button>
            </div>

            <button
                v-if="cameraSupported"
                type="button"
                class="shrink-0 rounded-xl border-2 border-slate-300 px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                :aria-pressed="scanning"
                @click="toggleCamera"
            >
                {{ scanning ? '■ إيقاف الكاميرا' : '📷 مسح بالكاميرا' }}
            </button>
        </div>

        <p class="mt-1.5 text-xs text-slate-500">
            امسح الباركود بالجهاز، أو اكتب الاسم أو الرمز ثم اضغط Enter لإضافة أعلى نتيجة.
        </p>

        <!-- شريط حيّ يقرؤه قارئ الشاشة: المسح لا يُحدث أثراً بصرياً يُنتبه له. -->
        <p class="sr-only" role="status" aria-live="polite">{{ announcement }}</p>

        <p v-if="notice" class="mt-2 rounded-lg px-3 py-2 text-xs" :class="noticeTone">
            {{ notice }}
        </p>

        <!-- رمز لم يُطابق شيئاً ليس نهاية الطريق: البضاعة أمام الموظّف الآن،
             وتسجيلها من هنا أقصر من ترك الفاتورة والذهاب إلى شاشة المواد. -->
        <button
            v-if="pending && allowCreate"
            type="button"
            class="mt-2 flex w-full items-center justify-between gap-3 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50 px-3 py-2.5 text-right transition hover:border-blue-500 hover:bg-blue-100"
            @click="emitCreate"
        >
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-blue-800">＋ سجّل مادة جديدة الآن</span>
                <span class="block truncate text-xs text-blue-600" :dir="pending.barcode ? 'ltr' : 'rtl'">
                    {{ pending.barcode || pending.name }}
                </span>
            </span>
            <span class="shrink-0 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">إضافة</span>
        </button>

        <!-- الكاميرا -->
        <div v-show="scanning" class="mt-3 overflow-hidden rounded-xl border border-slate-300 bg-black">
            <video ref="video" class="block max-h-64 w-full object-cover" muted playsinline></video>
        </div>

        <!-- نتائج البحث بالاسم -->
        <ul
            v-if="open && (results.length || canOfferCreate)"
            id="scan-results"
            role="listbox"
            class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
        >
            <li
                v-for="(row, index) in results"
                :key="`${row.kind}-${row.id}`"
                role="option"
                :aria-selected="index === highlighted"
                class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-sm"
                :class="index === highlighted ? 'bg-blue-50' : 'hover:bg-slate-50'"
                @mousedown.prevent="pick(row)"
                @mouseenter="highlighted = index"
            >
                <span class="flex min-w-0 items-center gap-2">
                    <span
                        class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-semibold"
                        :class="row.kind === 'SERVICE' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'"
                    >
                        {{ row.kind === 'SERVICE' ? 'خدمة' : 'مادة' }}
                    </span>
                    <span class="truncate font-medium text-slate-900">{{ row.name }}</span>
                    <span v-if="row.code" class="hidden shrink-0 text-xs text-slate-400 sm:inline" dir="ltr">{{ row.code }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-3 text-xs">
                    <span v-if="showStock && row.kind === 'MATERIAL'" :class="stockTone(row.stock)">
                        {{ row.stock > 0 ? `متوفّر ${formatQty(row.stock)}` : 'نفد' }}
                    </span>
                    <b class="tabular-nums text-slate-900">{{ formatMoney(row.price) }}</b>
                </span>
            </li>

            <li v-if="!results.length" class="px-3 py-3 text-sm text-slate-400">
                لا مادة تطابق «{{ term.trim() }}»
            </li>

            <li v-if="canOfferCreate" class="border-t border-slate-200">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2.5 text-right text-sm font-medium text-blue-600 transition hover:bg-blue-50"
                    @mousedown.prevent="createFromTerm()"
                >
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold">+</span>
                    <span class="truncate">مادة جديدة «{{ term.trim() }}»</span>
                </button>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, useId } from 'vue';
import { materialsApi, servicesApi } from '../../services/api';
import { formatMoney, formatQty, toLatinDigits } from '../../utils/format';

const props = defineProps({
    /**
     * A sale may bill labour as well as parts; a purchase invoice buys goods
     * and nothing else, so it asks for materials only.
     */
    includeServices: { type: Boolean, default: true },
    /**
     * Which price a picked row carries back. Selling reads `sale_price`;
     * receiving a delivery reads `cost_price`, and filling a purchase line with
     * the retail price is a costing error that survives into stock valuation.
     */
    priceField: { type: String, default: 'sale_price' },
    /** Stock is what a seller needs to see; a buyer is the one replenishing it. */
    showStock: { type: Boolean, default: true },
    placeholder: { type: String, default: 'امسح الباركود أو ابحث بالاسم أو الرمز...' },
    /**
     * Whether an unmatched code may be turned into a new material from here.
     * A purchase invoice is exactly where unknown goods first appear, so it
     * says yes; a sales screen sells what the shop already owns and says no.
     */
    allowCreate: { type: Boolean, default: false },
});

const emit = defineEmits(['pick', 'create']);

const listboxId = `scan-results-${useId()}`;

const input = ref(null);
const video = ref(null);
const term = ref('');
const results = ref([]);
const highlighted = ref(0);
const open = ref(false);
const notice = ref('');
const noticeKind = ref('info');
const announcement = ref('');
const scanning = ref(false);

/** الرمز أو النص الذي لم يُطابق شيئاً، معروضاً كعرض بإنشاء مادة منه. */
const pending = ref(null);

let debounce = null;
let stream = null;
let detector = null;
let rafId = null;

// BarcodeDetector أصلي في المتصفّح: لا مكتبة تُحمَّل ولا اعتماد يُضاف. غيابه
// لا يعطّل شيئاً — الماسح السلكي هو الطريق الأساسي وهذا تحسين إضافي.
const cameraSupported = typeof window !== 'undefined'
    && 'BarcodeDetector' in window
    && Boolean(navigator.mediaDevices?.getUserMedia);

const noticeTone = computed(() =>
    noticeKind.value === 'error'
        ? 'bg-red-50 text-red-700'
        : noticeKind.value === 'success'
            ? 'bg-emerald-50 text-emerald-700'
            : 'bg-slate-100 text-slate-600'
);

/** صفّ «مادة جديدة» يحتاج نصّاً يحمله؛ قائمة فارغة بلا بحث لا تعرض شيئاً. */
const canOfferCreate = computed(() => props.allowCreate && term.value.trim().length >= 2);

function stockTone(stock) {
    return Number(stock) > 0 ? 'text-slate-500' : 'text-red-600 font-medium';
}

function say(message, kind = 'info') {
    notice.value = message;
    noticeKind.value = kind;
    announcement.value = message;
}

function close() {
    open.value = false;
}

function reset(focus = false) {
    term.value = '';
    results.value = [];
    open.value = false;
    highlighted.value = 0;
    pending.value = null;
    if (focus) input.value?.focus();
}

function move(step) {
    // صفّ «مادة جديدة» جزء من الدورة: من يتنقّل بالأسهم يصل إليه كما يصل
    // بالفأرة، ولا يُضطرّ إلى ترك لوحة المفاتيح عند آخر خطوة.
    const total = results.value.length + (canOfferCreate.value ? 1 : 0);
    if (!total) return;

    open.value = true;
    highlighted.value = (highlighted.value + step + total) % total;
}

function onInput() {
    notice.value = '';
    pending.value = null;
    clearTimeout(debounce);

    if (term.value.trim().length < 2) {
        results.value = [];
        open.value = false;
        return;
    }

    debounce = setTimeout(search, 250);
}

async function search() {
    const query = term.value.trim();
    if (query.length < 2) return;

    try {
        const materialsPromise = materialsApi.index({ search: query, is_active: 1, per_page: 15 })
            .catch(() => ({ data: [] }));
        const servicesPromise = props.includeServices
            ? servicesApi.index({ search: query, per_page: 10 }).catch(() => ({ data: [] }))
            : Promise.resolve({ data: [] });

        const [materials, services] = await Promise.all([materialsPromise, servicesPromise]);

        results.value = [
            ...(materials.data ?? []).map((m) => ({
                kind: 'MATERIAL',
                id: m.id,
                name: m.name,
                code: m.barcode || m.sku,
                price: m[props.priceField],
                stock: Number(m.stock_quantity) || 0,
                unit_id: m.unit_id,
                raw: m,
            })),
            ...((services.data ?? services) || []).map((s) => ({
                kind: 'SERVICE',
                id: s.id,
                name: s.name,
                code: '',
                price: s.default_price,
                stock: null,
                unit_id: s.unit_id,
                raw: s,
            })),
        ];

        highlighted.value = 0;
        open.value = results.value.length > 0 || canOfferCreate.value;
    } catch {
        say('تعذّر البحث', 'error');
    }
}

/**
 * Enter من الماسح يصل ومعه الرمز كاملاً؛ ومن لوحة المفاتيح يصل ومعه ما كُتب.
 * لا سبيل للتفريق بينهما بثقة، فالترتيب واحد: جرّب المطابقة التامّة أوّلاً —
 * وهي ما يريده الماسح — وإن لم تُطابق فأضف أعلى نتيجة بحث.
 */
async function onEnter() {
    const code = term.value.trim();
    if (!code) return;

    // بشرط وجود نتائج: قائمة فارغة يقف مؤشّرها على صفّ الإنشاء صدفةً، ولو
    // فاز هنا لفتح نموذج مادة جديدة قبل أن يُجرَّب الرمز مطابقةً تامّة — وهذا
    // أقصر طريق إلى بطاقتين للمادة نفسها.
    if (open.value && results.value.length && canOfferCreate.value && highlighted.value === results.value.length) {
        createFromTerm();
        return;
    }

    if (open.value && results.value[highlighted.value] && highlighted.value > 0) {
        pick(results.value[highlighted.value]);
        return;
    }

    const material = await lookup(code);
    if (material) return;

    if (results.value.length) {
        pick(results.value[highlighted.value] ?? results.value[0]);
        return;
    }

    await search();
    if (results.value.length) {
        pick(results.value[0]);
        return;
    }

    if (props.allowCreate) {
        offerCreate(code);
        return;
    }

    say(`لا توجد نتيجة للرمز ${code}`, 'error');
}

/**
 * رمز طويل من أرقام فقط هو باركود مطبوع؛ ما عداه نصّ كتبه الموظّف بحثاً عن
 * اسم. التمييز يقرّر أين يهبط النصّ في نموذج المادة الجديدة: في خانة الباركود
 * أم في خانة الاسم، فلا يُعاد كتابته مرّة ثانية.
 */
function looksLikeBarcode(value) {
    const digits = toLatinDigits(value).trim();
    return /^\d{6,}$/.test(digits);
}

function offerCreate(value, { notFound = true } = {}) {
    const raw = String(value).trim();
    const isBarcode = looksLikeBarcode(raw);

    pending.value = isBarcode
        ? { barcode: toLatinDigits(raw).trim(), name: '' }
        : { barcode: '', name: raw };

    // «لم أجد» تُقال حين لا يوجد شيء فعلاً؛ من ضغط «مادة جديدة» وأمامه نتائج
    // يعرف أنّها موجودة، ويقصد غيرها.
    if (!notFound) return;

    say(
        isBarcode
            ? `لا توجد مادة بالباركود ${raw} — يمكن تسجيلها الآن.`
            : `لا توجد مادة باسم «${raw}» — يمكن تسجيلها الآن.`,
        'info'
    );
}

function emitCreate() {
    if (!pending.value) return;

    // الكاميرا تلتهم الإطار والمعالج؛ إبقاؤها تعمل خلف نافذة نموذج لا معنى له.
    stopCamera();
    emit('create', { ...pending.value });
}

/** «مادة جديدة «...»» من داخل قائمة النتائج: النصّ المكتوب هو الاسم المرشَّح. */
function createFromTerm() {
    const raw = term.value.trim();
    if (!raw) return;

    close();
    offerCreate(raw, { notFound: false });
    emitCreate();
}

/**
 * @returns {boolean} whether the code was dealt with — a material added, or a
 * code that belongs to an archived one, which must not be offered as "new".
 */
async function lookup(code) {
    try {
        const { material, archived, message } = await materialsApi.lookup(code, { detail: true });

        if (archived) {
            say(message, 'error');
            return true;
        }

        if (!material) return false;

        pick({
            kind: 'MATERIAL',
            id: material.id,
            name: material.name,
            code: material.barcode || material.sku,
            price: material[props.priceField],
            stock: Number(material.stock_quantity) || 0,
            unit_id: material.unit_id,
            raw: material,
        });

        return true;
    } catch {
        say('تعذّر قراءة الرمز', 'error');
        return false;
    }
}

function pick(row) {
    emit('pick', row);
    say(`أُضيف ${row.name}`, 'success');
    reset(true);
}

// ------------------------------------------------------------------ camera

async function toggleCamera() {
    if (scanning.value) {
        stopCamera();
        return;
    }

    try {
        detector = new window.BarcodeDetector({
            formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf'],
        });

        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
        });

        video.value.srcObject = stream;
        await video.value.play();

        scanning.value = true;
        say('وجّه الكاميرا نحو الباركود');
        tick();
    } catch {
        say('تعذّر فتح الكاميرا — تحقّق من الإذن', 'error');
        stopCamera();
    }
}

let lastCode = '';
let lastAt = 0;

async function tick() {
    if (!scanning.value || !video.value) return;

    try {
        const codes = await detector.detect(video.value);
        const value = codes[0]?.rawValue?.trim();

        // الكاميرا تقرأ الرمز نفسه عشرات المرات في الثانية؛ بلا هذه المهلة
        // تُضاف المادة مرّة لكل إطار.
        if (value && (value !== lastCode || Date.now() - lastAt > 2000)) {
            lastCode = value;
            lastAt = Date.now();
            const found = await lookup(value);
            if (!found) {
                if (props.allowCreate) offerCreate(value);
                else say(`باركود غير معروف: ${value}`, 'error');
            }
        }
    } catch {
        // إطار واحد تعذّرت قراءته ليس سبباً لإيقاف المسح.
    }

    rafId = requestAnimationFrame(tick);
}

function stopCamera() {
    scanning.value = false;
    if (rafId) cancelAnimationFrame(rafId);
    rafId = null;
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
    if (video.value) video.value.srcObject = null;
}

function focus() {
    input.value?.focus();
}

defineExpose({ focus, reset });

onBeforeUnmount(() => {
    clearTimeout(debounce);
    stopCamera();
});
</script>
