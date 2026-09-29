<template>
    <div id="statement-sheet" class="mx-auto max-w-6xl space-y-5">
        <div class="flex items-center justify-between print:hidden">
            <div>
                <h1 class="text-xl font-bold text-slate-900">كشف حساب {{ kind.label }}</h1>
                <p class="text-sm text-slate-500">{{ statement?.party?.name || '—' }}</p>
            </div>
            <div class="flex gap-2">
                <button
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                    @click="$router.back()"
                >
                    → عودة
                </button>
                <button
                    :disabled="!statement"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                    @click="printSheet"
                >
                    🖨 طباعة الصفحة
                </button>
                <button
                    v-if="kind.pdf"
                    :disabled="!statement || generating"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40"
                    @click="downloadPdf"
                >
                    {{ generating ? 'جارٍ التجهيز...' : '📄 كشف PDF' }}
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end gap-3 print:hidden">
                <label class="text-sm">
                    من تاريخ
                    <input v-model="filters.from" type="date" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-sm">
                    إلى تاريخ
                    <input v-model="filters.to" type="date" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-sm">
                    عدد الحركات
                    <select v-model.number="filters.perPage" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="n in [25, 50, 100, 200]" :key="n" :value="n">{{ n }}</option>
                    </select>
                </label>
                <button
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="applyFilters"
                >
                    عرض
                </button>
                <button
                    v-if="filters.from || filters.to"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                    @click="resetFilters"
                >
                    إلغاء الفلترة
                </button>
            </div>

            <div v-if="error || pdfError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error || pdfError }}
            </div>

            <template v-if="statement">
                <div class="mb-2 hidden print:block">
                    <h2 class="text-lg font-bold">كشف حساب {{ kind.label }} — {{ statement.party.name }}</h2>
                    <p class="text-xs text-slate-600">
                        الفترة: {{ statement.period.from || 'البداية' }} — {{ statement.period.to || 'اليوم' }}
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="text-xs text-slate-500">اسم {{ kind.label }}</div>
                        <div class="mt-1 font-bold text-slate-900">{{ statement.party.name }}</div>
                        <div v-if="statement.party.phone" class="text-xs text-slate-500" dir="ltr">
                            {{ statement.party.phone }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="text-xs text-slate-500">الرصيد الافتتاحي</div>
                        <div class="mt-1 font-bold text-slate-900">{{ formatMoney(statement.opening_balance) }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="text-xs text-slate-500">إجمالي المدين</div>
                        <div class="mt-1 font-bold text-slate-900">{{ formatMoney(statement.totals.debit) }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="text-xs text-slate-500">إجمالي الدائن</div>
                        <div class="mt-1 font-bold text-slate-900">{{ formatMoney(statement.totals.credit) }}</div>
                    </div>
                    <div class="rounded-xl border p-4" :class="balanceTone(statement.closing_balance)">
                        <div class="text-xs opacity-70">الرصيد الختامي</div>
                        <div class="mt-1 font-bold">{{ formatMoney(statement.closing_balance) }}</div>
                        <div class="text-xs opacity-70">{{ balanceHint(statement.closing_balance) }}</div>
                    </div>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                            <tr>
                                <th class="px-3 py-3">التاريخ</th>
                                <th class="px-3 py-3">البيان</th>
                                <th class="px-3 py-3">المرجع</th>
                                <th class="px-3 py-3 text-left">مدين</th>
                                <th class="px-3 py-3 text-left">دائن</th>
                                <th class="px-3 py-3 text-left">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="loading" class="text-slate-400">
                                <td colspan="6" class="px-3 py-8 text-center">جارٍ التحميل...</td>
                            </tr>

                            <template v-else>
                                <tr class="bg-slate-50/60 text-slate-600">
                                    <td class="px-3 py-2" dir="ltr">{{ statement.period.from || '—' }}</td>
                                    <td class="px-3 py-2 font-medium" colspan="2">
                                        رصيد ما قبله
                                        <span v-if="meta && meta.current_page > 1" class="text-xs text-slate-400">
                                            (حتى نهاية الصفحة السابقة)
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-left">—</td>
                                    <td class="px-3 py-2 text-left">—</td>
                                    <td class="px-3 py-2 text-left font-semibold" :class="amountClass(statement.page_opening_balance)">
                                        {{ formatMoney(statement.page_opening_balance) }}
                                    </td>
                                </tr>

                                <tr v-if="!statement.rows.length" class="text-slate-400">
                                    <td colspan="6" class="px-3 py-8 text-center">لا توجد حركات في الفترة المحددة</td>
                                </tr>

                                <tr v-for="row in statement.rows" :key="row.id" class="align-top">
                                    <td class="px-3 py-2 text-slate-600" dir="ltr">{{ row.transaction_date }}</td>
                                    <td class="px-3 py-2 text-slate-900">
                                        <span class="font-medium">{{ row.type_label }}</span>
                                        <div v-if="row.description" class="text-xs text-slate-500">{{ row.description }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600" dir="ltr">
                                        <RouterLink
                                            v-if="row.reference_no && referenceRoute(row.reference, row)"
                                            :to="referenceRoute(row.reference, row)"
                                            class="text-slate-700 underline decoration-dotted hover:text-slate-900 print:no-underline"
                                        >
                                            {{ row.reference_no }}
                                        </RouterLink>
                                        <span v-else>{{ row.reference_no || '—' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-left text-slate-900">{{ formatAmount(row.debit) }}</td>
                                    <td class="px-3 py-2 text-left text-slate-900">{{ formatAmount(row.credit) }}</td>
                                    <td class="px-3 py-2 text-left font-semibold" :class="amountClass(row.running_balance)">
                                        {{ formatMoney(row.running_balance) }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot v-if="!loading" class="border-t-2 border-slate-200 text-sm font-semibold text-slate-900">
                            <tr>
                                <td class="px-3 py-3" colspan="3">إجمالي الفترة</td>
                                <td class="px-3 py-3 text-left">{{ formatMoney(statement.totals.debit) }}</td>
                                <td class="px-3 py-3 text-left">{{ formatMoney(statement.totals.credit) }}</td>
                                <td class="px-3 py-3 text-left" :class="amountClass(statement.closing_balance)">
                                    {{ formatMoney(statement.closing_balance) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div
                    v-if="meta && meta.total"
                    class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3 text-xs text-slate-500 print:hidden"
                >
                    <span>صفحة {{ meta.current_page }} من {{ meta.last_page }} — {{ meta.total }} حركة</span>
                    <div class="flex gap-2">
                        <button
                            :disabled="meta.current_page <= 1 || loading"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40"
                            @click="goTo(meta.current_page - 1)"
                        >
                            السابق
                        </button>
                        <button
                            :disabled="meta.current_page >= meta.last_page || loading"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40"
                            @click="goTo(meta.current_page + 1)"
                        >
                            التالي
                        </button>
                    </div>
                </div>
            </template>

            <div v-else-if="loading" class="py-10 text-center text-sm text-slate-400">جارٍ التحميل...</div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { documentsApi, statementsApi } from '../../services/api';
import { usePdf } from '../../composables/usePdf';

const route = useRoute();

/**
 * الشاشة نفسها تخدم ثلاثة دفاتر. كل ما يتغيّر بينها المسمّيات ومصدر البيانات،
 * أمّا الحساب والعرض فواحد — والخادم هو من يحسب الأرصدة في الحالات الثلاث.
 */
const KINDS = {
    customer: {
        key: 'customer',
        label: 'عميل',
        // موجب = الطرف مدين لنا، سالب = نحن مدينون له.
        owed: 'مستحق على العميل',
        prepaid: 'رصيد لصالح العميل',
        fetch: (id, params) => statementsApi.customer(id, params),
        pdf: (id, params) => documentsApi.customerStatement(id, params),
    },
    supplier: {
        key: 'supplier',
        label: 'مورد',
        owed: 'مستحق للمورد',
        prepaid: 'رصيد لصالحنا',
        fetch: (id, params) => statementsApi.supplier(id, params),
        pdf: (id, params) => documentsApi.supplierStatement(id, params),
    },
    technician: {
        key: 'technician',
        label: 'خبير صيانة',
        owed: 'مستحق للخبير',
        prepaid: 'مصروف زائد عن استحقاقه',
        fetch: (id, params) => statementsApi.technician(id, params),
        // لا يوجد مستند PDF لكشف الخبير بعد؛ الطباعة من المتصفّح تغطّيه.
        pdf: null,
    },
};

const kind = route.path.startsWith('/customers')
    ? KINDS.customer
    : route.path.startsWith('/technicians')
        ? KINDS.technician
        : KINDS.supplier;

const owedLabel = kind.owed;
const prepaidLabel = kind.prepaid;

const REFERENCE_ROUTES = {
    sales_invoice: 'sales.edit',
    purchase_invoice: 'purchases.edit',
    payment: 'payments.edit',
};

const statement = ref(null);
const meta = ref(null);
const loading = ref(false);
const error = ref('');
const page = ref(1);
const filters = reactive({ from: '', to: '', perPage: 50 });

const numberFormat = new Intl.NumberFormat('ar-SY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function formatMoney(value) {
    return numberFormat.format(Number(value) || 0);
}

// Blank out zero debit/credit cells so the eye follows the side that moved.
function formatAmount(value) {
    return Number(value) ? formatMoney(value) : '—';
}

function amountClass(value) {
    return Number(value) < 0 ? 'text-red-600' : '';
}

function balanceTone(value) {
    return Number(value) < 0
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : 'border-slate-200 text-slate-900';
}

function balanceHint(value) {
    const amount = Number(value) || 0;
    if (amount === 0) return 'الحساب مسدد';
    return amount > 0 ? owedLabel : prepaidLabel;
}

function referenceRoute(reference, row) {
    if (!reference) return null;

    // بلاغ الصيانة لا تُفتح له شاشة مستقلة، فيُفتح في قائمة البلاغات مبحوثاً
    // عنه برقم إيصاله.
    if (reference.kind === 'repair') {
        return row?.reference_no
            ? { name: 'repairs', query: { search: row.reference_no } }
            : null;
    }

    if (!REFERENCE_ROUTES[reference.kind]) return null;

    return { name: REFERENCE_ROUTES[reference.kind], params: { id: reference.id } };
}

function applyFilters() {
    page.value = 1;
    load();
}

function resetFilters() {
    filters.from = '';
    filters.to = '';
    applyFilters();
}

function goTo(target) {
    page.value = target;
}

const { generating, pdfError, openPdf } = usePdf();

function printSheet() {
    window.print();
}

// The PDF renders the whole period, not just the page on screen.
function downloadPdf() {
    const params = {};
    if (filters.from) params.from = filters.from;
    if (filters.to) params.to = filters.to;

    if (!kind.pdf) return;

    openPdf('statement', () => kind.pdf(route.params.id, params));
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const params = { page: page.value, per_page: filters.perPage };
        if (filters.from) params.from = filters.from;
        if (filters.to) params.to = filters.to;

        const result = await kind.fetch(route.params.id, params);

        statement.value = result.statement;
        meta.value = result.meta ?? null;
    } catch (e) {
        error.value = e?.response?.data?.message || 'تعذّر تحميل كشف الحساب';
    } finally {
        loading.value = false;
    }
}

watch(page, load);

onMounted(load);
</script>

<style>
@media print {
    @page {
        margin: 14mm;
    }

    body {
        background: #fff;
    }

    #statement-sheet {
        max-width: none;
    }

    #statement-sheet .shadow-sm {
        box-shadow: none;
    }

    #statement-sheet table {
        font-size: 11px;
    }

    #statement-sheet thead {
        display: table-header-group;
    }

    #statement-sheet tr {
        break-inside: avoid;
    }
}
</style>
