<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <Field label="اسم المادة *" required>
                <input v-model.trim="form.name" type="text" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="SKU / كود الصنف *" required>
                <input v-model.trim="form.sku" type="text" dir="ltr" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="الباركود">
                <!-- الحقل مركَّز للمسح: الماسح يكتب الرمز ثم Enter، فلا يُرسَل
                     النموذج قبل اكتمال بقية الحقول. -->
                <input
                    v-model.trim="form.barcode"
                    type="text"
                    dir="ltr"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="امسح أو اكتب الباركود"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    @keydown.enter.prevent
                />
                <p class="mt-1 text-xs text-slate-500">اختياري — يُترك فارغاً للمواد غير المرقّمة من مصنعها.</p>
            </Field>
            <Field label="الصنف">
                <select v-model="form.category_id"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option :value="null">— بدون صنف —</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </Field>
            <Field label="وحدة القياس *" required>
                <select v-model="form.unit_id" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name_ar }}</option>
                </select>
            </Field>
            <Field label="سعر التكلفة *" required>
                <input v-model="form.cost_price" type="number" step="0.01" min="0" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="سعر البيع *" required>
                <input v-model="form.sale_price" type="number" step="0.01" min="0" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="حد إعادة الطلب (الحد الأدنى)">
                <input v-model="form.min_stock" type="number" step="0.01" min="0"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
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
import { materialsApi, materialCategoriesApi, serviceUnitsApi } from '../../services/api';

const props = defineProps({ material: { type: Object, default: null } });
const emit = defineEmits(['close', 'saved']);
const loading = ref(false);
const error = ref('');
const categories = ref([]);
const units = ref([]);

const form = reactive({
    name: props.material?.name ?? '',
    sku: props.material?.sku ?? '',
    barcode: props.material?.barcode ?? '',
    category_id: props.material?.category_id ?? null,
    unit_id: props.material?.unit_id ?? null,
    cost_price: props.material?.cost_price ?? '0.00',
    sale_price: props.material?.sale_price ?? '0.00',
    min_stock: props.material?.min_stock ?? '0.00',
    is_active: props.material?.is_active ?? true,
});

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        const payload = { ...form, category_id: form.category_id || null };
        const { data } = props.material
            ? await materialsApi.update(props.material.id, payload)
            : await materialsApi.store(payload);
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
        [categories.value, units.value] = await Promise.all([materialCategoriesApi.options(), serviceUnitsApi.options()]);
    } catch { /* ignore */ }
});
</script>