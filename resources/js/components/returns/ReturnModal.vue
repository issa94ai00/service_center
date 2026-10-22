<template>
    <BaseModal
        :open="open"
        :title="copy.title"
        :subtitle="subtitle"
        max-width="max-w-3xl"
        :close-on-overlay="!busy && !dirty"
        :close-on-esc="!busy"
        @close="$emit('close')"
    >
        <div v-if="loading" class="space-y-3">
            <div class="h-16 animate-pulse rounded-xl bg-slate-100"></div>
            <div class="h-40 animate-pulse rounded-xl bg-slate-100"></div>
            <div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>
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

        <!-- فاتورة لا تقبل مرتجعاً (مسودة، ملغاة، فاتورة صيانة): يُقال السبب بدل
             نموذج يُملأ ثم يُرفض عند الحفظ. -->
        <div v-else-if="ctx && ctx.blocked_reason" class="space-y-4">
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ ctx.blocked_reason }}</p>
            <div class="flex justify-end">
                <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                    @click="$emit('close')">حسناً</button>
            </div>
        </div>

        <form v-else-if="ctx" class="space-y-5" @submit.prevent="submit">
            <!-- الفاتورة في سطر: ما يهمّ من يقف أمام العميل هو كم دُفع وكم بقي،
                 لأنّ ذلك ما يقرّر هل يردّ نقداً أم يُنقص الدَّين. -->
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div v-for="stat in invoiceStats" :key="stat.label" class="rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2">
                    <p class="text-[11px] font-semibold text-slate-500">{{ stat.label }}</p>
                    <p class="mt-0.5 text-sm font-bold tabular-nums" :class="stat.tone || 'text-slate-900'">{{ formatUsd(stat.value) }}</p>
                </div>
            </div>

            <!-- ------------------------------------------------------ the lines -->
            <section class="overflow-hidden rounded-xl border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-xs font-semibold text-slate-600">
                        البنود المرتجعة
                        <span v-if="selectedCount" class="ms-1 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold text-blue-700">{{ selectedCount }}</span>
                    </p>
                    <div class="flex gap-1.5">
                        <button type="button" :disabled="!anyReturnable || busy"
                            class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-blue-400 hover:text-blue-600 disabled:opacity-40"
                            @click="returnAll">إرجاع الكل</button>
                        <button type="button" :disabled="!selectedCount || busy"
                            class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-slate-500 disabled:opacity-40"
                            @click="clearAll">مسح</button>
                    </div>
                </div>

                <ul class="max-h-[22rem] divide-y divide-slate-100 overflow-y-auto">
                    <li v-for="line in lines" :key="line.id"
                        class="flex flex-col gap-2 px-3 py-3 transition sm:flex-row sm:items-center sm:gap-4"
                        :class="[
                            qty(line) > 0 ? 'bg-blue-50/60' : '',
                            line.exhausted ? 'opacity-60' : '',
                        ]">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span v-if="kind === 'sales'" class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                    :class="line.line_type === 'SERVICE' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'">
                                    {{ line.line_type === 'SERVICE' ? 'خدمة' : 'مادة' }}
                                </span>
                                <span class="truncate text-sm font-semibold text-slate-900">{{ line.name }}</span>
                            </div>
                            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500">
                                <span v-if="line.code" dir="ltr">{{ line.code }}</span>
                                <span>{{ copy.movedLabel }} {{ formatQty(line.quantity) }}<template v-if="line.unit"> {{ line.unit }}</template></span>
                                <span>× {{ formatUsd(line.unitNet) }}</span>
                                <span v-if="Number(line.returned_quantity) > 0" class="text-orange-600">
                                    أُرجع سابقاً {{ formatQty(line.returned_quantity) }}
                                </span>
                                <span v-if="kind === 'purchases' && line.stock_quantity != null"
                                    :class="line.short ? 'font-semibold text-red-600' : ''">
                                    بالمستودع {{ formatQty(line.stock_quantity) }}
                                </span>
                            </p>
                        </div>

                        <div v-if="line.exhausted" class="shrink-0 text-xs font-semibold text-slate-500">أُرجع بالكامل</div>
                        <div v-else class="flex shrink-0 items-center gap-3">
                            <!-- المؤشّر والحقل معاً: الضغط على + أسرع للقطعة والقطعتين،
                                 والكتابة أسرع للكميات الكبيرة والكسرية. -->
                            <div class="flex items-center rounded-lg border border-slate-300 bg-white"
                                :class="line.over ? 'border-red-400 ring-2 ring-red-100' : ''">
                                <button type="button" class="h-8 w-8 text-lg leading-none text-slate-500 transition hover:text-slate-900 disabled:opacity-30"
                                    :disabled="qty(line) <= 0 || busy" :aria-label="`إنقاص ${line.name}`" @click="step(line, -1)">−</button>
                                <input
                                    v-model="quantities[line.id]"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    :max="line.returnable"
                                    step="any"
                                    placeholder="0"
                                    :disabled="busy"
                                    :aria-label="`الكمية المرتجعة من ${line.name}`"
                                    class="h-8 w-14 border-x border-slate-200 bg-transparent text-center text-sm font-semibold tabular-nums focus:outline-none"
                                />
                                <button type="button" class="h-8 w-8 text-lg leading-none text-slate-500 transition hover:text-slate-900 disabled:opacity-30"
                                    :disabled="qty(line) >= line.returnable || busy" :aria-label="`زيادة ${line.name}`" @click="step(line, 1)">+</button>
                            </div>
                            <button type="button" class="text-[11px] font-medium text-blue-600 hover:text-blue-800 disabled:opacity-40"
                                :disabled="busy" @click="quantities[line.id] = String(line.returnable)">
                                الكل ({{ formatQty(line.returnable) }})
                            </button>
                            <span class="w-24 text-left text-sm font-semibold tabular-nums"
                                :class="qty(line) > 0 ? 'text-slate-900' : 'text-slate-300'">{{ formatUsd(line.amount) }}</span>
                        </div>
                        <p v-if="line.over" class="text-xs text-red-600 sm:basis-full">
                            المتاح للإرجاع {{ formatQty(line.returnable) }} فقط.
                        </p>
                    </li>
                </ul>
            </section>

            <!-- مرتجع شراء لبضاعة لم تعد على الرفّ: يُقال قبل الضغط لا بعده. -->
            <p v-if="shortLines.length" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                الكمية المرتجعة من {{ shortLines.map((l) => `«${l.name}»`).join('، ') }} أكبر من رصيد المستودع؛
                قد يُرفض المرتجع بحسب سياسة المخزون.
            </p>

            <!-- ------------------------------------------------------- the why -->
            <section class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="return-reason">
                        سبب الإرجاع <span class="text-red-500">*</span>
                    </label>
                    <div class="mb-2 flex flex-wrap gap-1.5">
                        <button v-for="preset in copy.reasons" :key="preset" type="button"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="form.reason === preset
                                ? 'border-blue-600 bg-blue-50 font-semibold text-blue-700'
                                : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                            @click="form.reason = preset">{{ preset }}</button>
                    </div>
                    <input id="return-reason" v-model.trim="form.reason" type="text" maxlength="255" :disabled="busy"
                        placeholder="أو اكتب السبب..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="return-date">تاريخ المرتجع</label>
                    <input id="return-date" v-model="form.return_date" type="date" :min="ctx.invoice.invoice_date" :disabled="busy"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                </div>
                <div class="sm:col-span-3">
                    <button v-if="!showNotes" type="button" class="text-xs font-medium text-slate-500 hover:text-slate-800"
                        @click="showNotes = true">+ إضافة ملاحظة</button>
                    <textarea v-else v-model="form.notes" rows="2" maxlength="2000" :disabled="busy" placeholder="ملاحظات (اختياري)"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
                </div>
            </section>

            <!-- ------------------------------------------------------ the money -->
            <section v-if="totals.total > 0" class="rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500">قيمة المرتجع</p>
                        <p class="text-2xl font-bold tabular-nums text-slate-900">{{ formatUsd(totals.total) }}</p>
                    </div>
                    <p v-if="totals.discount > 0" class="text-xs text-slate-500">
                        {{ formatUsd(totals.subtotal) }} − حصّته من خصم الفاتورة {{ formatUsd(totals.discount) }}
                    </p>
                </div>

                <!-- كيف تتوزّع القيمة: أولاً على ما بقي مفتوحاً من الفاتورة، وما يزيد
                     عنه دُفع فعلاً فهو وحده ما يمكن ردّه. -->
                <div class="mt-3 space-y-2 text-sm">
                    <div v-if="settlement.applied > 0" class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
                        <span class="text-slate-600">{{ copy.appliedLabel }}</span>
                        <span class="font-semibold tabular-nums text-slate-900">{{ formatUsd(settlement.applied) }}</span>
                    </div>
                    <div v-if="settlement.excess > 0" class="rounded-lg bg-slate-50 px-3 py-2">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-600">{{ copy.excessLabel }}</span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ formatUsd(settlement.excess) }}</span>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-1 rounded-lg border border-slate-300 bg-white p-0.5 text-xs">
                            <button type="button" class="rounded-md px-3 py-1.5 font-medium transition"
                                :class="form.settlement === 'CREDIT' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:text-slate-900'"
                                @click="form.settlement = 'CREDIT'">{{ copy.creditOption }}</button>
                            <button type="button" class="rounded-md px-3 py-1.5 font-medium transition disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!canRefund"
                                :title="canRefund ? '' : 'يتطلّب صلاحية إنشاء السندات'"
                                :class="form.settlement === 'REFUND' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:text-slate-900'"
                                @click="form.settlement = 'REFUND'">{{ copy.refundOption }}</button>
                        </div>
                        <MethodPicker v-if="form.settlement === 'REFUND'" v-model="form.refund_method" :label="copy.methodLabel" class="mt-2" />
                    </div>
                </div>
            </section>

            <!-- ماذا يترتّب على «تأكيد»: كما في الترحيل، تُقال الحركات قبل أن تحدث. -->
            <ul v-if="selectedCount" class="space-y-1.5 text-xs text-slate-600">
                <li v-if="stockQty > 0" class="flex gap-2">
                    <span aria-hidden="true">📦</span>
                    <span>{{ copy.stockEffect(formatQty(stockQty)) }}</span>
                </li>
                <li class="flex gap-2">
                    <span aria-hidden="true">📒</span>
                    <span>{{ copy.ledgerEffect(formatUsd(totals.total), partyName) }}</span>
                </li>
                <li v-if="form.settlement === 'REFUND' && settlement.excess > 0" class="flex gap-2">
                    <span aria-hidden="true">💵</span>
                    <span>{{ copy.refundEffect(formatUsd(settlement.excess)) }}</span>
                </li>
                <li v-else-if="settlement.excess > 0" class="flex gap-2">
                    <span aria-hidden="true">🏦</span>
                    <span>{{ copy.creditEffect(formatUsd(settlement.excess), partyName) }}</span>
                </li>
                <li class="flex gap-2 text-slate-500">
                    <span aria-hidden="true">↩️</span>
                    <span>يمكن إلغاء المرتجع لاحقاً مع ذكر السبب، فتُعكس حركاته وقيوده.</span>
                </li>
            </ul>

            <!-- مرتجعات سابقة: من يرى «أُرجع سابقاً 2» يريد أن يعرف متى ولماذا. -->
            <details v-if="ctx.returns.length" class="rounded-xl border border-slate-200 text-sm">
                <summary class="cursor-pointer select-none px-3 py-2 text-xs font-semibold text-slate-600">
                    مرتجعات سابقة على هذه الفاتورة ({{ ctx.returns.length }})
                </summary>
                <ul class="divide-y divide-slate-100 border-t border-slate-200">
                    <li v-for="r in ctx.returns" :key="r.id" class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="font-semibold text-slate-800" dir="ltr">{{ r.return_no }}</span>
                            <span class="text-slate-400">{{ formatDate(r.return_date) }}</span>
                            <span class="truncate text-slate-500">{{ r.reason }}</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span class="font-semibold tabular-nums" :class="r.status === 'CANCELLED' ? 'text-slate-400 line-through' : 'text-slate-900'">{{ formatUsd(r.total) }}</span>
                            <StatusBadge :status="r.status" />
                        </span>
                    </li>
                </ul>
            </details>

            <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

            <div class="flex gap-2 pt-1">
                <button type="button" :disabled="busy"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    @click="$emit('close')">تراجع</button>
                <button type="submit" :disabled="!canSubmit"
                    class="flex-1 rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:opacity-50">
                    {{ submitLabel }}
                </button>
            </div>
        </form>
    </BaseModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import BaseModal from '../ui/BaseModal.vue';
import StatusBadge from '../ui/StatusBadge.vue';
import MethodPicker from '../repairs/PaymentMethodPicker.vue';
import { purchaseReturnsApi, salesReturnsApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { apiMessage, formatDate, formatQty, formatUsd, isoDate } from '../../utils/format';

const props = defineProps({
    open: Boolean,
    /** 'sales' | 'purchases' — which side of the counter the goods are crossing. */
    kind: { type: String, default: 'sales' },
    /** The invoice row; its lines and balances are re-read when the modal opens. */
    invoice: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const { can } = usePermissions();

/**
 * Every sentence that differs between the two sides, so the template reads
 * the same for both and the wording stays the counter's own.
 */
const COPY = {
    sales: {
        api: salesReturnsApi,
        itemKey: 'sales_invoice_item_id',
        title: 'مرتجع بيع',
        movedLabel: 'مُباع',
        appliedLabel: 'يُخصم من المتبقّي على الفاتورة',
        excessLabel: 'دفعه العميل سابقاً',
        creditOption: 'رصيد على حساب العميل',
        refundOption: 'ردّ المبلغ للعميل',
        methodLabel: 'طريقة الردّ',
        reasons: ['تالف / عيب مصنعي', 'غير مطابق للطلب', 'تغيير رأي العميل', 'خطأ في الفاتورة'],
        stockEffect: (q) => `تعود ${q} إلى المستودع بحركة «مرتجع بيع» داخلة، بتكلفتها يوم البيع.`,
        ledgerEffect: (amount, party) => `يُقيَّد ${amount} دائناً لـ ${party}.`,
        refundEffect: (amount) => `يُردّ ${amount} للعميل ويُسجَّل في كشف حسابه.`,
        creditEffect: (amount, party) => `يبقى ${amount} رصيداً لـ ${party} يُخصم من فاتورته القادمة.`,
    },
    purchases: {
        api: purchaseReturnsApi,
        itemKey: 'purchase_invoice_item_id',
        title: 'مرتجع شراء',
        movedLabel: 'مُشترى',
        appliedLabel: 'يُخصم ممّا بقي للمورد على الفاتورة',
        excessLabel: 'دُفع للمورد سابقاً',
        creditOption: 'رصيد لنا لدى المورد',
        refundOption: 'استرداد المبلغ من المورد',
        methodLabel: 'طريقة الاسترداد',
        reasons: ['تالف / عيب مصنعي', 'غير مطابق للطلب', 'كمية زائدة', 'خطأ في الفاتورة'],
        stockEffect: (q) => `تخرج ${q} من المستودع بحركة «مرتجع شراء».`,
        ledgerEffect: (amount, party) => `يُقيَّد ${amount} مديناً على ${party}، فينقص ما نَدين به له.`,
        refundEffect: (amount) => `يُسجَّل ${amount} مستردّاً من المورد في كشف حسابه.`,
        creditEffect: (amount, party) => `يبقى ${amount} رصيداً لنا لدى ${party} يُخصم من فاتورته القادمة.`,
    },
};

const copy = computed(() => COPY[props.kind] ?? COPY.sales);

// ردّ النقد سند صرف في جوهره؛ الخادم يرفضه لمن لا يملك إنشاء السندات.
const canRefund = computed(() => can('payments.create'));

const ctx = ref(null);
const loading = ref(false);
const loadError = ref('');
const busy = ref(false);
const error = ref('');
const showNotes = ref(false);
const quantities = reactive({});
const form = reactive({ reason: '', return_date: isoDate(), notes: '', settlement: 'CREDIT', refund_method: 'CASH' });

const partyName = computed(() => ctx.value?.invoice?.party?.name || (props.kind === 'sales' ? 'العميل' : 'المورد'));

const subtitle = computed(() => {
    const inv = ctx.value?.invoice ?? props.invoice;
    const no = inv?.invoice_no;
    return no ? `عن الفاتورة ${no}${ctx.value ? ` · ${partyName.value}` : ''}` : '';
});

const invoiceStats = computed(() => {
    const inv = ctx.value?.invoice ?? {};
    return [
        { label: 'إجمالي الفاتورة', value: inv.total },
        { label: 'المدفوع', value: inv.paid_amount, tone: 'text-emerald-700' },
        { label: 'مرتجع سابقاً', value: inv.returned_amount, tone: Number(inv.returned_amount) > 0 ? 'text-orange-600' : 'text-slate-400' },
        { label: 'المتبقّي', value: inv.remaining },
    ];
});

function cents(value) {
    return Math.round((Number(value) || 0) * 100);
}

function qty(line) {
    const n = Number(quantities[line.id]);
    return Number.isFinite(n) && n > 0 ? n : 0;
}

/**
 * The same arithmetic the server does, so the figure on the button is the
 * figure in the ledger: a line returned in full takes exactly what is left of
 * it, a partial one its share, and the invoice discount follows in proportion
 * until the last return squares it.
 */
const lines = computed(() =>
    (ctx.value?.lines ?? []).map((line) => {
        const sold = Number(line.quantity) || 0;
        const returnable = Number(line.returnable_quantity) || 0;
        const q = qty(line);
        const closes = q > 0 && Math.abs(q - returnable) < 0.0005;
        const amountCents = q <= 0
            ? 0
            : closes
                ? cents(line.line_total) - cents(line.returned_total)
                : Math.round(cents(line.line_total) * (q / (sold || 1)));
        const stock = line.stock_quantity == null ? null : Number(line.stock_quantity);

        return {
            ...line,
            returnable,
            exhausted: returnable <= 0,
            closes,
            over: q - returnable > 0.0005,
            short: props.kind === 'purchases' && stock !== null && q > stock + 0.0005,
            unitNet: sold > 0 ? Number(line.line_total) / sold : Number(line.unit_price),
            amountCents,
            amount: amountCents / 100,
        };
    })
);

const selected = computed(() => lines.value.filter((line) => qty(line) > 0));
const selectedCount = computed(() => selected.value.length);
const anyReturnable = computed(() => lines.value.some((line) => !line.exhausted));
const anyOver = computed(() => lines.value.some((line) => line.over));
const shortLines = computed(() => lines.value.filter((line) => line.short));
const stockQty = computed(() => selected.value
    .filter((line) => line.line_type === 'MATERIAL')
    .reduce((sum, line) => sum + qty(line), 0));

const totals = computed(() => {
    const inv = ctx.value?.invoice ?? {};
    const subtotalCents = selected.value.reduce((sum, line) => sum + line.amountCents, 0);
    const discountCents = cents(inv.discount_amount);
    const leftOnInvoice = lines.value.some((line) => line.returnable - qty(line) > 0.0005);

    let share = 0;
    if (discountCents > 0 && subtotalCents > 0) {
        share = !leftOnInvoice
            ? discountCents - cents(inv.prior_discount)
            : cents(inv.subtotal) > 0 ? Math.round(discountCents * (subtotalCents / cents(inv.subtotal))) : 0;
        share = Math.min(Math.max(share, 0), subtotalCents);
    }

    return {
        subtotal: subtotalCents / 100,
        discount: share / 100,
        total: (subtotalCents - share) / 100,
        totalCents: subtotalCents - share,
    };
});

const settlement = computed(() => {
    const open = Math.max(0, cents(ctx.value?.invoice?.remaining));
    const applied = Math.min(totals.value.totalCents, open);
    return { applied: applied / 100, excess: (totals.value.totalCents - applied) / 100 };
});

const dirty = computed(() => selectedCount.value > 0 || Boolean(form.notes));

const canSubmit = computed(() =>
    !busy.value && selectedCount.value > 0 && !anyOver.value && form.reason.trim().length >= 2
);

const submitLabel = computed(() => {
    if (busy.value) return 'جارٍ التسجيل...';
    if (!selectedCount.value) return 'اختر بنداً للإرجاع';
    if (form.reason.trim().length < 2) return 'اذكر سبب الإرجاع';
    return `تأكيد المرتجع · ${formatUsd(totals.value.total)}`;
});

function step(line, delta) {
    const next = Math.min(line.returnable, Math.max(0, Math.round((qty(line) + delta) * 1000) / 1000));
    quantities[line.id] = next > 0 ? String(next) : '';
}

function returnAll() {
    for (const line of lines.value) {
        if (!line.exhausted) quantities[line.id] = String(line.returnable);
    }
}

function clearAll() {
    for (const key of Object.keys(quantities)) delete quantities[key];
}

async function load() {
    const id = props.invoice?.id;
    if (!id) return;

    loading.value = true;
    loadError.value = '';
    try {
        ctx.value = await copy.value.api.returnable(id);
    } catch (e) {
        ctx.value = null;
        loadError.value = apiMessage(e, 'تعذّر قراءة بنود الفاتورة');
    } finally {
        loading.value = false;
    }

    // فاتورة ذات بند واحد يبقى منه شيء: الإرجاع شبه مؤكَّد أنّه له، فيُجهَّز سلفاً.
    const open = lines.value.filter((line) => !line.exhausted);
    if (open.length === 1 && open[0].returnable === 1) quantities[open[0].id] = '1';
}

async function submit() {
    if (!canSubmit.value) return;

    busy.value = true;
    error.value = '';
    try {
        const refund = form.settlement === 'REFUND' && settlement.value.excess > 0;
        const data = await copy.value.api.store(ctx.value.invoice.id, {
            reason: form.reason.trim(),
            return_date: form.return_date || undefined,
            notes: form.notes?.trim() || undefined,
            settlement: refund ? 'REFUND' : 'CREDIT',
            refund_method: refund ? form.refund_method : undefined,
            items: selected.value.map((line) => ({ [copy.value.itemKey]: line.id, quantity: qty(line) })),
        });
        emit('saved', data);
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر تسجيل المرتجع');
    } finally {
        busy.value = false;
    }
}

watch(
    () => [props.open, props.invoice?.id],
    ([open]) => {
        if (!open) return;
        ctx.value = null;
        busy.value = false;
        error.value = '';
        showNotes.value = false;
        clearAll();
        Object.assign(form, { reason: '', return_date: isoDate(), notes: '', settlement: 'CREDIT', refund_method: 'CASH' });
        load();
    },
    { immediate: true }
);
</script>
