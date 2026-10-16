<template>
    <div class="mx-auto max-w-md space-y-6">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900">حسابي</h1>
            <p class="mt-0.5 text-sm text-slate-500" dir="ltr">{{ store.admin?.email }}</p>
        </div>

        <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" novalidate @submit.prevent="save">
            <h2 class="text-base font-bold text-slate-900">تغيير كلمة المرور</h2>
            <FormField label="كلمة المرور الحالية" required>
                <input v-model="form.current_password" type="password" autocomplete="current-password" dir="ltr" :class="INPUT" />
                <p v-if="errors.current_password" class="mt-1 text-xs text-red-600">{{ errors.current_password }}</p>
            </FormField>
            <FormField label="كلمة المرور الجديدة" required>
                <input v-model="form.password" type="password" autocomplete="new-password" dir="ltr" :class="INPUT" />
                <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password }}</p>
            </FormField>
            <FormField label="تأكيد كلمة المرور" required>
                <input v-model="form.password_confirmation" type="password" autocomplete="new-password" dir="ltr" :class="INPUT" />
            </FormField>
            <button type="submit" class="w-full rounded-xl bg-blue-600 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="saving">
                حفظ
            </button>
        </form>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import FormField from '../../components/ui/FormField.vue';
import { useToast } from '../../composables/useToast';
import { adminAuthApi } from '../../services/adminApi';
import { usePlatformAdminStore } from '../../stores/platformAdmin';
import { apiFieldErrors, apiMessage } from '../../utils/format';

const INPUT = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

const store = usePlatformAdminStore();
const toast = useToast();

const form = reactive({ current_password: '', password: '', password_confirmation: '' });
const errors = ref({});
const saving = ref(false);

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        const { message } = await adminAuthApi.updatePassword(form);
        toast.success(message);
        Object.assign(form, { current_password: '', password: '', password_confirmation: '' });
    } catch (e) {
        errors.value = apiFieldErrors(e);
        if (!Object.keys(errors.value).length) toast.error(apiMessage(e));
    } finally {
        saving.value = false;
    }
}
</script>
