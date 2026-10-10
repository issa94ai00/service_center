<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">المخزون</h1>
                <p class="text-sm text-slate-500">أرصدة المواد وتسويات الجرد وحركة كل مادة</p>
            </div>
            <button
                v-if="can('inventory.adjust')"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="openAdjustment()"
            >
                + تسوية مخزون
            </button>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>
        <div v-if="notice" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ notice }}
        </div>

        <!-- Summary -->
        <div v-if="totals" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-sm text-slate-500">عدد المواد</div>
                <div class="mt-1 text-2xl font-bold text-slate-900">{{ totals.materials }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="text-sm text-slate-500">قيمة المخزون (بالتكلفة)</div>
                <div class="mt-1 text-2xl font-bold text-slate-900">{{ formatMoney(totals.stock_value) }}</div>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <div class="text-sm text-amber-800">مواد قرب النفاد</div>
                <div class="mt-1 text-2xl font-bold text-amber-900">{{ totals.low }}</div>
            </div>
            <div class="rounded-2xl border border-red-200 bg-red-50 p-5">
                <div class="text-sm text-red-700">مواد نافدة</div>
                <div class="mt-1 text-2xl font-bold text-red-800">{{ totals.out_of_stock }}</div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button v-for="t in tabs" :key="t.key" :class="tabClass(t.key)" @click="selectTab(t.key)">
                {{ t.label }}
            </button>
        </div>

        <!-- ------------------------------------------------------- stock list -->
        <div v-if="tab === 'stock'" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-4">
                <label class="text-sm">
                    بحث
                    <input v-model="filters.search" type="search" placeholder="اسم المادة أو الرمز"
                        class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm" @keyup.enter="applyFilters" />
                </label>
                <label class="text-sm">
                    الحالة
                    <select v-model="filters.status" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="all">الكل</option>
                        <option value="available">ضمن الحد الآمن</option>
                        <option value="low">قرب النفاد</option>
                        <option value="out">نافدة</option>
                    </select>
                </label>
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" @click="applyFilters">
                    عرض
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-4 py-3">المادة</th>
                            <th class="px-4 py-3">الرمز</th>
                            <th class="px-4 py-3">الصنف</th>
                            <th class="px-4 py-3">الوحدة</th>
                            <th class="px-4 py-3 text-left">الرصيد</th>
                            <th class="px-4 py-3 text-left">الحد الأدنى</th>
                            <th class="px-4 py-3 text-left">قيمة الرصيد</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !(tab === 'stock' ? rows : adjustments).length" class="text-slate-400"><td colspan="8" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!rows.length" class="text-slate-400"><td colspan="8" class="px-4 py-8 text-center">لا توجد مواد</td></tr>
                        <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ row.name }}
                                <span v-if="row.is_out_of_stock" class="mr-2 rounded bg-red-50 px-2 py-0.5 text-xs text-red-700">نافدة</span>
                                <span v-else-if="row.is_low" class="mr-2 rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-800">قرب النفاد</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500" dir="ltr">{{ row.sku || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.category || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.unit || '—' }}</td>
                            <td class="px-4 py-3 text-left font-semibold"
                                :class="row.is_out_of_stock ? 'text-red-600' : row.is_low ? 'text-amber-700' : 'text-slate-900'">
                                {{ formatQty(row.stock_quantity) }}
                            </td>
                            <td class="px-4 py-3 text-left text-slate-500">{{ formatQty(row.min_stock) }}</td>
                            <td class="px-4 py-3 text-left text-slate-700">{{ formatMoney(row.stock_value) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button class="text-slate-500 hover:text-slate-900" @click="openHistory(row)">الحركة</button>
                                    <button v-if="can('inventory.adjust')" class="text-slate-500 hover:text-slate-900"
                                        @click="openAdjustment(row)">تسوية</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <InfiniteScrollFooter
                :shown="rows.length"
                :total="stockMeta?.total ?? 0"
                :has-more="stockHasMore"
                :loading-more="stockLoadingMore"
                :busy="stockLoading"
                :error="stockMoreError"
                unit="مادة"
                @more="loadMoreStock"
            />
        </div>

        <!-- ------------------------------------------------------ adjustments -->
        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-4 py-3">الرقم</th>
                            <th class="px-4 py-3">السبب</th>
                            <th class="px-4 py-3">البنود</th>
                            <th class="px-4 py-3">بواسطة</th>
                            <th class="px-4 py-3">التاريخ</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !(tab === 'stock' ? rows : adjustments).length" class="text-slate-400"><td colspan="7" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!adjustments.length" class="text-slate-400"><td colspan="7" class="px-4 py-8 text-center">لا توجد تسويات</td></tr>
                        <tr v-for="adj in adjustments" :key="adj.id" class="align-top">
                            <td class="px-4 py-3 font-semibold text-slate-900" dir="ltr">{{ adj.adjustment_no }}</td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ adj.reason }}
                                <div v-if="adj.cancellation_reason" class="text-xs text-red-600">إلغاء: {{ adj.cancellation_reason }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                <div v-for="item in adj.items" :key="item.id" class="whitespace-nowrap text-xs">
                                    <span :class="item.direction === 'IN' ? 'text-emerald-700' : 'text-red-600'">
                                        {{ item.direction === 'IN' ? '+' : '−' }}{{ formatQty(item.quantity) }}
                                    </span>
                                    {{ item.unit || '' }} — {{ item.material }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ adj.adjusted_by || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500" dir="ltr">{{ adj.posted_at || adj.created_at }}</td>
                            <td class="px-4 py-3"><StatusBadge :status="adj.status" /></td>
                            <td class="px-4 py-3">
                                <button v-if="adj.status === 'POSTED' && can('inventory.adjust')"
                                    class="text-orange-600 hover:text-orange-800" @click="cancelAdjustment(adj)">إلغاء</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <InfiniteScrollFooter
                :shown="adjustments.length"
                :total="adjMeta?.total ?? 0"
                :has-more="adjHasMore"
                :loading-more="adjLoadingMore"
                :busy="adjLoading"
                :error="adjMoreError"
                unit="تسوية"
                @more="loadMoreAdjustments"
            />
        </div>

        <!-- ----------------------------------------------------- adjust modal -->
        <BaseModal :open="adjustOpen" title="تسوية مخزون" @close="adjustOpen = false">
            <form class="space-y-4" @submit.prevent="submitAdjustment">
                <FormField label="سبب التسوية" required>
                    <input v-model="form.reason" type="text" placeholder="جرد فعلي، تلف، هدر..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <p v-if="formErrors.reason" class="mt-1 text-xs text-red-600">{{ formErrors.reason }}</p>
                </FormField>

                <div class="space-y-3">
                    <div v-for="(line, index) in form.items" :key="index" class="rounded-xl border border-slate-200 p-3">
                        <div class="grid gap-3 sm:grid-cols-4">
                            <label class="text-xs text-slate-500 sm:col-span-2">
                                المادة
                                <select v-model.number="line.material_id" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option :value="null">اختر مادة</option>
                                    <option v-for="m in materialOptions" :key="m.id" :value="m.id">
                                        {{ m.name }} ({{ formatQty(m.stock_quantity) }})
                                    </option>
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">
                                الاتجاه
                                <select v-model="line.direction" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="IN">إدخال (+)</option>
                                    <option value="OUT">إخراج (−)</option>
                                </select>
                            </label>
                            <label class="text-xs text-slate-500">
                                الكمية
                                <input v-model="line.quantity" type="number" step="0.001" min="0"
                                    class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </label>
                        </div>
                        <div v-if="formErrors[`items.${index}.quantity`] || formErrors[`items.${index}.material_id`]"
                            class="mt-2 text-xs text-red-600">
                            {{ formErrors[`items.${index}.quantity`] || formErrors[`items.${index}.material_id`] }}
                        </div>
                        <button v-if="form.items.length > 1" type="button"
                            class="mt-2 text-xs text-red-500 hover:text-red-700" @click="form.items.splice(index, 1)">
                            حذف البند
                        </button>
                    </div>

                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50"
                        @click="form.items.push({ material_id: null, direction: 'IN', quantity: '' })">
                        + إضافة بند
                    </button>
                </div>

                <div v-if="formErrors.items" class="text-xs text-red-600">{{ formErrors.items }}</div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        @click="adjustOpen = false">إلغاء</button>
                    <button type="submit" :disabled="saving"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40">
                        {{ saving ? 'جارٍ الحفظ...' : 'حفظ التسوية' }}
                    </button>
                </div>
            </form>
        </BaseModal>

        <!-- ---------------------------------------------------- history modal -->
        <BaseModal :open="historyOpen" :title="`حركة المادة — ${history?.material?.name || ''}`" @close="historyOpen = false">
            <div v-if="history" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="text-xs text-slate-500">الرصيد الحالي</div>
                        <div class="mt-1 font-bold text-slate-900">{{ formatQty(history.material.stock_quantity) }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="text-xs text-slate-500">الحد الأدنى</div>
                        <div class="mt-1 font-bold text-slate-900">{{ formatQty(history.material.min_stock) }}</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="text-xs text-slate-500">التكلفة</div>
                        <div class="mt-1 font-bold text-slate-900">{{ formatMoney(history.material.cost_price) }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto sm:max-h-80 sm:overflow-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-xs font-semibold text-slate-500">
                            <tr>
                                <th class="px-3 py-2">التاريخ</th>
                                <th class="px-3 py-2">الحركة</th>
                                <th class="px-3 py-2">المرجع</th>
                                <th class="px-3 py-2 text-left">الكمية</th>
                                <th class="px-3 py-2 text-left">الرصيد</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!history.movements.length" class="text-slate-400">
                                <td colspan="5" class="px-3 py-6 text-center">لا توجد حركات</td>
                            </tr>
                            <tr v-for="m in history.movements" :key="m.id">
                                <td class="px-3 py-2 text-slate-600" dir="ltr">{{ m.movement_date }}</td>
                                <td class="px-3 py-2 text-slate-700">
                                    {{ m.type_label }}
                                    <span v-if="m.is_reversal" class="mr-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-700">عكسية</span>
                                </td>
                                <td class="px-3 py-2 text-slate-500" dir="ltr">{{ m.reference_no || '—' }}</td>
                                <td class="px-3 py-2 text-left font-medium"
                                    :class="Number(m.signed_quantity) < 0 ? 'text-red-600' : 'text-emerald-700'">
                                    {{ formatQty(m.signed_quantity) }}
                                </td>
                                <td class="px-3 py-2 text-left font-semibold text-slate-900">{{ formatQty(m.running_quantity) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </BaseModal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import FormField from '../../components/ui/FormField.vue';
import StatusBadge from '../../components/ui/StatusBadge.vue';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';
import { inventoryApi, materialsApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';

const { can } = usePermissions();

const tabs = [
    { key: 'stock', label: 'أرصدة المواد' },
    { key: 'adjustments', label: 'تسويات الجرد' },
];

const tab = ref('stock');
const error = ref('');
const notice = ref('');
const materialOptions = ref([]);

const filters = reactive({ search: '', status: 'all' });

const loadFailed = (e) => {
    error.value = e?.response?.data?.message || 'تعذّر تحميل بيانات المخزون';
};

// Each tab scrolls its own list; the stock endpoint caps per_page at 200.
const {
    items: rows, meta: stockMeta, response: stockResponse, loading: stockLoading, loadingMore: stockLoadingMore,
    moreError: stockMoreError, hasMore: stockHasMore, reload: reloadStock, loadMore: loadMoreStock,
} = useInfiniteList({
    fetch: (page, perPage) => inventoryApi.index({
        page,
        per_page: perPage,
        status: filters.status,
        search: filters.search || undefined,
    }),
    maxPerPage: 200,
    onError: loadFailed,
});

const {
    items: adjustments, meta: adjMeta, loading: adjLoading, loadingMore: adjLoadingMore,
    moreError: adjMoreError, hasMore: adjHasMore, reload: reloadAdjustments, loadMore: loadMoreAdjustments,
} = useInfiniteList({
    fetch: (page, perPage) => inventoryApi.adjustments({ page, per_page: perPage }),
    onError: loadFailed,
});

/** The cards count every material under the filter, not just the rows scrolled into view. */
const totals = computed(() => stockResponse.value?.totals ?? null);
const loading = computed(() => (tab.value === 'stock' ? stockLoading.value : adjLoading.value));

const adjustOpen = ref(false);
const saving = ref(false);
const formErrors = reactive({});
const form = reactive({ reason: '', items: [] });

const historyOpen = ref(false);
const history = ref(null);

const moneyFormat = new Intl.NumberFormat('ar-SY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatMoney = (v) => moneyFormat.format(Number(v) || 0);
const formatQty = (v) => new Intl.NumberFormat('en', { maximumFractionDigits: 3 }).format(Number(v) || 0);

function tabClass(key) {
    return tab.value === key
        ? 'rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white'
        : 'rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50';
}

function selectTab(key) {
    if (tab.value === key) return;
    tab.value = key;
    load();
}

function applyFilters() {
    load();
}

function clearErrors() {
    Object.keys(formErrors).forEach((k) => delete formErrors[k]);
}

function openAdjustment(material = null) {
    clearErrors();
    form.reason = '';
    form.items = [{ material_id: material?.id ?? null, direction: 'IN', quantity: '' }];
    adjustOpen.value = true;
    loadMaterialOptions();
}

async function loadMaterialOptions() {
    if (materialOptions.value.length) return;
    try {
        const response = await materialsApi.index({ per_page: 200 });
        materialOptions.value = response.data ?? [];
    } catch {
        materialOptions.value = [];
    }
}

async function submitAdjustment() {
    saving.value = true;
    clearErrors();

    try {
        await inventoryApi.storeAdjustment({
            reason: form.reason,
            items: form.items.map((l) => ({
                material_id: l.material_id,
                direction: l.direction,
                quantity: l.quantity,
            })),
        });

        adjustOpen.value = false;
        notice.value = 'تم تسجيل التسوية وتحديث الأرصدة.';
        setTimeout(() => (notice.value = ''), 4000);
        await load({ keep: true });
    } catch (e) {
        const data = e?.response?.data;
        if (data?.errors) {
            Object.entries(data.errors).forEach(([key, messages]) => {
                formErrors[key] = messages[0];
            });
        }
        // Business rules (insufficient stock) come back as 409, not 422.
        if (!data?.errors && data?.message) formErrors.items = data.message;
    } finally {
        saving.value = false;
    }
}

async function cancelAdjustment(adjustment) {
    const reason = window.prompt(`سبب إلغاء التسوية ${adjustment.adjustment_no}؟`);
    if (!reason) return;

    error.value = '';
    try {
        await inventoryApi.cancelAdjustment(adjustment.id, reason);
        notice.value = 'تم إلغاء التسوية وإرجاع الكميات.';
        setTimeout(() => (notice.value = ''), 4000);
        await load({ keep: true });
    } catch (e) {
        error.value = e?.response?.data?.message || 'تعذّر إلغاء التسوية';
    }
}

async function openHistory(material) {
    historyOpen.value = true;
    history.value = null;
    try {
        history.value = await inventoryApi.material(material.id, { per_page: 100 });
    } catch (e) {
        error.value = 'تعذّر تحميل حركة المادة';
        historyOpen.value = false;
    }
}

function load({ keep = false } = {}) {
    error.value = '';
    return tab.value === 'stock' ? reloadStock({ keep }) : reloadAdjustments({ keep });
}

onMounted(() => load());
</script>
