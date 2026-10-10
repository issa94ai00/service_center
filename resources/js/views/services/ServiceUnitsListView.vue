<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">وحدات القياس</h1>
                <p class="text-sm text-slate-500">وحدات الخدمات والمواد</p>
            </div>
            <button v-if="can('services.create')" type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="openCreate">+ وحدة جديدة</button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <input v-model.trim="search" type="search" placeholder="بحث..."
                    class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                <select v-model="activeFilter"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">كل الحالات</option>
                    <option value="1">نشط</option>
                    <option value="0">موقوف</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">الاسم</th>
                            <th class="px-4 py-3">الرمز</th>
                            <th class="px-4 py-3">الفئة</th>
                            <th class="px-4 py-3">قياسي</th>
                            <th class="px-4 py-3 text-left">خدمات</th>
                            <th class="px-4 py-3 text-left">مواد</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !items.length" class="text-slate-400"><td colspan="8" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length" class="text-slate-400"><td colspan="8" class="px-4 py-8 text-center">لا توجد نتائج</td></tr>
                        <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ row.name_ar }}<span v-if="row.name_en" class="text-slate-400"> ({{ row.name_en }})</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.symbol || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ unitCategory[row.category] || row.category }}</td>
                            <td class="px-4 py-3">{{ row.is_measurable ? 'نعم' : 'لا' }}</td>
                            <td class="px-4 py-3 text-left">{{ row.services_count }}</td>
                            <td class="px-4 py-3 text-left">{{ row.materials_count }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                    {{ row.is_active ? 'نشط' : 'موقوف' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button v-if="can('services.edit')" class="text-slate-500 hover:text-slate-900" :title="'تعديل'" @click="openEdit(row)">تعديل</button>
                                    <button v-if="can('services.delete')"
                                        class="text-red-500 hover:text-red-700 disabled:opacity-40"
                                        :disabled="deletingId === row.id"
                                        :title="'حذف'"
                                        @click="remove(row)">
                                        {{ deletingId === row.id ? '...' : 'حذف' }}</button>
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
                unit="وحدة"
                @more="loadMore"
            />
        </div>

        <BaseModal :open="modalOpen" :title="editing ? 'تعديل وحدة' : 'وحدة جديدة'" @close="modalOpen = false">
            <ServiceUnitForm :unit="editing" @close="modalOpen = false" @saved="onSaved" />
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { serviceUnitsApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { apiMessage } from '../../utils/format';
import BaseModal from '../../components/ui/BaseModal.vue';
import ServiceUnitForm from '../../components/forms/ServiceUnitForm.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';

const { can } = usePermissions();
const toast = useToast();
const unitCategory = { LENGTH: 'طول', AREA: 'مساحة', VOLUME: 'حجم', COUNT: 'عدد', TIME: 'زمن', OTHER: 'أخرى' };
const { items, meta, loading, loadingMore, moreError, hasMore, reload: fetchData, loadMore } = useInfiniteList({
    fetch: (page, perPage) => serviceUnitsApi.index({
        search: search.value || undefined,
        is_active: activeFilter.value === '' ? undefined : activeFilter.value,
        page,
        per_page: perPage,
    }),
    onError: (e) => toast.error(apiMessage(e, 'تعذّر تحميل القائمة')),
});
const search = ref('');
const activeFilter = ref('');
const modalOpen = ref(false);
const editing = ref(null);
const deletingId = ref(null);
let t = null;

watch([search, activeFilter], () => {
    clearTimeout(t);
    t = setTimeout(() => fetchData(), 350);
});


function openCreate() { editing.value = null; modalOpen.value = true; }
function openEdit(row) { editing.value = row; modalOpen.value = true; }
function onSaved() { fetchData({ keep: true }); }

async function remove(row) {
    const ok = await confirm({
        title: 'حذف الوحدة؟',
        message: `سيُحذف "${row.name_ar}" نهائيًا ولا يمكن استرجاعها.`,
        detail: `خدمات: ${row.services_count ?? 0} · مواد: ${row.materials_count ?? 0}`,
        confirmLabel: 'حذف نهائي',
        tone: 'danger',
    });
    if (!ok) return;

    deletingId.value = row.id;
    try {
        await serviceUnitsApi.destroy(row.id);
        toast.success('تم حذف الوحدة');
        fetchData({ keep: true });
    } catch (e) {
        toast.error(
            apiMessage(e, 'تعذّر حذف هذه الوحدة — الوحدات المرتبطة بخدمات أو مواد لا يمكن حذفها')
        );
    } finally {
        deletingId.value = null;
    }
}

onMounted(fetchData);
</script>