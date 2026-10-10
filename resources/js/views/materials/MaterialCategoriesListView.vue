<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">أصناف المواد</h1>
                <p class="text-sm text-slate-500">تصنيف المواد المخزنية</p>
            </div>
            <button v-if="can('materials.create')" type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="openCreate">+ صنف جديد</button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <input v-model.trim="search" type="search" placeholder="بحث بالاسم..."
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
                            <th class="px-4 py-3">الصنف الرئيسي</th>
                            <th class="px-4 py-3 text-left">عدد المواد</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !items.length" class="text-slate-400"><td colspan="5" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length" class="text-slate-400"><td colspan="5" class="px-4 py-8 text-center">لا توجد نتائج</td></tr>
                        <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ parentName(row.parent_id) }}</td>
                            <td class="px-4 py-3 text-left">{{ row.materials_count }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                    {{ row.is_active ? 'نشط' : 'موقوف' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button v-if="can('materials.edit')" class="text-slate-500 hover:text-slate-900" @click="openEdit(row)">تعديل</button>
                                    <button v-if="can('materials.delete')"
                                        class="text-red-500 hover:text-red-700 disabled:opacity-40"
                                        :disabled="deletingId === row.id"
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
                unit="فئة"
                @more="loadMore"
            />
        </div>

        <BaseModal :open="modalOpen" :title="editing ? 'تعديل صنف' : 'صنف جديد'" @close="modalOpen = false">
            <MaterialCategoryForm :category="editing" @close="modalOpen = false" @saved="onSaved" />
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { materialCategoriesApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { apiMessage } from '../../utils/format';
import BaseModal from '../../components/ui/BaseModal.vue';
import MaterialCategoryForm from '../../components/forms/MaterialCategoryForm.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';

const { can } = usePermissions();
const toast = useToast();
const { items, meta, loading, loadingMore, moreError, hasMore, reload: fetchData, loadMore } = useInfiniteList({
    fetch: (page, perPage) => materialCategoriesApi.index({
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


function parentName(id) {
    if (!id) return '—';
    const found = items.value.find((c) => c.id === id);
    return found ? found.name : `#${id}`;
}

function openCreate() { editing.value = null; modalOpen.value = true; }
function openEdit(row) { editing.value = row; modalOpen.value = true; }
function onSaved() { fetchData({ keep: true }); }

async function remove(row) {
    const ok = await confirm({
        title: 'حذف الصنف؟',
        message: `سيُحذف "${row.name}" نهائيًا ولا يمكن استرجاعه.`,
        detail: `عدد المواد: ${row.materials_count ?? 0}`,
        confirmLabel: 'حذف نهائي',
        tone: 'danger',
    });
    if (!ok) return;

    deletingId.value = row.id;
    try {
        await materialCategoriesApi.destroy(row.id);
        toast.success('تم حذف الصنف');
        fetchData({ keep: true });
    } catch (e) {
        toast.error(
            apiMessage(e, 'تعذّر حذف هذا الصنف — الأصناف ذات المواد أو الأصناف الفرعية لا يمكن حذفها')
        );
    } finally {
        deletingId.value = null;
    }
}

onMounted(fetchData);
</script>