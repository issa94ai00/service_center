<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <Field label="الاسم" required>
                <input v-model.trim="form.name" type="text" required :class="inputClass" />
                <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
            </Field>
            <Field label="التخصّص">
                <input v-model.trim="form.specialty" type="text" placeholder="شاشات، بوردات، برمجيات..." :class="inputClass" />
            </Field>

            <Field label="النسبة من قيمة البلاغ (٪)">
                <div class="relative">
                    <input
                        v-model="form.commission_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        :class="[inputClass, 'pe-8']"
                    />
                    <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm text-slate-400">٪</span>
                </div>
                <!-- النسبة هنا افتراضية فقط؛ ما يُقيَّد في الدفتر هو نسبة لحظة التسوية. -->
                <p class="mt-1 text-xs text-slate-500">
                    تُطبَّق تلقائياً على التكلفة النهائية لكل بلاغ، ويمكن تعديلها عند التسوية.
                </p>
                <p v-if="fieldErrors.commission_rate" class="mt-1 text-xs text-red-600">
                    {{ fieldErrors.commission_rate }}
                </p>
            </Field>

            <Field label="الرصيد الافتتاحي">
                <input v-model="form.opening_balance" type="number" step="0.01" :class="inputClass" />
                <p class="mt-1 text-xs text-slate-500">موجب إذا كان للخبير رصيد سابق على المحل.</p>
            </Field>

            <Field label="الهاتف">
                <input v-model.trim="form.phone" type="text" dir="ltr" :class="inputClass" />
            </Field>
            <Field label="الجوال">
                <input v-model.trim="form.mobile" type="text" dir="ltr" :class="inputClass" />
            </Field>
            <Field label="البريد الإلكتروني">
                <input v-model.trim="form.email" type="email" dir="ltr" :class="inputClass" />
            </Field>
            <Field label="المدينة">
                <input v-model.trim="form.city" type="text" :class="inputClass" />
            </Field>
            <Field label="العنوان" class="sm:col-span-2">
                <input v-model.trim="form.address" type="text" :class="inputClass" />
            </Field>
            <Field label="ملاحظات" class="sm:col-span-2">
                <textarea v-model.trim="form.notes" rows="2" :class="inputClass"></textarea>
            </Field>

            <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                خبير نشط — يظهر في قوائم إسناد البلاغات
            </label>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <button
                type="button"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                @click="$emit('close')"
            >
                إلغاء
            </button>
            <button
                type="submit"
                :disabled="loading"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
            >
                {{ loading ? 'جارٍ الحفظ...' : 'حفظ' }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { reactive, ref } from 'vue';
import Field from '../ui/FormField.vue';
import { techniciansApi } from '../../services/api';
import { apiFieldErrors, apiMessage } from '../../utils/format';

const props = defineProps({
    technician: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';

const loading = ref(false);
const error = ref('');
const fieldErrors = ref({});

const form = reactive({
    name: props.technician?.name ?? '',
    specialty: props.technician?.specialty ?? '',
    phone: props.technician?.phone ?? '',
    mobile: props.technician?.mobile ?? '',
    email: props.technician?.email ?? '',
    address: props.technician?.address ?? '',
    city: props.technician?.city ?? '',
    commission_rate: props.technician?.commission_rate ?? '0.00',
    opening_balance: props.technician?.opening_balance ?? '0.00',
    is_active: props.technician ? Boolean(props.technician.is_active) : true,
    notes: props.technician?.notes ?? '',
});

const isEdit = Boolean(props.technician);

async function submit() {
    loading.value = true;
    error.value = '';
    fieldErrors.value = {};

    try {
        const { data } = isEdit
            ? await techniciansApi.update(props.technician.id, form)
            : await techniciansApi.store(form);

        emit('saved', data);
        emit('close');
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر حفظ بيانات الخبير');
        fieldErrors.value = apiFieldErrors(e);
    } finally {
        loading.value = false;
    }
}
</script>
