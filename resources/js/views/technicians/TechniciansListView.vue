<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">خبراء الصيانة</h1>
                <p class="text-sm text-slate-500">نسب العمولة ومستحقات كل خبير</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <RouterLink
                    v-if="can('reports.view') && can('reports.technicians')"
                    :to="{ name: 'reports', query: { tab: 'technicians' } }"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    التقرير المحاسبي
                </RouterLink>
                <button
                    v-if="can('technicians.create')"
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                    @click="openCreate"
                >
                    ＋ خبير جديد
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <input
                    v-model.trim="search"
                    type="search"
                    placeholder="بحث بالاسم أو التخصّص أو الهاتف..."
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

            <TableSkeleton v-if="loading && !rows.length" :columns="6" />

            <EmptyState
                v-else-if="!rows.length"
                icon="🧰"
                title="لا يوجد خبراء صيانة"
                :hint="search ? 'جرّب بحثاً آخر' : 'أضف خبيراً ليمكن إسناد البلاغات إليه'"
            />

            <div v-else class="overflow-x-auto" :class="loading ? 'opacity-50' : ''" :aria-busy="loading">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-4 py-3">الاسم</th>
                            <th class="px-4 py-3">التخصّص</th>
                            <th class="px-4 py-3">الهاتف</th>
                            <th class="px-4 py-3 text-left">النسبة</th>
                            <th class="px-4 py-3 text-left">الرصيد المستحق له</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.specialty || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.phone || row.mobile || '—' }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-slate-700">{{ formatPercent(row.commission_rate / 100) }}</td>
                            <td
                                class="px-4 py-3 text-left font-semibold tabular-nums"
                                :class="Number(row.balance) > 0 ? 'text-amber-700' : 'text-slate-500'"
                            >
                                {{ formatMoney(row.balance) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ row.is_active ? 'نشط' : 'موقوف' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-3">
                                    <button
                                        v-if="can('technicians.edit')"
                                        type="button"
                                        class="text-slate-500 transition hover:text-slate-900"
                                        @click="openEdit(row)"
                                    >
                                        تعديل
                                    </button>
                                    <RouterLink
                                        v-if="can('statements.view')"
                                        class="text-slate-500 transition hover:text-slate-900"
                                        :to="{ name: 'technicians.statement', params: { id: row.id } }"
                                    >
                                        كشف حساب
                                    </RouterLink>
                                    <button
                                        v-if="can('payments.create')"
                                        type="button"
                                        class="text-slate-500 transition hover:text-slate-900"
                                        @click="payout(row)"
                                    >
                                        صرف مستحقات
                                    </button>
                                    <button
                                        v-if="can('technicians.delete')"
                                        type="button"
                                        class="text-red-500 transition hover:text-red-700"
                                        @click="remove(row)"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <InfiniteScrollFooter
                :shown="rows.length"
                :total="meta?.total ?? 0"
                :has-more="hasMore"
                :loading-more="loadingMore"
                :busy="loading"
                :error="moreError"
                unit="خبير"
                @more="loadMore"
            />
        </div>

        <BaseModal
            :open="modalOpen"
            :title="editing ? 'تعديل خبير صيانة' : 'خبير صيانة جديد'"
            @close="modalOpen = false"
        >
            <TechnicianForm :technician="editing" @close="modalOpen = false" @saved="onSaved" />
        </BaseModal>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import BaseModal from '../../components/ui/BaseModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import TableSkeleton from '../../components/ui/TableSkeleton.vue';
import TechnicianForm from '../../components/forms/TechnicianForm.vue';
import { techniciansApi } from '../../services/api';
import { useConfirm } from '../../composables/useConfirm';
import { usePermissions } from '../../composables/usePermissions';
import { useToast } from '../../composables/useToast';
import { apiMessage, formatMoney, formatPercent } from '../../utils/format';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';

const { can } = usePermissions();
const { confirm } = useConfirm();
const toast = useToast();
const router = useRouter();

const search = ref('');
const activeFilter = ref('');
const modalOpen = ref(false);
const editing = ref(null);

const { items: rows, meta, loading, loadingMore, moreError, hasMore, reload: load, loadMore } = useInfiniteList({
    fetch: (page, perPage) => techniciansApi.index({
        search: search.value || undefined,
        is_active: activeFilter.value === '' ? undefined : activeFilter.value,
        page,
        per_page: perPage,
    }),
    onError: (e) => toast.error(apiMessage(e, 'تعذّر تحميل خبراء الصيانة')),
});

let debounce = null;

watch([search, activeFilter], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        load();
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
    toast.success('تم حفظ بيانات الخبير');
    load({ keep: true });
}

/** سند الصرف يُفتح مهيّأً بالخبير، فلا يُعاد اختياره في شاشة الدفعات. */
function payout(row) {
    router.push({ name: 'payments.create', query: { type: 'TECHNICIAN', technician_id: row.id } });
}

async function remove(row) {
    const ok = await confirm({
        title: 'حذف خبير الصيانة',
        message: `سيُحذف "${row.name}" من قوائم الإسناد.`,
        detail: 'لا يمكن حذف خبير له بلاغات أو حركات مالية — عطّله بدل ذلك.',
        confirmLabel: 'حذف',
        tone: 'danger',
    });

    if (!ok) return;

    try {
        await techniciansApi.destroy(row.id);
        toast.success('تم حذف الخبير');
        load({ keep: true });
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر حذف هذا الخبير'));
    }
}

onBeforeUnmount(() => {
    clearTimeout(debounce);
});

onMounted(() => load());
</script>
