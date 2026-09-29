<template>
    <div class="space-y-5">
        <!-- ------------------------------------------------------------ head -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">المصاريف</h1>
                <p class="text-sm text-slate-500">
                    ما يخرج من الصندوق ولا يقابله مورد ولا فاتورة — إيجار، كهرباء، مواصلات، نثريات
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="canManageCategories"
                    type="button"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="categoriesOpen = true"
                >
                    أبواب الصرف
                </button>
                <button
                    type="button"
                    :disabled="!rows.length"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-40"
                    title="تصدير الصفحة المعروضة إلى ملف CSV"
                    @click="exportCsv"
                >
                    تصدير CSV
                </button>
                <button
                    v-if="can('expenses.create')"
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                    @click="toggleQuickAdd"
                >
                    {{ quickAddOpen ? 'إخفاء نموذج التسجيل' : '＋ مصروف جديد' }}
                </button>
            </div>
        </div>

        <!-- --------------------------------------------------------- summary -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="rounded-2xl border bg-white p-4 shadow-sm"
                :class="card.accent"
            >
                <p class="text-xs font-medium text-slate-500">{{ card.label }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">
                    {{ card.value }}
                    <span class="text-xs font-normal text-slate-400">{{ CURRENCY }}</span>
                </p>
                <p class="mt-0.5 text-xs text-slate-400">{{ card.hint }}</p>
            </div>
        </div>

        <!-- ------------------------------------------------------- quick add -->
        <div v-if="can('expenses.create') && quickAddOpen" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 font-bold text-slate-900">تسجيل مصروف</h2>
            <ExpenseForm
                ref="quickForm"
                :categories="activeCategories"
                :payee-suggestions="payeeSuggestions"
                :can-manage-categories="canManageCategories"
                @saved="onSaved"
                @manage-categories="categoriesOpen = true"
            />
        </div>

        <!-- ------------------------------------------------------------ list -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="space-y-3 border-b border-slate-200 p-4">
                <!-- الفترة أوّل ما يُرشَّح به الصندوق، فلها أزرارها بدل حقلَي
                     تاريخ يُملآن باليد في كل مرة. -->
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="period in PERIODS"
                        :key="period.label"
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition sm:text-sm"
                        :class="activePeriod === period.label
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                        @click="applyPeriod(period)"
                    >
                        {{ period.label }}
                    </button>
                    <span class="mx-1 hidden h-5 w-px bg-slate-200 sm:block"></span>
                    <input
                        v-model="from"
                        type="date"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
                        title="من تاريخ"
                    />
                    <input
                        v-model="to"
                        type="date"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
                        title="إلى تاريخ"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <input
                        v-model.trim="search"
                        type="search"
                        placeholder="بحث برقم السند أو المستفيد أو البيان..."
                        class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    />
                    <select
                        v-model="categoryFilter"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                    >
                        <option value="">كل الأبواب</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                    </select>
                    <select
                        v-model="methodFilter"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                    >
                        <option value="">كل الطرق</option>
                        <option v-for="method in PAYMENT_METHODS" :key="method.value" :value="method.value">{{ method.label }}</option>
                    </select>
                    <select
                        v-model="statusFilter"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                    >
                        <option value="">المسجّل والملغى</option>
                        <option value="POSTED">المسجّل فقط</option>
                        <option value="CANCELLED">الملغى فقط</option>
                    </select>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="text-xs font-medium text-blue-600 underline-offset-2 hover:underline"
                        @click="clearFilters"
                    >
                        مسح الترشيح
                    </button>
                </div>

                <!-- أين ذهب المال في ما رُشِّح: سطر واحد يغني عن تقرير. -->
                <div v-if="byCategory.length > 1" class="flex flex-wrap gap-2 pt-1">
                    <button
                        v-for="slice in byCategory"
                        :key="slice.id"
                        type="button"
                        class="group rounded-lg border border-slate-200 px-2.5 py-1.5 text-start transition hover:border-slate-300 hover:bg-slate-50"
                        :class="categoryFilter === slice.id ? 'border-slate-900 bg-slate-50' : ''"
                        :title="`ترشيح على ${slice.name}`"
                        @click="categoryFilter = categoryFilter === slice.id ? '' : slice.id"
                    >
                        <span class="block text-[11px] text-slate-500">{{ slice.name }}</span>
                        <span class="block text-xs font-semibold tabular-nums text-slate-800">{{ formatMoney(slice.amount) }}</span>
                        <span class="mt-1 block h-1 w-16 overflow-hidden rounded-full bg-slate-100">
                            <span class="block h-full rounded-full bg-slate-400" :style="{ width: shareOf(slice) }"></span>
                        </span>
                    </button>
                </div>
            </div>

            <TableSkeleton v-if="loading && !rows.length" :columns="7" />

            <EmptyState
                v-else-if="!rows.length"
                icon="📤"
                :title="hasFilters ? 'لا مصاريف مطابقة للترشيح' : 'لم يُسجَّل أي مصروف بعد'"
                :hint="hasFilters ? 'جرّب فترة أوسع أو امسح الترشيح' : 'سجّل أول مصروف ليبدأ الصندوق بحساب ما يخرج منه'"
                :action-label="hasFilters ? 'مسح الترشيح' : ''"
                @action="clearFilters"
            />

            <div v-else class="overflow-x-auto" :class="loading ? 'opacity-50' : ''" :aria-busy="loading">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-4 py-3">رقم السند</th>
                            <th class="px-4 py-3">التاريخ</th>
                            <th class="px-4 py-3">الباب</th>
                            <th class="px-4 py-3">المستفيد والبيان</th>
                            <th class="px-4 py-3">الطريقة</th>
                            <th class="px-3 py-3 text-left">المبلغ</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="transition hover:bg-slate-50"
                            :class="row.status === 'CANCELLED' ? 'bg-slate-50/60 text-slate-400' : ''"
                        >
                            <td class="px-4 py-3">
                                <span class="font-semibold" :class="row.status === 'CANCELLED' ? 'text-slate-400' : 'text-slate-900'" dir="ltr">
                                    {{ row.expense_no }}
                                </span>
                                <StatusBadge v-if="row.status === 'CANCELLED'" class="ms-2" status="CANCELLED" />
                            </td>
                            <td class="px-4 py-3" dir="ltr">{{ row.expense_date }}</td>
                            <td class="px-4 py-3">{{ row.category_name || '—' }}</td>
                            <td class="max-w-xs px-4 py-3">
                                <span class="block truncate">{{ row.payee || '—' }}</span>
                                <span v-if="row.notes" class="block truncate text-xs text-slate-400">{{ row.notes }}</span>
                                <span v-if="row.cancellation_reason" class="block truncate text-xs text-orange-600">
                                    سبب الإلغاء: {{ row.cancellation_reason }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ paymentMethodLabel(row.payment_method) }}</td>
                            <td class="px-3 py-3 text-left font-semibold tabular-nums" :class="row.status === 'CANCELLED' ? 'text-slate-400 line-through' : 'text-slate-900'">
                                {{ formatMoney(row.amount) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" class="text-slate-500 transition hover:text-slate-900" @click="detail = row">
                                        عرض
                                    </button>
                                    <button
                                        v-if="row.status === 'POSTED' && can('expenses.edit')"
                                        type="button"
                                        class="text-slate-500 transition hover:text-slate-900"
                                        @click="editing = row"
                                    >
                                        تعديل
                                    </button>
                                    <button
                                        v-if="row.status === 'POSTED' && can('expenses.cancel')"
                                        type="button"
                                        class="text-orange-600 transition hover:text-orange-800"
                                        @click="askCancel(row)"
                                    >
                                        إلغاء
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pager v-if="meta && rows.length" :meta="meta" unit="مصروف" @go="goToPage" @per-page="setPerPage" />
        </div>

        <!-- ---------------------------------------------------------- modals -->
        <BaseModal
            :open="Boolean(editing)"
            title="تعديل مصروف"
            :subtitle="editing?.expense_no"
            :close-on-overlay="false"
            @close="editing = null"
        >
            <ExpenseForm
                v-if="editing"
                :expense="editing"
                :categories="editableCategories"
                :payee-suggestions="payeeSuggestions"
                :can-manage-categories="canManageCategories"
                autofocus
                @saved="onSaved"
                @close="editing = null"
                @manage-categories="categoriesOpen = true"
            />
        </BaseModal>

        <BaseModal :open="Boolean(detail)" :title="`المصروف ${detail?.expense_no || ''}`" @close="detail = null">
            <dl v-if="detail" class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-500">الباب</dt><dd class="font-medium">{{ detail.category_name || '—' }}</dd></div>
                <div><dt class="text-slate-500">التاريخ</dt><dd dir="ltr">{{ detail.expense_date }}</dd></div>
                <div><dt class="text-slate-500">المبلغ</dt><dd class="font-bold tabular-nums">{{ formatMoney(detail.amount) }} {{ CURRENCY }}</dd></div>
                <div><dt class="text-slate-500">الطريقة</dt><dd>{{ paymentMethodLabel(detail.payment_method) }}</dd></div>
                <div><dt class="text-slate-500">المستفيد</dt><dd>{{ detail.payee || '—' }}</dd></div>
                <div><dt class="text-slate-500">سجّله</dt><dd>{{ detail.recorded_by_name || '—' }}</dd></div>
                <div class="col-span-2"><dt class="text-slate-500">البيان</dt><dd>{{ detail.notes || '—' }}</dd></div>
                <div v-if="detail.cancellation_reason" class="col-span-2 rounded-lg bg-orange-50 px-3 py-2 text-orange-700">
                    أُلغي {{ formatDateTime(detail.cancelled_at) }} — {{ detail.cancellation_reason }}
                </div>
            </dl>
        </BaseModal>

        <!-- الإلغاء لا يُحذف السند ولا رقمه، فالسبب هو كل ما يبقى ليُقرأ لاحقاً. -->
        <BaseModal
            :open="Boolean(cancelling)"
            title="إلغاء مصروف"
            :subtitle="cancelling?.expense_no"
            :close-on-overlay="false"
            @close="cancelling = null"
        >
            <form v-if="cancelling" class="space-y-4" @submit.prevent="confirmCancel">
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    <b class="tabular-nums text-slate-900">{{ formatMoney(cancelling.amount) }} {{ CURRENCY }}</b>
                    · {{ cancelling.category_name }} · {{ cancelling.expense_date }}
                </p>
                <p class="text-sm text-slate-500">
                    يبقى السند في القائمة برقمه، ويخرج من كل المجاميع. لا يمكن التراجع عن الإلغاء.
                </p>
                <Field label="سبب الإلغاء" required>
                    <textarea
                        ref="reasonInput"
                        v-model.trim="cancelReason"
                        rows="2"
                        maxlength="255"
                        placeholder="سُجّل مرتين، مبلغ خاطئ، ..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    ></textarea>
                    <p v-if="cancelError" class="mt-1 text-xs text-red-600">{{ cancelError }}</p>
                </Field>
                <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="cancelling = null"
                    >
                        تراجع
                    </button>
                    <button
                        type="submit"
                        :disabled="cancelBusy || cancelReason.length < 3"
                        class="rounded-lg bg-orange-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:opacity-50"
                    >
                        {{ cancelBusy ? 'جارٍ الإلغاء...' : 'إلغاء المصروف' }}
                    </button>
                </div>
            </form>
        </BaseModal>

        <ExpenseCategoriesModal
            :open="categoriesOpen"
            :categories="categories"
            @close="categoriesOpen = false"
            @changed="loadCategories"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import Field from '../../components/ui/FormField.vue';
import Pager from '../../components/ui/Pager.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import TableSkeleton from '../../components/ui/TableSkeleton.vue';
import ExpenseForm from '../../components/expenses/ExpenseForm.vue';
import ExpenseCategoriesModal from '../../components/expenses/ExpenseCategoriesModal.vue';
import { expenseCategoriesApi, expensesApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { useToast } from '../../composables/useToast';
import { PAYMENT_METHODS, paymentMethodLabel } from '../../constants/paymentMethods';
import { csvNumber, downloadCsv } from '../../utils/csv';
import { apiMessage, formatDateTime, formatMoney, isoDate } from '../../utils/format';

const { can } = usePermissions();
const toast = useToast();

const CURRENCY = 'ل.س';
/** فتح نموذج التسجيل تفضيل شاشة واحدة، فيُحفظ في المتصفح لا على الحساب. */
const QUICK_ADD_KEY = 'expenses.quickAdd';

const PERIODS = [
    { label: 'اليوم', from: () => isoDate(), to: () => isoDate() },
    { label: 'هذا الشهر', from: () => monthStart(), to: () => isoDate() },
    { label: 'الشهر الماضي', from: () => monthStart(-1), to: () => monthEnd(-1) },
    { label: 'الكل', from: () => '', to: () => '' },
];

const rows = ref([]);
const meta = ref(null);
const summary = ref(null);
const categories = ref([]);
const loading = ref(false);
const search = ref('');
const categoryFilter = ref('');
const methodFilter = ref('');
const statusFilter = ref('');
const from = ref(monthStart());
const to = ref(isoDate());
const page = ref(1);
const perPage = ref(25);
const detail = ref(null);
const editing = ref(null);
const cancelling = ref(null);
const cancelReason = ref('');
const cancelError = ref('');
const cancelBusy = ref(false);
const reasonInput = ref(null);
const categoriesOpen = ref(false);
const quickForm = ref(null);
const quickAddOpen = ref(readQuickAdd());

let debounce = null;

const canManageCategories = computed(() => can('expenses.create') || can('expenses.edit'));

/** التسجيل الجديد لا يُعرض عليه باب معطَّل؛ التعديل يُبقي باب السند كما هو. */
const activeCategories = computed(() => categories.value.filter((category) => category.is_active));

const editableCategories = computed(() => {
    const current = categories.value.find((category) => category.id === editing.value?.expense_category_id);
    return current && !current.is_active ? [current, ...activeCategories.value] : activeCategories.value;
});

const byCategory = computed(() => summary.value?.by_category ?? []);

/**
 * أسماء مستفيدين سبق صرفها — تُلتقط من الصفحة المعروضة، فهي اقتراح يوفّر
 * كتابة لا قائمة مرجعية تستحق طلباً خاصاً.
 */
const payeeSuggestions = computed(() =>
    [...new Set(rows.value.map((row) => row.payee).filter(Boolean))].slice(0, 20)
);

const hasFilters = computed(() =>
    Boolean(search.value || categoryFilter.value || methodFilter.value || statusFilter.value || from.value || to.value)
);

const activePeriod = computed(() =>
    PERIODS.find((period) => period.from() === from.value && period.to() === to.value)?.label ?? ''
);

const summaryCards = computed(() => [
    {
        label: 'مصروف اليوم',
        value: formatMoney(summary.value?.today ?? 0),
        hint: 'كل ما خرج من الصندوق منذ الصباح',
        accent: 'border-slate-200',
    },
    {
        label: 'مصروف هذا الشهر',
        value: formatMoney(summary.value?.month ?? 0),
        hint: 'من أول الشهر حتى آخره',
        accent: 'border-slate-200',
    },
    {
        label: 'مجموع المعروض',
        value: formatMoney(summary.value?.filtered?.amount ?? 0),
        hint: `${summary.value?.filtered?.count ?? 0} سند ضمن الترشيح الحالي — الملغى غير محسوب`,
        accent: 'border-blue-200 bg-blue-50/40',
    },
]);

function monthStart(offset = 0) {
    const d = new Date();
    return isoDate(new Date(d.getFullYear(), d.getMonth() + offset, 1));
}

function monthEnd(offset = 0) {
    const d = new Date();
    return isoDate(new Date(d.getFullYear(), d.getMonth() + offset + 1, 0));
}

function readQuickAdd() {
    try {
        return localStorage.getItem(QUICK_ADD_KEY) !== '0';
    } catch {
        // نافذة خاصة أو تخزين محجوب: النموذج يبقى مفتوحاً كما هو الافتراض.
        return true;
    }
}

function toggleQuickAdd() {
    quickAddOpen.value = !quickAddOpen.value;
    try {
        localStorage.setItem(QUICK_ADD_KEY, quickAddOpen.value ? '1' : '0');
    } catch {
        /* انظر readQuickAdd */
    }
    if (quickAddOpen.value) nextTick(() => quickForm.value?.focus());
}

function shareOf(slice) {
    const total = byCategory.value.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    if (!total) return '0%';
    return `${Math.max(4, Math.round((Number(slice.amount) / total) * 100))}%`;
}

function applyPeriod(period) {
    from.value = period.from();
    to.value = period.to();
}

function clearFilters() {
    search.value = '';
    categoryFilter.value = '';
    methodFilter.value = '';
    statusFilter.value = '';
    from.value = '';
    to.value = '';
}

watch([search, categoryFilter, methodFilter, statusFilter, from, to], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        page.value = 1;
        load();
    }, 350);
});

async function load() {
    loading.value = true;
    try {
        const data = await expensesApi.index({
            search: search.value || undefined,
            expense_category_id: categoryFilter.value || undefined,
            payment_method: methodFilter.value || undefined,
            status: statusFilter.value || undefined,
            from: from.value || undefined,
            to: to.value || undefined,
            page: page.value,
            per_page: perPage.value,
        });
        rows.value = data.data ?? [];
        meta.value = data.meta ?? null;
        summary.value = data.summary ?? null;
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تحميل المصاريف'));
    } finally {
        loading.value = false;
    }
}

async function loadCategories() {
    try {
        categories.value = await expenseCategoriesApi.index();
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تحميل أبواب الصرف'));
    }
}

function goToPage(next) {
    page.value = next;
    load();
}

function setPerPage(size) {
    perPage.value = size;
    page.value = 1;
    load();
}

/**
 * بعد كل حفظ تُعاد القائمة والمجاميع: الرقم الذي في الأعلى يجب أن يكون قد
 * تحرّك، وإلّا شكّ المستخدم في أن سنده سُجّل.
 */
function onSaved({ message, again }) {
    toast.success(message || 'تم حفظ المصروف');
    if (!again) editing.value = null;
    load();
    // باب استُعمل للتوّ يصعد في ترتيب الأزرار، فترتيبها يتبع عادة المحل.
    loadCategories();
}

function askCancel(row) {
    cancelling.value = row;
    cancelReason.value = '';
    cancelError.value = '';
    nextTick(() => reasonInput.value?.focus());
}

async function confirmCancel() {
    if (cancelReason.value.length < 3) {
        cancelError.value = 'اكتب سبباً واضحاً للإلغاء.';
        return;
    }

    cancelBusy.value = true;
    try {
        await expensesApi.cancel(cancelling.value.id, { reason: cancelReason.value });
        toast.success('تم إلغاء المصروف');
        cancelling.value = null;
        load();
    } catch (e) {
        cancelError.value = apiMessage(e, 'تعذّر الإلغاء');
    } finally {
        cancelBusy.value = false;
    }
}

function exportCsv() {
    const columns = [
        { label: 'رقم السند', value: (row) => row.expense_no },
        { label: 'التاريخ', value: (row) => row.expense_date },
        { label: 'الباب', value: (row) => row.category_name },
        { label: 'المستفيد', value: (row) => row.payee },
        { label: 'البيان', value: (row) => row.notes },
        { label: 'الطريقة', value: (row) => paymentMethodLabel(row.payment_method) },
        { label: 'المبلغ', value: (row) => csvNumber(row.amount) },
        { label: 'الحالة', value: (row) => (row.status === 'CANCELLED' ? 'ملغى' : 'مسجّل') },
    ];

    downloadCsv(`expenses-${from.value || 'all'}-${to.value || 'all'}`, columns, rows.value);
}

onMounted(() => {
    loadCategories();
    load();
});
</script>
