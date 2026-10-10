<template>
    <BaseModal
        :open="open"
        :title="doc?.is_repair ? 'ترحيل فاتورة صيانة' : 'ترحيل فاتورة بيع'"
        :subtitle="subtitle"
        max-width="max-w-xl"
        :close-on-overlay="!busy"
        :close-on-esc="!busy"
        @close="$emit('close')"
    >
        <!-- التحميل: الفاتورة تُقرأ من جديد قبل السؤال، فما يوافق عليه المستخدم
             هو حالتها الآن — وأرصدة المواد التي ستُخصم هي أرصدة هذه اللحظة. -->
        <div v-if="loading" class="space-y-3">
            <div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>
            <div class="h-32 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <div v-else-if="loadError" class="space-y-4">
            <p class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ loadError }}</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="$emit('close')">إغلاق</button>
                <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                    @click="load">إعادة المحاولة</button>
            </div>
        </div>

        <!-- فاتورة رحّلها زميل قبل ثوانٍ: الترحيل مرّة ثانية يخصم المخزون
             مرّتين ويُقيّد الدَّين مرّتين، فالسؤال يُسحب ويُستبدل بخبر عمّا جرى. -->
        <div v-else-if="doc && doc.status !== 'DRAFT'" class="space-y-4">
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ doc.status === 'POSTED'
                    ? `هذه الفاتورة مرحَّلة بالفعل${doc.invoice_no ? ` برقم ${doc.invoice_no}` : ''}، وخُصم مخزونها وقُيّدت على العميل.`
                    : 'هذه الفاتورة ملغاة، ولا يمكن ترحيلها.' }}
            </p>
            <div class="flex justify-end">
                <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                    @click="$emit('stale')">متابعة</button>
            </div>
        </div>

        <form v-else-if="doc" class="space-y-4" @submit.prevent="submit">
            <!-- ما يُرحَّل، برقم واحد كبير: الإجمالي هو ما سيُقيَّد على العميل، والباقي سياقه. -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold text-slate-500">يُباع إلى</p>
                <p class="mt-0.5 truncate text-sm font-bold text-slate-900">{{ doc.customer?.name || '—' }}</p>
                <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatMoney(doc.total) }}
                    <span class="text-sm font-medium text-slate-500">{{ CURRENCY }}</span>
                </p>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                    <span>{{ formatDate(doc.invoice_date) }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ itemsLabel }}</span>
                    <template v-if="Number(doc.discount_amount) > 0">
                        <span aria-hidden="true">·</span>
                        <span>بعد خصم {{ formatUsd(doc.discount_amount) }}</span>
                    </template>
                    <template v-if="doc.due_date">
                        <span aria-hidden="true">·</span>
                        <span>تستحقّ {{ formatDate(doc.due_date) }}</span>
                    </template>
                </p>
                <p v-if="doc.notes" class="mt-2 line-clamp-2 text-xs text-slate-500">{{ doc.notes }}</p>
            </div>

            <!-- الرصيد بعد الخصم لا قبله: الرقم الذي يهمّ من يقف عند الرفّ هو ما
                 سيصبح عليه، لا ما هو عليه الآن. الخدمات لا تمسّ المستودع فلا تُذكر هنا. -->
            <div v-if="stockLines.length" class="overflow-hidden rounded-xl border border-slate-200">
                <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500">
                    يخرج من المستودع
                </p>
                <ul class="max-h-56 divide-y divide-slate-100 overflow-y-auto">
                    <li v-for="row in stockLines" :key="row.id" class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate font-semibold text-slate-800">{{ row.name }}</span>
                            <span v-if="row.code" class="text-[11px] text-slate-400" dir="ltr">{{ row.code }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span
                                v-if="row.known"
                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
                                :class="row.short ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                            >{{ formatQty(row.before) }} ← {{ formatQty(row.after) }}</span>
                            <span class="font-semibold tabular-nums text-slate-900">−{{ formatQty(row.quantity) }}</span>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- سياسة المخزون يحكمها الخادم (inventory_sale_policy)، وقد تسمح
                 بالبيع على المكشوف. القول هنا أنّ الرصيد لا يكفي أصدق من ادّعاء
                 أنّ الترحيل سيُرفض، وأصدق من السكوت حتى يظهر رصيد سالب. -->
            <div v-if="shortages.length" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                <p class="font-semibold">
                    {{ shortages.length === 1
                        ? 'بند كميّته أكبر من رصيد المستودع:'
                        : `${shortages.length} بنود كميّاتها أكبر من أرصدة المستودع:` }}
                </p>
                <ul class="mt-1.5 space-y-0.5">
                    <li v-for="row in shortages" :key="row.id" class="flex items-center justify-between gap-3">
                        <span class="truncate">{{ row.name }}</span>
                        <span class="shrink-0 tabular-nums">
                            مطلوب {{ formatQty(row.quantity) }} · متوفّر {{ formatQty(row.before) }}
                        </span>
                    </li>
                </ul>
                <p class="mt-1.5">
                    يقبل النظام هذا أو يرفضه بحسب سياسة المخزون المضبوطة في الإعدادات.
                </p>
            </div>

            <!-- المال يُقبض حيث يُدفع: البيع عند الصندوق يُسدَّد في لحظته، وإرسال
                 البائع إلى شاشة السندات ليقول ما قاله للتوّ هو ما يجعل الدفتر
                 يحمل ديوناً سُدِّدت. الحقل اختياري: صفره يترك الفاتورة ذمّة. -->
            <div v-if="canCollect" class="rounded-xl border border-slate-200 p-3">
                <label class="mb-1 block text-sm font-medium text-slate-700" for="sale-collect">المبلغ المدفوع الآن</label>
                <div class="relative">
                    <input
                        id="sale-collect"
                        v-model="form.collect_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        :disabled="busy"
                        class="w-full rounded-lg border px-3 py-2.5 pe-14 text-base font-semibold tabular-nums focus:outline-none focus:ring-2"
                        :class="overCollecting
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                            : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100'"
                    />
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">{{ CURRENCY }}</span>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs transition"
                        :class="collectIsFull ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                        @click="form.collect_amount = dueNow.toFixed(2)"
                    >دفع كامل ({{ formatUsd(dueNow) }})</button>
                    <button
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs transition"
                        :class="collectAmount === 0 ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                        @click="form.collect_amount = '0'"
                    >على الحساب</button>
                </div>
                <p v-if="overCollecting" class="mt-1.5 text-xs text-red-600">
                    المبلغ أكبر من إجمالي الفاتورة ({{ formatUsd(dueNow) }}).
                </p>
                <p v-else-if="collectAmount > 0" class="mt-1.5 text-xs text-slate-500">
                    يتبقّى على {{ doc.customer?.name || 'العميل' }}
                    <strong class="tabular-nums text-slate-800">{{ formatUsd(leftover) }}</strong>
                    <template v-if="leftover <= 0"> — الفاتورة مسدَّدة بالكامل</template>
                </p>

                <MethodPicker v-if="collectAmount > 0" v-model="form.payment_method" class="mt-3" />
            </div>

            <!-- ماذا يترتّب على «نعم»: ترقيم نهائي، بضاعة تخرج، ودَين يُقيَّد. -->
            <ul class="space-y-1.5 text-xs text-slate-600">
                <li class="flex gap-2">
                    <span aria-hidden="true">🔢</span>
                    <span v-if="doc.invoice_no">تُرحَّل الفاتورة برقمها <strong dir="ltr">{{ doc.invoice_no }}</strong>.</span>
                    <span v-else>تأخذ الفاتورة رقمها النهائي على سلسلة {{ doc.is_repair ? 'فواتير الصيانة' : 'فواتير البيع' }}.</span>
                </li>
                <li v-if="stockLines.length" class="flex gap-2">
                    <span aria-hidden="true">📦</span>
                    <span>تُسجَّل حركة بيع خارجة لكل مادة، وينقص رصيد المستودع بمجموع {{ formatQty(totalQuantity) }}.</span>
                </li>
                <li v-else class="flex gap-2">
                    <span aria-hidden="true">🧰</span>
                    <span>لا مواد في الفاتورة — الخدمات وحدها لا تمسّ رصيد المستودع.</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true">📒</span>
                    <span>
                        يُقيَّد <strong class="tabular-nums">{{ formatUsd(doc.total) }}</strong>
                        ذمّةً على {{ doc.customer?.name || 'العميل' }}<template v-if="collectAmount <= 0">، يُحصَّل بسند قبض لاحق</template>.
                    </span>
                </li>
                <li v-if="collectAmount > 0" class="flex gap-2">
                    <span aria-hidden="true">💵</span>
                    <span>
                        يُسجَّل سند قبض بـ <strong class="tabular-nums">{{ formatUsd(collectAmount) }}</strong>
                        مخصَّصاً على هذه الفاتورة، فيبقى عليه {{ formatUsd(leftover) }}.
                    </span>
                </li>
                <li class="flex gap-2 text-amber-700">
                    <span aria-hidden="true">⚠️</span>
                    <span>لا يمكن التراجع بعدها إلا بإلغاء الفاتورة مع ذكر السبب، فتُعكس الحركات والقيد.</span>
                </li>
            </ul>

            <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

            <div class="flex gap-2 pt-1">
                <button type="button" :disabled="busy"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    @click="$emit('close')">{{ cancelLabel }}</button>
                <button ref="confirmButton" type="submit" :disabled="busy || overCollecting"
                    class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50">
                    {{ busy ? 'جارٍ الترحيل...' : 'تأكيد الترحيل' }}
                </button>
            </div>
        </form>
    </BaseModal>
</template>

<script setup>
import { BASE_SYMBOL } from '../../constants/currency';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import BaseModal from '../ui/BaseModal.vue';
import MethodPicker from '../repairs/PaymentMethodPicker.vue';
import { salesApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { apiMessage, formatDate, formatMoney, formatQty, formatUsd } from '../../utils/format';

const props = defineProps({
    open: Boolean,
    /** صفّ الجدول أو الفاتورة المحفوظة للتوّ؛ تفاصيلها تُقرأ من جديد عند الفتح. */
    invoice: { type: Object, default: null },
    /**
     * نصّ زرّ التراجع. الجدول يقول «تراجع»، أمّا الشاشة التي حفظت المسودة
     * قبل لحظة فتقول «لاحقاً»: الرفض هناك لا يُلغي شيئاً، والمسودة محفوظة.
     */
    cancelLabel: { type: String, default: 'تراجع' },
    /** ما كُتب في حقل «المدفوع الآن» على شاشة الفاتورة، يُملأ به الحقل هنا. */
    collect: { type: [String, Number], default: '' },
});

const emit = defineEmits(['close', 'posted', 'stale']);

const CURRENCY = BASE_SYMBOL;

const { can } = usePermissions();

// السند يُنشأ ويُعتمد في الطلب نفسه، فمن لا يملك الاثنتين لا يُعرض عليه الحقل
// أصلاً؛ الخادم يرفضه على أي حال.
const canCollect = can('payments.create') && can('payments.confirm');

const doc = ref(null);
const loading = ref(false);
const loadError = ref('');
const busy = ref(false);
const error = ref('');
const confirmButton = ref(null);
const form = reactive({ collect_amount: '', payment_method: 'CASH' });

const subtitle = computed(() => {
    const no = (doc.value ?? props.invoice)?.invoice_no;
    return no ? `الفاتورة ${no}` : 'مسودة — تُرقَّم عند الترحيل';
});

/** المواد وحدها: سطر الخدمة لا رصيد له ولا حركة مستودع. */
const stockLines = computed(() =>
    (doc.value?.items ?? [])
        .filter((item) => item.line_type === 'MATERIAL')
        .map((item) => {
            const before = Number(item.material?.stock_quantity);
            const known = Number.isFinite(before);
            const quantity = Number(item.quantity) || 0;

            return {
                id: item.id,
                name: item.material?.name || item.description || `#${item.material_id}`,
                code: item.material?.barcode || item.material?.sku || '',
                quantity,
                known,
                before: known ? before : 0,
                after: known ? before - quantity : 0,
                short: known && quantity > before,
            };
        })
);

const totalQuantity = computed(() => stockLines.value.reduce((sum, row) => sum + row.quantity, 0));

const shortages = computed(() => stockLines.value.filter((row) => row.short));

const itemsLabel = computed(() => {
    const n = (doc.value?.items ?? []).length;
    if (n === 1) return 'بند واحد';
    if (n === 2) return 'بندان';
    return n <= 10 ? `${n} بنود` : `${n} بنداً`;
});

/** المستحقّ الآن: المتبقي إن وُجد — فاتورة صيانة قد تحمل عربوناً سبقها. */
const dueNow = computed(() => {
    const remaining = Number(doc.value?.remaining);
    if (Number.isFinite(remaining) && remaining > 0) return remaining;
    return Number(doc.value?.total) || 0;
});

const collectAmount = computed(() => Math.max(0, Number(form.collect_amount) || 0));

const leftover = computed(() => Math.max(0, dueNow.value - collectAmount.value));

/** قرش واحد من فروق الفاصلة العشرية لا يستحقّ منع الترحيل. */
const overCollecting = computed(() => collectAmount.value - dueNow.value > 0.005);

const collectIsFull = computed(() =>
    collectAmount.value > 0 && Math.abs(collectAmount.value - dueNow.value) < 0.005
);

async function load() {
    const id = props.invoice?.id;
    if (!id) return;

    loading.value = true;
    loadError.value = '';
    error.value = '';
    try {
        const data = await salesApi.show(id);
        doc.value = data.data ?? data;
    } catch (e) {
        doc.value = null;
        loadError.value = apiMessage(e, 'تعذّر قراءة تفاصيل الفاتورة');
    } finally {
        loading.value = false;
    }

    // ما كُتب على شاشة الفاتورة يصل إلى هنا مبدئياً لا نهائياً: الزبون قد
    // يبدّل رأيه بين الحفظ والترحيل، والرقم الأخير هو ما يُقبض.
    const prefill = Number(props.collect);
    form.collect_amount = canCollect && Number.isFinite(prefill) && prefill > 0
        ? String(Math.min(prefill, dueNow.value))
        : '';

    // بعد رسم النموذج لا قبله: زر التأكيد لم يكن موجوداً وقت الهيكل العظمي،
    // ومن يعمل بلوحة المفاتيح يريد Enter جاهزاً لا بحثاً عن الزر.
    await nextTick();
    confirmButton.value?.focus();
}

async function submit() {
    if (busy.value || overCollecting.value) return;

    busy.value = true;
    error.value = '';
    try {
        const data = await salesApi.post(doc.value.id, collectAmount.value > 0
            ? { collect_amount: collectAmount.value.toFixed(2), payment_method: form.payment_method }
            : {});
        emit('posted', data.data ?? doc.value);
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر ترحيل الفاتورة');
    } finally {
        busy.value = false;
    }
}

watch(
    () => [props.open, props.invoice?.id],
    ([open]) => {
        if (open) {
            doc.value = null;
            busy.value = false;
            form.collect_amount = '';
            form.payment_method = 'CASH';
            load();
        }
    },
    { immediate: true }
);
</script>
