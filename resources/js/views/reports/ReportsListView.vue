<template>
    <div id="report-sheet" class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3 print:hidden">
            <div>
                <h1 class="text-xl font-bold text-slate-900">التقارير</h1>
                <p class="text-sm text-slate-500">{{ activeTab.hint }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    :disabled="exporting || !hasRows"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="exportCsv"
                >
                    {{ exporting ? 'جارٍ التصدير...' : '⤓ تصدير CSV' }}
                </button>
                <button
                    type="button"
                    :disabled="!hasRows"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="printSheet"
                >
                    🖨 طباعة
                </button>
            </div>
        </div>

        <!-- Tabs scroll sideways on a phone instead of wrapping into a wall of pills. -->
        <div class="-mx-1 overflow-x-auto px-1 pb-1 print:hidden">
            <div ref="tablist" role="tablist" aria-label="التقارير" class="flex min-w-max gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    :id="`report-tab-${tab.key}`"
                    type="button"
                    role="tab"
                    :aria-selected="current === tab.key"
                    :aria-controls="`report-panel-${tab.key}`"
                    :tabindex="current === tab.key ? 0 : -1"
                    class="flex items-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition"
                    :class="current === tab.key
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    @click="selectTab(tab.key)"
                    @keydown="onTabKeydown"
                >
                    <span aria-hidden="true">{{ tab.icon }}</span>
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div
            :id="`report-panel-${current}`"
            role="tabpanel"
            :aria-labelledby="`report-tab-${current}`"
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none"
        >
            <!-- Print header: a printed sheet has to say what it is on its own. -->
            <div class="hidden border-b border-slate-200 px-4 py-3 print:block">
                <h2 class="text-lg font-bold">{{ activeTab.label }}</h2>
                <p v-if="filterSummary" class="mt-0.5 text-xs text-slate-600">{{ filterSummary }}</p>
                <p class="mt-0.5 text-[11px] text-slate-500">
                    طُبع في {{ printedAt }}
                    <template v-if="meta && meta.last_page > 1">
                        — الصفحة {{ meta.current_page }} من {{ meta.last_page }} فقط
                    </template>
                </p>
            </div>

            <!-- ------------------------------------------------ filter bar -->
            <div class="border-b border-slate-100 bg-slate-50/60 px-4 py-3 print:hidden">
                <div v-if="showDates" class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-slate-400">فترات سريعة</span>
                    <button
                        v-for="preset in presets"
                        :key="preset.key"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="activePreset === preset.key
                            ? 'bg-blue-100 text-blue-800'
                            : 'border border-slate-200 bg-white text-slate-500 hover:bg-slate-100'"
                        @click="applyPreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <template v-if="showDates">
                        <label class="text-xs font-medium text-slate-500">
                            من تاريخ
                            <input
                                v-model="filters.from"
                                type="date"
                                class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"
                                @keyup.enter="applyFilters"
                            />
                        </label>
                        <label class="text-xs font-medium text-slate-500">
                            إلى تاريخ
                            <input
                                v-model="filters.to"
                                type="date"
                                :min="filters.from || undefined"
                                class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"
                                @keyup.enter="applyFilters"
                            />
                        </label>
                    </template>

                    <label v-if="showSearch" class="text-xs font-medium text-slate-500">
                        بحث
                        <input
                            v-model="filters.search"
                            type="search"
                            :placeholder="current === 'low-stock' ? 'اسم المادة أو الرمز' : 'الاسم أو الشركة أو الهاتف'"
                            class="mt-1 block w-56 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"
                            @keyup.enter="applyFilters"
                        />
                    </label>

                    <template v-if="partyTabs.includes(current)">
                        <label class="text-xs font-medium text-slate-500">
                            الرصيد بتاريخ
                            <input
                                v-model="filters.asOf"
                                type="date"
                                class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"
                                @keyup.enter="applyFilters"
                            />
                        </label>
                        <label class="flex items-center gap-2 pb-2.5 text-sm text-slate-700">
                            <input v-model="filters.onlyOutstanding" type="checkbox" class="rounded border-slate-300" />
                            غير المسددة فقط
                        </label>
                    </template>

                    <template v-if="current === 'stock-movements'">
                        <label class="text-xs font-medium text-slate-500">
                            المادة
                            <select
                                v-model="filters.materialId"
                                class="mt-1 block w-52 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"
                            >
                                <option :value="null">كل المواد</option>
                                <option v-for="material in materials" :key="material.id" :value="material.id">
                                    {{ material.name }}
                                </option>
                            </select>
                        </label>
                        <label class="text-xs font-medium text-slate-500">
                            نوع الحركة
                            <select
                                v-model="filters.movementType"
                                class="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"
                            >
                                <option :value="null">الكل</option>
                                <option v-for="type in movementTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                        </label>
                    </template>

                    <div class="flex items-center gap-2 pb-0.5">
                        <button
                            type="button"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                            :class="dirty ? 'ring-2 ring-blue-200' : ''"
                            @click="applyFilters"
                        >
                            عرض
                        </button>
                        <button
                            v-if="canReset"
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50"
                            @click="resetFilters"
                        >
                            إعادة تعيين
                        </button>
                        <!-- Filters only take effect on "عرض"; say so rather than let the table lie. -->
                        <span v-if="dirty" class="text-xs text-blue-700">تغييرات غير مطبّقة</span>
                    </div>
                </div>
            </div>

            <div v-if="error" class="m-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <span>{{ error }}</span>
                <button
                    type="button"
                    class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 print:hidden"
                    @click="load"
                >
                    إعادة المحاولة
                </button>
            </div>

            <div :aria-busy="loading">
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
                        <LowStockReport v-if="current === 'low-stock'" :rows="lowStock" />
                        <PeriodSummaryReport
                            v-else-if="periodTabs.includes(current)"
                            :summary="summary"
                            :kind="current"
                        />
                        <PartyBalancesReport
                            v-else-if="partyTabs.includes(current)"
                            :data="parties"
                            :kind="current"
                            :as-of="filters.asOf"
                        />
                        <TechnicianAccountsReport
                            v-else-if="technicianTabs.includes(current)"
                            :data="technicianReport"
                            :kind="current"
                            :from="filters.from"
                            :to="filters.to"
                        />
                        <StockMovementsReport v-else :data="movements" />
                    </template>
                </template>
            </div>

            <Pager
                v-if="meta && meta.total && !loading"
                :meta="meta"
                :unit="activeTab.unit"
                @go="goTo"
                @per-page="changePerPage"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import EmptyState from '../../components/ui/EmptyState.vue';
import Pager from '../../components/ui/Pager.vue';
import TableSkeleton from '../../components/ui/TableSkeleton.vue';
import LowStockReport from '../../components/reports/LowStockReport.vue';
import PartyBalancesReport from '../../components/reports/PartyBalancesReport.vue';
import TechnicianAccountsReport from '../../components/reports/TechnicianAccountsReport.vue';
import PeriodSummaryReport from '../../components/reports/PeriodSummaryReport.vue';
import StockMovementsReport from '../../components/reports/StockMovementsReport.vue';
import { materialsApi, reportsApi } from '../../services/api';
import { useToast } from '../../composables/useToast';
import { csvNumber, downloadCsv } from '../../utils/csv';
import { isoDate } from '../../utils/format';

const tabs = [
    {
        key: 'low-stock',
        icon: '📉',
        label: 'المخزون المنخفض',
        unit: 'مادة',
        hint: 'المواد التي بلغت حدّها الأدنى أو نفدت، مرتّبة من الأشدّ نقصًا',
    },
    {
        key: 'sales',
        icon: '🧾',
        label: 'ملخص المبيعات',
        unit: 'يوم',
        hint: 'إجماليات المبيعات المعتمدة وتفصيلها اليومي خلال فترة',
    },
    {
        key: 'purchases',
        icon: '📦',
        label: 'ملخص المشتريات',
        unit: 'يوم',
        hint: 'إجماليات المشتريات المعتمدة وتفصيلها اليومي خلال فترة',
    },
    {
        key: 'receivables',
        icon: '💰',
        label: 'ذمم العملاء',
        unit: 'حساب',
        hint: 'أرصدة العملاء المستحقة لنا، من الأكبر إلى الأصغر',
    },
    {
        key: 'payables',
        icon: '💳',
        label: 'ذمم الموردين',
        unit: 'حساب',
        hint: 'أرصدة الموردين المستحقة علينا، من الأكبر إلى الأصغر',
    },
    {
        key: 'technicians',
        icon: '🧰',
        label: 'محاسبة خبراء الصيانة',
        unit: 'خبير',
        hint: 'ما استحقّه كل خبير في الفترة وما صُرف له، ورصيده القائم',
    },
    {
        key: 'technician-repairs',
        icon: '🔧',
        label: 'عمولات بلاغ بلاغ',
        unit: 'بلاغ',
        hint: 'كل بلاغ سُوِّي في الفترة، بتكلفته ونصيب الخبير منه',
    },
    {
        key: 'stock-movements',
        icon: '🔄',
        label: 'حركات المخزون',
        unit: 'حركة',
        hint: 'سجل دخول وخروج المواد؛ اختيار مادة واحدة يُظهر الرصيد بعد كل حركة',
    },
];

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

const DEFAULT_PER_PAGE = 50;
const EXPORT_PAGE_SIZE = 200;
const MAX_EXPORT_ROWS = 5000;

const route = useRoute();
const router = useRouter();
const toast = useToast();

const tablist = ref(null);
const current = ref('low-stock');
const loading = ref(true);
const exporting = ref(false);
const error = ref('');
const page = ref(1);
const perPage = ref(DEFAULT_PER_PAGE);

const lowStock = ref([]);
const summary = ref(null);
const parties = ref(null);
const movements = ref(null);
const technicianReport = ref(null);
const meta = ref(null);
const materials = ref([]);

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

const activeTab = computed(() => tabs.find((tab) => tab.key === current.value) ?? tabs[0]);
const showDates = computed(() =>
    periodTabs.includes(current.value)
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

const hasRows = computed(() => !loading.value && !error.value && !isEmpty.value);

const isEmpty = computed(() => {
    if (current.value === 'low-stock') return !lowStock.value.length;
    if (periodTabs.includes(current.value)) return !summary.value || !Number(summary.value.count);
    if (partyTabs.includes(current.value)) return !(parties.value?.rows || []).length;
    if (technicianTabs.includes(current.value)) return !(technicianReport.value?.rows || []).length;
    return !(movements.value?.rows || []).length;
});

const emptyState = computed(() => {
    const filtered = canReset.value;

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
    page.value = 1;
    load();
}

function resetFilters() {
    Object.assign(filters, defaults());
    applyFilters();
}

function selectTab(key) {
    if (current.value === key) return;

    current.value = key;
    page.value = 1;
    meta.value = null;

    // Only the movement log may run without a period; carrying its empty dates
    // into a summary would just bounce off the API's "from is required".
    if (periodTabs.includes(key) && (!filters.from || !filters.to)) {
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

    const index = tabs.findIndex((tab) => tab.key === current.value);
    const target = offset === 'first'
        ? 0
        : offset === 'last'
            ? tabs.length - 1
            : (index + offset + tabs.length) % tabs.length;

    selectTab(tabs[target].key);
    tablist.value?.children[target]?.focus();
}

function goTo(target) {
    page.value = target;
    load();
}

function changePerPage(size) {
    perPage.value = size;
    page.value = 1;
    load();
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

    if (page.value > 1) query.page = String(page.value);
    if (perPage.value !== DEFAULT_PER_PAGE) query.per_page = String(perPage.value);

    return query;
}

function readQuery(query) {
    const tab = tabs.find((item) => item.key === query.tab);
    current.value = tab ? tab.key : 'low-stock';

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

    page.value = Math.max(1, Number(query.page) || 1);
    perPage.value = Number(query.per_page) || DEFAULT_PER_PAGE;
}

function syncQuery() {
    const query = currentQuery();
    if (JSON.stringify(query) === JSON.stringify(route.query)) return;
    // replace, not push: a filter change should not bury the previous screen.
    router.replace({ query }).catch(() => {});
}

// --------------------------------------------------------------------- loading

function requestParams(overrides = {}) {
    const params = { page: page.value, per_page: perPage.value, ...overrides };

    if (current.value === 'low-stock') {
        return filters.search ? { search: filters.search } : {};
    }

    if (periodTabs.includes(current.value)) {
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
    loading.value = true;
    error.value = '';

    try {
        if (current.value === 'low-stock') {
            lowStock.value = await reportsApi.lowStock(requestParams());
            meta.value = null;
        } else if (periodTabs.includes(current.value)) {
            const params = requestParams();
            summary.value = current.value === 'sales'
                ? await reportsApi.salesSummary(params)
                : await reportsApi.purchasesSummary(params);
            meta.value = null;
        } else if (partyTabs.includes(current.value)) {
            parties.value = await fetcher()(requestParams());
            meta.value = parties.value.meta ?? null;
        } else if (technicianTabs.includes(current.value)) {
            technicianReport.value = current.value === 'technicians'
                ? await reportsApi.technicianSummary(requestParams())
                : await reportsApi.technicianRepairs(requestParams());
            meta.value = technicianReport.value.meta ?? null;
        } else {
            movements.value = await reportsApi.stockMovements(requestParams());
            meta.value = movements.value.meta ?? null;
        }

        applied.value = filterSnapshot();
        syncQuery();
    } catch (e) {
        error.value = e?.response?.data?.message || 'تعذّر تحميل التقرير';
    } finally {
        loading.value = false;
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
                { label: 'تكلفة', value: (row) => csvNumber(row.cost_price) },
                { label: 'سعر بيع', value: (row) => csvNumber(row.sale_price) },
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
                { label: 'الإجمالي', value: (row) => csvNumber(row.total) },
                { label: 'المدفوع', value: (row) => csvNumber(row.paid) },
                { label: 'المتبقي', value: (row) => csvNumber(row.outstanding) },
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
                { label: 'إيراد البلاغات', value: (row) => csvNumber(row.revenue) },
                { label: 'استحقّ في الفترة', value: (row) => csvNumber(row.commission) },
                { label: 'صُرف في الفترة', value: (row) => csvNumber(row.paid) },
                { label: 'الرصيد المستحق له', value: (row) => csvNumber(row.balance) },
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
                { label: 'التكلفة النهائية', value: (row) => csvNumber(row.final_cost) },
                { label: 'النسبة ٪', value: (row) => csvNumber(row.commission_rate) },
                { label: 'نصيب الخبير', value: (row) => csvNumber(row.commission_amount) },
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
                { label: 'الرصيد الافتتاحي', value: (row) => csvNumber(row.opening_balance) },
                { label: 'الرصيد', value: (row) => csvNumber(row.balance) },
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
            { label: 'سعر الوحدة', value: (row) => csvNumber(row.unit_price) },
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
        toast.success(`تم تصدير ${rows.length} سطرًا`);
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
        margin: 12mm;
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

    /* Tailwind's print:hidden covers the controls; these are the coloured
       surfaces that only cost ink. */
    #report-sheet .bg-slate-50\/60,
    #report-sheet [class*="bg-emerald-50"],
    #report-sheet [class*="bg-amber-50"],
    #report-sheet [class*="bg-red-50"] {
        background: #fff !important;
    }
}
</style>
