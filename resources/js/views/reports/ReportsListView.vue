<template>
    <div id="report-sheet" class="space-y-5">
        <!-- ------------------------------------------------ Top View Header -->
        <div class="flex flex-wrap items-start justify-between gap-4 print:hidden">
            <div>
                <div class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-xl text-blue-600 shadow-2xs">
                        {{ activeTab.icon }}
                    </span>
                    <div>
                        <h1 class="text-xl font-extrabold text-slate-900">{{ activeTab.label }}</h1>
                        <p class="text-xs text-slate-500">{{ activeTab.hint }}</p>
                    </div>
                </div>
            </div>

            <!-- Export and Print Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    :disabled="exporting || !hasRows"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="exportCsv"
                >
                    <span v-if="exporting" class="inline-block animate-spin text-xs">⏳</span>
                    <span v-else aria-hidden="true">📥</span>
                    {{ exporting ? 'جارٍ التصدير...' : 'تصدير CSV' }}
                </button>

                <button
                    type="button"
                    :disabled="!hasRows"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="printSheet"
                >
                    <span aria-hidden="true">🖨️</span>
                    طباعة
                </button>
            </div>
        </div>

        <!-- ------------------------------------------------ Categorized Tabs Bar -->
        <div class="space-y-2.5 print:hidden">
            <!-- Group Category Filter Pills -->
            <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-200 pb-2.5">
                <button
                    v-for="cat in categories"
                    :key="cat.key"
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="selectedCategory === cat.key
                        ? 'bg-slate-900 text-white shadow-xs'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    @click="setCategory(cat.key)"
                >
                    <span>{{ cat.label }}</span>
                    <span
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="selectedCategory === cat.key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'"
                    >
                        {{ tabCountFor(cat.key) }}
                    </span>
                </button>
            </div>

            <!-- Tab Buttons Scroll Container -->
            <div class="-mx-1 overflow-x-auto px-1 pb-1">
                <div ref="tablist" role="tablist" aria-label="التقارير" class="flex min-w-max gap-2">
                    <button
                        v-for="tab in visibleTabs"
                        :key="tab.key"
                        :id="`report-tab-${tab.key}`"
                        type="button"
                        role="tab"
                        :aria-selected="current === tab.key"
                        :aria-controls="`report-panel-${tab.key}`"
                        :tabindex="current === tab.key ? 0 : -1"
                        class="flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-medium transition"
                        :class="current === tab.key
                            ? 'bg-blue-600 text-white shadow-sm ring-2 ring-blue-600/30 font-semibold'
                            : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900'"
                        @click="selectTab(tab.key)"
                        @keydown="onTabKeydown"
                    >
                        <span aria-hidden="true" class="text-base">{{ tab.icon }}</span>
                        <span>{{ tab.label }}</span>
                        <span
                            v-if="tab.badge"
                            class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                            :class="current === tab.key ? 'bg-white/25 text-white' : 'bg-emerald-100 text-emerald-800'"
                        >
                            {{ tab.badge }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ------------------------------------------------ Main Report Panel -->
        <div
            :id="`report-panel-${current}`"
            role="tabpanel"
            :aria-labelledby="`report-tab-${current}`"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none"
        >
            <!-- Print Header: Clean store branding on printouts -->
            <div class="hidden border-b-2 border-slate-900 px-6 py-4 print:block">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-bold">دماتك للاتصالات وأنظمة الصيانة</h1>
                        <h2 class="text-base font-semibold text-slate-800 mt-1">{{ activeTab.label }}</h2>
                    </div>
                    <div class="text-left text-xs text-slate-500">
                        <div>طُبع في {{ printedAt }}</div>
                        <div v-if="filterSummary" class="mt-0.5">{{ filterSummary }}</div>
                    </div>
                </div>
            </div>

            <!-- ------------------------------------------------ Filter Bar -->
            <div class="border-b border-slate-100 bg-slate-50/70 p-4 print:hidden">
                <!-- Date Presets Row -->
                <div v-if="showDates" class="mb-3.5 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500">فترات سريعة:</span>
                    <button
                        v-for="preset in presets"
                        :key="preset.key"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="activePreset === preset.key
                            ? 'bg-blue-600 text-white font-semibold shadow-2xs'
                            : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-100'"
                        @click="applyPreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <div class="flex flex-wrap items-end gap-3.5">
                    <!-- Date Pickers -->
                    <template v-if="showDates">
                        <label class="text-xs font-semibold text-slate-600">
                            من تاريخ
                            <input
                                v-model="filters.from"
                                type="date"
                                class="mt-1 block rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                @keyup.enter="applyFilters"
                            />
                        </label>
                        <label class="text-xs font-semibold text-slate-600">
                            إلى تاريخ
                            <input
                                v-model="filters.to"
                                type="date"
                                :min="filters.from || undefined"
                                class="mt-1 block rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                @keyup.enter="applyFilters"
                            />
                        </label>
                    </template>

                    <!-- Search Box -->
                    <label v-if="showSearch" class="text-xs font-semibold text-slate-600">
                        بحث
                        <input
                            v-model="filters.search"
                            type="search"
                            :placeholder="current === 'low-stock' ? 'اسم المادة أو الرمز...' : 'الاسم أو الشركة أو الهاتف...'"
                            class="mt-1 block w-full rounded-xl sm:w-60 border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            @keyup.enter="applyFilters"
                        />
                    </label>

                    <!-- Party Ledger Filters -->
                    <template v-if="partyTabs.includes(current)">
                        <label class="text-xs font-semibold text-slate-600">
                            الرصيد بتاريخ
                            <input
                                v-model="filters.asOf"
                                type="date"
                                class="mt-1 block rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                @keyup.enter="applyFilters"
                            />
                        </label>
                        <label class="flex items-center gap-2 pb-2.5 text-sm font-medium text-slate-700">
                            <input v-model="filters.onlyOutstanding" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            غير المسددة فقط
                        </label>
                    </template>

                    <!-- Stock Movement Filters -->
                    <template v-if="current === 'stock-movements'">
                        <label class="text-xs font-semibold text-slate-600">
                            المادة
                            <select
                                v-model="filters.materialId"
                                class="mt-1 block w-full rounded-xl sm:w-52 border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option :value="null">كل المواد</option>
                                <option v-for="material in materials" :key="material.id" :value="material.id">
                                    {{ material.name }}
                                </option>
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-slate-600">
                            نوع الحركة
                            <select
                                v-model="filters.movementType"
                                class="mt-1 block rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-2xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            >
                                <option :value="null">الكل</option>
                                <option v-for="type in movementTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                        </label>
                    </template>

                    <!-- Filter Action Buttons -->
                    <div class="flex flex-wrap items-center gap-2 pb-0.5">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-2xs transition hover:bg-blue-700 focus:ring-2 focus:ring-blue-500/40"
                            :class="dirty ? 'ring-2 ring-blue-400' : ''"
                            @click="applyFilters"
                        >
                            <span>🔍</span>
                            عرض
                        </button>

                        <button
                            v-if="canReset"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-2xs transition hover:bg-slate-50"
                            @click="resetFilters"
                        >
                            <span>↺</span>
                            إعادة تعيين
                        </button>

                        <!-- Unapplied changes warning -->
                        <span v-if="dirty" class="flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-blue-600 animate-pulse"></span>
                            تغييرات غير مطبّقة
                        </span>
                    </div>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="error" class="m-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <span>{{ error }}</span>
                <button
                    type="button"
                    class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 shadow-2xs transition hover:bg-red-50 print:hidden"
                    @click="load"
                >
                    إعادة المحاولة
                </button>
            </div>

            <!-- Main Content Area -->
            <div :aria-busy="loading" class="pt-4">
                <TableSkeleton v-if="loading" :columns="skeletonColumns" />

                <template v-else-if="!error">
                    <EmptyState
                        v-if="isEmpty"
                        :icon="emptyState.icon"
                        :title="emptyState.title"
                        :hint="emptyState.hint"
                        :action-label="canReset ? 'إعادة تعيين التصفية' : ''"
                        @action="resetFilters"
                    />

                    <template v-else>
                        <!-- Profit & Loss Report -->
                        <ProfitReport
                            v-if="current === 'profit'"
                            :summary="profitSummaryData"
                            :show-repairs="showRepairs"
                            :show-technicians="showTechnicians"
                            :show-services="showServices"
                        />

                        <!-- Low Stock Alerts -->
                        <LowStockReport
                            v-else-if="current === 'low-stock'"
                            :rows="lowStock"
                        />

                        <!-- Sales or Purchases Periodic Summary -->
                        <PeriodSummaryReport
                            v-else-if="periodTabs.includes(current)"
                            :summary="summary"
                            :kind="current"
                        />

                        <!-- Customer / Supplier Balances -->
                        <PartyBalancesReport
                            v-else-if="partyTabs.includes(current)"
                            :data="pagedReport"
                            :kind="current"
                            :as-of="filters.asOf"
                        />

                        <!-- Technician Accounts & Repairs Breakdown -->
                        <TechnicianAccountsReport
                            v-else-if="technicianTabs.includes(current)"
                            :data="pagedReport"
                            :kind="current"
                            :from="filters.from"
                            :to="filters.to"
                        />

                        <!-- Stock Movement Log -->
                        <StockMovementsReport
                            v-else
                            :data="pagedReport"
                        />
                    </template>
                </template>
            </div>

            <!-- Pagination Footer for Paginated Reports -->
            <InfiniteScrollFooter
                v-if="isPaged && !loading && !error"
                :shown="pagedRows.length"
                :total="meta?.total ?? 0"
                :has-more="hasMore"
                :loading-more="loadingMore"
                :error="moreError"
                :unit="activeTab.unit"
                @more="loadMore"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import EmptyState from '../../components/ui/EmptyState.vue';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import TableSkeleton from '../../components/ui/TableSkeleton.vue';
import LowStockReport from '../../components/reports/LowStockReport.vue';
import ProfitReport from '../../components/reports/ProfitReport.vue';
import PartyBalancesReport from '../../components/reports/PartyBalancesReport.vue';
import TechnicianAccountsReport from '../../components/reports/TechnicianAccountsReport.vue';
import PeriodSummaryReport from '../../components/reports/PeriodSummaryReport.vue';
import StockMovementsReport from '../../components/reports/StockMovementsReport.vue';
import { materialsApi, reportsApi } from '../../services/api';
import { useInfiniteList } from '../../composables/useInfiniteList';
import { useToast } from '../../composables/useToast';
import { csvNumber, downloadCsv } from '../../utils/csv';
import { isoDate } from '../../utils/format';
import { BASE_SYMBOL } from '../../constants/currency';
import { usePermissions } from '../../composables/usePermissions';
import { useFeaturesStore } from '../../stores/features';

const { can } = usePermissions();
const features = useFeaturesStore();

const categoryOptions = [
    { key: 'all', label: 'كل التقارير' },
    { key: 'finance', label: '💰 المالية والأرباح' },
    { key: 'inventory', label: '📦 المخزون' },
    { key: 'repairs', label: '🔧 الصيانة' },
];

const selectedCategory = ref('all');

const allTabs = [
    {
        key: 'profit',
        group: 'finance',
        icon: '📈',
        label: 'الأرباح والخسائر',
        badge: 'جديد',
        unit: 'يوم',
        hint: 'تقرير الأرباح الشامل: الإيرادات، تكلفة المواد المباعة، المصاريف التشغيلية، وصافي الربح المحقق',
    },
    {
        key: 'sales',
        group: 'finance',
        icon: '🧾',
        label: 'ملخص المبيعات',
        unit: 'يوم',
        hint: 'إجماليات المبيعات المعتمدة وتفصيلها اليومي خلال فترة',
    },
    {
        key: 'purchases',
        group: 'finance',
        icon: '📦',
        label: 'ملخص المشتريات',
        unit: 'يوم',
        hint: 'إجماليات المشتريات المعتمدة وتفصيلها اليومي خلال فترة',
    },
    {
        key: 'receivables',
        group: 'finance',
        icon: '💰',
        label: 'ذمم العملاء',
        unit: 'حساب',
        hint: 'أرصدة العملاء المستحقة لنا، من الأكبر إلى الأصغر',
    },
    {
        key: 'payables',
        group: 'finance',
        icon: '💳',
        label: 'ذمم الموردين',
        unit: 'حساب',
        hint: 'أرصدة الموردين المستحقة علينا، من الأكبر إلى الأصغر',
    },
    {
        key: 'low-stock',
        group: 'inventory',
        icon: '📉',
        label: 'المخزون المنخفض',
        unit: 'مادة',
        hint: 'المواد التي بلغت حدّها الأدنى أو نفدت، مرتّبة من الأشدّ نقصًا',
    },
    {
        key: 'stock-movements',
        group: 'inventory',
        icon: '🔄',
        label: 'حركات المخزون',
        unit: 'حركة',
        hint: 'سجل دخول وخروج المواد؛ اختيار مادة واحدة يُظهر الرصيد بعد كل حركة',
    },
    {
        key: 'technicians',
        group: 'repairs',
        icon: '🧰',
        label: 'محاسبة خبراء الصيانة',
        unit: 'خبير',
        permission: 'reports.technicians',
        features: ['repairs', 'technicians'],
        hint: 'ما استحقّه كل خبير في الفترة وما صُرف له، ورصيده القائم',
    },
    {
        key: 'technician-repairs',
        group: 'repairs',
        icon: '🔧',
        label: 'عمولات بلاغ بلاغ',
        unit: 'بلاغ',
        permission: 'reports.technician_repairs',
        features: ['repairs', 'technicians'],
        hint: 'كل بلاغ سُوِّي في الفترة، بتكلفته ونصيب الخبير منه',
    },
];

/**
 * A tab may need its own permission (on top of reports.view) and its modules switched on.
 * A switched-off module hides its tabs even for Master.
 * Permissions and features are loaded by the router guard before this view opens.
 */
function tabAllowed(tab) {
    if (tab.permission && !can(tab.permission)) return false;
    return (tab.features ?? []).every((f) => features.isEnabled(f));
}

// Repair revenue, technician commissions and top services drop out of the profit report with their modules.
const showRepairs = features.isEnabled('repairs');
const showTechnicians = showRepairs && features.isEnabled('technicians');
const showServices = features.isEnabled('services');

const tabs = allTabs.filter(tabAllowed);

// Categories with no tab left for this user are dropped.
const categories = categoryOptions.filter((cat) => cat.key === 'all' || tabs.some((tab) => tab.group === cat.key));

const periodTabs = ['sales', 'purchases'];
const partyTabs = ['receivables', 'payables'];
const technicianTabs = ['technicians', 'technician-repairs'];

const movementTypes = [
    { value: 'PURCHASE', label: 'شراء' },
    { value: 'SALE', label: 'بيع' },
    { value: 'ADJUSTMENT', label: 'تسوية مخزون' },
    { value: 'PURCHASE_RETURN', label: 'مرتجع شراء' },
    { value: 'SALE_RETURN', label: 'مرتجع بيع' },
    { value: 'OPENING', label: 'رصيد افتتاحي' },
];

const PAGE_SIZE = 50;
const EXPORT_PAGE_SIZE = 200;
const MAX_EXPORT_ROWS = 5000;

const route = useRoute();
const router = useRouter();
const toast = useToast();

const tablist = ref(null);
const current = ref('profit');
const loading = ref(true);
const exporting = ref(false);
const error = ref('');

/** Bumped on every fresh load so a late response can't land on a newer report. */
let requestId = 0;
/** A failed paged load, handed back from the list so load() can report it. */
let pagedError = null;

const profitSummaryData = ref(null);
const lowStock = ref([]);
const summary = ref(null);
const materials = ref([]);

// The paginated tabs (balances, technicians, movements) grow as the user scrolls.
const {
    items: pagedRows, meta: pagedMeta, response: pagedResponse, loadingMore, moreError, hasMore,
    reload: reloadPaged, loadMore,
} = useInfiniteList({
    fetch: (page, perPage) => fetcher()(requestParams({ page, per_page: perPage })),
    pageSize: PAGE_SIZE,
    maxPerPage: 200,
    select: (result) => ({ rows: result?.rows ?? [], meta: result?.meta ?? null }),
    onError: (e) => {
        pagedError = e;
    },
});

const defaults = () => ({
    from: isoDate(new Date(Date.now() - 29 * 864e5)),
    to: isoDate(),
    asOf: '',
    search: '',
    onlyOutstanding: true,
    materialId: null,
    movementType: null,
});

const filters = reactive(defaults());

/** Snapshot of the filters behind the data on screen, for the "unapplied" hint. */
const applied = ref('');

const visibleTabs = computed(() => {
    if (selectedCategory.value === 'all') return tabs;
    return tabs.filter((t) => t.group === selectedCategory.value);
});

function tabCountFor(categoryKey) {
    if (categoryKey === 'all') return tabs.length;
    return tabs.filter((t) => t.group === categoryKey).length;
}

function setCategory(catKey) {
    selectedCategory.value = catKey;
    // If current tab is not in selected category, pick first in that category
    const inCategory = visibleTabs.value.some((t) => t.key === current.value);
    if (!inCategory && visibleTabs.value.length) {
        selectTab(visibleTabs.value[0].key);
    }
}

const activeTab = computed(() => tabs.find((tab) => tab.key === current.value) ?? tabs[0]);

const showDates = computed(() =>
    current.value === 'profit'
    || periodTabs.includes(current.value)
    || technicianTabs.includes(current.value)
    || current.value === 'stock-movements'
);

const showSearch = computed(() =>
    current.value === 'low-stock' || partyTabs.includes(current.value) || current.value === 'technicians'
);

const presets = computed(() => {
    const list = [
        { key: 'today', label: 'اليوم' },
        { key: 'week', label: 'آخر ٧ أيام' },
        { key: 'month', label: 'هذا الشهر' },
        { key: 'last-month', label: 'الشهر الماضي' },
        { key: '30', label: 'آخر ٣٠ يومًا' },
        { key: 'quarter', label: 'هذا الربع' },
        { key: 'year', label: 'هذه السنة' },
    ];

    // Only the movement log can run unbounded; the summaries require a period.
    return current.value === 'stock-movements'
        ? [...list, { key: 'all', label: 'كل الفترات' }]
        : list;
});

const activePreset = computed(() =>
    presets.value.find((preset) => {
        const range = presetRange(preset.key);
        return range.from === filters.from && range.to === filters.to;
    })?.key ?? ''
);

const dirty = computed(() => filterSnapshot() !== applied.value);

const canReset = computed(() => filterSnapshot() !== snapshotOf(defaults()));

const skeletonColumns = computed(() => (current.value === 'stock-movements' ? 7 : 6));

const isPaged = computed(() =>
    partyTabs.includes(current.value) || technicianTabs.includes(current.value) || current.value === 'stock-movements'
);

const meta = computed(() => (isPaged.value ? pagedMeta.value : null));

/** Totals cover the whole report; only the rows grow as the user scrolls. */
const pagedReport = computed(() => (pagedResponse.value ? { ...pagedResponse.value, rows: pagedRows.value } : null));

const hasRows = computed(() => !loading.value && !error.value && !isEmpty.value);

const isEmpty = computed(() => {
    if (current.value === 'profit') {
        return !profitSummaryData.value || (
            !Number(profitSummaryData.value.revenue?.total)
            && !Number(profitSummaryData.value.operating_expenses?.total)
            && !Number(profitSummaryData.value.technician_commissions?.total)
        );
    }
    if (current.value === 'low-stock') return !lowStock.value.length;
    if (periodTabs.includes(current.value)) return !summary.value || !Number(summary.value.count);
    return !pagedRows.value.length;
});

const emptyState = computed(() => {
    const filtered = canReset.value;

    if (current.value === 'profit') {
        return {
            icon: '📊',
            title: 'لا توجد حركات أرباح أو مصاريف في هذه الفترة',
            hint: filtered ? 'جرّب توسيع نطاق التواريخ أو اختيار فترة أخرى' : 'ستظهر أرباح وتكاليف الفواتير المعتمدة هنا فور تسجيلها',
        };
    }

    if (current.value === 'low-stock') {
        return filtered
            ? { icon: '🔍', title: 'لا توجد مواد مطابقة للبحث', hint: 'جرّب اسمًا أو رمزًا آخر' }
            : { icon: '✅', title: 'كل المواد ضمن الحد الآمن', hint: 'لا توجد مادة بلغت حدّها الأدنى' };
    }

    if (periodTabs.includes(current.value)) {
        return {
            icon: '📈',
            title: current.value === 'sales' ? 'لا توجد مبيعات معتمدة في هذه الفترة' : 'لا توجد مشتريات معتمدة في هذه الفترة',
            hint: 'وسّع الفترة أو تأكّد من اعتماد الفواتير',
        };
    }

    if (technicianTabs.includes(current.value)) {
        return current.value === 'technicians'
            ? { icon: '🧰', title: 'لا يوجد خبراء صيانة', hint: 'أضف خبيراً من شاشة خبراء الصيانة ليظهر هنا' }
            : { icon: '🔧', title: 'لا توجد بلاغات مسوّاة في هذه الفترة', hint: 'وسّع الفترة أو تأكّد من تسوية البلاغات' };
    }

    if (partyTabs.includes(current.value)) {
        return filters.onlyOutstanding
            ? {
                icon: '🎉',
                title: current.value === 'receivables' ? 'لا توجد ذمم على العملاء' : 'لا توجد ذمم للموردين',
                hint: 'أزل "غير المسددة فقط" لعرض كل الحسابات',
            }
            : { icon: '📂', title: 'لا توجد حسابات مطابقة', hint: 'جرّب تعديل البحث أو التاريخ' };
    }

    return {
        icon: '📦',
        title: 'لا توجد حركات مخزون',
        hint: filtered ? 'جرّب توسيع الفترة أو إزالة تصفية المادة' : 'ستظهر الحركات هنا بعد اعتماد أول فاتورة',
    };
});

const printedAt = computed(() =>
    new Intl.DateTimeFormat('ar-SY-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date())
);

const filterSummary = computed(() => {
    const parts = [];

    if (showDates.value && (filters.from || filters.to)) {
        parts.push(`الفترة: ${filters.from || 'البداية'} — ${filters.to || 'اليوم'}`);
    }

    if (partyTabs.includes(current.value)) {
        if (filters.asOf) parts.push(`الرصيد بتاريخ ${filters.asOf}`);
        parts.push(filters.onlyOutstanding ? 'الحسابات غير المسددة فقط' : 'كل الحسابات');
    }

    if (showSearch.value && filters.search) parts.push(`بحث: ${filters.search}`);

    if (current.value === 'stock-movements') {
        const material = materials.value.find((row) => row.id === filters.materialId);
        if (material) parts.push(`المادة: ${material.name}`);
        const type = movementTypes.find((row) => row.value === filters.movementType);
        if (type) parts.push(`نوع الحركة: ${type.label}`);
    }

    return parts.join(' · ');
});

// ------------------------------------------------------------------- filters

function snapshotOf(values) {
    const keys = ['onlyOutstanding'];
    if (showDates.value) keys.push('from', 'to');
    if (showSearch.value) keys.push('search');
    if (partyTabs.includes(current.value)) keys.push('asOf');
    if (current.value === 'stock-movements') keys.push('materialId', 'movementType');

    return JSON.stringify(keys.sort().map((key) => [key, values[key]]));
}

function filterSnapshot() {
    return snapshotOf(filters);
}

function presetRange(key) {
    const today = new Date();
    const start = (date) => isoDate(date);

    switch (key) {
        case 'today':
            return { from: start(today), to: start(today) };
        case 'week':
            return { from: start(new Date(Date.now() - 6 * 864e5)), to: start(today) };
        case 'month':
            return { from: start(new Date(today.getFullYear(), today.getMonth(), 1)), to: start(today) };
        case 'last-month':
            return {
                from: start(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
                to: start(new Date(today.getFullYear(), today.getMonth(), 0)),
            };
        case '30':
            return { from: start(new Date(Date.now() - 29 * 864e5)), to: start(today) };
        case 'quarter': {
            const quarterMonth = Math.floor(today.getMonth() / 3) * 3;
            return {
                from: start(new Date(today.getFullYear(), quarterMonth, 1)),
                to: start(today),
            };
        }
        case 'year':
            return { from: start(new Date(today.getFullYear(), 0, 1)), to: start(today) };
        case 'all':
            return { from: '', to: '' };
        default:
            return { from: filters.from, to: filters.to };
    }
}

function applyPreset(preset) {
    const range = presetRange(preset.key);
    filters.from = range.from;
    filters.to = range.to;
    applyFilters();
}

function applyFilters() {
    load();
}

function resetFilters() {
    Object.assign(filters, defaults());
    applyFilters();
}

function selectTab(key) {
    if (current.value === key) return;

    current.value = key;

    // Make sure the category matches if it's currently on a specific category
    const tabObj = tabs.find((t) => t.key === key);
    if (tabObj && selectedCategory.value !== 'all' && tabObj.group !== selectedCategory.value) {
        selectedCategory.value = 'all';
    }

    // Only the movement log may run without a period; carrying its empty dates
    // into a summary would just bounce off the API's "from is required".
    if ((key === 'profit' || periodTabs.includes(key)) && (!filters.from || !filters.to)) {
        const fallback = defaults();
        filters.from = fallback.from;
        filters.to = fallback.to;
    }

    load();
}

/** Arrow keys move between tabs, as a tablist is expected to. */
function onTabKeydown(event) {
    const offset = { ArrowRight: -1, ArrowLeft: 1, Home: 'first', End: 'last' }[event.key];
    if (offset === undefined) return;

    event.preventDefault();

    const currentList = visibleTabs.value;
    const index = currentList.findIndex((tab) => tab.key === current.value);
    const target = offset === 'first'
        ? 0
        : offset === 'last'
            ? currentList.length - 1
            : (index + offset + currentList.length) % currentList.length;

    selectTab(currentList[target].key);
    tablist.value?.children[target]?.focus();
}

function printSheet() {
    window.print();
}

// ----------------------------------------------------------------- url state

function currentQuery() {
    const query = { tab: current.value };

    if (showDates.value) {
        if (filters.from) query.from = filters.from;
        if (filters.to) query.to = filters.to;
    }

    if (showSearch.value && filters.search) query.search = filters.search;

    if (partyTabs.includes(current.value)) {
        if (filters.asOf) query.as_of = filters.asOf;
        if (!filters.onlyOutstanding) query.all = '1';
    }

    if (current.value === 'stock-movements') {
        if (filters.materialId) query.material_id = String(filters.materialId);
        if (filters.movementType) query.type = filters.movementType;
    }

    return query;
}

function readQuery(query) {
    const tab = tabs.find((item) => item.key === query.tab);
    current.value = tab ? tab.key : 'profit';

    Object.assign(filters, defaults());

    if (query.from !== undefined) filters.from = String(query.from);
    if (query.to !== undefined) filters.to = String(query.to);
    if (showDates.value && query.from === undefined && query.to === undefined && current.value === 'stock-movements') {
        // A shared movement-log link without dates means "everything".
        filters.from = '';
        filters.to = '';
    }
    if (query.search) filters.search = String(query.search);
    if (query.as_of) filters.asOf = String(query.as_of);
    if (query.all === '1') filters.onlyOutstanding = false;
    if (query.material_id) filters.materialId = Number(query.material_id);
    if (query.type) filters.movementType = String(query.type);
}

function syncQuery() {
    const query = currentQuery();
    if (JSON.stringify(query) === JSON.stringify(route.query)) return;
    // replace, not push: a filter change should not bury the previous screen.
    router.replace({ query }).catch(() => {});
}

// --------------------------------------------------------------------- loading

function requestParams(overrides = {}) {
    const params = { page: 1, per_page: PAGE_SIZE, ...overrides };

    if (current.value === 'low-stock') {
        return filters.search ? { search: filters.search } : {};
    }

    if (current.value === 'profit' || periodTabs.includes(current.value)) {
        return { from: filters.from, to: filters.to };
    }

    if (partyTabs.includes(current.value)) {
        params.only_outstanding = filters.onlyOutstanding ? 1 : 0;
        if (filters.asOf) params.as_of = filters.asOf;
        if (filters.search) params.search = filters.search;
        return params;
    }

    if (technicianTabs.includes(current.value)) {
        if (filters.from) params.from = filters.from;
        if (filters.to) params.to = filters.to;
        if (current.value === 'technicians' && filters.search) params.search = filters.search;
        return params;
    }

    if (filters.materialId) params.material_id = filters.materialId;
    if (filters.movementType) params.type = filters.movementType;
    if (filters.from) params.from = filters.from;
    if (filters.to) params.to = filters.to;

    return params;
}

/** Wrapped rather than handed over bare, so the client keeps its own `this`. */
function fetcher() {
    if (current.value === 'receivables') return (params) => reportsApi.receivables(params);
    if (current.value === 'payables') return (params) => reportsApi.payables(params);
    if (current.value === 'technicians') return (params) => reportsApi.technicianSummary(params);
    if (current.value === 'technician-repairs') return (params) => reportsApi.technicianRepairs(params);
    return (params) => reportsApi.stockMovements(params);
}

async function load() {
    const id = ++requestId;
    loading.value = true;
    error.value = '';

    try {
        if (current.value === 'profit') {
            profitSummaryData.value = await reportsApi.profitSummary(requestParams());
        } else if (current.value === 'low-stock') {
            lowStock.value = await reportsApi.lowStock(requestParams());
        } else if (periodTabs.includes(current.value)) {
            const params = requestParams();
            summary.value = current.value === 'sales'
                ? await reportsApi.salesSummary(params)
                : await reportsApi.purchasesSummary(params);
        } else {
            pagedError = null;
            await reloadPaged();
            if (pagedError) throw pagedError;
        }

        if (id !== requestId) return;
        applied.value = filterSnapshot();
        syncQuery();
    } catch (e) {
        if (id !== requestId) return;
        error.value = e?.response?.data?.message || 'تعذّر تحميل التقرير';
    } finally {
        if (id === requestId) loading.value = false;
    }
}

async function loadMaterials() {
    try {
        const response = await materialsApi.index({ per_page: 200 });
        materials.value = response.data ?? [];
    } catch {
        materials.value = [];
    }
}

// ---------------------------------------------------------------------- export

/** Paginated reports export every matching row, not just the page on screen. */
async function fetchAllRows() {
    const request = fetcher();
    const rows = [];
    let target = 1;

    for (;;) {
        const response = await request(requestParams({ page: target, per_page: EXPORT_PAGE_SIZE }));
        rows.push(...(response.rows ?? []));

        const last = response.meta?.last_page ?? 1;
        if (target >= last || rows.length >= MAX_EXPORT_ROWS) break;
        target += 1;
    }

    return rows.slice(0, MAX_EXPORT_ROWS);
}

async function exportPayload() {
    if (current.value === 'profit') {
        const p = profitSummaryData.value;
        const rows = (p.by_day ?? []).map((day) => ({
            date: day.date,
            invoices: day.count,
            revenue: day.revenue,
            cogs: day.cogs,
            gross_profit: day.gross_profit,
            expenses: day.expenses,
            commissions: day.commissions,
            operating_costs: day.operating_costs,
            net_profit: day.net_profit,
            margin: `${day.margin_percent}%`,
        }));

        rows.push({
            date: 'الإجمالي العام للفترة',
            invoices: p.revenue.invoices_count,
            revenue: p.revenue.total,
            cogs: p.cogs.total,
            gross_profit: p.gross_profit.total,
            expenses: p.operating_expenses.total,
            commissions: p.technician_commissions.total,
            operating_costs: p.operating_costs.total,
            net_profit: p.net_profit.total,
            margin: `${p.net_profit.margin_percent}%`,
        });

        return {
            filename: `profit-report-${filters.from}_${filters.to}`,
            columns: [
                { label: 'التاريخ', value: (row) => row.date },
                { label: 'عدد الفواتير', value: (row) => row.invoices },
                { label: `الإيرادات (${BASE_SYMBOL})`, value: (row) => csvNumber(row.revenue) },
                { label: `تكلفة المواد (COGS) (${BASE_SYMBOL})`, value: (row) => csvNumber(row.cogs) },
                { label: `إجمالي الربح (${BASE_SYMBOL})`, value: (row) => csvNumber(row.gross_profit) },
                { label: `المصاريف التشغيلية (${BASE_SYMBOL})`, value: (row) => csvNumber(row.expenses) },
                showTechnicians && { label: `عمولات الفنيين (${BASE_SYMBOL})`, value: (row) => csvNumber(row.commissions) },
                { label: `إجمالي التكاليف (${BASE_SYMBOL})`, value: (row) => csvNumber(row.operating_costs) },
                { label: `صافي الربح (${BASE_SYMBOL})`, value: (row) => csvNumber(row.net_profit) },
                { label: 'هامش الربح', value: (row) => row.margin },
            ].filter(Boolean),
            rows,
        };
    }

    if (current.value === 'low-stock') {
        return {
            filename: `low-stock-${isoDate()}`,
            columns: [
                { label: 'المادة', value: (row) => row.name },
                { label: 'الرمز', value: (row) => row.sku },
                { label: 'الوحدة', value: (row) => row.unit },
                { label: 'الكمية الحالية', value: (row) => csvNumber(row.stock_quantity, 3) },
                { label: 'الحد الأدنى', value: (row) => csvNumber(row.min_stock, 3) },
                { label: 'النقص', value: (row) => csvNumber(row.shortfall, 3) },
                { label: `تكلفة (${BASE_SYMBOL})`, value: (row) => csvNumber(row.cost_price) },
                { label: `سعر بيع (${BASE_SYMBOL})`, value: (row) => csvNumber(row.sale_price) },
                { label: 'الحالة', value: (row) => (row.is_out_of_stock ? 'نفد' : 'قرب النفاد') },
            ],
            rows: lowStock.value,
        };
    }

    if (periodTabs.includes(current.value)) {
        const rows = [
            ...(summary.value.by_day ?? []),
            {
                date: 'الإجمالي',
                count: summary.value.count,
                total: summary.value.total,
                paid: summary.value.paid,
                outstanding: summary.value.outstanding,
            },
        ];

        return {
            filename: `${current.value}-summary-${filters.from}_${filters.to}`,
            columns: [
                { label: 'التاريخ', value: (row) => row.date },
                { label: 'العدد', value: (row) => row.count },
                { label: `الإجمالي (${BASE_SYMBOL})`, value: (row) => csvNumber(row.total) },
                { label: `المدفوع (${BASE_SYMBOL})`, value: (row) => csvNumber(row.paid) },
                { label: `المتبقي (${BASE_SYMBOL})`, value: (row) => csvNumber(row.outstanding) },
            ],
            rows,
        };
    }

    if (current.value === 'technicians') {
        return {
            filename: `technicians-${filters.from}_${filters.to}`,
            columns: [
                { label: 'الخبير', value: (row) => row.name },
                { label: 'التخصّص', value: (row) => row.specialty },
                { label: 'الهاتف', value: (row) => row.phone },
                { label: 'النسبة ٪', value: (row) => csvNumber(row.commission_rate) },
                { label: 'بلاغات الفترة', value: (row) => row.repairs_count },
                { label: `إيراد البلاغات (${BASE_SYMBOL})`, value: (row) => csvNumber(row.revenue) },
                { label: `استحقّ في الفترة (${BASE_SYMBOL})`, value: (row) => csvNumber(row.commission) },
                { label: `صُرف في الفترة (${BASE_SYMBOL})`, value: (row) => csvNumber(row.paid) },
                { label: `الرصيد المستحق له (${BASE_SYMBOL})`, value: (row) => csvNumber(row.balance) },
            ],
            rows: await fetchAllRows(),
        };
    }

    if (current.value === 'technician-repairs') {
        return {
            filename: `technician-repairs-${filters.from}_${filters.to}`,
            columns: [
                { label: 'الإيصال', value: (row) => row.receipt_no },
                { label: 'تاريخ التسوية', value: (row) => row.settled_at },
                { label: 'الخبير', value: (row) => row.technician },
                { label: 'العميل', value: (row) => row.customer },
                { label: 'الجهاز', value: (row) => row.device },
                { label: `التكلفة النهائية (${BASE_SYMBOL})`, value: (row) => csvNumber(row.final_cost) },
                { label: 'النسبة ٪', value: (row) => csvNumber(row.commission_rate) },
                { label: `نصيب الخبير (${BASE_SYMBOL})`, value: (row) => csvNumber(row.commission_amount) },
            ],
            rows: await fetchAllRows(),
        };
    }

    if (partyTabs.includes(current.value)) {
        return {
            filename: `${current.value}-${filters.asOf || isoDate()}`,
            columns: [
                { label: 'الاسم', value: (row) => row.name },
                { label: 'الشركة', value: (row) => row.company },
                { label: 'الهاتف', value: (row) => row.phone },
                { label: `الرصيد الافتتاحي (${BASE_SYMBOL})`, value: (row) => csvNumber(row.opening_balance) },
                { label: `الرصيد (${BASE_SYMBOL})`, value: (row) => csvNumber(row.balance) },
            ],
            rows: await fetchAllRows(),
        };
    }

    return {
        filename: `stock-movements-${isoDate()}`,
        columns: [
            { label: 'التاريخ', value: (row) => row.movement_date },
            { label: 'المادة', value: (row) => row.material.name },
            { label: 'الرمز', value: (row) => row.material.sku },
            { label: 'نوع الحركة', value: (row) => row.type_label },
            { label: 'المرجع', value: (row) => row.reference_no },
            { label: 'الكمية', value: (row) => csvNumber(row.signed_quantity, 3) },
            { label: 'الرصيد بعد الحركة', value: (row) => csvNumber(row.running_quantity, 3) },
            { label: `سعر الوحدة (${BASE_SYMBOL})`, value: (row) => csvNumber(row.unit_price) },
            { label: 'أُنشئت بواسطة', value: (row) => row.created_by },
        ],
        rows: await fetchAllRows(),
    };
}

async function exportCsv() {
    exporting.value = true;

    try {
        const { filename, columns, rows } = await exportPayload();
        downloadCsv(filename, columns, rows);
        toast.success(`تم تصدير ${rows.length} سطرًا بنجاح`);
    } catch {
        toast.error('تعذّر تصدير التقرير');
    } finally {
        exporting.value = false;
    }
}

// Browser back/forward replays a report exactly as it was shared or bookmarked.
watch(
    () => route.query,
    (query) => {
        if (JSON.stringify(query) === JSON.stringify(currentQuery())) return;
        readQuery(query);
        load();
    }
);

onMounted(() => {
    readQuery(route.query);
    load();
    loadMaterials();
});
</script>

<style>
@media print {
    @page {
        margin: 10mm;
    }

    body {
        background: #fff;
    }

    #report-sheet table {
        font-size: 11px;
    }

    #report-sheet thead {
        display: table-header-group;
    }

    #report-sheet tr {
        break-inside: avoid;
    }

    /* Print styles */
    #report-sheet .bg-slate-50\/60,
    #report-sheet .bg-slate-50\/70,
    #report-sheet [class*="bg-emerald-50"],
    #report-sheet [class*="bg-amber-50"],
    #report-sheet [class*="bg-red-50"],
    #report-sheet [class*="bg-rose-50"] {
        background: #fff !important;
    }
}
</style>
