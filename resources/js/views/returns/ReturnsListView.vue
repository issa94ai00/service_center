<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ copy.title }}</h1>
                <p class="text-sm text-slate-500">{{ copy.subtitle }}</p>
            </div>
            <button v-if="can(copy.permission)" type="button"
                class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-orange-700"
                @click="openFinder">+ مرتجع جديد</button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <input v-model.trim="search" type="search" placeholder="رقم المرتجع أو الفاتورة..."
                    class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                <div class="flex rounded-lg border border-slate-300 p-0.5 text-sm">
                    <button v-for="option in STATUSES" :key="option.value" type="button"
                        class="rounded-md px-3 py-1.5 font-medium transition"
                        :class="statusFilter === option.value ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-800'"
                        @click="statusFilter = option.value">{{ option.label }}</button>
                </div>
                <input v-model="from" type="date" title="من تاريخ"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                <input v-model="to" type="date" title="إلى تاريخ"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-4 py-3">رقم المرتجع</th>
                            <th class="px-4 py-3">الفاتورة</th>
                            <th class="px-4 py-3">{{ copy.partyLabel }}</th>
                            <th class="px-4 py-3">التاريخ</th>
                            <th class="px-4 py-3">السبب</th>
                            <th class="px-4 py-3 text-left">القيمة</th>
                            <th class="px-4 py-3">التسوية</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !items.length" class="text-slate-400"><td colspan="9" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length">
                            <td colspan="9" class="px-4 py-12 text-center">
                                <p class="text-3xl" aria-hidden="true">↩️</p>
                                <p class="mt-2 text-sm font-semibold text-slate-700">لا توجد مرتجعات</p>
                                <p class="mt-1 text-xs text-slate-500">{{ copy.emptyHint }}</p>
                            </td>
                        </tr>
                        <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50" :class="row.status === 'CANCELLED' ? 'text-slate-400' : ''">
                            <td class="px-4 py-3 font-semibold text-slate-900" dir="ltr">{{ row.return_no }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ row.invoice?.invoice_no || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ party(row)?.name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.return_date }}</td>
                            <td class="max-w-[12rem] truncate px-4 py-3 text-slate-600" :title="row.reason">{{ row.reason }}</td>
                            <td class="px-4 py-3 text-left font-semibold tabular-nums"
                                :class="row.status === 'CANCELLED' ? 'line-through' : ''">{{ formatUsd(row.total) }}</td>
                            <td class="px-4 py-3"><SettlementChips :row="row" :kind="kind" /></td>
                            <td class="px-4 py-3"><StatusBadge :status="row.status" /></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3 whitespace-nowrap">
                                    <button class="text-slate-500 hover:text-slate-900" @click="openDetail(row)">عرض</button>
                                    <button v-if="row.status === 'POSTED' && can(copy.permission)"
                                        class="text-red-600 hover:text-red-800" @click="cancel(row)">إلغاء</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <InfiniteScrollFooter
                :shown="items.length"
                :total="meta?.total ?? 0"
                :has-more="hasMore"
                :loading-more="loadingMore"
                :busy="loading"
                :error="moreError"
                unit="مرتجع"
                @more="loadMore"
            />
        </div>

        <!-- ------------------------------------------------------------ detail -->
        <BaseModal :open="Boolean(detail)" :title="`${copy.single} ${detail?.return_no || ''}`"
            :subtitle="detail?.invoice?.invoice_no ? `عن الفاتورة ${detail.invoice.invoice_no}` : ''" @close="detail = null">
            <div v-if="detail" class="space-y-4">
                <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500">{{ copy.partyLabel }}</dt><dd class="font-medium">{{ party(detail)?.name }}</dd></div>
                    <div><dt class="text-slate-500">التاريخ</dt><dd dir="ltr">{{ detail.return_date }}</dd></div>
                    <div><dt class="text-slate-500">الحالة</dt><dd><StatusBadge :status="detail.status" /></dd></div>
                    <div class="col-span-2 sm:col-span-3"><dt class="text-slate-500">السبب</dt><dd>{{ detail.reason }}</dd></div>
                    <div v-if="detail.creator"><dt class="text-slate-500">سجّله</dt><dd>{{ detail.creator }}</dd></div>
                </dl>
                <p v-if="detail.notes" class="text-sm text-slate-600">ملاحظات: {{ detail.notes }}</p>
                <p v-if="detail.cancellation_reason" class="rounded bg-red-50 px-3 py-2 text-sm text-red-700">
                    سبب الإلغاء: {{ detail.cancellation_reason }}
                </p>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-right text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                            <tr><th class="px-3 py-2">البند</th><th class="px-3 py-2">الكمية</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2 text-left">القيمة</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="l in (detail.items || [])" :key="l.id">
                                <td class="px-3 py-2">{{ l.name || '—' }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ formatQty(l.quantity) }}</td>
                                <td class="px-3 py-2 tabular-nums">{{ formatUsd(l.unit_price) }}</td>
                                <td class="px-3 py-2 text-left font-medium tabular-nums">{{ formatUsd(l.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="space-y-1.5 rounded-xl border border-slate-200 p-3 text-sm">
                    <div v-if="Number(detail.discount_amount) > 0" class="flex justify-between text-slate-500">
                        <span>حصّة خصم الفاتورة</span><span class="tabular-nums">− {{ formatUsd(detail.discount_amount) }}</span>
                    </div>
                    <div class="flex justify-between font-bold"><span>قيمة المرتجع</span><span class="tabular-nums">{{ formatUsd(detail.total) }}</span></div>
                    <div v-if="Number(detail.applied_amount) > 0" class="flex justify-between text-slate-600">
                        <span>خُصم من المتبقّي على الفاتورة</span><span class="tabular-nums">{{ formatUsd(detail.applied_amount) }}</span>
                    </div>
                    <div v-if="Number(detail.refunded_amount) > 0" class="flex justify-between text-emerald-700">
                        <span>{{ copy.refundedLabel }} ({{ paymentMethodLabel(detail.refund_method) }})</span>
                        <span class="tabular-nums">{{ formatUsd(detail.refunded_amount) }}</span>
                    </div>
                    <div v-if="Number(detail.credited_amount) > 0" class="flex justify-between text-blue-700">
                        <span>بقي رصيداً على الحساب</span><span class="tabular-nums">{{ formatUsd(detail.credited_amount) }}</span>
                    </div>
                </div>

                <div v-if="detail.status === 'POSTED' && can(copy.permission)" class="flex justify-end">
                    <button type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                        @click="cancel(detail)">إلغاء المرتجع</button>
                </div>
            </div>
        </BaseModal>

        <!-- ---------------------------------------------- pick the invoice first -->
        <BaseModal :open="finderOpen" :title="`${copy.single} جديد`" subtitle="ابحث عن الفاتورة المرحَّلة التي تعود منها البضاعة"
            max-width="max-w-lg" @close="finderOpen = false">
            <div class="space-y-3">
                <input ref="finderInput" v-model.trim="finderQuery" type="search" placeholder="رقم الفاتورة..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    @keydown.enter.prevent="finderResults[0] && pickInvoice(finderResults[0])" />
                <p v-if="finderLoading" class="py-6 text-center text-sm text-slate-400">جارٍ البحث...</p>
                <p v-else-if="!finderResults.length" class="py-6 text-center text-sm text-slate-400">لا فواتير مرحَّلة مطابقة</p>
                <ul v-else class="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
                    <li v-for="inv in finderResults" :key="inv.id">
                        <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-right text-sm transition hover:bg-blue-50"
                            @click="pickInvoice(inv)">
                            <span class="min-w-0">
                                <span class="block font-semibold text-slate-900" dir="ltr">{{ inv.invoice_no }}</span>
                                <span class="block truncate text-xs text-slate-500">
                                    {{ (inv.customer || inv.supplier)?.name }} · {{ inv.invoice_date || inv.purchase_date }}
                                </span>
                            </span>
                            <span class="shrink-0 text-left">
                                <span class="block font-semibold tabular-nums">{{ formatUsd(inv.total) }}</span>
                                <span v-if="inv.active_returns_count" class="text-[11px] text-orange-600">عليها {{ inv.active_returns_count }} مرتجع</span>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>
        </BaseModal>

        <!-- ------------------------------------------------ cancel, with a reason -->
        <BaseModal :open="Boolean(cancelling)" :title="`إلغاء ${copy.single} ${cancelling?.return_no || ''}`" max-width="max-w-md"
            :close-on-overlay="!cancelBusy" @close="cancelling = null">
            <form v-if="cancelling" class="space-y-4" @submit.prevent="confirmCancel">
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    {{ kind === 'sales'
                        ? 'تخرج البضاعة من المستودع مرة أخرى، ويُعاد القيد على العميل وما خُصم من فاتورته.'
                        : 'تعود البضاعة إلى المستودع، ويُعاد القيد للمورد وما خُصم من فاتورته.' }}
                    <template v-if="Number(cancelling.refunded_amount) > 0">
                        ويُعكس قيد المبلغ {{ kind === 'sales' ? 'المردود' : 'المستردّ' }} ({{ formatUsd(cancelling.refunded_amount) }}).
                    </template>
                </p>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="cancel-reason">سبب الإلغاء <span class="text-red-500">*</span></label>
                    <textarea id="cancel-reason" ref="cancelInput" v-model.trim="cancelReason" rows="2" maxlength="255" :disabled="cancelBusy"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
                </div>
                <p v-if="cancelError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ cancelError }}</p>
                <div class="flex gap-2">
                    <button type="button" :disabled="cancelBusy"
                        class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                        @click="cancelling = null">تراجع</button>
                    <button type="submit" :disabled="cancelBusy || cancelReason.length < 3"
                        class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-50">
                        {{ cancelBusy ? 'جارٍ الإلغاء...' : 'إلغاء المرتجع' }}
                    </button>
                </div>
            </form>
        </BaseModal>

        <ReturnModal :open="Boolean(returnFor)" :kind="kind" :invoice="returnFor"
            @close="returnFor = null" @saved="onSaved" />
    </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { purchaseReturnsApi, purchasesApi, salesApi, salesReturnsApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { useInfiniteList } from '../../composables/useInfiniteList';
import { useToast } from '../../composables/useToast';
import BaseModal from '../../components/ui/BaseModal.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import ReturnModal from '../../components/returns/ReturnModal.vue';
import { paymentMethodLabel } from '../../constants/paymentMethods';
import { apiMessage, formatQty, formatUsd } from '../../utils/format';

const route = useRoute();
const { can } = usePermissions();
const toast = useToast();

/** Both pages are this one view; the route says which side it shows. */
const kind = computed(() => route.meta.returnKind === 'purchases' ? 'purchases' : 'sales');

const COPY = {
    sales: {
        api: salesReturnsApi,
        invoices: salesApi,
        permission: 'sales.return',
        title: 'مرتجعات المبيعات',
        subtitle: 'بضاعة وخدمات أعادها العملاء عن فواتير بيع مرحَّلة',
        single: 'مرتجع بيع',
        partyLabel: 'العميل',
        partyKey: 'customer',
        refundedLabel: 'رُدّ للعميل',
        emptyHint: 'سجّل مرتجعاً من زر «إرجاع» في قائمة فواتير البيع، أو من «مرتجع جديد» أعلاه.',
    },
    purchases: {
        api: purchaseReturnsApi,
        invoices: purchasesApi,
        permission: 'purchases.return',
        title: 'مرتجعات المشتريات',
        subtitle: 'بضاعة أُعيدت إلى الموردين عن فواتير شراء مرحَّلة',
        single: 'مرتجع شراء',
        partyLabel: 'المورد',
        partyKey: 'supplier',
        refundedLabel: 'استُردّ من المورد',
        emptyHint: 'سجّل مرتجعاً من زر «إرجاع» في قائمة فواتير الشراء، أو من «مرتجع جديد» أعلاه.',
    },
};

const copy = computed(() => COPY[kind.value]);

const STATUSES = [
    { value: '', label: 'الكل' },
    { value: 'POSTED', label: 'قائمة' },
    { value: 'CANCELLED', label: 'ملغاة' },
];

/** Where each return's value went: the open invoice, cash, or the account. */
const SettlementChips = defineComponent({
    props: { row: { type: Object, required: true }, kind: { type: String, required: true } },
    setup(p) {
        return () => {
            const chips = [];
            if (Number(p.row.applied_amount) > 0) chips.push(['bg-slate-100 text-slate-700', `من الفاتورة ${formatUsd(p.row.applied_amount)}`]);
            if (Number(p.row.refunded_amount) > 0) chips.push(['bg-emerald-100 text-emerald-700', `${p.kind === 'sales' ? 'رُدّ' : 'استُردّ'} ${formatUsd(p.row.refunded_amount)}`]);
            if (Number(p.row.credited_amount) > 0) chips.push(['bg-blue-100 text-blue-700', `رصيد ${formatUsd(p.row.credited_amount)}`]);
            return h('div', { class: 'flex flex-wrap gap-1' }, chips.map(([cls, text]) =>
                h('span', { class: `whitespace-nowrap rounded px-1.5 py-0.5 text-[11px] font-semibold ${cls}` }, text)));
        };
    },
});

const search = ref(typeof route.query.search === 'string' ? route.query.search : '');
const statusFilter = ref('');
const from = ref('');
const to = ref('');
const detail = ref(null);
const returnFor = ref(null);

const { items, meta, loading, loadingMore, moreError, hasMore, reload, loadMore } = useInfiniteList({
    fetch: (page, perPage) => copy.value.api.index({
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        page,
        per_page: perPage,
    }),
    onError: (e) => toast.error(apiMessage(e, 'تعذّر تحميل المرتجعات')),
});

function party(row) {
    return row?.[copy.value.partyKey];
}

let debounce = null;
watch([search, statusFilter, from, to], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => reload(), 350);
});

// A ledger row links here with ?search=<return no>; follow it while mounted too.
watch(() => route.query.search, (value) => {
    if (typeof value === 'string' && value !== search.value) search.value = value;
});

// The two menu entries share this component, so moving between them is a
// route change, not a remount.
watch(kind, () => {
    detail.value = null;
    returnFor.value = null;
    reload();
});

async function openDetail(row) {
    try {
        const data = await copy.value.api.show(row.id);
        detail.value = data.data;
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر قراءة المرتجع'));
    }
}

const cancelling = ref(null);
const cancelReason = ref('');
const cancelBusy = ref(false);
const cancelError = ref('');
const cancelInput = ref(null);

async function cancel(row) {
    cancelling.value = row;
    cancelReason.value = '';
    cancelError.value = '';
    await nextTick();
    cancelInput.value?.focus();
}

async function confirmCancel() {
    const row = cancelling.value;
    if (!row || cancelBusy.value || cancelReason.value.length < 3) return;

    cancelBusy.value = true;
    cancelError.value = '';
    try {
        const data = await copy.value.api.cancel(row.id, { reason: cancelReason.value });
        toast.success(data.message || 'تم إلغاء المرتجع.');
        if (detail.value?.id === row.id) detail.value = data.data;
        cancelling.value = null;
        reload({ keep: true });
    } catch (e) {
        cancelError.value = apiMessage(e, 'تعذّر إلغاء المرتجع');
    } finally {
        cancelBusy.value = false;
    }
}

// ---------------------------------------------------------------- the finder

const finderOpen = ref(false);
const finderQuery = ref('');
const finderResults = ref([]);
const finderLoading = ref(false);
const finderInput = ref(null);
let finderTimer = null;

async function runFinder() {
    finderLoading.value = true;
    try {
        const data = await copy.value.invoices.index({
            search: finderQuery.value || undefined,
            status: 'POSTED',
            source: kind.value === 'sales' ? 'SALES' : undefined,
            per_page: 10,
        });
        finderResults.value = data.data ?? [];
    } catch {
        finderResults.value = [];
    } finally {
        finderLoading.value = false;
    }
}

watch(finderQuery, () => {
    clearTimeout(finderTimer);
    finderTimer = setTimeout(runFinder, 300);
});

async function openFinder() {
    finderOpen.value = true;
    finderQuery.value = '';
    runFinder();
    await nextTick();
    finderInput.value?.focus();
}

function pickInvoice(invoice) {
    finderOpen.value = false;
    returnFor.value = invoice;
}

function onSaved(data) {
    returnFor.value = null;
    toast.success(data?.message || 'تم تسجيل المرتجع.');
    reload();
}

onMounted(reload);
</script>
