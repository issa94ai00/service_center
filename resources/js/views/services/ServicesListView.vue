<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">الخدمات</h1>
                <p class="text-sm text-slate-500">خدمات الإكساء والمقاولات الداخلية</p>
            </div>
            <button v-if="can('services.create')" type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="openCreate">+ خدمة جديدة</button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <ArchiveTabs v-model="view" :archived-count="archivedCount" />
                <input v-model.trim="search" type="search" placeholder="بحث بالاسم..."
                    class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                <select v-model="typeFilter"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">كل الأنواع</option>
                    <option value="METER_BASED">مقاسة</option>
                    <option value="MANUAL">يدوية</option>
                </select>
            </div>
            <p v-if="archivedView" class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs text-slate-500">
                الخدمات المؤرشفة لا تظهر في الفواتير الجديدة، وتبقى في الفواتير السابقة والتقارير.
                الحذف النهائي متاح فقط لما لم يُستخدم في أي فاتورة.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">الاسم</th>
                            <th class="px-4 py-3">النوع</th>
                            <th class="px-4 py-3">الوحدة</th>
                            <th class="px-4 py-3 text-left">السعر الافتراضي</th>
                            <th class="px-4 py-3">{{ archivedView ? 'أُرشفت' : 'الحالة' }}</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !items.length" class="text-slate-400"><td colspan="6" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length" class="text-slate-400">
                            <td colspan="6" class="px-4 py-10 text-center">
                                <template v-if="archivedView && !search && !typeFilter">
                                    <p class="text-2xl" aria-hidden="true">🗄️</p>
                                    <p class="mt-1 font-medium text-slate-600">الأرشيف فارغ</p>
                                    <p class="mt-0.5 text-xs">الخدمات التي تؤرشفها تظهر هنا، ويمكن استعادتها في أي وقت.</p>
                                </template>
                                <template v-else>لا توجد نتائج</template>
                            </td>
                        </tr>
                        <tr v-for="row in items" :key="row.id" :class="archivedView ? 'bg-slate-50/40 text-slate-500' : 'hover:bg-slate-50'">
                            <td class="px-4 py-3 font-medium" :class="archivedView ? 'text-slate-600' : 'text-slate-900'">{{ row.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.type === 'METER_BASED' ? 'مقاسة' : 'يدوية' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.unit?.name_ar || '—' }}</td>
                            <td class="px-4 py-3 text-left font-semibold">{{ formatMoney(row.default_price) }}</td>
                            <td v-if="archivedView" class="whitespace-nowrap px-4 py-3 text-xs">{{ formatDate(row.archived_at) }}</td>
                            <td v-else class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                    {{ row.is_active ? 'نشط' : 'موقوف' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="!archivedView" class="flex items-center gap-2">
                                    <button v-if="can('services.edit')" class="text-slate-500 hover:text-slate-900" @click="openEdit(row)">تعديل</button>
                                    <button v-if="can('services.delete')"
                                        class="text-slate-500 hover:text-amber-700 disabled:opacity-40"
                                        :disabled="busyId === row.id"
                                        title="إخفاء من القوائم والفواتير الجديدة — يمكن استعادتها"
                                        @click="archiveRow(row)">
                                        {{ busyId === row.id ? '...' : 'أرشفة' }}</button>
                                </div>
                                <div v-else-if="can('services.delete')" class="flex items-center gap-3">
                                    <button class="font-medium text-blue-600 hover:text-blue-800 disabled:opacity-40"
                                        :disabled="busyId === row.id" @click="restore(row)">
                                        {{ busyId === row.id ? '...' : '↩ استعادة' }}</button>
                                    <button v-if="!row.is_referenced"
                                        class="text-red-500 hover:text-red-700 disabled:opacity-40"
                                        :disabled="busyId === row.id" @click="forceDelete(row)">حذف نهائي</button>
                                    <span v-else class="cursor-help text-xs text-slate-400"
                                        title="مستخدمة في فواتير، فتبقى في الأرشيف ليظل سجلها مقروءًا">🔒 لها سجلّ</span>
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
                unit="خدمة"
                @more="loadMore"
            />
        </div>

        <BaseModal :open="modalOpen" :title="editing ? 'تعديل خدمة' : 'خدمة جديدة'" @close="modalOpen = false">
            <ServiceForm :service="editing" @close="modalOpen = false" @saved="onSaved" />
        </BaseModal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { servicesApi } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { usePermissions } from '../../composables/usePermissions';
import { formatDate } from '../../utils/format';
import { useCatalogArchive } from '../../composables/useCatalogArchive';
import ArchiveTabs from '../../components/ui/ArchiveTabs.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import ServiceForm from '../../components/forms/ServiceForm.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import { apiMessage } from '../../utils/format';
import { useToast } from '../../composables/useToast';

const { can } = usePermissions();
const toast = useToast();
const auth = useAuthStore();
const { items, meta, response, loading, loadingMore, moreError, hasMore, reload: fetchData, loadMore } = useInfiniteList({
    fetch: (page, perPage) => servicesApi.index({
        search: search.value || undefined,
        type: typeFilter.value || undefined,
        status: archivedView.value ? 'archived' : undefined,
        page,
        per_page: perPage,
    }),
    onError: (e) => toast.error(apiMessage(e, 'تعذّر تحميل القائمة')),
});
const search = ref('');
const typeFilter = ref('');
const modalOpen = ref(false);
const editing = ref(null);
const view = ref('active');
const archivedCount = ref(0);
const archivedView = computed(() => view.value === 'archived');
let t = null;

const { busyId, archive, restore, forceDelete } = useCatalogArchive({
    api: servicesApi,
    noun: 'الخدمة',
    onChange: () => fetchData({ keep: true }),
});

watch(view, () => fetchData());

watch([search, typeFilter], () => {
    clearTimeout(t);
    t = setTimeout(() => fetchData(), 350);
});


function openCreate() { editing.value = null; modalOpen.value = true; }
function openEdit(row) { editing.value = row; modalOpen.value = true; }
watch(response, (data) => {
    if (!data) return;
    archivedCount.value = data.counts?.archived ?? 0;
    // The last archived row restored: nothing left to look at here.
    if (archivedView.value && !items.value.length && archivedCount.value === 0) {
        view.value = 'active';
    }
});

function onSaved() { fetchData({ keep: true }); }

function archiveRow(row) {
    archive(row, { detail: `السعر الافتراضي: ${formatMoney(row.default_price)}` });
}

function formatMoney(value) {
    return new Intl.NumberFormat(auth.locale === 'en' ? 'en' : 'ar-SY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

onMounted(fetchData);
</script>