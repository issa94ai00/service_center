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
                <p class="mt-1 text-xs" :class="form.cost_method === 'MANUAL' ? 'text-slate-400' : 'text-blue-600'">
                    {{ costPriceHint }}
                </p>
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

        <Field label="طريقة حساب التكلفة" required>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4" role="radiogroup" aria-label="طريقة حساب التكلفة">
                <label v-for="m in costMethods" :key="m.value"
                    class="flex cursor-pointer flex-col rounded-xl border p-3 transition focus-within:ring-2 focus-within:ring-blue-200"
                    :class="form.cost_method === m.value
                        ? 'border-blue-500 bg-blue-50'
                        : 'border-slate-200 hover:border-slate-300'">
                    <input v-model="form.cost_method" type="radio" class="sr-only" name="cost_method" :value="m.value"
                        @change="costMethodTouched = true" />
                    <span class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full"
                            :class="form.cost_method === m.value ? 'bg-blue-600' : 'bg-slate-300'"></span>
                        <span class="text-sm font-semibold"
                            :class="form.cost_method === m.value ? 'text-blue-800' : 'text-slate-700'">{{ m.label }}</span>
                    </span>
                    <span class="mt-1 text-xs leading-5"
                        :class="form.cost_method === m.value ? 'text-blue-700' : 'text-slate-400'">{{ m.hint }}</span>
                </label>
            </div>
        </Field>

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
import { computed, onMounted, reactive, ref } from 'vue';
import Field from '../ui/FormField.vue';
import { materialsApi, materialCategoriesApi, serviceUnitsApi, settingsApi } from '../../services/api';

const props = defineProps({ material: { type: Object, default: null } });
const emit = defineEmits(['close', 'saved']);
const loading = ref(false);
const error = ref('');
const costMethodTouched = ref(false);
const categories = ref([]);
const units = ref([]);

const costMethods = [
    { value: 'MANUAL', label: 'يدوية', hint: 'التكلفة تبقى كما تُدخلها ولا تتغيّر مع الشراء.' },
    { value: 'WEIGHTED_AVERAGE', label: 'المتوسط المرحّل', hint: 'تتحدّث مع كل توريد بمتوسط الكميات والأسعار.' },
    { value: 'LAST_PURCHASE', label: 'آخر سعر شراء', hint: 'تُستبدل بسعر آخر فاتورة شراء مرحّلة.' },
    { value: 'FIFO', label: 'الوارد أولاً', hint: 'تُصرف الكميات من أقدم دفعات الشراء بسعرها الفعلي.' },
];

const form = reactive({
    name: props.material?.name ?? '',
    sku: props.material?.sku ?? '',
    barcode: props.material?.barcode ?? '',
    category_id: props.material?.category_id ?? null,
    unit_id: props.material?.unit_id ?? null,
    cost_price: props.material?.cost_price ?? '0.00',
    cost_method: props.material?.cost_method ?? 'MANUAL',
    sale_price: props.material?.sale_price ?? '0.00',
    min_stock: props.material?.min_stock ?? '0.00',
    is_active: props.material?.is_active ?? true,
});

const costPriceHint = computed(() => form.cost_method === 'MANUAL'
    ? 'أدخل تكلفة الشراء الحالية — لن تتغيّر تلقائياً.'
    : 'القيمة الحالية نقطة انطلاق ستُحدَّث تلقائياً عند ترحيل فواتير الشراء.');

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        const payload = { ...form, category_id: form.category_id || null };
        if (!props.material && !costMethodTouched.value) {
            delete payload.cost_method;
        }
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

    if (!props.material) {
        try {
            const list = await settingsApi.index();
            const setting = list.find((s) => s.key === 'inventory_cost_method');
            if (setting?.value) form.cost_method = setting.value;
        } catch { /* بلا صلاحية إعدادات — يطبّق الخادم الافتراضي */ }
    }
});
</script>