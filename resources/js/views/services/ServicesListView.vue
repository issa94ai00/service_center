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
                <input v-model.trim="search" type="search" placeholder="بحث بالاسم..."
                    class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                <select v-model="typeFilter"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">كل الأنواع</option>
                    <option value="METER_BASED">مقاسة</option>
                    <option value="MANUAL">يدوية</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">الاسم</th>
                            <th class="px-4 py-3">النوع</th>
                            <th class="px-4 py-3">الوحدة</th>
                            <th class="px-4 py-3 text-left">السعر الافتراضي</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading" class="text-slate-400"><td colspan="6" class="px-4 py-8 text-center">جارٍ التحميل...</td></tr>
                        <tr v-else-if="!items.length" class="text-slate-400"><td colspan="6" class="px-4 py-8 text-center">لا توجد نتائج</td></tr>
                        <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.type === 'METER_BASED' ? 'مقاسة' : 'يدوية' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.unit?.name_ar || '—' }}</td>
                            <td class="px-4 py-3 text-left font-semibold">{{ formatMoney(row.default_price) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                    {{ row.is_active ? 'نشط' : 'موقوف' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button v-if="can('services.edit')" class="text-slate-500 hover:text-slate-900" @click="openEdit(row)">تعديل</button>
                                    <button v-if="can('services.delete')"
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

        <BaseModal :open="modalOpen" :title="editing ? 'تعديل خدمة' : 'خدمة جديدة'" @close="modalOpen = false">
            <ServiceForm :service="editing" @close="modalOpen = false" @saved="onSaved" />
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { servicesApi } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { usePermissions } from '../../composables/usePermissions';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { apiMessage } from '../../utils/format';
import BaseModal from '../../components/ui/BaseModal.vue';
import ServiceForm from '../../components/forms/ServiceForm.vue';

const { can } = usePermissions();
const auth = useAuthStore();
const toast = useToast();
const items = ref([]);
const meta = ref(null);
const loading = ref(false);
const search = ref('');
const typeFilter = ref('');
const page = ref(1);
const modalOpen = ref(false);
const editing = ref(null);
const deletingId = ref(null);
let t = null;

watch([search, typeFilter], () => {
    clearTimeout(t);
    t = setTimeout(() => { page.value = 1; fetchData(); }, 350);
});
watch(page, fetchData);

async function fetchData() {
    loading.value = true;
    try {
        const data = await servicesApi.index({
            search: search.value || undefined,
            type: typeFilter.value || undefined,
            page: page.value,
            per_page: 15,
        });
        items.value = data.data ?? data;
        meta.value = data.meta ?? null;
    } finally {
        loading.value = false;
    }
}

function openCreate() { editing.value = null; modalOpen.value = true; }
function openEdit(row) { editing.value = row; modalOpen.value = true; }
function onSaved() { fetchData(); }

async function remove(row) {
    const ok = await confirm({
        title: 'حذف الخدمة؟',
        message: `سيُحذف "${row.name}" نهائيًا ولا يمكن استرجاعها.`,
        detail: `السعر الافتراضي: ${formatMoney(row.default_price)}`,
        confirmLabel: 'حذف نهائي',
        tone: 'danger',
    });
    if (!ok) return;

    deletingId.value = row.id;
    try {
        await servicesApi.destroy(row.id);
        toast.success('تم حذف الخدمة');
        fetchData();
    } catch (e) {
        toast.error(
            apiMessage(e, 'تعذّر حذف هذه الخدمة — الخدمات المستخدمة في فواتير لا يمكن حذفها')
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