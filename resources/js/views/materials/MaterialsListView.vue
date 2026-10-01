<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">المواد</h1>
                <p class="text-sm text-slate-500">مواد العمل والمخزون</p>
            </div>
            <button v-if="can('materials.create')" type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="openCreate">+ مادة جديدة</button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <ArchiveTabs v-model="view" :archived-count="archivedCount" />
                <input v-model.trim="search" type="search" placeholder="بحث بالاسم أو SKU..."
                    class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                <select v-model="categoryFilter"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">كل الأصناف</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <label v-if="!archivedView" class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="lowOnly" type="checkbox" class="h-4 w-4 accent-red-600" />
                    تحت الحد الأدنى فقط
                </label>
            </div>
            <p v-if="archivedView" class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-500">
                المواد المؤرشفة لا تظهر في الفواتير الجديدة ولا عند مسح الباركود، وتبقى في الفواتير السابقة والتقارير.
                الحذف النهائي متاح فقط لما لم يُستخدم قط.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">الاسم</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">الصنف</th>
                            <th class="px-4 py-3">الوحدة</th>
                            <th class="px-4 py-3 text-left">التكلفة</th>
                            <th class="px-4 py-3 text-left">البيع</th>
                            <th class="px-4 py-3 text-left">الرصيد</th>
                            <th v-if="archivedView" class="px-4 py-3">أُرشفت</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading" class="text-slate-400"><td :colspan="columns" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length" class="text-slate-400">
                            <td :colspan="columns" class="px-4 py-10 text-center">
                                <template v-if="archivedView && !search && !categoryFilter">
                                    <p class="text-2xl" aria-hidden="true">🗄️</p>
                                    <p class="mt-1 font-medium text-slate-600">الأرشيف فارغ</p>
                                    <p class="mt-0.5 text-xs">المواد التي تؤرشفها تظهر هنا، ويمكن استعادتها في أي وقت.</p>
                                </template>
                                <template v-else>لا توجد نتائج</template>
                            </td>
                        </tr>
                        <tr v-for="row in items" :key="row.id" :class="archivedView ? 'bg-slate-50/40 text-slate-500' : 'hover:bg-slate-50'">
                            <td class="px-4 py-3 font-medium" :class="archivedView ? 'text-slate-600' : 'text-slate-900'">
                                {{ row.name }}
                                <span v-if="row.is_low && !archivedView"
                                    class="ms-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700">منخفض</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.sku }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.category?.name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.unit?.name_ar || '—' }}</td>
                            <td class="px-4 py-3 text-left">{{ formatMoney(row.cost_price) }}</td>
                            <td class="px-4 py-3 text-left">{{ formatMoney(row.sale_price) }}</td>
                            <td class="px-4 py-3 text-left font-semibold" :class="{ 'text-red-600': row.is_low && !archivedView }">
                                {{ formatQty(row.stock_quantity) }}
                            </td>
                            <td v-if="archivedView" class="whitespace-nowrap px-4 py-3 text-xs">{{ formatDate(row.archived_at) }}</td>
                            <td class="px-4 py-3">
                                <div v-if="!archivedView" class="flex items-center gap-2">
                                    <button v-if="can('materials.edit')" class="text-slate-500 hover:text-slate-900" @click="openEdit(row)">تعديل</button>
                                    <button v-if="can('materials.delete')"
                                        class="text-slate-500 hover:text-amber-700 disabled:opacity-40"
                                        :disabled="busyId === row.id"
                                        title="إخفاء من القوائم والفواتير الجديدة — يمكن استعادتها"
                                        @click="archiveRow(row)">
                                        {{ busyId === row.id ? '...' : 'أرشفة' }}</button>
                                </div>
                                <div v-else-if="can('materials.delete')" class="flex items-center gap-3">
                                    <button class="font-medium text-blue-600 hover:text-blue-800 disabled:opacity-40"
                                        :disabled="busyId === row.id" @click="restore(row)">
                                        {{ busyId === row.id ? '...' : '↩ استعادة' }}</button>
                                    <button v-if="!row.is_referenced"
                                        class="text-red-500 hover:text-red-700 disabled:opacity-40"
                                        :disabled="busyId === row.id" @click="forceDelete(row)">حذف نهائي</button>
                                    <span v-else class="cursor-help text-xs text-slate-400"
                                        title="لها فواتير أو حركات مخزنية، فتبقى في الأرشيف ليظل سجلها مقروءًا">🔒 لها سجلّ</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="meta" class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
                <span>صفحة {{ meta.current_page }} من {{ meta.last_page }} — {{ meta.total }}</span>
                <div class="flex gap-2">
                    <button :disabled="!meta.prev_page_url"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40" @click="page--">السابق</button>
                    <button :disabled="!meta.next_page_url"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 disabled:opacity-40" @click="page++">التالي</button>
                </div>
            </div>
        </div>

        <BaseModal :open="modalOpen" :title="editing ? 'تعديل مادة' : 'مادة جديدة'" @close="modalOpen = false">
            <MaterialForm :material="editing" @close="modalOpen = false" @saved="onSaved" />
        </BaseModal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { materialsApi, materialCategoriesApi } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { usePermissions } from '../../composables/usePermissions';
import { formatDate } from '../../utils/format';
import { useCatalogArchive } from '../../composables/useCatalogArchive';
import ArchiveTabs from '../../components/ui/ArchiveTabs.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import MaterialForm from '../../components/forms/MaterialForm.vue';

const { can } = usePermissions();
const auth = useAuthStore();
const items = ref([]);
const categories = ref([]);
const meta = ref(null);
const loading = ref(false);
const search = ref('');
const categoryFilter = ref('');
const lowOnly = ref(false);
const page = ref(1);
const modalOpen = ref(false);
const editing = ref(null);
const view = ref('active');
const archivedCount = ref(0);
const archivedView = computed(() => view.value === 'archived');
const columns = computed(() => (archivedView.value ? 9 : 8));
let t = null;

const { busyId, archive, restore, forceDelete } = useCatalogArchive({
    api: materialsApi,
    noun: 'المادة',
    onChange: fetchData,
});

// Resetting the page refetches through its own watcher.
watch(view, () => { if (page.value !== 1) page.value = 1; else fetchData(); });
watch([search, categoryFilter, lowOnly], () => {
    clearTimeout(t);
    t = setTimeout(() => { page.value = 1; fetchData(); }, 350);
});
watch(page, fetchData);

async function fetchData() {
    loading.value = true;
    try {
        const data = await materialsApi.index({
            search: search.value || undefined,
            category_id: categoryFilter.value || undefined,
            low_stock: lowOnly.value && !archivedView.value ? 1 : undefined,
            status: archivedView.value ? 'archived' : undefined,
            page: page.value,
            per_page: 15,
        });
        items.value = data.data ?? data;
        meta.value = data.meta ?? null;
        archivedCount.value = data.counts?.archived ?? 0;
        // The last archived row restored: nothing left to look at here.
        if (archivedView.value && !items.value.length && archivedCount.value === 0 && page.value === 1) {
            view.value = 'active';
        }
    } finally {
        loading.value = false;
    }
}

function openCreate() { editing.value = null; modalOpen.value = true; }
function openEdit(row) { editing.value = row; modalOpen.value = true; }
function onSaved() { fetchData(); }

function archiveRow(row) {
    archive(row, { detail: `SKU: ${row.sku} · الرصيد: ${formatQty(row.stock_quantity)}` });
}

function formatMoney(value) {
    return new Intl.NumberFormat(auth.locale === 'en' ? 'en' : 'ar-SY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

function formatQty(value) {
    return new Intl.NumberFormat(auth.locale === 'en' ? 'en' : 'ar-SY').format(Number(value));
}

onMounted(async () => {
    fetchData();
    try {
        categories.value = await materialCategoriesApi.options();
    } catch { /* ignore */ }
});
</script>