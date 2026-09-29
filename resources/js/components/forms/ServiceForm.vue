<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <Field label="اسم الخدمة *" required>
                <input v-model.trim="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="نوع الخدمة *">
                <select v-model="form.type" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="METER_BASED">مقاسة (بالعداد)</option>
                    <option value="MANUAL">يدوية (قطعة)</option>
                </select>
            </Field>
            <Field label="وحدة القياس">
                <select v-model="form.unit_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option :value="null">— بدون وحدة —</option>
                    <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name_ar }}</option>
                </select>
            </Field>
            <Field label="السعر الافتراضي">
                <input v-model="form.default_price" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="الوصف" class="sm:col-span-2">
                <textarea v-model.trim="form.description" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
            </Field>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-blue-600" />
                مفعّلة
            </label>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" @click="$emit('close')">إلغاء</button>
            <button type="submit" :disabled="loading" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" :class="{ 'opacity-60': loading }">
                {{ loading ? 'جارٍ الحفظ...' : 'حفظ' }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import Field from '../ui/FormField.vue';
import { servicesApi, serviceUnitsApi } from '../../services/api';

const props = defineProps({ service: { type: Object, default: null } });
const emit = defineEmits(['close', 'saved']);
const loading = ref(false);
const error = ref('');
const units = ref([]);

const form = reactive({
    name: props.service?.name ?? '',
    description: props.service?.description ?? '',
    type: props.service?.type ?? 'METER_BASED',
    unit_id: props.service?.unit_id ?? null,
    default_price: props.service?.default_price ?? '0.00',
    is_active: props.service?.is_active ?? true,
});

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        const payload = { ...form, unit_id: form.unit_id || null };
        const { data } = props.service
            ? await servicesApi.update(props.service.id, payload)
            : await servicesApi.store(payload);
        emit('saved', data);
        emit('close');
    } catch (e) {
        const msg = e.response?.data?.message;
        error.value = msg ? (typeof msg === 'string' ? msg : Object.values(msg).flat().join('، ')) : 'تعذّر الحفظ';
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    try {
        units.value = await serviceUnitsApi.options();
    } catch {
        /* ignore */
    }
});
</script>
