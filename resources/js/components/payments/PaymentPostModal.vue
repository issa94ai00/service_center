<template>
    <BaseModal
        :open="open"
        :title="title"
        :subtitle="subtitle"
        max-width="max-w-xl"
        :close-on-overlay="!busy"
        :close-on-esc="!busy"
        @close="$emit('close')"
    >
        <!-- التحميل: السند يُقرأ من جديد قبل السؤال، فما يوافق عليه المستخدم
             هو حالة السند الآن لا ما كان في الجدول قبل دقائق. -->
        <div v-if="loading" class="space-y-3">
            <div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>
            <div class="h-28 animate-pulse rounded-xl bg-slate-100"></div>
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

        <!-- سند اعتمده زميل قبل ثوانٍ: الاعتماد مرة ثانية يعيد قيده، فالسؤال
             يُسحب ويُستبدل بخبر عمّا جرى. -->
        <div v-else-if="doc && doc.status !== 'DRAFT'" class="space-y-4">
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ doc.status === 'POSTED'
                    ? `هذه الدفعة معتمدة بالفعل${doc.reference_no ? ` برقم ${doc.reference_no}` : ''}، ولا تحتاج اعتماداً آخر.`
                    : 'هذه الدفعة ملغاة، ولا يمكن اعتمادها.' }}
            </p>
            <div class="flex justify-end">
                <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                    @click="$emit('stale')">تحديث القائمة</button>
            </div>
        </div>

        <form v-else-if="doc" class="space-y-4" @submit.prevent="submit">
            <!-- ما يُعتمد، برقم واحد كبير: المبلغ هو القرار، والباقي سياقه. -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold text-slate-500">{{ isIncoming ? 'يُقبض من' : 'يُدفع إلى' }}</p>
                <p class="mt-0.5 truncate text-sm font-bold text-slate-900">{{ partyName || '—' }}</p>
                <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatMoney(doc.amount) }}
                    <span class="text-sm font-medium text-slate-500">{{ CURRENCY }}</span>
                </p>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                    <span>{{ methodLabel(doc.payment_method) }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ formatDate(doc.payment_date) }}</span>
                    <template v-if="doc.repair?.receipt_no">
                        <span aria-hidden="true">·</span>
                        <span class="rounded bg-violet-100 px-1.5 py-0.5 font-semibold text-violet-700" dir="ltr">
                            {{ doc.repair.receipt_no }}
                        </span>
                    </template>
                </p>
                <p v-if="doc.notes" class="mt-2 line-clamp-2 text-xs text-slate-500">{{ doc.notes }}</p>
            </div>

            <!-- الفواتير التي ستتسدّد، ومتبقّي كلٍّ منها بعد الاعتماد لا قبله:
                 الرقم الذي يهم الصندوق هو ما سيصبح عليه الحال. -->
            <div v-if="allocations.length" class="overflow-hidden rounded-xl border border-slate-200">
                <p class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500">
                    تُسدَّد {{ allocationsLabel }}
                </p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="row in allocations" :key="row.id" class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate font-semibold text-slate-800" dir="ltr">{{ row.invoice_no || '—' }}</span>
                            <span class="text-[11px] text-slate-400">{{ formatDate(row.invoice_date) }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <!-- فاتورة حُذفت أو تعذّر قراءة رصيدها: لا شارة خير من
                                 شارة تقول «يبقى ٠٫٠٠» على فاتورة لا يُعرف رصيدها. -->
                            <span
                                v-if="hasRemaining(row)"
                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                :class="settlesFully(row) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'"
                            >{{ settlesFully(row) ? 'تُسدَّد بالكامل' : `يبقى ${formatMoney(remainingAfter(row))}` }}</span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ formatMoney(row.amount) }}</span>
                        </span>
                    </li>
                </ul>
                <div v-if="allocations.length > 1"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50/70 px-3 py-2 text-xs">
                    <span class="text-slate-500">مجموع المخصَّص</span>
                    <span class="font-bold tabular-nums text-slate-900">{{ formatMoney(allocatedCents / 100) }}</span>
                </div>
            </div>

            <!-- المبلغ غير المخصَّص يبقى رصيداً؛ قوله هنا يمنع سؤال «أين ذهب». -->
            <p v-if="onAccountCents > 0" class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
                <template v-if="allocations.length">
                    يبقى <strong class="tabular-nums">{{ formatMoney(onAccountCents / 100) }} {{ CURRENCY }}</strong>
                    رصيداً {{ isIncoming ? 'لصالح العميل' : 'لدى المورد' }} يُخصَّص على فاتورة لاحقة.
                </template>
                <template v-else>
                    دفعة على الحساب: لا تُسدِّد فاتورة بعينها، ويُقيَّد كامل المبلغ رصيداً
                    {{ isIncoming ? 'لصالح العميل' : 'لدى المورد' }} يُخصَّص على فاتورة لاحقة.
                </template>
            </p>

            <!-- الخادم يرفض سنداً مخصَّصاً لا يساوي مجموع تخصيصه. رفضٌ بعد
                 الضغط يبدو عطلاً، فالشرط يُقال قبله مع طريق الإصلاح. -->
            <div v-if="mismatch" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-700">
                <p>
                    المخصَّص على الفواتير <strong class="tabular-nums">{{ formatMoney(allocatedCents / 100) }}</strong>
                    و{{ overAllocated ? 'يزيد عن' : 'يقلّ عن' }} مبلغ السند
                    <strong class="tabular-nums">{{ formatMoney(doc.amount) }}</strong>
                    بفارق <strong class="tabular-nums">{{ formatMoney(Math.abs(onAccountCents) / 100) }}</strong>.
                </p>
                <p class="mt-1">لا يمكن الاعتماد قبل أن يتطابق الطرفان.</p>
                <button type="button" class="mt-2 rounded-lg border border-red-300 bg-white px-2.5 py-1 font-medium text-red-700 transition hover:bg-red-100"
                    @click="editDraft">✎ تعديل المسودة</button>
            </div>

            <!-- ماذا يترتّب على «نعم»: ترقيم نهائي وقيد على الحساب ورجوعٌ لا
                 يكون إلا بإلغاء مُعلَّل. -->
            <ul class="space-y-1.5 text-xs text-slate-600">
                <li class="flex gap-2">
                    <span aria-hidden="true">🔢</span>
                    <span v-if="doc.reference_no">يُعتمد السند برقمه <strong dir="ltr">{{ doc.reference_no }}</strong>.</span>
                    <span v-else>يأخذ السند رقمه النهائي على سلسلة {{ doc.repair_id ? 'سندات الصيانة' : 'سندات الصندوق' }}.</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true">📒</span>
                    <span>يُقيَّد المبلغ كاملاً في حساب {{ isIncoming ? 'العميل' : 'المورد' }}.</span>
                </li>
                <li class="flex gap-2 text-amber-700">
                    <span aria-hidden="true">⚠️</span>
                    <span>لا يمكن التراجع بعدها إلا بإلغاء السند مع ذكر السبب.</span>
                </li>
            </ul>

            <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

            <div class="flex gap-2 pt-1">
                <button type="button" :disabled="busy"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    @click="$emit('close')">تراجع</button>
                <button ref="confirmButton" type="submit" :disabled="busy || mismatch"
                    class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50">
                    {{ busy ? 'جارٍ الاعتماد...' : 'تأكيد الاعتماد' }}
                </button>
            </div>
        </form>
    </BaseModal>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import BaseModal from '../ui/BaseModal.vue';
import { paymentsApi } from '../../services/api';
import { apiMessage, formatDate, formatMoney } from '../../utils/format';
import { paymentMethodLabel } from '../../constants/paymentMethods';

const props = defineProps({
    open: Boolean,
    /** صفّ الجدول؛ تفاصيله تُقرأ من جديد عند الفتح. */
    payment: { type: Object, default: null },
});

const emit = defineEmits(['close', 'posted', 'stale']);

const CURRENCY = 'ل.س';

const router = useRouter();
const doc = ref(null);
const loading = ref(false);
const loadError = ref('');
const busy = ref(false);
const error = ref('');
const confirmButton = ref(null);

const isIncoming = computed(() => (doc.value ?? props.payment)?.payment_type === 'CUSTOMER');

const title = computed(() => (isIncoming.value ? 'اعتماد سند قبض' : 'اعتماد سند دفع'));

const subtitle = computed(() => {
    const ref_no = (doc.value ?? props.payment)?.reference_no;
    return ref_no ? `السند ${ref_no}` : 'مسودة — تُرقَّم عند الاعتماد';
});

const partyName = computed(() => {
    const row = doc.value ?? props.payment;
    return row?.customer?.name || row?.supplier?.name || row?.technician?.name || '';
});

const allocations = computed(() => doc.value?.allocations ?? []);

const allocatedCents = computed(() =>
    allocations.value.reduce((sum, row) => sum + toCents(row.amount), 0)
);

/** ما لم تطلبه فاتورة: موجبٌ رصيدٌ على الحساب، وسالبٌ تخصيصٌ زائد يرفضه الخادم. */
const onAccountCents = computed(() => toCents(doc.value?.amount) - allocatedCents.value);

const mismatch = computed(() => allocations.value.length > 0 && onAccountCents.value !== 0);
const overAllocated = computed(() => onAccountCents.value < 0);

const allocationsLabel = computed(() => {
    const n = allocations.value.length;
    if (n === 1) return 'فاتورة واحدة';
    if (n === 2) return 'فاتورتان';
    return n <= 10 ? `${n} فواتير` : `${n} فاتورة`;
});

function toCents(value) {
    return Math.round((Number(value) || 0) * 100);
}

function methodLabel(value) {
    return paymentMethodLabel(value);
}

function remainingAfter(row) {
    return (toCents(row.remaining) - toCents(row.amount)) / 100;
}

function hasRemaining(row) {
    return row.remaining !== null && row.remaining !== undefined && row.remaining !== '';
}

function settlesFully(row) {
    return toCents(row.remaining) === toCents(row.amount);
}

async function load() {
    const id = props.payment?.id;
    if (!id) return;

    loading.value = true;
    loadError.value = '';
    error.value = '';
    try {
        const data = await paymentsApi.show(id);
        doc.value = data.data ?? data;
    } catch (e) {
        doc.value = null;
        loadError.value = apiMessage(e, 'تعذّر قراءة تفاصيل الدفعة');
    } finally {
        loading.value = false;
    }

    // بعد رسم النموذج لا قبله: زر التأكيد لم يكن موجوداً وقت الهيكل العظمي،
    // ومن يعمل بلوحة المفاتيح يريد Enter جاهزاً لا بحثاً عن الزر.
    await nextTick();
    confirmButton.value?.focus();
}

function editDraft() {
    const id = doc.value?.id ?? props.payment?.id;
    emit('close');
    if (id) router.push({ name: 'payments.edit', params: { id } });
}

async function submit() {
    if (busy.value || mismatch.value) return;

    busy.value = true;
    error.value = '';
    try {
        const data = await paymentsApi.post(doc.value.id);
        emit('posted', data.data ?? doc.value);
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر اعتماد الدفعة');
    } finally {
        busy.value = false;
    }
}

watch(
    () => [props.open, props.payment?.id],
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
