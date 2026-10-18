<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">أجهزة الصيانة</h1>
                <p class="text-sm text-slate-500">استلام أجهزة الهواتف ومتابعة أعمال الصيانة حتى التسليم</p>
            </div>
            <div class="flex items-center gap-2.5">
                <!-- View Mode Switcher -->
                <div class="flex rounded-xl bg-slate-200/80 p-0.5 text-xs font-semibold">
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                        :class="viewMode === 'table' ? 'bg-white text-blue-700 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
                        @click="viewMode = 'table'"
                    >
                        <span>📋</span>
                        <span>جدول</span>
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
                        :class="viewMode === 'kanban' ? 'bg-white text-blue-700 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
                        @click="viewMode = 'kanban'"
                    >
                        <span>🗂️</span>
                        <span>لوحة الورشة (كانبان)</span>
                    </button>
                </div>

                <button
                    v-if="can('repairs.create')"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    @click="openCreate"
                >
                    <span class="text-base leading-none">＋</span> استلام جهاز
                </button>
            </div>
        </div>

        <!-- Status chips: the shop's real question is "how many are ready, how
             many are still on the bench", so the counts lead and double as the
             filter itself. -->
        <div class="flex gap-2 overflow-x-auto pb-1">
            <button
                type="button"
                class="shrink-0 rounded-xl border px-3 py-2 text-right transition"
                :class="statusFilter === '' ? 'border-blue-600 bg-blue-50' : 'border-slate-200 bg-white hover:border-slate-300'"
                @click="setStatus('')"
            >
                <span class="block text-[11px] text-slate-500">الكل</span>
                <span class="block text-base font-bold tabular-nums text-slate-900">{{ totalCount }}</span>
            </button>
            <button
                type="button"
                class="shrink-0 rounded-xl border px-3 py-2 text-right transition"
                :class="statusFilter === OPEN_KEY ? 'border-blue-600 bg-blue-50' : 'border-slate-200 bg-white hover:border-slate-300'"
                @click="setStatus(OPEN_KEY)"
            >
                <span class="block text-[11px] text-slate-500">قيد العمل</span>
                <span class="block text-base font-bold tabular-nums text-slate-900">{{ openCount }}</span>
            </button>
            <button
                v-for="s in REPAIR_STATUSES"
                :key="s.value"
                type="button"
                class="shrink-0 rounded-xl border px-3 py-2 text-right transition"
                :class="statusFilter === s.value ? 'border-blue-600 bg-blue-50' : 'border-slate-200 bg-white hover:border-slate-300'"
                @click="setStatus(s.value)"
            >
                <span class="flex items-center gap-1.5 text-[11px] text-slate-500">
                    <span class="h-1.5 w-1.5 rounded-full" :class="s.dot"></span>{{ s.short }}
                </span>
                <span class="block text-base font-bold tabular-nums text-slate-900">{{ statusCounts[s.value] ?? 0 }}</span>
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <!-- Filters -->
            <div class="space-y-3 border-b border-slate-200 p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative w-full max-w-md">
                        <input
                            v-model.trim="search"
                            type="search"
                            placeholder="ابحث برقم الإيصال أو اسم العميل أو هاتفه أو السيريال..."
                            class="w-full rounded-lg border border-slate-300 py-2 pr-9 ps-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 transition hover:bg-slate-50"
                        @click="showAdvanced = !showAdvanced"
                    >
                        {{ showAdvanced ? 'إخفاء' : 'تصفية بالتاريخ' }}
                    </button>

                    <button
                        v-if="hasFilters"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-blue-600 transition hover:bg-blue-50"
                        @click="resetFilters"
                    >
                        مسح التصفية
                    </button>
                </div>

                <div v-if="showAdvanced" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs text-slate-500" for="from">من تاريخ</label>
                        <input id="from" v-model="from" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-slate-500" for="to">إلى تاريخ</label>
                        <input id="to" v-model="to" type="date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none" />
                    </div>
                    <div class="flex gap-2 pb-0.5">
                        <button v-for="p in DATE_PRESETS" :key="p.label" type="button"
                            class="rounded-full border border-slate-300 px-3 py-1.5 text-xs text-slate-600 transition hover:border-blue-400 hover:text-blue-600"
                            @click="applyPreset(p)">{{ p.label }}</button>
                    </div>
                </div>
            </div>

            <!-- Desktop table -->
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">الإيصال</th>
                            <th class="px-4 py-3">العميل</th>
                            <th class="px-4 py-3">الجهاز</th>
                            <th class="px-4 py-3">العطل</th>
                            <th class="px-4 py-3 text-left">التكلفة</th>
                            <th class="px-4 py-3">الاستلام</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3 text-left">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="n in (showSkeleton ? 5 : 0)" :key="`sk-${n}`">
                            <td v-for="c in 8" :key="c" class="px-4 py-4">
                                <div class="h-3 animate-pulse rounded bg-slate-100"></div>
                            </td>
                        </tr>

                        <tr v-if="!showSkeleton && !items.length">
                            <td colspan="8" class="px-4 py-12">
                                <EmptyState :filtered="hasFilters" :can-create="can('repairs.create')" @create="openCreate" @reset="resetFilters" />
                            </td>
                        </tr>

                        <template v-for="row in items" :key="row.id">
                            <tr class="align-top transition hover:bg-slate-50/70" :class="expanded === row.id ? 'bg-slate-50' : ''">
                                <td class="px-4 py-3">
                                    <button type="button" class="font-semibold tabular-nums text-blue-700 hover:underline" dir="ltr" @click="toggleExpand(row.id)">
                                        {{ row.receipt_no }}
                                    </button>
                                    <div v-if="isOverdue(row)" class="mt-1 inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">
                                        ⏰ متأخر {{ daysSince(row.received_at) }} يوم
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ row.customer?.name || '—' }}</div>
                                    <a
                                        v-if="phoneOf(row)"
                                        :href="`tel:${phoneOf(row)}`"
                                        class="text-xs tabular-nums text-slate-400 hover:text-blue-600 hover:underline"
                                        dir="ltr"
                                    >{{ phoneOf(row) }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ [row.brand, row.model].filter(Boolean).join(' ') || row.device_type }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ row.device_type }}
                                        <span v-if="row.serial_number" class="tabular-nums" dir="ltr">· {{ row.serial_number }}</span>
                                    </div>
                                </td>
                                <td class="max-w-[240px] px-4 py-3 text-slate-600">
                                    <div class="line-clamp-2">{{ row.reported_issue }}</div>
                                </td>
                                <td class="px-4 py-3 text-left">
                                    <div class="font-semibold tabular-nums text-slate-800">{{ formatMoney(row.chargeable_cost) }}</div>
                                    <!-- Only ever says something when money has
                                         moved: a device nobody has paid for
                                         shows the cost and nothing else. -->
                                    <div v-if="hasMoney(row)" class="mt-0.5 text-[11px] tabular-nums" :class="balanceTone(row)">
                                        {{ balanceLabel(row) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-slate-600 tabular-nums" dir="ltr">{{ row.received_at }}</div>
                                    <div class="text-xs text-slate-400">{{ relativeDays(row.received_at) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <StatusControl :row="row" :editable="can('repairs.edit')" :busy="statusBusy === row.id" @change="changeStatus" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            v-if="can('repairs.edit') && nextStatus(row.status)"
                                            type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50 disabled:opacity-40"
                                            :disabled="statusBusy === row.id"
                                            :title="`نقل إلى: ${nextStatus(row.status).label}`"
                                            @click="changeStatus(row, nextStatus(row.status).value)"
                                        >
                                            {{ nextStatus(row.status).short }} ←
                                        </button>
                                        <IconButton v-if="canSettle" :title="row.is_settled ? 'حساب البلاغ' : 'قبض / تسوية'" @click="openSettlement(row)">💵</IconButton>
                                        <PrintButton kind="repair" variant="icon" title="طباعة إيصال الاستلام" :load="(paper) => documentsApi.repairReceipt(row.id, paper)" />
                                        <IconButton v-if="can('repairs.edit')" title="تعديل" @click="openEdit(row)">✏️</IconButton>
                                        <IconButton v-if="can('repairs.delete')" title="حذف" danger @click="remove(row)">🗑️</IconButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expanded === row.id" class="bg-slate-50">
                                <td colspan="8" class="px-4 pb-4">
                                    <RepairDetails :row="row" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards: an eight-column table is unusable on the phone the
                 counter staff actually hold. -->
            <div class="divide-y divide-slate-100 lg:hidden">
                <div v-if="showSkeleton" class="space-y-3 p-4">
                    <div v-for="n in 4" :key="n" class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
                </div>

                <div v-else-if="!items.length" class="p-8">
                    <EmptyState :filtered="hasFilters" :can-create="can('repairs.create')" @create="openCreate" @reset="resetFilters" />
                </div>

                <article v-for="row in items" v-else :key="row.id" class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex items-center gap-2">
                                <span class="font-semibold tabular-nums text-blue-700" dir="ltr">{{ row.receipt_no }}</span>
                                <span v-if="isOverdue(row)" class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">متأخر</span>
                            </p>
                            <p class="truncate font-medium text-slate-900">{{ row.customer?.name || '—' }}</p>
                            <a v-if="phoneOf(row)" :href="`tel:${phoneOf(row)}`" class="text-xs tabular-nums text-slate-500" dir="ltr">{{ phoneOf(row) }}</a>
                        </div>
                        <StatusControl :row="row" :editable="can('repairs.edit')" :busy="statusBusy === row.id" @change="changeStatus" />
                    </div>

                    <p class="mt-2 text-sm text-slate-700">
                        {{ [row.brand, row.model].filter(Boolean).join(' ') || row.device_type }}
                        <span class="text-xs text-slate-400">· {{ relativeDays(row.received_at) }}</span>
                    </p>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ row.reported_issue }}</p>
                    <p v-if="row.chargeable_cost != null" class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold tabular-nums text-slate-800">{{ formatMoney(row.chargeable_cost) }}</span>
                        <span v-if="hasMoney(row)" class="rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums" :class="balanceChip(row)">
                            {{ balanceLabel(row) }}
                        </span>
                    </p>

                    <RepairDetails v-if="expanded === row.id" :row="row" class="mt-3" />

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600" @click="toggleExpand(row.id)">
                            {{ expanded === row.id ? 'إخفاء التفاصيل' : 'التفاصيل' }}
                        </button>
                        <button
                            v-if="can('repairs.edit') && nextStatus(row.status)"
                            type="button"
                            class="rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 disabled:opacity-40"
                            :disabled="statusBusy === row.id"
                            @click="changeStatus(row, nextStatus(row.status).value)"
                        >
                            نقل إلى {{ nextStatus(row.status).label }}
                        </button>
                        <button
                            v-if="canSettle"
                            type="button"
                            class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700"
                            @click="openSettlement(row)"
                        >💵 {{ row.is_settled ? 'الحساب' : 'قبض / تسوية' }}</button>
                        <PrintButton kind="repair" variant="outline" label="إيصال" busy-label="جارٍ الإصدار..."
                            title="طباعة إيصال الاستلام" :load="(paper) => documentsApi.repairReceipt(row.id, paper)" />
                        <button v-if="can('repairs.edit')" type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600" @click="openEdit(row)">تعديل</button>
                        <button v-if="can('repairs.delete')" type="button" class="rounded-lg px-3 py-1.5 text-xs text-red-600" @click="remove(row)">حذف</button>
                    </div>
                </article>
            </div>

            <InfiniteScrollFooter
                :shown="items.length"
                :total="meta?.total ?? 0"
                :has-more="hasMore"
                :loading-more="loadingMore"
                :busy="loading"
                :error="moreError"
                @more="loadMore"
            />
        </div>

        <BaseModal
            :open="modalOpen"
            :title="modalTitle"
            :subtitle="modalSubtitle"
            max-width="max-w-3xl"
            :close-on-overlay="false"
            @close="closeModal"
        >
            <IntakeDone
                v-if="intakeDone"
                :repair="intakeDone.repair"
                :payment-id="intakeDone.paymentId"
                @close="closeModal"
                @again="openCreate"
            />
            <RepairForm v-else :repair="editing" @close="closeModal" @saved="onSaved" />
        </BaseModal>

        <SettlementModal
            :open="Boolean(settling)"
            :repair="settling"
            :intent="settlingIntent"
            @close="settling = null"
            @updated="onSettled"
            @deliver-only="deliverWithoutSettlement"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePermissions } from '../../composables/usePermissions';
import { useToast } from '../../composables/useToast';
import { confirm } from '../../composables/useConfirm';
import BaseModal from '../../components/ui/BaseModal.vue';
import IconButton from '../../components/ui/IconButton.vue';
import PrintButton from '../../components/ui/PrintButton.vue';
import RepairForm from '../../components/forms/RepairForm.vue';
import StatusControl from '../../components/repairs/RepairStatusControl.vue';
import RepairDetails from '../../components/repairs/RepairDetails.vue';
import IntakeDone from '../../components/repairs/RepairIntakeDone.vue';
import SettlementModal from '../../components/repairs/RepairSettlementModal.vue';
import EmptyState from '../../components/repairs/RepairsEmptyState.vue';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';
import { documentsApi, repairsApi } from '../../services/api';
import { OPEN_STATUSES, REPAIR_STATUSES, isOpenStatus, nextStatus, repairStatus } from '../../constants/repairStatus';
import { apiMessage, daysSince, formatMoney, relativeDays } from '../../utils/format';

const { can } = usePermissions();
const toast = useToast();
const route = useRoute();
const router = useRouter();

const OPEN_KEY = 'OPEN';
const OVERDUE_DAYS = 7;

const DATE_PRESETS = [
    { label: 'اليوم', days: 0 },
    { label: 'آخر ٧ أيام', days: 7 },
    { label: 'آخر ٣٠ يوماً', days: 30 },
];

const statusCounts = ref({});
const statusBusy = ref(null);
const expanded = ref(null);
const showAdvanced = ref(false);

const search = ref(route.query.search ?? '');
const statusFilter = ref(route.query.status ?? '');
const from = ref(route.query.from ?? '');
const to = ref(route.query.to ?? '');


const modalOpen = ref(false);
const editing = ref(null);
/** Set once a device has been registered: the receipts step replaces the form. */
const intakeDone = ref(null);
const settling = ref(null);
const settlingIntent = ref('');

const canSettle = computed(() => can('repairs.settle'));

const modalTitle = computed(() => {
    if (intakeDone.value) return `تم الاستلام · ${intakeDone.value.repair.receipt_no}`;
    return editing.value ? `تعديل ${editing.value.receipt_no}` : 'استلام جهاز صيانة';
});

const modalSubtitle = computed(() => {
    if (intakeDone.value) return 'اطبع ما يحتاجه العميل قبل أن يغادر';
    return editing.value ? editing.value.customer?.name ?? '' : 'سجّل الجهاز والعطل، واطبع إيصال الاستلام للعميل';
});

let debounce = null;

const showSkeleton = computed(() => loading.value && !items.value.length);
const totalCount = computed(() => Object.values(statusCounts.value).reduce((sum, n) => sum + n, 0));
const openCount = computed(() => OPEN_STATUSES.reduce((sum, s) => sum + (statusCounts.value[s] ?? 0), 0));
const hasFilters = computed(() => Boolean(search.value || statusFilter.value || from.value || to.value));

/** Whether a row with this status still belongs under the active status chip. */
function matchesFilter(status) {
    if (!statusFilter.value) return true;
    if (statusFilter.value === OPEN_KEY) return isOpenStatus(status);
    return statusFilter.value === status;
}

/** Whether anything financial has happened to this device yet. */
function hasMoney(row) {
    return Number(row.paid ?? 0) > 0 || row.is_settled;
}

/**
 * One line that answers the only money question the list is ever asked:
 * is this device paid for, and if not, by how much?
 */
function balanceLabel(row) {
    const due = Number(row.due ?? 0);
    const paid = Number(row.paid ?? 0);

    if (Math.abs(due) < 0.005) return `مدفوع ${formatMoney(paid)}`;
    if (due < 0) return `رصيد للعميل ${formatMoney(Math.abs(due))}`;
    return `متبقٍ ${formatMoney(due)}`;
}

function balanceTone(row) {
    const due = Number(row.due ?? 0);
    if (Math.abs(due) < 0.005) return 'text-emerald-600';
    return due < 0 ? 'text-blue-600' : 'text-amber-600';
}

function balanceChip(row) {
    const due = Number(row.due ?? 0);
    if (Math.abs(due) < 0.005) return 'bg-emerald-50 text-emerald-700';
    return due < 0 ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700';
}

function openSettlement(row, intent = '') {
    settlingIntent.value = intent;
    settling.value = row;
}

function phoneOf(row) {
    return row.customer?.mobile || row.customer?.phone || '';
}

/** A device still on the bench a week after it arrived needs chasing. */
function isOverdue(row) {
    if (!isOpenStatus(row.status)) return false;
    const days = daysSince(row.received_at);
    return days !== null && days >= OVERDUE_DAYS;
}

function setStatus(value) {
    statusFilter.value = statusFilter.value === value ? '' : value;
}

function applyPreset(preset) {
    const d = new Date();
    d.setDate(d.getDate() - preset.days);
    const pad = (n) => String(n).padStart(2, '0');
    from.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    to.value = '';
}

function resetFilters() {
    search.value = '';
    statusFilter.value = '';
    from.value = '';
    to.value = '';
}

function toggleExpand(id) {
    expanded.value = expanded.value === id ? null : id;
}

/**
 * The "قيد العمل" chip stands for the five statuses that mean the device is
 * still on the bench; it goes to the server as a set so the page counts stay
 * honest, rather than being sieved out of an already-paginated page.
 */
const {
    items, meta, response, loading, loadingMore, moreError, hasMore, reload: fetchData, loadMore, removeLocal,
} = useInfiniteList({
    fetch: (page, perPage) => repairsApi.index(requestParams(page, perPage)),
    onError: (e) => toast.error(apiMessage(e, 'تعذّر تحميل قائمة الصيانة')),
});

// The chip counts span the whole filter; they arrive with each fresh load.
watch(response, (data) => {
    if (data) statusCounts.value = data.meta?.status_counts ?? {};
});

function requestParams(page, perPage) {
    const status = statusFilter.value === OPEN_KEY ? OPEN_STATUSES.join(',') : statusFilter.value;

    return {
        search: search.value || undefined,
        status: status || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        page,
        per_page: perPage,
    };
}

/** Keeps filters in the URL so a reload, a bookmark or the back button all work. */
function syncUrl() {
    const query = {};
    if (search.value) query.search = search.value;
    if (statusFilter.value) query.status = statusFilter.value;
    if (from.value) query.from = from.value;
    if (to.value) query.to = to.value;
    router.replace({ query }).catch(() => {});
}

watch([search, from, to], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        syncUrl();
        fetchData();
    }, 350);
});

watch(statusFilter, () => {
    syncUrl();
    fetchData();
});

function openCreate() {
    editing.value = null;
    intakeDone.value = null;
    modalOpen.value = true;
}

function openEdit(row) {
    editing.value = row;
    intakeDone.value = null;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    intakeDone.value = null;
}

function onSaved(saved, { created, paymentId } = {}) {
    const paid = Number(saved?.paid ?? 0);

    if (!created) {
        toast.success('تم حفظ التعديلات');
    } else {
        // Say the amount back: a deposit booked silently is a deposit the clerk
        // will count again at hand-over. The dialog stays open on the receipts
        // step, because the customer is still standing at the counter.
        toast.success(paid > 0
            ? `تم استلام الجهاز ${saved?.receipt_no ?? ''} وقبض ${formatMoney(paid)}`
            : `تم استلام الجهاز برقم ${saved?.receipt_no ?? ''}`);

        intakeDone.value = { repair: saved, paymentId: paymentId ?? null };
    }

    fetchData({ keep: true });
}

/**
 * Moving a device along its lifecycle is the most repeated action on this
 * screen. It used to require opening the whole form; this patches the one field
 * and repaints the row optimistically so the list never flickers.
 *
 * The one step that is not just a status: handing the device back. That is
 * where the money is settled, so it opens the settlement dialog instead of
 * silently marking an unpaid device delivered. `force` is how that dialog comes
 * back and asks for the plain status change anyway.
 */
async function changeStatus(row, status, { force = false } = {}) {
    if (!status || status === row.status || statusBusy.value === row.id) return;

    if (status === 'DELIVERED' && !force && canSettle.value && !row.is_settled) {
        openSettlement(row, 'settle');
        return;
    }

    const previous = row.status;
    const previousLabel = row.status_label;
    statusBusy.value = row.id;
    row.status = status;
    row.status_label = repairStatus(status).label;

    try {
        const { data } = await repairsApi.update(row.id, { status });
        Object.assign(row, data.data);
        toast.success(`${row.receipt_no}: ${repairStatus(status).label}`);
        syncRowAfterStatus(row, previous, status);
    } catch (e) {
        row.status = previous;
        row.status_label = previousLabel;
        toast.error(apiMessage(e, 'تعذّر تغيير الحالة'));
    } finally {
        statusBusy.value = null;
    }
}

/**
 * Keep the chip counts honest without a round trip, and drop the row if it no
 * longer belongs under the active filter.
 */
function syncRowAfterStatus(row, previous, status) {
    if (previous === status) return;

    statusCounts.value = {
        ...statusCounts.value,
        [previous]: Math.max(0, (statusCounts.value[previous] ?? 0) - 1),
        [status]: (statusCounts.value[status] ?? 0) + 1,
    };

    if (!matchesFilter(status)) {
        removeLocal((r) => r.id === row.id);
    }
}

/**
 * The settlement dialog reports back: money moved, and possibly the device with
 * it. Patch the row in place so the table shows the new balance without
 * reloading the page under the user.
 */
function onSettled(data, settlement, message) {
    const row = items.value.find((r) => r.id === data.id) ?? settling.value;
    const previous = row?.status;

    if (row) Object.assign(row, data);
    if (message) toast.success(message);
    if (row && previous) syncRowAfterStatus(row, previous, data.status);
}

/** "Just give the customer their device" — the pre-settlement behaviour. */
function deliverWithoutSettlement() {
    const row = settling.value;
    settling.value = null;
    if (row) changeStatus(row, 'DELIVERED', { force: true });
}

async function remove(row) {
    const ok = await confirm({
        title: `حذف بلاغ الصيانة ${row.receipt_no}؟`,
        message: `سيُحذف سجل جهاز «${row.customer?.name || 'العميل'}» نهائياً.`,
        detail: [row.device_type, row.brand, row.model].filter(Boolean).join(' — '),
        confirmLabel: 'حذف نهائي',
        tone: 'danger',
    });
    if (!ok) return;

    try {
        await repairsApi.destroy(row.id);
        toast.success('تم حذف بلاغ الصيانة');
        fetchData({ keep: true });
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر حذف بلاغ الصيانة'));
    }
}

onBeforeUnmount(() => {
    clearTimeout(debounce);
});

onMounted(() => {
    fetchData();
    // The dashboard's quick action lands here with ?create=1: open intake straight away.
    if (route.query.create && can('repairs.create')) {
        openCreate();
        syncUrl();
    }
});
</script>
