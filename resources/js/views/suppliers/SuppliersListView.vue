<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">الموردون</h1>
                <p class="text-sm text-slate-500">إدارة حسابات الموردين وأرصدتهم</p>
            </div>
            <button
                v-if="can('suppliers.create')"
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                @click="openCreate"
            >
                + مورد جديد
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <input
                    v-model.trim="search"
                    type="search"
                    placeholder="بحث بالاسم أو الشركة أو الهاتف..."
                    class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
                <select
                    v-model="activeFilter"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                >
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
                            <th class="px-4 py-3">الشركة</th>
                            <th class="px-4 py-3">الهاتف</th>
                            <th class="px-4 py-3">المدينة</th>
                            <th class="px-4 py-3 text-left">الرصيد</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !items.length" class="text-slate-400">
                            <td colspan="7" class="px-4 py-8 text-center">جارٍ التحميل...</td>
                        </tr>
                        <tr v-else-if="!items.length" class="text-slate-400">
                            <td colspan="7" class="px-4 py-8 text-center">لا توجد نتائج</td>
                        </tr>
                        <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.company || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.phone || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.city || '—' }}</td>
                            <td class="px-4 py-3 text-left font-semibold">{{ formatMoney(row.balance) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ row.is_active ? 'نشط' : 'موقوف' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2 whitespace-nowrap">
                                    <button
                                        v-if="can('suppliers.edit')"
                                        class="text-slate-500 hover:text-slate-900"
                                        title="تعديل"
                                        @click="openEdit(row)"
                                    >
                                        تعديل
                                    </button>
                                    <RouterLink
                                        v-if="can('statements.view')"
                                        class="text-slate-500 hover:text-slate-900"
                                        title="كشف حساب"
                                        :to="{ name: 'suppliers.statement', params: { id: row.id } }"
                                    >
                                        كشف حساب
                                    </RouterLink>
                                    <button
                                        v-if="can('suppliers.delete')"
                                        class="text-red-500 hover:text-red-700 disabled:opacity-40"
                                        title="حذف"
                                        :disabled="deletingId === row.id"
                                        @click="remove(row)"
                                    >
                                        {{ deletingId === row.id ? '...' : 'حذف' }}
                                    </button>
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
                unit="مورد"
                @more="loadMore"
            />
        </div>

        <BaseModal
            :open="modalOpen"
            :title="editing ? 'تعديل مورد' : 'مورد جديد'"
            @close="modalOpen = false"
        >
            <SupplierForm :supplier="editing" @close="modalOpen = false" @saved="onSaved" />
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { suppliersApi } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { usePermissions } from '../../composables/usePermissions';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { apiMessage } from '../../utils/format';
import BaseModal from '../../components/ui/BaseModal.vue';
import SupplierForm from '../../components/forms/SupplierForm.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';

const { can } = usePermissions();
const auth = useAuthStore();
const toast = useToast();

const { items, meta, loading, loadingMore, moreError, hasMore, reload: fetchData, loadMore } = useInfiniteList({
    fetch: (page, perPage) => suppliersApi.index({
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
let debounceTimer = null;

watch([search, activeFilter], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchData();
    }, 350);
});



function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(row) {
    editing.value = row;
    modalOpen.value = true;
}

function onSaved() {
    fetchData({ keep: true });
}

async function remove(row) {
    const ok = await confirm({
        title: 'حذف المورد؟',
        message: `سيُحذف "${row.name}" نهائيًا ولا يمكن استرجاعه.`,
        detail: `الرصيد الحالي: ${formatMoney(row.balance)}`,
        confirmLabel: 'حذف نهائي',
        tone: 'danger',
    });
    if (!ok) return;

    deletingId.value = row.id;
    try {
        await suppliersApi.destroy(row.id);
        toast.success('تم حذف المورد');
        fetchData({ keep: true });
    } catch (e) {
        toast.error(
            apiMessage(e, 'تعذّر حذف هذا المورد — الموردون ذوو الحركات المالية لا يمكن حذفهم')
        );
    } finally {
        deletingId.value = null;
    }
}

function formatMoney(value) {
    return new Intl.NumberFormat(auth.locale === 'en' ? 'en' : 'ar-SY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
}

onMounted(fetchData);
</script>