<template>
    <div id="dashboard-sheet" class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3 print:hidden">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ greeting }}، {{ auth.user?.name }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span v-if="loadedAt" class="text-xs text-slate-400">آخر تحديث {{ loadedAtLabel }}</span>
                <button
                    type="button"
                    :disabled="loading || refreshing"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-40"
                    @click="refresh"
                >
                    {{ refreshing ? 'جارٍ التحديث...' : '⟳ تحديث' }}
                </button>
                <button
                    type="button"
                    :disabled="!data"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-40"
                    @click="printSheet"
                >
                    🖨 طباعة
                </button>
            </div>
        </div>

        <!-- What someone opens the dashboard to do, not just to read. -->
        <div v-if="quickActions.length" class="flex flex-wrap gap-2 print:hidden">
            <RouterLink
                v-for="(action, index) in quickActions"
                :key="action.label"
                :to="action.to"
                class="rounded-lg px-4 py-2 text-sm font-medium transition"
                :class="index === 0
                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                    : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
            >
                {{ action.label }}
            </RouterLink>
        </div>

        <!-- ---------------------------------------------------- period bar -->
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 print:hidden">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-slate-400">الفترة</span>
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
                <label class="text-xs font-medium text-slate-500">
                    من
                    <input
                        v-model="filters.from"
                        type="date"
                        class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
                        @keyup.enter="applyFilters"
                    />
                </label>
                <label class="text-xs font-medium text-slate-500">
                    إلى
                    <input
                        v-model="filters.to"
                        type="date"
                        :min="filters.from || undefined"
                        class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900"
                        @keyup.enter="applyFilters"
                    />
                </label>
                <div class="flex items-center gap-2 pb-0.5">
                    <button
                        type="button"
                        :disabled="rangeInvalid"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
                        :class="dirty && !rangeInvalid ? 'ring-2 ring-blue-200' : ''"
                        @click="applyFilters"
                    >
                        عرض
                    </button>
                    <button
                        v-if="canReset"
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        إعادة تعيين
                    </button>
                    <span v-if="rangeInvalid" class="text-xs text-red-600" role="alert">تاريخ البداية بعد تاريخ النهاية</span>
                    <span v-else-if="dirty" class="text-xs text-blue-700">تغييرات غير مطبّقة</span>
                </div>
            </div>
        </div>

        <!-- Print header: the sheet has to name its own period. -->
        <div class="hidden border-b border-slate-200 pb-3 print:block">
            <h2 class="text-lg font-bold">لوحة التحكم — {{ auth.user?.name }}</h2>
            <p class="mt-0.5 text-xs text-slate-600">{{ periodLabel }}</p>
            <p class="mt-0.5 text-[11px] text-slate-500">طُبع في {{ printedAt }}</p>
        </div>

        <div
            v-if="error"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            <span>{{ error }}</span>
            <button
                type="button"
                class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 print:hidden"
                @click="load"
            >
                إعادة المحاولة
            </button>
        </div>

        <DashboardSkeleton v-if="loading && !data" />

        <!-- A refresh keeps the old figures on screen, dimmed, instead of blanking. -->
        <div
            v-else-if="data"
            class="space-y-5 transition-opacity"
            :class="refreshing ? 'opacity-50' : ''"
            :aria-busy="refreshing"
        >
            <!-- Hero: the one number the dashboard leads with -->
            <div class="dashboard-hero relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-950 via-blue-900 to-cyan-900 p-6 text-white shadow-xl shadow-blue-950/15 sm:p-7">
                <div class="hero-blob pointer-events-none absolute -end-16 -top-20 h-56 w-56 rounded-full bg-cyan-400/20 blur-3xl" aria-hidden="true" />
                <div class="hero-blob pointer-events-none absolute -start-12 -bottom-24 h-52 w-52 rounded-full bg-blue-400/20 blur-3xl" aria-hidden="true" />

                <div class="relative flex items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-2xl ring-1 ring-white/15" aria-hidden="true">💰</span>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-blue-100/80">صافي المركز المالي</div>
                        <div
                            class="mt-1 truncate text-4xl font-bold tabular-nums sm:text-5xl"
                            :class="Number(data.kpis.net_position) < 0 ? 'text-red-300' : 'text-white'"
                        >
                            {{ formatMoney(data.kpis.net_position) }}
                        </div>
                    </div>
                </div>

                <!-- The split behind the net number, in one bar. -->
                <div class="relative mt-6">
                    <div class="position-track flex h-2 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                        <div class="h-full bg-emerald-400" :style="{ width: `${positionSplit.receivables}%` }"></div>
                        <div class="h-full bg-amber-400" :style="{ width: `${positionSplit.payables}%` }"></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <component
                            :is="can('reports.view') ? RouterLink : 'span'"
                            :to="can('reports.view') ? { name: 'reports', query: { tab: 'receivables' } } : undefined"
                            class="flex items-center gap-2 text-blue-100/80"
                            :class="can('reports.view') ? 'transition hover:text-white hover:underline' : ''"
                        >
                            <span class="h-2 w-2 rounded-full bg-emerald-400" aria-hidden="true"></span>
                            مستحق لنا
                            <b class="tabular-nums text-white">{{ formatMoney(data.kpis.receivables) }}</b>
                        </component>
                        <component
                            :is="can('reports.view') ? RouterLink : 'span'"
                            :to="can('reports.view') ? { name: 'reports', query: { tab: 'payables' } } : undefined"
                            class="flex items-center gap-2 text-blue-100/80"
                            :class="can('reports.view') ? 'transition hover:text-white hover:underline' : ''"
                        >
                            <span class="h-2 w-2 rounded-full bg-amber-400" aria-hidden="true"></span>
                            مستحق علينا
                            <b class="tabular-nums text-white">{{ formatMoney(data.kpis.payables) }}</b>
                        </component>
                    </div>
                </div>
            </div>

            <!-- Alerts: status colour is always paired with an icon and a label -->
            <div v-if="activeAlerts.length" class="flex flex-wrap gap-2">
                <RouterLink
                    v-for="alert in activeAlerts"
                    :key="alert.label"
                    :to="alert.to"
                    class="flex items-center gap-2 rounded-xl border px-4 py-2 text-sm transition"
                    :class="alert.tone"
                >
                    <span aria-hidden="true">{{ alert.icon }}</span>
                    <span>{{ alert.label }}</span>
                    <span class="font-bold tabular-nums">{{ alert.count }}</span>
                </RouterLink>
            </div>

            <!-- KPI row -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <StatCard
                    v-for="card in kpiCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :hint="card.hint"
                    :icon="card.icon"
                    :icon-class="card.iconClass"
                    :tone="card.tone"
                    :to="card.to"
                    :delta="card.delta"
                    :good-when="card.goodWhen"
                    :previous-label="comparisonLabel"
                />
            </div>

            <!-- Trend -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-bold text-slate-900">المبيعات مقابل المشتريات</h3>
                    <span class="text-xs text-slate-400">
                        {{ data.trend.granularity === 'day' ? 'يومي' : 'شهري' }} ·
                        <span dir="ltr">{{ data.period.from }} — {{ data.period.to }}</span>
                    </span>
                </div>
                <p class="mb-3 text-xs text-slate-500">
                    مبيعات <b class="tabular-nums text-slate-700">{{ formatMoney(data.kpis.sales_period.amount) }}</b>
                    · مشتريات <b class="tabular-nums text-slate-700">{{ formatMoney(data.kpis.purchases_period.amount) }}</b>
                    · الفرق
                    <b class="tabular-nums" :class="tradeMargin < 0 ? 'text-red-600' : 'text-emerald-700'">
                        {{ formatMoney(tradeMargin) }}
                    </b>
                </p>
                <TrendChart :points="data.trend.points" :series="trendSeries" :format-value="formatMoney" />
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Top customers -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-bold text-slate-900">أعلى العملاء في الفترة</h3>
                        <RouterLink
                            v-if="can('customers.view')"
                            :to="{ name: 'customers' }"
                            class="text-xs text-slate-500 hover:underline"
                        >
                            كل العملاء
                        </RouterLink>
                    </div>
                    <RankedList
                        :rows="topCustomers"
                        empty-icon="👥"
                        empty-title="لا توجد مبيعات في الفترة"
                        empty-hint="جرّب توسيع الفترة"
                    />
                </div>

                <!-- Top materials -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-bold text-slate-900">أعلى المواد مبيعًا</h3>
                        <RouterLink
                            v-if="can('inventory.view')"
                            :to="{ name: 'inventory' }"
                            class="text-xs text-slate-500 hover:underline"
                        >
                            المخزون
                        </RouterLink>
                    </div>
                    <RankedList
                        :rows="topMaterials"
                        color="#7c3aed"
                        empty-icon="📦"
                        empty-title="لا توجد مواد مباعة في الفترة"
                        empty-hint="جرّب توسيع الفترة"
                    />
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Recent activity -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 class="mb-3 font-bold text-slate-900">آخر الحركات</h3>
                    <ul v-if="data.recent_documents.length" class="divide-y divide-slate-100">
                        <li
                            v-for="doc in data.recent_documents"
                            :key="`${doc.kind}-${doc.id}`"
                            class="flex items-center justify-between gap-3 py-2.5"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm"
                                    :class="doc.direction === 'in' ? 'bg-emerald-50' : 'bg-slate-100'"
                                    aria-hidden="true"
                                >
                                    {{ documentIcon(doc) }}
                                </span>
                                <div class="min-w-0">
                                    <component
                                        :is="documentRoute(doc) ? RouterLink : 'span'"
                                        :to="documentRoute(doc) || undefined"
                                        class="text-sm font-medium text-slate-900"
                                        :class="documentRoute(doc) ? 'hover:underline' : ''"
                                    >
                                        {{ doc.kind_label }}
                                        <span v-if="doc.reference_no" class="text-slate-400" dir="ltr">{{ doc.reference_no }}</span>
                                    </component>
                                    <div class="truncate text-xs text-slate-500">
                                        {{ doc.party || '—' }} · <span dir="ltr">{{ doc.date }}</span>
                                    </div>
                                </div>
                            </div>
                            <span
                                class="shrink-0 text-sm font-semibold tabular-nums"
                                :class="doc.direction === 'in' ? 'text-emerald-700' : 'text-slate-700'"
                            >
                                {{ doc.direction === 'in' ? '+' : '−' }}{{ formatMoney(doc.amount) }}
                            </span>
                        </li>
                    </ul>
                    <EmptyState v-else icon="🗂" title="لا توجد حركات معتمدة بعد" hint="ستظهر هنا أول فاتورة أو دفعة تُعتمد" />
                </div>

                <!-- Low stock -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-bold text-slate-900">مواد قرب النفاد</h3>
                        <RouterLink
                            v-if="can('reports.view')"
                            :to="{ name: 'reports', query: { tab: 'low-stock' } }"
                            class="text-xs text-slate-500 hover:underline"
                        >
                            التقرير الكامل
                        </RouterLink>
                    </div>
                    <ul v-if="data.low_stock.length" class="divide-y divide-slate-100">
                        <li v-for="material in data.low_stock" :key="material.id" class="py-2.5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <component
                                        :is="can('reports.view') ? RouterLink : 'span'"
                                        :to="can('reports.view')
                                            ? { name: 'reports', query: { tab: 'low-stock', search: material.name } }
                                            : undefined"
                                        class="block truncate text-sm font-medium text-slate-900"
                                        :class="can('reports.view') ? 'hover:underline' : ''"
                                    >
                                        {{ material.name }}
                                    </component>
                                    <div class="text-xs text-slate-500">الحد الأدنى {{ formatQty(material.min_stock) }}</div>
                                </div>
                                <span
                                    class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold tabular-nums"
                                    :class="material.is_out_of_stock ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'"
                                >
                                    <span aria-hidden="true">{{ material.is_out_of_stock ? '⛔' : '⚠' }}</span>
                                    {{ formatQty(material.stock_quantity) }} {{ material.unit || '' }}
                                </span>
                            </div>
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full" :style="coverageStyle(material)"></div>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else icon="✅" title="كل المواد ضمن الحد الآمن" hint="لا توجد مادة بلغت حدّها الأدنى" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import TrendChart from '../../components/charts/TrendChart.vue';
import DashboardSkeleton from '../../components/dashboard/DashboardSkeleton.vue';
import RankedList from '../../components/dashboard/RankedList.vue';
import StatCard from '../../components/dashboard/StatCard.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { dashboardApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { useAuthStore } from '../../stores/auth';
import { formatMoney, formatQty, isoDate } from '../../utils/format';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { can } = usePermissions();

// Categorical slots 1 and 2 — validated for CVD separation on a light surface.
const trendSeries = [
    { key: 'sales', label: 'المبيعات', color: '#2a78d6' },
    { key: 'purchases', label: 'المشتريات', color: '#eb6834' },
];

const data = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const error = ref('');
const loadedAt = ref(null);

const defaults = () => {
    const today = new Date();
    return {
        from: isoDate(new Date(today.getFullYear(), today.getMonth(), 1)),
        to: isoDate(today),
    };
};

const filters = reactive(defaults());

/** The period behind the figures on screen, for the "unapplied" hint. */
const applied = ref('');

const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour >= 5 && hour < 12 ? 'صباح الخير' : 'مساء الخير';
});

const periodLabel = computed(() => {
    const period = data.value?.period;
    if (!period) return 'نظرة عامة على أداء الأعمال';
    const days = Number(period.days) || 0;
    return `${period.from} — ${period.to} · ${days} ${days === 1 ? 'يوم' : days === 2 ? 'يومان' : days <= 10 ? 'أيام' : 'يومًا'}`;
});

const loadedAtLabel = computed(() =>
    loadedAt.value
        ? new Intl.DateTimeFormat('ar-SY-u-nu-latn', { timeStyle: 'short' }).format(loadedAt.value)
        : ''
);

const printedAt = computed(() =>
    new Intl.DateTimeFormat('ar-SY-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date())
);

/** The period the change badges measure against, named in their tooltip. */
const comparisonLabel = computed(() => {
    const previous = data.value?.comparison;
    return previous ? `${previous.from} — ${previous.to}` : '';
});

const presets = [
    { key: 'today', label: 'اليوم' },
    { key: 'week', label: 'آخر ٧ أيام' },
    { key: 'month', label: 'هذا الشهر' },
    { key: 'last-month', label: 'الشهر الماضي' },
    { key: '30', label: 'آخر ٣٠ يومًا' },
    { key: 'year', label: 'هذه السنة' },
];

const activePreset = computed(() =>
    presets.find((preset) => {
        const range = presetRange(preset.key);
        return range.from === filters.from && range.to === filters.to;
    })?.key ?? ''
);

const dirty = computed(() => snapshot(filters) !== applied.value);
const rangeInvalid = computed(() => Boolean(filters.from && filters.to && filters.from > filters.to));
const canReset = computed(() => snapshot(filters) !== snapshot(defaults()));

const quickActions = computed(() =>
    [
        { label: '＋ فاتورة بيع', to: { name: 'sales.create' }, permission: 'sales.create' },
        { label: '＋ فاتورة شراء', to: { name: 'purchases.create' }, permission: 'purchases.create' },
        { label: '＋ سند قبض', to: { name: 'payments.create', query: { type: 'CUSTOMER' } }, permission: 'payments.create' },
        { label: '＋ استلام جهاز', to: { name: 'repairs', query: { create: 1 } }, permission: 'repairs.create' },
        { label: '＋ مصروف', to: { name: 'expenses', query: { create: 1 } }, permission: 'expenses.create' },
    ].filter((action) => can(action.permission))
);

const positionSplit = computed(() => {
    const receivables = Number(data.value?.kpis.receivables) || 0;
    const payables = Number(data.value?.kpis.payables) || 0;
    const total = receivables + payables;
    if (total <= 0) return { receivables: 0, payables: 0 };
    return {
        receivables: (receivables / total) * 100,
        payables: (payables / total) * 100,
    };
});

const tradeMargin = computed(
    () => (Number(data.value?.kpis.sales_period.amount) || 0) - (Number(data.value?.kpis.purchases_period.amount) || 0)
);

const kpiCards = computed(() => {
    if (!data.value) return [];

    const kpis = data.value.kpis;
    const previous = data.value.comparison?.kpis ?? null;

    return [
        {
            label: 'مبيعات اليوم',
            value: formatMoney(kpis.sales_today.amount),
            hint: `${kpis.sales_today.count} فاتورة`,
            icon: '🧾',
            iconClass: 'bg-blue-50',
            to: can('sales.view') ? { name: 'sales' } : null,
        },
        {
            label: 'مبيعات الفترة',
            value: formatMoney(kpis.sales_period.amount),
            hint: `${kpis.sales_period.count} فاتورة`,
            delta: delta(kpis.sales_period.amount, previous?.sales_period.amount),
            goodWhen: 'up',
            icon: '📈',
            iconClass: 'bg-cyan-50',
            to: can('sales.view') ? { name: 'sales' } : null,
        },
        {
            label: 'مشتريات الفترة',
            value: formatMoney(kpis.purchases_period.amount),
            hint: `${kpis.purchases_period.count} فاتورة`,
            delta: delta(kpis.purchases_period.amount, previous?.purchases_period.amount),
            icon: '📥',
            iconClass: 'bg-orange-50',
            to: can('purchases.view') ? { name: 'purchases' } : null,
        },
        {
            label: 'المقبوضات في الفترة',
            value: formatMoney(kpis.collected_period),
            delta: delta(kpis.collected_period, previous?.collected_period),
            goodWhen: 'up',
            icon: '💵',
            iconClass: 'bg-emerald-50',
            to: can('payments.create') ? { name: 'payments', query: { type: 'CUSTOMER' } } : null,
        },
        {
            label: 'المدفوعات في الفترة',
            value: formatMoney(kpis.paid_period),
            delta: delta(kpis.paid_period, previous?.paid_period),
            icon: '💸',
            iconClass: 'bg-amber-50',
            to: can('payments.create') ? { name: 'payments', query: { type: 'SUPPLIER' } } : null,
        },
        {
            label: 'مصاريف الفترة',
            value: formatMoney(kpis.expenses_period.amount),
            hint: `${kpis.expenses_period.count} سند`,
            delta: delta(kpis.expenses_period.amount, previous?.expenses_period.amount),
            icon: '📤',
            iconClass: 'bg-rose-50',
            to: can('expenses.view') ? { name: 'expenses' } : null,
        },
    ];
});

const activeAlerts = computed(() => {
    if (!data.value) return [];
    const alerts = data.value.alerts;

    return [
        {
            count: alerts.out_of_stock,
            label: 'مواد نفدت من المخزون',
            icon: '⛔',
            tone: 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 hover:shadow-sm',
            to: { name: 'reports', query: { tab: 'low-stock' } },
            permission: 'reports.view',
        },
        {
            count: alerts.low_stock,
            label: 'مواد قرب النفاد',
            icon: '⚠',
            tone: 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:shadow-sm',
            to: { name: 'reports', query: { tab: 'low-stock' } },
            permission: 'reports.view',
        },
        {
            count: alerts.draft_sales,
            label: 'فواتير بيع غير معتمدة',
            icon: '📝',
            tone: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:shadow-sm',
            to: { name: 'sales' },
            permission: 'sales.view',
        },
        {
            count: alerts.draft_purchases,
            label: 'فواتير شراء غير معتمدة',
            icon: '📝',
            tone: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:shadow-sm',
            to: { name: 'purchases' },
            permission: 'purchases.view',
        },
        {
            count: alerts.draft_payments,
            label: 'دفعات غير معتمدة',
            icon: '📝',
            tone: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:shadow-sm',
            to: { name: 'payments' },
            permission: 'payments.create',
        },
    ].filter((alert) => alert.count > 0 && can(alert.permission));
});

const topCustomers = computed(() => {
    const rows = data.value?.top_customers ?? [];
    const total = rows.reduce((sum, row) => sum + (Number(row.total) || 0), 0);

    return rows.map((row) => ({
        id: row.id,
        name: row.name,
        amount: Number(row.total) || 0,
        value: formatMoney(row.total),
        meta: `${row.invoices} فاتورة · ${share(row.total, total)} من مبيعات القائمة`,
        to: can('statements.view') ? { name: 'customers.statement', params: { id: row.id } } : null,
    }));
});

const topMaterials = computed(() => {
    const rows = data.value?.top_materials ?? [];
    const total = rows.reduce((sum, row) => sum + (Number(row.total) || 0), 0);

    return rows.map((row) => ({
        id: row.id,
        name: row.name,
        amount: Number(row.total) || 0,
        value: formatMoney(row.total),
        meta: `الكمية ${formatQty(row.quantity)} · ${share(row.total, total)} من مبيعات القائمة`,
    }));
});

const DOCUMENT_ROUTES = {
    sales_invoice: { name: 'sales.edit', permission: 'sales.view' },
    purchase_invoice: { name: 'purchases.edit', permission: 'purchases.view' },
    payment: { name: 'payments.edit', permission: 'payments.create' },
};

const DOCUMENT_ICONS = {
    sales_invoice: '🧾',
    purchase_invoice: '📥',
    payment: '💵',
};

function documentRoute(doc) {
    const target = DOCUMENT_ROUTES[doc.kind];
    if (!target || !can(target.permission)) return null;
    return { name: target.name, params: { id: doc.id } };
}

function documentIcon(doc) {
    if (doc.kind === 'payment') return doc.direction === 'in' ? '💵' : '💸';
    return DOCUMENT_ICONS[doc.kind] ?? '📄';
}

function share(value, total) {
    if (!total) return '0٪';
    return `${Math.round(((Number(value) || 0) / total) * 100)}٪`;
}

/** Ratio of change, or null when the previous period has nothing to divide by. */
function delta(current, previous) {
    const before = Number(previous);
    if (!Number.isFinite(before) || before <= 0) return null;
    return ((Number(current) || 0) - before) / before;
}

function coverageStyle(material) {
    const min = Number(material.min_stock) || 0;
    const stock = Number(material.stock_quantity) || 0;
    const ratio = min <= 0 ? (stock > 0 ? 1 : 0) : Math.max(0, Math.min(1, stock / min));

    return {
        // A zero-width bar is invisible; a hairline still says "nothing left".
        width: `${Math.max(2, ratio * 100)}%`,
        background: ratio <= 0 ? '#dc2626' : ratio < 0.5 ? '#f59e0b' : '#0ea5e9',
    };
}

// ------------------------------------------------------------------- period

function snapshot(values) {
    return `${values.from}|${values.to}`;
}

function presetRange(key) {
    const today = new Date();

    switch (key) {
        case 'today':
            return { from: isoDate(today), to: isoDate(today) };
        case 'week':
            return { from: isoDate(new Date(Date.now() - 6 * 864e5)), to: isoDate(today) };
        case 'month':
            return { from: isoDate(new Date(today.getFullYear(), today.getMonth(), 1)), to: isoDate(today) };
        case 'last-month':
            return {
                from: isoDate(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
                to: isoDate(new Date(today.getFullYear(), today.getMonth(), 0)),
            };
        case '30':
            return { from: isoDate(new Date(Date.now() - 29 * 864e5)), to: isoDate(today) };
        case 'year':
            return { from: isoDate(new Date(today.getFullYear(), 0, 1)), to: isoDate(today) };
        default:
            return { from: filters.from, to: filters.to };
    }
}

function applyPreset(preset) {
    Object.assign(filters, presetRange(preset.key));
    applyFilters();
}

function applyFilters() {
    if (rangeInvalid.value) return;
    load();
}

function resetFilters() {
    Object.assign(filters, defaults());
    load();
}

function refresh() {
    load();
}

function printSheet() {
    window.print();
}

// ----------------------------------------------------------------- url state

function currentQuery() {
    return { from: filters.from, to: filters.to };
}

function readQuery(query) {
    Object.assign(filters, defaults());
    if (query.from) filters.from = String(query.from);
    if (query.to) filters.to = String(query.to);
}

function syncQuery() {
    const query = currentQuery();
    if (JSON.stringify(query) === JSON.stringify(route.query)) return;
    // replace, not push: changing the period should not bury the previous screen.
    router.replace({ query }).catch(() => {});
}

// -------------------------------------------------------------------- loading

/** Only the latest request may write to the screen; a slow earlier one is dropped. */
let requestSeq = 0;

async function load() {
    const seq = ++requestSeq;
    if (data.value) refreshing.value = true;
    else loading.value = true;

    error.value = '';

    try {
        // compare: the previous period's window figures ride along in the same
        // response, so the change badges cost one request instead of two.
        const response = await dashboardApi.index({ from: filters.from, to: filters.to, compare: 1 });
        if (seq !== requestSeq) return;
        data.value = response;
        applied.value = snapshot(filters);
        loadedAt.value = new Date();
        syncQuery();
    } catch (e) {
        if (seq !== requestSeq) return;
        error.value = e?.response?.data?.message || 'تعذّر تحميل لوحة التحكم';
    } finally {
        if (seq === requestSeq) {
            loading.value = false;
            refreshing.value = false;
        }
    }
}

// A tab left open over lunch should not greet the user with stale figures.
const STALE_AFTER_MS = 5 * 60 * 1000;

function onVisibilityChange() {
    if (document.visibilityState !== 'visible' || loading.value || refreshing.value) return;
    if (loadedAt.value && Date.now() - loadedAt.value.getTime() > STALE_AFTER_MS && !dirty.value) load();
}

// Browser back/forward replays a period exactly as it was shared or bookmarked.
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
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisibilityChange));
</script>

<style>
@media print {
    @page {
        margin: 12mm;
    }

    body {
        background: #fff;
    }

    #dashboard-sheet {
        gap: 0;
    }

    #dashboard-sheet .rounded-2xl {
        break-inside: avoid;
        box-shadow: none !important;
    }

    /*
     * The hero's gradient does not print, so its white text would vanish on a
     * white page — force dark ink and drop the decorative glows instead.
     */
    #dashboard-sheet .dashboard-hero,
    #dashboard-sheet .dashboard-hero * {
        color: #0f172a !important;
    }

    #dashboard-sheet .dashboard-hero .hero-blob {
        display: none;
    }

    #dashboard-sheet .dashboard-hero .position-track {
        background: #f1f5f9;
    }
}
</style>
