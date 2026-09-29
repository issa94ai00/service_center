<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <Field label="اسم الصنف *" required>
                <input v-model.trim="form.name" type="text" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="الصنف الرئيسي">
                <select v-model="form.parent_id"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option :value="null">— بدون —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </Field>
            <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-blue-600" />
                مفعّل
            </label>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="$emit('close')">إلغاء</button>
            <button type="submit" :disabled="loading"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                :class="{ 'opacity-60': loading }">
                {{ loading ? 'جارٍ الحفظ...' : 'حفظ' }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import Field from '../ui/FormField.vue';
import { materialCategoriesApi } from '../../services/api';

const props = defineProps({ category: { type: Object, default: null } });
const emit = defineEmits(['close', 'saved']);
const loading = ref(false);
const error = ref('');
const categories = ref([]);

const form = reactive({
    name: props.category?.name ?? '',
    parent_id: props.category?.parent_id ?? null,
    is_active: props.category?.is_active ?? true,
});

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        const payload = { ...form, parent_id: form.parent_id || null };
        const { data } = props.category
            ? await materialCategoriesApi.update(props.category.id, payload)
            : await materialCategoriesApi.store(payload);
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
        categories.value = (await materialCategoriesApi.options()).filter((c) => c.id !== props.category?.id);
    } catch { /* ignore */ }
});
</script>