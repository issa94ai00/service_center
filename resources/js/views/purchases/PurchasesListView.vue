<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">فواتير الشراء</h1>
                <p class="text-sm text-slate-500">مشتريات المواد من الموردين</p>
            </div>
            <RouterLink v-if="can('purchases.create')" :to="{ name: 'purchases.create' }"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + فاتورة شراء جديدة
            </RouterLink>
        </div>

        <div v-if="pdfError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ pdfError }}
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <input v-model.trim="search" type="search" placeholder="بحث برقم الفاتورة..."
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
                            <th class="px-4 py-3">رقم الفاتورة</th>
                            <th class="px-4 py-3">المورد</th>
                            <th class="px-4 py-3">التاريخ</th>
                            <th class="px-4 py-3 text-left">الإجمالي</th>
                            <th class="px-4 py-3 text-left">المتبقي</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading" class="text-slate-400"><td colspan="7" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length" class="text-slate-400"><td colspan="7" class="px-4 py-8 text-center">لا توجد نتائج</td></tr>
                        <tr v-for="row in items" :key="row.id"
                            :class="posting?.id === row.id ? 'bg-emerald-50/70' : 'hover:bg-slate-50'">
                            <td class="px-4 py-3 font-semibold text-slate-900" dir="ltr">{{ row.invoice_no || `مسودة#${row.id}` }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.supplier?.name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.purchase_date }}</td>
                            <td class="px-4 py-3 text-left font-semibold">{{ formatMoney(row.total) }}</td>
                            <td class="px-4 py-3 text-left">{{ formatMoney(row.remaining) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="row.status" /></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button class="text-slate-500 hover:text-slate-900" @click="openDetail(row)">عرض</button>
                                    <button class="text-slate-500 hover:text-slate-900 disabled:opacity-40"
                                        :disabled="generating === row.id" @click="printDocument(row)">
                                        {{ generating === row.id ? '...' : 'طباعة' }}</button>
                                    <RouterLink v-if="row.status === 'DRAFT'" :to="{ name: 'purchases.edit', params: { id: row.id } }"
                                        class="text-slate-500 hover:text-slate-900">تعديل</RouterLink>
                                    <button v-if="row.status === 'DRAFT' && can('purchases.post')"
                                        class="text-emerald-600 hover:text-emerald-800" @click="post(row)">ترحيل</button>
                                    <button v-if="row.status === 'POSTED' && can('purchases.cancel')"
                                        class="text-orange-600 hover:text-orange-800" @click="cancel(row)">إلغاء</button>
                                    <button v-if="row.status === 'DRAFT' && can('purchases.delete')"
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

        <BaseModal :open="detail?.id" :title="`فاتورة الشراء ${detail?.invoice_no || ''}`" @close="detail = null">
            <div v-if="detail">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">المورد</dt><dd class="font-medium">{{ detail.supplier?.name }}</dd></div>
                    <div><dt class="text-slate-500">التاريخ</dt><dd dir="ltr">{{ detail.purchase_date }}</dd></div>
                    <div><dt class="text-slate-500">المجموع الفرعي</dt><dd>{{ formatMoney(detail.subtotal) }}</dd></div>
                    <div><dt class="text-slate-500">الخصم</dt><dd>{{ formatMoney(detail.discount_amount) }}</dd></div>
                    <div><dt class="text-slate-500">الإجمالي</dt><dd class="font-bold">{{ formatMoney(detail.total) }}</dd></div>
                    <div><dt class="text-slate-500">المتبقي</dt><dd>{{ formatMoney(detail.remaining) }}</dd></div>
                </dl>
                <div v-if="detail.notes" class="mt-3 text-sm text-slate-600">ملاحظات: {{ detail.notes }}</div>
                <div v-if="detail.cancellation_reason" class="mt-3 rounded bg-orange-50 px-3 py-2 text-sm text-orange-700">
                    سبب الإلغاء: {{ detail.cancellation_reason }}
                </div>
                <table class="mt-4 w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr><th class="px-3 py-2">المادة</th><th class="px-3 py-2">الكمية</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2 text-left">الإجمالي</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="l in (detail.items || [])" :key="l.id">
                            <td class="px-3 py-2">{{ l.material?.name || `#${l.material_id}` }}</td>
                            <td class="px-3 py-2">{{ l.quantity }}</td>
                            <td class="px-3 py-2">{{ formatMoney(l.unit_price) }}</td>
                            <td class="px-3 py-2 text-left font-medium">{{ formatMoney(l.line_total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </BaseModal>

        <PurchasePostModal
            :open="Boolean(posting)"
            :invoice="posting"
            @close="posting = null"
            @posted="onPosted"
            @stale="posting = null; fetchData()"
        />
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { documentsApi, purchasesApi } from '../../services/api';
import { usePdf } from '../../composables/usePdf';
import { useAuthStore } from '../../stores/auth';
import { usePermissions } from '../../composables/usePermissions';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { apiMessage } from '../../utils/format';
import BaseModal from '../../components/ui/BaseModal.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import PurchasePostModal from '../../components/purchases/PurchasePostModal.vue';

const { generating, pdfError, openPdf } = usePdf();

function printDocument(row) {
    openPdf(row.id, () => documentsApi.purchaseInvoice(row.id));
}

const { can } = usePermissions();
const auth = useAuthStore();
const items = ref([]);
const meta = ref(null);
const loading = ref(false);
const search = ref('');
const statusFilter = ref('');
const from = ref('');
const to = ref('');
const page = ref(1);
const detail = ref(null);
const posting = ref(null);
const toast = useToast();
let t = null;

watch([search, statusFilter, from, to], () => {
    clearTimeout(t);
    t = setTimeout(() => { page.value = 1; fetchData(); }, 350);
});
watch(page, fetchData);

async function fetchData() {
    loading.value = true;
    try {
        const data = await purchasesApi.index({
            search: search.value || undefined,
            status: statusFilter.value || undefined,
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
        const d = await purchasesApi.show(row.id);
        detail.value = d.data;
    } catch { /* ignore */ }
}

/**
 * الترحيل قرار لا يُتراجع عنه إلا بإلغاء مُعلَّل، فالسؤال يعرض ما سيقع فعلاً:
 * المورد والإجمالي، وكل مادة ورصيدها قبل الترحيل وبعده، والدَّين الذي يُقيَّد.
 * `confirm()` الأصلي لا يسع شيئاً من هذا، ولا يُقرأ في اتجاه اللغة أصلاً.
 */
function post(row) {
    posting.value = row;
}

function onPosted(invoice) {
    posting.value = null;
    toast.success(
        invoice?.invoice_no
            ? `تم ترحيل الفاتورة ${invoice.invoice_no} ودخلت البضاعة المستودع`
            : 'تم ترحيل الفاتورة'
    );
    fetchData();
}

async function cancel(row) {
    const reason = prompt('سبب الإلغاء (إلزامي):');
    if (reason === null) return;
    if (reason.trim().length < 3) { toast.error('الإلغاء يتطلب سببًا واضحًا.'); return; }
    try {
        await purchasesApi.cancel(row.id, { reason: reason.trim() });
        toast.success('تم إلغاء الفاتورة وعُكست حركاتها');
        fetchData();
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر الإلغاء'));
    }
}

async function remove(row) {
    const ok = await confirm({
        title: 'حذف مسودة الفاتورة؟',
        message: `تُحذف مسودة ${row.supplier?.name || 'الفاتورة'} نهائياً ولا يمكن استرجاعها.`,
        detail: `${formatMoney(row.total)} ل.س · ${row.purchase_date}`,
        confirmLabel: 'حذف نهائي',
        tone: 'danger',
    });
    if (!ok) return;
    try {
        await purchasesApi.destroy(row.id);
        toast.success('تم حذف المسودة');
        fetchData();
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر الحذف'));
    }
}

function formatMoney(value) {
    return new Intl.NumberFormat(auth.locale === 'en' ? 'en' : 'ar-SY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value) || 0);
}

onMounted(fetchData);
</script>