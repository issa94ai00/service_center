<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ isIncoming ? 'المقبوضات' : 'المدفوعات' }}</h1>
                <p class="text-sm text-slate-500">{{ isIncoming ? 'تحصيلات من العملاء — مقابل فواتير البيع وعلى بلاغات الصيانة' : 'مدفوعات للموردين مقابل فواتير الشراء' }}</p>
            </div>
            <RouterLink v-if="can('payments.create')" :to="{ name: 'payments.create', query: { type: paymentType } }"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + {{ isIncoming ? 'دفعة جديدة من عميل' : 'دفعة جديدة لمورد' }}
            </RouterLink>
        </div>

        <div v-if="pdfError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ pdfError }}
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <div class="flex rounded-lg border border-slate-300 p-0.5">
                    <RouterLink :to="{ name: 'payments', query: { type: 'CUSTOMER' } }" :class="segCls(isIncoming)">قبض</RouterLink>
                    <RouterLink :to="{ name: 'payments', query: { type: 'SUPPLIER' } }" :class="segCls(!isIncoming)">دفع</RouterLink>
                </div>
                <!-- سندات الصيانة تحمل سلسلتها الخاصة؛ الفلتر يفصلها عن سندات
                     الصندوق دون إخفائها في شاشة أخرى. -->
                <div v-if="isIncoming" class="flex rounded-lg border border-slate-300 p-0.5 text-sm">
                    <button v-for="option in SOURCES" :key="option.value" type="button"
                        class="rounded-md px-3 py-1.5 font-medium transition"
                        :class="sourceFilter === option.value ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-800'"
                        @click="sourceFilter = option.value">{{ option.label }}</button>
                </div>
                <input v-model.trim="search" type="search" placeholder="بحث برقم المرجع..."
                    class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                <select v-model="statusFilter"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">كل الحالات</option>
                    <option value="DRAFT">مسودة</option>
                    <option value="POSTED">معتمدة</option>
                    <option value="CANCELLED">ملغاة</option>
                </select>
                <input v-model="from" type="date"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" title="من تاريخ" />
                <input v-model="to" type="date"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" title="إلى تاريخ" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">رقم الدفعة</th>
                            <th class="px-4 py-3">{{ isIncoming ? 'العميل' : 'المورد' }}</th>
                            <th class="px-4 py-3">الطريقة</th>
                            <th class="px-4 py-3">التاريخ</th>
                            <th class="px-3 py-3 text-left">المبلغ</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading" class="text-slate-400"><td colspan="7" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length" class="text-slate-400"><td colspan="7" class="px-4 py-8 text-center">لا توجد نتائج</td></tr>
                        <tr v-for="row in items" :key="row.id" class="transition"
                            :class="posting?.id === row.id ? 'bg-emerald-50/70' : 'hover:bg-slate-50'">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-slate-900" dir="ltr">{{ row.reference_no || `مسودة#${row.id}` }}</span>
                                <span v-if="row.is_repair"
                                    class="ms-2 rounded bg-violet-100 px-1.5 py-0.5 text-[11px] font-semibold text-violet-700">صيانة</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ partyName(row) }}
                                <span v-if="row.repair" class="block text-xs text-slate-400">
                                    {{ row.repair.receipt_no }}<template v-if="row.repair.device"> · {{ row.repair.device }}</template>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ methodLabel(row.payment_method) }}</td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.payment_date }}</td>
                            <td class="px-3 py-3 text-left font-semibold">{{ formatMoney(row.amount) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="row.status" /></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button class="text-slate-500 hover:text-slate-900" @click="openDetail(row)">عرض</button>
                                    <button class="text-slate-500 hover:text-slate-900 disabled:opacity-40"
                                        :disabled="generating === row.id" @click="printDocument(row)">
                                        {{ generating === row.id ? '...' : 'طباعة' }}</button>
                                    <RouterLink v-if="row.repair" :to="{ name: 'repairs', query: { search: row.repair.receipt_no } }"
                                        class="text-violet-600 hover:text-violet-800">البلاغ</RouterLink>
                                    <RouterLink v-if="row.status === 'DRAFT'" :to="{ name: 'payments.edit', params: { id: row.id } }"
                                        class="text-slate-500 hover:text-slate-900">تعديل</RouterLink>
                                    <button v-if="row.status === 'DRAFT' && can('payments.confirm')"
                                        class="rounded-md bg-emerald-50 px-2 py-1 font-medium text-emerald-700 transition hover:bg-emerald-100"
                                        @click="post(row)">اعتماد</button>
                                    <button v-if="row.status === 'POSTED' && can('payments.cancel')"
                                        class="text-orange-600 hover:text-orange-800" @click="cancel(row)">إلغاء</button>
                                    <button v-if="row.status === 'DRAFT' && can('payments.create')"
                                        class="text-red-500 hover:text-red-700" @click="remove(row)">حذف</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="meta" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
                <span>صفحة {{ meta.current_page }} من {{ meta.last_page }} — {{ meta.total }}</span>
                <div class="flex gap-2">
                    <button :disabled="!meta.prev_page_url" class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40" @click="page--">السابق</button>
                    <button :disabled="!meta.next_page_url" class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40" @click="page++">التالي</button>
                </div>
            </div>
        </div>

        <BaseModal :open="detail?.id" :title="`الدفعة ${detail?.reference_no || ''}`" @close="detail = null">
            <div v-if="detail">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">{{ isIncoming ? 'العميل' : 'المورد' }}</dt><dd class="font-medium">{{ partyName(detail) }}</dd></div>
                    <div><dt class="text-slate-500">التاريخ</dt><dd dir="ltr">{{ detail.payment_date }}</dd></div>
                    <div><dt class="text-slate-500">الطريقة</dt><dd>{{ methodLabel(detail.payment_method) }}</dd></div>
                    <div><dt class="text-slate-500">المبلغ</dt><dd class="font-bold">{{ formatMoney(detail.amount) }}</dd></div>
                    <div v-if="detail.repair"><dt class="text-slate-500">بلاغ الصيانة</dt><dd class="font-medium" dir="ltr">{{ detail.repair.receipt_no }}</dd></div>
                    <div v-if="detail.repair?.device"><dt class="text-slate-500">الجهاز</dt><dd>{{ detail.repair.device }}</dd></div>
                </dl>
                <div v-if="detail.cancellation_reason" class="mt-3 rounded bg-orange-50 px-3 py-2 text-sm text-orange-700">
                    سبب الإلغاء: {{ detail.cancellation_reason }}
                </div>
                <div v-if="detail.notes" class="mt-3 text-sm text-slate-600">ملاحظات: {{ detail.notes }}</div>
                <h3 class="mt-4 mb-2 text-sm font-bold text-slate-900">التخصيص على الفواتير</h3>
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr><th class="px-3 py-2">الفاتورة</th><th class="px-3 py-2">التاريخ</th><th class="px-3 py-2 text-left">المبلغ المخصص</th><th class="px-3 py-2 text-left">المتبقي</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="a in (detail.allocations || [])" :key="a.id">
                            <td class="px-3 py-2 font-medium" dir="ltr">{{ a.invoice_no }}</td>
                            <td class="px-3 py-2" dir="ltr">{{ a.invoice_date }}</td>
                            <td class="px-3 py-2 text-left font-semibold">{{ formatMoney(a.amount) }}</td>
                            <td class="px-3 py-2 text-left">{{ formatMoney(a.remaining) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </BaseModal>

        <PaymentPostModal
            :open="Boolean(posting)"
            :payment="posting"
            @close="posting = null"
            @posted="onPosted"
            @stale="posting = null; fetchData()"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { documentsApi, paymentsApi } from '../../services/api';
import { usePdf } from '../../composables/usePdf';
import { usePermissions } from '../../composables/usePermissions';
import { useToast } from '../../composables/useToast';
import { confirm } from '../../composables/useConfirm';
import { apiMessage, formatMoney } from '../../utils/format';
import { paymentMethodLabel } from '../../constants/paymentMethods';
import BaseModal from '../../components/ui/BaseModal.vue';
import PaymentPostModal from '../../components/payments/PaymentPostModal.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';

const { generating, pdfError, openPdf } = usePdf();

function printDocument(row) {
    openPdf(row.id, () => documentsApi.paymentReceipt(row.id));
}

const { can } = usePermissions();
const toast = useToast();
const route = useRoute();
const CURRENCY = 'ل.س';
const items = ref([]);
const meta = ref(null);
const loading = ref(false);
const search = ref('');
const statusFilter = ref('');
const sourceFilter = ref('');
const from = ref('');
const to = ref('');
const page = ref(1);
const detail = ref(null);
const posting = ref(null);
let t = null;

const paymentType = computed(() => (route.query.type === 'SUPPLIER' ? 'SUPPLIER' : 'CUSTOMER'));
const isIncoming = computed(() => paymentType.value === 'CUSTOMER');

const SOURCES = [
    { value: '', label: 'الكل' },
    { value: 'DIRECT', label: 'الصندوق' },
    { value: 'REPAIR', label: 'صيانة' },
];

// A supplier has no repairs, so the filter would silently empty the list.
watch(paymentType, () => { sourceFilter.value = ''; });

function segCls(active) {
    return active
        ? 'rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white'
        : 'rounded-md px-3 py-1.5 text-sm font-medium text-slate-500 hover:text-slate-800';
}

function partyName(row) {
    return isIncoming.value ? row.customer?.name : row.supplier?.name;
}

function methodLabel(m) {
    return paymentMethodLabel(m);
}

watch([paymentType, search, statusFilter, sourceFilter, from, to], () => {
    clearTimeout(t);
    t = setTimeout(() => { page.value = 1; fetchData(); }, 350);
});
watch(page, fetchData);

async function fetchData() {
    loading.value = true;
    try {
        const data = await paymentsApi.index({
            payment_type: paymentType.value,
            search: search.value || undefined,
            status: statusFilter.value || undefined,
            source: isIncoming.value ? (sourceFilter.value || undefined) : undefined,
            from: from.value || undefined,
            to: to.value || undefined,
            page: page.value,
            per_page: 15,
        });
        items.value = data.data ?? data;
        meta.value = data.meta ?? null;
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    try {
        const d = await paymentsApi.show(row.id);
        detail.value = d.data;
    } catch { /* ignore */ }
}

/**
 * الاعتماد قرار لا يُتراجع عنه إلا بإلغاء مُعلَّل، فالسؤال يعرض ما سيقع فعلاً:
 * المبلغ والطرف، والفواتير التي تتسدّد ومتبقّي كلٍّ منها بعد الاعتماد، وما
 * يبقى رصيداً على الحساب. `confirm()` الأصلي لا يسع شيئاً من هذا.
 */
function post(row) {
    posting.value = row;
}

function onPosted(payment) {
    posting.value = null;
    toast.success(
        payment?.reference_no
            ? `تم اعتماد الدفعة ${payment.reference_no}`
            : 'تم اعتماد الدفعة'
    );
    fetchData();
}

async function cancel(row) {
    const reason = prompt('سبب الإلغاء (إلزامي):');
    if (reason === null) return;
    if (reason.trim().length < 3) { toast.error('الإلغاء يتطلب سببًا واضحًا.'); return; }
    try {
        await paymentsApi.cancel(row.id, { reason: reason.trim() });
        toast.success('تم إلغاء الدفعة');
        fetchData();
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر الإلغاء'));
    }
}

async function remove(row) {
    const ok = await confirm({
        title: 'حذف مسودة الدفعة؟',
        message: `تُحذف مسودة ${partyName(row) || 'الدفعة'} نهائياً ولا يمكن استرجاعها.`,
        detail: `${formatMoney(row.amount)} ${CURRENCY} · ${methodLabel(row.payment_method)} · ${row.payment_date}`,
        confirmLabel: 'حذف نهائي',
        tone: 'danger',
    });
    if (!ok) return;
    try {
        await paymentsApi.destroy(row.id);
        toast.success('تم حذف المسودة');
        fetchData();
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر الحذف'));
    }
}

onMounted(fetchData);
</script>