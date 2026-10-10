<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">فواتير البيع</h1>
                <p class="text-sm text-slate-500">مبيعات الخدمات والمواد للعملاء، وفواتير تسوية الصيانة</p>
            </div>
            <RouterLink v-if="can('sales.create')" :to="{ name: 'sales.create' }"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                + فاتورة بيع جديدة
            </RouterLink>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <!-- الفاتورتان تعيشان في جدول واحد، فالفصل بينهما فلتر لا شاشة
                     ثانية: المحاسب يقرؤهما معاً حين يريد، ومفصولتين حين يريد. -->
                <div class="flex rounded-lg border border-slate-300 p-0.5 text-sm">
                    <button v-for="option in SOURCES" :key="option.value" type="button"
                        class="rounded-md px-3 py-1.5 font-medium transition"
                        :class="sourceFilter === option.value ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-800'"
                        @click="sourceFilter = option.value">{{ option.label }}</button>
                </div>
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
                            <th class="px-4 py-3">العميل</th>
                            <th class="px-4 py-3">التاريخ</th>
                            <th class="px-4 py-3 text-left">الإجمالي</th>
                            <th class="px-4 py-3 text-left">المتبقي</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !items.length" class="text-slate-400"><td colspan="7" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length" class="text-slate-400"><td colspan="7" class="px-4 py-8 text-center">لا توجد نتائج</td></tr>
                        <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <span class="font-semibold text-slate-900" dir="ltr">{{ row.invoice_no || `مسودة#${row.id}` }}</span>
                                <span v-if="row.is_repair"
                                    class="ms-2 rounded bg-violet-100 px-1.5 py-0.5 text-[11px] font-semibold text-violet-700">صيانة</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ row.customer?.name || '—' }}
                                <span v-if="row.repair" class="block text-xs text-slate-400">
                                    {{ row.repair.receipt_no }}<template v-if="row.repair.device"> · {{ row.repair.device }}</template>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.invoice_date }}</td>
                            <td class="px-4 py-3 text-left font-semibold">{{ formatMoney(row.total) }}</td>
                            <td class="px-4 py-3 text-left">{{ formatMoney(row.remaining) }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="row.status" /></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 whitespace-nowrap">
                                    <button class="text-slate-500 hover:text-slate-900" @click="openDetail(row)">عرض</button>
                                    <PrintButton kind="invoice" title="طباعة الفاتورة" :load="(paper) => documentsApi.salesInvoice(row.id, paper)" />
                                    <RouterLink v-if="row.repair" :to="{ name: 'repairs', query: { search: row.repair.receipt_no } }"
                                        class="text-violet-600 hover:text-violet-800">البلاغ</RouterLink>
                                    <RouterLink v-if="row.status === 'DRAFT'" :to="{ name: 'sales.edit', params: { id: row.id } }"
                                        class="text-slate-500 hover:text-slate-900">تعديل</RouterLink>
                                    <button v-if="row.status === 'DRAFT' && can('sales.post')"
                                        class="text-emerald-600 hover:text-emerald-800" @click="post(row)">ترحيل</button>
                                    <button v-if="row.status === 'POSTED' && can('sales.cancel')"
                                        class="text-orange-600 hover:text-orange-800" @click="cancel(row)">إلغاء</button>
                                    <button v-if="row.status === 'DRAFT' && can('sales.delete')"
                                        class="text-red-500 hover:text-red-700" @click="remove(row)">حذف</button>
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
                unit="فاتورة"
                @more="loadMore"
            />
        </div>

        <BaseModal :open="detail?.id" :title="`${detail?.is_repair ? 'فاتورة الصيانة' : 'فاتورة البيع'} ${detail?.invoice_no || ''}`" @close="detail = null">
            <div v-if="detail">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">العميل</dt><dd class="font-medium">{{ detail.customer?.name }}</dd></div>
                    <div><dt class="text-slate-500">التاريخ</dt><dd dir="ltr">{{ detail.invoice_date }}</dd></div>
                    <div v-if="detail.repair"><dt class="text-slate-500">إيصال الاستلام</dt><dd class="font-medium" dir="ltr">{{ detail.repair.receipt_no }}</dd></div>
                    <div v-if="detail.repair?.device"><dt class="text-slate-500">الجهاز</dt><dd>{{ detail.repair.device }}</dd></div>
                    <div><dt class="text-slate-500">المجموع الفرعي</dt><dd>{{ formatMoney(detail.subtotal) }}</dd></div>
                    <div><dt class="text-slate-500">الخصم</dt><dd>{{ formatMoney(detail.discount_amount) }}</dd></div>
                    <div><dt class="text-slate-500">الإجمالي</dt><dd class="font-bold">{{ formatMoney(detail.total) }}</dd></div>
                    <div><dt class="text-slate-500">المتبقي</dt><dd>{{ formatMoney(detail.remaining) }}</dd></div>
                </dl>
                <div v-if="detail.notes" class="mt-3 text-sm text-slate-600">ملاحظات: {{ detail.notes }}</div>
                <div v-if="detail.cancellation_reason" class="mt-3 rounded bg-orange-50 px-3 py-2 text-sm text-orange-700">
                    سبب الإلغاء: {{ detail.cancellation_reason }}
                </div>
                <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[480px] text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr><th class="px-3 py-2">النوع</th><th class="px-3 py-2">العنصر</th><th class="px-3 py-2">الكمية</th><th class="px-3 py-2">السعر</th><th class="px-3 py-2 text-left">الإجمالي</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="l in (detail.items || [])" :key="l.id">
                            <td class="px-3 py-2">
                                <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold"
                                    :class="l.line_type === 'SERVICE' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'">
                                    {{ l.line_type === 'SERVICE' ? 'خدمة' : 'مادة' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ l.service?.name || l.material?.name || `#${l.service_id ?? l.material_id}` }}</td>
                            <td class="px-3 py-2">{{ l.quantity }}</td>
                            <td class="px-3 py-2">{{ formatMoney(l.unit_price) }}</td>
                            <td class="px-3 py-2 text-left font-medium">{{ formatMoney(l.line_total) }}</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { documentsApi, salesApi } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { usePermissions } from '../../composables/usePermissions';
import BaseModal from '../../components/ui/BaseModal.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import PrintButton from '../../components/ui/PrintButton.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import { BASE_SYMBOL } from '../../constants/currency';
import { apiMessage } from '../../utils/format';
import { useToast } from '../../composables/useToast';


const { can } = usePermissions();
const toast = useToast();
const auth = useAuthStore();
const { items, meta, loading, loadingMore, moreError, hasMore, reload: fetchData, loadMore } = useInfiniteList({
    fetch: (page, perPage) => salesApi.index({
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        source: sourceFilter.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        page,
        per_page: perPage,
    }),
    onError: (e) => toast.error(apiMessage(e, 'تعذّر تحميل القائمة')),
});
const search = ref('');
const statusFilter = ref('');
const sourceFilter = ref('');
const from = ref('');
const to = ref('');
const detail = ref(null);
let t = null;

const SOURCES = [
    { value: '', label: 'الكل' },
    { value: 'SALES', label: 'مبيعات' },
    { value: 'REPAIR', label: 'صيانة' },
];

watch([search, statusFilter, sourceFilter, from, to], () => {
    clearTimeout(t);
    t = setTimeout(() => fetchData(), 350);
});


async function openDetail(row) {
    try {
        const d = await salesApi.show(row.id);
        detail.value = d.data;
    } catch { /* ignore */ }
}

async function post(row) {
    if (!confirm(`تأكيد ترحيل فاتورة البيع؟ سيتم ترقيمها وخصم المخزون وتقييد ذمّة العميل.`)) return;
    try {
        await salesApi.post(row.id);
        fetchData({ keep: true });
    } catch (e) {
        alert(e.response?.data?.message || 'تعذّر الترحيل');
    }
}

async function cancel(row) {
    const reason = prompt('سبب الإلغاء (إلزامي):');
    if (!reason || reason.trim().length < 3) { alert('الإلغاء يتطلب سببًا واضحًا.'); return; }
    try {
        await salesApi.cancel(row.id, { reason: reason.trim() });
        fetchData({ keep: true });
    } catch (e) {
        alert(e.response?.data?.message || 'تعذّر الإلغاء');
    }
}

async function remove(row) {
    if (!confirm('حذف المسودة نهائيًا؟')) return;
    try {
        await salesApi.destroy(row.id);
        fetchData({ keep: true });
    } catch (e) {
        alert(e.response?.data?.message || 'تعذّر الحذف');
    }
}

function formatMoney(value) {
    return new Intl.NumberFormat(auth.locale === 'en' ? 'en' : 'ar-SY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value) || 0) + ` ${BASE_SYMBOL}`;
}

onMounted(fetchData);
</script>