<template>
    <BaseModal
        :open="open"
        title="ترحيل فاتورة شراء"
        :subtitle="subtitle"
        max-width="max-w-xl"
        :close-on-overlay="!busy"
        :close-on-esc="!busy"
        @close="$emit('close')"
    >
        <!-- التحميل: الفاتورة تُقرأ من جديد قبل السؤال، فما يوافق عليه المستخدم
             هو حالتها الآن لا ما كان في الجدول قبل دقائق. -->
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

        <!-- فاتورة رحّلها زميل قبل ثوانٍ: الترحيل مرّة ثانية يُدخل البضاعة
             مرّتين، فالسؤال يُسحب ويُستبدل بخبر عمّا جرى. -->
        <div v-else-if="doc && doc.status !== 'DRAFT'" class="space-y-4">
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ doc.status === 'POSTED'
                    ? `هذه الفاتورة مرحَّلة بالفعل${doc.invoice_no ? ` برقم ${doc.invoice_no}` : ''}، وبضاعتها دخلت المستودع.`
                    : 'هذه الفاتورة ملغاة، ولا يمكن ترحيلها.' }}
            </p>
            <div class="flex justify-end">
                <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                    @click="$emit('stale')">تحديث القائمة</button>
            </div>
        </div>

        <form v-else-if="doc" class="space-y-4" @submit.prevent="submit">
            <!-- ما يُرحَّل، برقم واحد كبير: الإجمالي هو ما سيُقيَّد ديناً، والباقي سياقه. -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold text-slate-500">يُشترى من</p>
                <p class="mt-0.5 truncate text-sm font-bold text-slate-900">{{ doc.supplier?.name || '—' }}</p>
                <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatMoney(doc.total) }}
                    <span class="text-sm font-medium text-slate-500">{{ CURRENCY }}</span>
                </p>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                    <span>{{ formatDate(doc.purchase_date) }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ itemsLabel }}</span>
                    <template v-if="Number(doc.discount_amount) > 0">
                        <span aria-hidden="true">·</span>
                        <span>بعد خصم {{ formatMoney(doc.discount_amount) }}</span>
                    </template>
                    <template v-if="doc.due_date">
                        <span aria-hidden="true">·</span>
                        <span>تستحقّ {{ formatDate(doc.due_date) }}</span>
                    </template>
                </p>
                <p v-if="doc.notes" class="mt-2 line-clamp-2 text-xs text-slate-500">{{ doc.notes }}</p>
            </div>

            <!-- الرصيد بعد الترحيل لا قبله: الرقم الذي يهمّ أمين المستودع هو ما
                 سيصبح عليه الرفّ، لا ما هو عليه الآن. -->
            <div v-if="lines.length" class="overflow-hidden rounded-xl border border-slate-200">
                <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500">
                    يدخل المستودع
                </p>
                <ul class="max-h-56 divide-y divide-slate-100 overflow-y-auto">
                    <li v-for="row in lines" :key="row.id" class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate font-semibold text-slate-800">{{ row.name }}</span>
                            <span v-if="row.code" class="text-[11px] text-slate-400" dir="ltr">{{ row.code }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span
                                v-if="row.known"
                                class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-emerald-700"
                            >{{ formatQty(row.before) }} ← {{ formatQty(row.after) }}</span>
                            <span class="font-semibold tabular-nums text-slate-900">+{{ formatQty(row.quantity) }}</span>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- الترحيل يحرّك المخزون ولا يمسّ بطاقة المادة. قولها هنا يمنع
                 اكتشاف تكلفة قديمة في تقرير أرباح بعد شهر. -->
            <div v-if="drifted.length" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
                <p class="font-semibold">
                    {{ drifted.length === 1
                        ? 'بند اشتُري بسعر يختلف عن التكلفة المسجّلة على بطاقته:'
                        : `${drifted.length} بنود اشتُريت بأسعار تختلف عن التكلفة المسجّلة على بطاقاتها:` }}
                </p>
                <ul class="mt-1.5 space-y-0.5">
                    <li v-for="row in drifted" :key="row.id" class="flex items-center justify-between gap-3">
                        <span class="truncate">{{ row.name }}</span>
                        <span class="shrink-0 tabular-nums">
                            {{ formatMoney(row.cardCost) }} ← {{ formatMoney(row.unitPrice) }}
                        </span>
                    </li>
                </ul>
                <p class="mt-1.5">
                    الترحيل لا يغيّر التكلفة على بطاقة المادة — تُعدَّل من شاشة المواد إن كان السعر الجديد هو الدائم.
                </p>
            </div>

            <!-- ماذا يترتّب على «نعم»: ترقيم نهائي، بضاعة تدخل، ودَين يُقيَّد. -->
            <ul class="space-y-1.5 text-xs text-slate-600">
                <li class="flex gap-2">
                    <span aria-hidden="true">🔢</span>
                    <span v-if="doc.invoice_no">تُرحَّل الفاتورة برقمها <strong dir="ltr">{{ doc.invoice_no }}</strong>.</span>
                    <span v-else>تأخذ الفاتورة رقمها النهائي على سلسلة فواتير الشراء.</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true">📦</span>
                    <span>تُسجَّل حركة شراء داخلة لكل بند، ويزيد رصيد المستودع بمجموع {{ formatQty(totalQuantity) }}.</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true">📒</span>
                    <span>
                        يُقيَّد <strong class="tabular-nums">{{ formatMoney(doc.total) }} {{ CURRENCY }}</strong>
                        ديناً على المحل لصالح {{ doc.supplier?.name || 'المورد' }}، يُسدَّد بسند دفع لاحق.
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
                <button ref="confirmButton" type="submit" :disabled="busy"
                    class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50">
                    {{ busy ? 'جارٍ الترحيل...' : 'تأكيد الترحيل' }}
                </button>
            </div>
        </form>
    </BaseModal>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import BaseModal from '../ui/BaseModal.vue';
import { purchasesApi } from '../../services/api';
import { apiMessage, formatDate, formatMoney, formatQty } from '../../utils/format';

const props = defineProps({
    open: Boolean,
    /** صفّ الجدول أو الفاتورة المحفوظة للتوّ؛ تفاصيلها تُقرأ من جديد عند الفتح. */
    invoice: { type: Object, default: null },
    /**
     * نصّ زرّ التراجع. الجدول يقول «تراجع»، أمّا الشاشة التي حفظت المسودة
     * قبل لحظة فتقول «لاحقاً»: الرفض هناك لا يُلغي شيئاً، والمسودة محفوظة.
     */
    cancelLabel: { type: String, default: 'تراجع' },
});

const emit = defineEmits(['close', 'posted', 'stale']);

const CURRENCY = 'ل.س';

const doc = ref(null);
const loading = ref(false);
const loadError = ref('');
const busy = ref(false);
const error = ref('');
const confirmButton = ref(null);

const subtitle = computed(() => {
    const no = (doc.value ?? props.invoice)?.invoice_no;
    return no ? `الفاتورة ${no}` : 'مسودة — تُرقَّم عند الترحيل';
});

const lines = computed(() =>
    (doc.value?.items ?? []).map((item) => {
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
            after: known ? before + quantity : 0,
            cardCost: Number(item.material?.cost_price) || 0,
            unitPrice: Number(item.unit_price) || 0,
        };
    })
);

const totalQuantity = computed(() => lines.value.reduce((sum, row) => sum + row.quantity, 0));

const itemsLabel = computed(() => {
    const n = lines.value.length;
    if (n === 1) return 'بند واحد';
    if (n === 2) return 'بندان';
    return n <= 10 ? `${n} بنود` : `${n} بنداً`;
});

/**
 * فرق يُذكر بين سعر الفاتورة والتكلفة المسجّلة.
 *
 * العتبة تمنع ضجيجاً من فروق القروش: تغيّر سعر بنسبة تحت الخمسة بالمئة لا
 * يستحقّ تحذيراً على شاشة قرار.
 */
const drifted = computed(() =>
    lines.value.filter((row) => {
        if (row.cardCost <= 0 || row.unitPrice <= 0) return false;
        return Math.abs(row.unitPrice - row.cardCost) / row.cardCost >= 0.05;
    })
);

async function load() {
    const id = props.invoice?.id;
    if (!id) return;

    loading.value = true;
    loadError.value = '';
    error.value = '';
    try {
        const data = await purchasesApi.show(id);
        doc.value = data.data ?? data;
    } catch (e) {
        doc.value = null;
        loadError.value = apiMessage(e, 'تعذّر قراءة تفاصيل الفاتورة');
    } finally {
        loading.value = false;
    }

    // بعد رسم النموذج لا قبله: زر التأكيد لم يكن موجوداً وقت الهيكل العظمي،
    // ومن يعمل بلوحة المفاتيح يريد Enter جاهزاً لا بحثاً عن الزر.
    await nextTick();
    confirmButton.value?.focus();
}

async function submit() {
    if (busy.value) return;

    busy.value = true;
    error.value = '';
    try {
        const data = await purchasesApi.post(doc.value.id);
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
            load();
        }
    },
    { immediate: true }
);
</script>
