<template>
    <BaseModal
        :open="open"
        title="مادة جديدة"
        subtitle="تُسجَّل برصيد صفر — الفاتورة هي ما يُدخل الكمية إلى المستودع"
        max-width="max-w-2xl"
        :close-on-overlay="!saving"
        @close="$emit('close')"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <!-- الباركود الممسوح أوّلاً: هو ما بيد الموظّف، والاسم هو ما يكتبه. -->
            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="اسم المادة" required class="sm:col-span-2">
                    <input
                        ref="nameInput"
                        v-model.trim="form.name"
                        type="text"
                        required
                        maxlength="150"
                        placeholder="مثال: شاحن سريع 25 واط"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('name')"
                        @input="scheduleNameCheck"
                    />
                    <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                </Field>

                <Field label="الباركود">
                    <input
                        v-model.trim="form.barcode"
                        type="text"
                        dir="ltr"
                        inputmode="text"
                        autocomplete="off"
                        maxlength="64"
                        placeholder="امسح أو اكتب الباركود"
                        class="w-full rounded-lg border px-3 py-2 text-left text-sm tabular-nums focus:outline-none focus:ring-2"
                        :class="fieldClass('barcode')"
                        @keydown.enter.prevent
                        @input="scheduleBarcodeCheck"
                    />
                    <p v-if="errors.barcode" class="mt-1 text-xs text-red-600">{{ errors.barcode }}</p>
                    <p v-else class="mt-1 text-xs text-slate-400">يُترك فارغاً للبضاعة غير المرقّمة من مصنعها</p>
                </Field>

                <Field label="وحدة القياس" required>
                    <select
                        v-model="form.unit_id"
                        required
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('unit_id')"
                    >
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name_ar }}</option>
                    </select>
                </Field>
            </div>

            <!-- التسعير: التكلفة تأتي من الفاتورة، والبيع يُشتقّ منها بهامش
                 يتذكّره المتصفّح — الموظّف يصحّحه حين يختلف لا حين يوافق. -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <Field label="سعر التكلفة" required>
                        <input
                            v-model="form.cost_price"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="w-full rounded-lg border bg-white px-3 py-2 text-sm tabular-nums focus:outline-none focus:ring-2"
                            :class="fieldClass('cost_price')"
                            @input="applyMarkup"
                        />
                        <p v-if="errors.cost_price" class="mt-1 text-xs text-red-600">{{ errors.cost_price }}</p>
                    </Field>

                    <Field label="هامش الربح ٪">
                        <input
                            v-model="markup"
                            type="number"
                            step="1"
                            min="0"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm tabular-nums focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            @input="applyMarkup"
                        />
                    </Field>

                    <Field label="سعر البيع" required>
                        <input
                            v-model="form.sale_price"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="w-full rounded-lg border bg-white px-3 py-2 text-sm tabular-nums focus:outline-none focus:ring-2"
                            :class="fieldClass('sale_price')"
                            @input="onSalePriceTyped"
                        />
                        <p v-if="errors.sale_price" class="mt-1 text-xs text-red-600">{{ errors.sale_price }}</p>
                    </Field>
                </div>

                <p class="mt-3 text-xs" :class="profit.tone">{{ profit.text }}</p>
            </div>

            <!-- ما لا يُسأل عنه عند الاستلام يبقى مطوياً: فتحه خيار، لا خطوة. -->
            <details class="rounded-xl border border-slate-200 px-4 py-3">
                <summary class="cursor-pointer select-none text-sm font-medium text-slate-700">
                    تفاصيل إضافية
                    <span class="font-normal text-slate-400">— الصنف، الرمز، حدّ إعادة الطلب</span>
                </summary>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <Field label="الصنف">
                        <select
                            v-model="form.category_id"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        >
                            <option :value="null">— بدون صنف —</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </Field>

                    <Field label="رمز الصنف (SKU)">
                        <input
                            v-model.trim="form.sku"
                            type="text"
                            dir="ltr"
                            maxlength="50"
                            placeholder="يُولَّد تلقائياً"
                            class="w-full rounded-lg border px-3 py-2 text-left text-sm focus:outline-none focus:ring-2"
                            :class="fieldClass('sku')"
                        />
                        <p v-if="errors.sku" class="mt-1 text-xs text-red-600">{{ errors.sku }}</p>
                    </Field>

                    <Field label="حدّ إعادة الطلب">
                        <input
                            v-model="form.min_stock"
                            type="number"
                            step="0.001"
                            min="0"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        />
                    </Field>
                </div>
            </details>

            <!-- مادة موجودة سلفاً: عرضها الآن أرخص من دمج بطاقتين لاحقاً. -->
            <div v-if="matches.length" class="rounded-xl border border-amber-300 bg-amber-50 p-3">
                <p class="text-xs font-semibold text-amber-900">
                    {{ barcodeMatch ? 'هذا الباركود مسجَّل على مادة موجودة:' : 'مواد مسجَّلة باسم مشابه:' }}
                </p>
                <ul class="mt-2 space-y-1">
                    <li v-for="m in matches" :key="m.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-right text-sm transition hover:bg-blue-50"
                            @click="$emit('use-existing', m)"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-slate-800">{{ m.name }}</span>
                                <span class="block truncate text-xs text-slate-400" dir="ltr">{{ m.barcode || m.sku }}</span>
                            </span>
                            <span class="shrink-0 text-xs tabular-nums text-slate-500">{{ formatMoney(m.cost_price) }}</span>
                            <span class="shrink-0 text-xs font-medium text-blue-600">استخدامها</span>
                        </button>
                    </li>
                </ul>
                <p class="mt-2 text-[11px] text-amber-800">
                    {{ barcodeMatch
                        ? 'لا يمكن تسجيل الباركود على مادتين — اختر الموجودة أو غيّر الرمز.'
                        : 'إن كانت إحداها هي نفسها، استخدمها بدل إنشاء بطاقة ثانية لها.' }}
                </p>
            </div>

            <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <p class="text-xs text-slate-400">تُضاف إلى الفاتورة فور الحفظ</p>
                <div class="flex gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="$emit('close')"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="saving || barcodeMatch"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-60"
                    >
                        {{ saving ? 'جارٍ الحفظ...' : 'حفظ وإضافة للفاتورة' }}
                    </button>
                </div>
            </div>
        </form>
    </BaseModal>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import BaseModal from '../ui/BaseModal.vue';
import Field from '../ui/FormField.vue';
import { materialsApi, materialCategoriesApi, serviceUnitsApi } from '../../services/api';
import { apiFieldErrors, apiMessage, formatMoney } from '../../utils/format';

const props = defineProps({
    open: { type: Boolean, default: false },
    /**
     * ما عُرف عن المادة قبل فتح النافذة: الباركود الممسوح الذي لم يُطابق شيئاً،
     * أو نصّ البحث الذي لم يُرجع نتيجة، أو تكلفة السطر الذي فُتحت منه.
     */
    prefill: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'created', 'use-existing']);

const MARKUP_KEY = 'material_quick_markup';
const DEFAULT_MARKUP = 25;

const nameInput = ref(null);
const units = ref([]);
const categories = ref([]);
const saving = ref(false);
const error = ref('');
const errors = ref({});
const matches = ref([]);
const barcodeMatch = ref(false);
const markup = ref(readMarkup());

let nameTimer = null;
let barcodeTimer = null;
let optionsLoaded = false;

const form = reactive({
    name: '',
    sku: '',
    barcode: '',
    category_id: null,
    unit_id: null,
    cost_price: '0.00',
    sale_price: '0.00',
    min_stock: '0',
});

function fieldClass(field) {
    return errors.value[field]
        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
}

// --------------------------------------------------------------- التسعير

/** الهامش المستخدَم آخر مرّة: محلّ يبيع بهامش ثابت لا يُعيد كتابته كل مرّة. */
function readMarkup() {
    try {
        const stored = Number(localStorage.getItem(MARKUP_KEY));
        return Number.isFinite(stored) && stored >= 0 ? stored : DEFAULT_MARKUP;
    } catch {
        return DEFAULT_MARKUP;
    }
}

function rememberMarkup(value) {
    try {
        localStorage.setItem(MARKUP_KEY, String(value));
    } catch {
        /* وضع التصفّح الخاص يمنع الكتابة — الهامش يبقى صالحاً لهذه الجلسة. */
    }
}

function applyMarkup() {
    const cost = Number(form.cost_price) || 0;
    const rate = Number(markup.value) || 0;
    form.sale_price = String(Math.round(cost * (1 + rate / 100) * 100) / 100);
    rememberMarkup(rate);
}

/** الكتابة في سعر البيع تفوز على الهامش، والهامش يُعاد حسابه ليطابقها. */
function onSalePriceTyped() {
    const cost = Number(form.cost_price) || 0;
    const sale = Number(form.sale_price) || 0;
    if (cost <= 0) return;

    const rate = Math.round(((sale - cost) / cost) * 100);
    markup.value = String(rate);
    if (rate >= 0) rememberMarkup(rate);
}

const profit = computed(() => {
    const cost = Number(form.cost_price) || 0;
    const sale = Number(form.sale_price) || 0;

    if (cost <= 0 || sale <= 0) return { text: 'أدخل التكلفة وسعر البيع لحساب الربح.', tone: 'text-slate-500' };
    if (sale < cost) return { text: `سعر البيع أقلّ من التكلفة بـ ${formatMoney(cost - sale)} — خسارة على كل قطعة.`, tone: 'text-red-700 font-medium' };
    if (sale === cost) return { text: 'سعر البيع يساوي التكلفة — بلا ربح.', tone: 'text-amber-700' };

    return {
        text: `ربح ${formatMoney(sale - cost)} على القطعة (${Math.round(((sale - cost) / cost) * 100)}٪).`,
        tone: 'text-emerald-700',
    };
});

// ------------------------------------------------------------- التكرار

function scheduleNameCheck() {
    errors.value = { ...errors.value, name: undefined };
    if (barcodeMatch.value) return;

    clearTimeout(nameTimer);
    nameTimer = setTimeout(checkName, 400);
}

async function checkName() {
    const query = form.name.trim();
    if (query.length < 3) {
        if (!barcodeMatch.value) matches.value = [];
        return;
    }

    try {
        const { data } = await materialsApi.index({ search: query, per_page: 4 });
        if (barcodeMatch.value) return;
        matches.value = data ?? [];
    } catch {
        /* البحث عن شبيه خدمة إضافية؛ تعذّره لا يمنع الحفظ. */
    }
}

/**
 * الباركود يُفحَص وحده لأن جوابه قاطع: الرمز إمّا حرّ أو مأخوذ. كشفه هنا يوفّر
 * رحلة كاملة إلى الخادم تعود بخطأ 422 بعد ملء كل الحقول.
 */
function scheduleBarcodeCheck() {
    errors.value = { ...errors.value, barcode: undefined };
    clearTimeout(barcodeTimer);
    barcodeTimer = setTimeout(checkBarcode, 400);
}

async function checkBarcode() {
    const code = form.barcode.trim();

    if (!code) {
        barcodeMatch.value = false;
        matches.value = [];
        checkName();
        return;
    }

    try {
        const material = await materialsApi.lookup(code);
        barcodeMatch.value = Boolean(material);
        matches.value = material ? [material] : [];
        if (!material) checkName();
    } catch {
        barcodeMatch.value = false;
    }
}

// -------------------------------------------------------------- الحفظ

async function submit() {
    error.value = '';
    errors.value = {};
    saving.value = true;

    try {
        const { data } = await materialsApi.store({
            name: form.name,
            // الفارغ يعني «ولّد لي رمزاً»؛ الخادم يتكفّل بالتسلسل.
            sku: form.sku || null,
            barcode: form.barcode || null,
            category_id: form.category_id || null,
            unit_id: form.unit_id,
            cost_price: form.cost_price,
            sale_price: form.sale_price,
            min_stock: form.min_stock || '0',
            is_active: true,
        });

        emit('created', data);
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر حفظ المادة');
        errors.value = apiFieldErrors(e);
    } finally {
        saving.value = false;
    }
}

// -------------------------------------------------------------- الفتح

async function loadOptions() {
    if (optionsLoaded) return;

    try {
        [categories.value, units.value] = await Promise.all([
            materialCategoriesApi.options(),
            serviceUnitsApi.options(),
        ]);
        optionsLoaded = true;
    } catch {
        error.value = 'تعذّر تحميل الوحدات والأصناف';
    }
}

/** «قطعة» هي وحدة أغلب ما يُشترى في محلّ قطع؛ البقية استثناء يُختار. */
function defaultUnitId() {
    const list = units.value;
    if (!list.length) return null;

    const piece = list.find((u) => u.name_ar === 'قطعة') ?? list.find((u) => u.category === 'COUNT');
    return (piece ?? list[0]).id;
}

watch(() => props.open, async (open) => {
    if (!open) return;

    error.value = '';
    errors.value = {};
    matches.value = [];
    barcodeMatch.value = false;
    markup.value = readMarkup();

    form.name = props.prefill.name ?? '';
    form.sku = '';
    form.barcode = props.prefill.barcode ?? '';
    form.category_id = null;
    form.cost_price = props.prefill.cost_price != null ? String(props.prefill.cost_price) : '0.00';
    form.min_stock = '0';
    applyMarkup();

    await loadOptions();
    form.unit_id = defaultUnitId();

    // باركود جاء من ماسح لم يُطابق شيئاً: يُفحَص فوراً لأن الموظّف لم يكتبه.
    if (form.barcode) checkBarcode();
    if (form.name) checkName();

    await nextTick();
    nameInput.value?.focus();
    nameInput.value?.select();
});

onBeforeUnmount(() => {
    clearTimeout(nameTimer);
    clearTimeout(barcodeTimer);
});
</script>
