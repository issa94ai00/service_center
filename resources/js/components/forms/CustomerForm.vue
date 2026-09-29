<template>
    <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <Field label="الاسم *" required>
                <input v-model.trim="form.name" type="text" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="الشركة">
                <input v-model.trim="form.company" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="الهاتف">
                <input v-model.trim="form.phone" type="text" dir="ltr" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="الجوال">
                <input v-model.trim="form.mobile" type="text" dir="ltr" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="البريد الإلكتروني">
                <input v-model.trim="form.email" type="email" dir="ltr" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="الرصيد الافتتاحي">
                <input v-model="form.opening_balance" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="المدينة">
                <input v-model.trim="form.city" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="الرقم الضريبي">
                <input v-model.trim="form.tax_number" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="العنوان" class="sm:col-span-2">
                <input v-model.trim="form.address" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
            </Field>
            <Field label="ملاحظات" class="sm:col-span-2">
                <textarea v-model.trim="form.notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
            </Field>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
            <button
                type="button"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                @click="$emit('close')"
            >
                إلغاء
            </button>
            <button
                type="submit"
                :disabled="loading"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
            >
                {{ loading ? 'جارٍ الحفظ...' : 'حفظ' }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { reactive, ref } from 'vue';
import Field from '../ui/FormField.vue';
import { customersApi } from '../../services/api';

const props = defineProps({
    customer: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);
const loading = ref(false);
const error = ref('');

const form = reactive({
    name: props.customer?.name ?? '',
    company: props.customer?.company ?? '',
    phone: props.customer?.phone ?? '',
    mobile: props.customer?.mobile ?? '',
    email: props.customer?.email ?? '',
    address: props.customer?.address ?? '',
    city: props.customer?.city ?? '',
    tax_number: props.customer?.tax_number ?? '',
    opening_balance: props.customer?.opening_balance ?? '0.00',
    notes: props.customer?.notes ?? '',
});

const isEdit = Boolean(props.customer);

async function submit() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = isEdit
            ? await customersApi.update(props.customer.id, form)
            : await customersApi.store(form);
        emit('saved', data);
        emit('close');
    } catch (e) {
        const msg = e.response?.data?.message;
        error.value = msg ? (typeof msg === 'string' ? msg : Object.values(msg).flat().join('، ')) : 'تعذّر الحفظ';
    } finally {
        loading.value = false;
    }
}
</script>
