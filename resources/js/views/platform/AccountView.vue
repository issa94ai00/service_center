<template>
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">حسابي</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">بيانات الدخول إلى لوحة إدارة المنصة</p>
        </div>

        <section class="flex items-center gap-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 text-xl font-bold text-white">
                {{ (store.admin?.name || '؟').charAt(0) }}
            </span>
            <div class="min-w-0">
                <p class="truncate text-base font-bold text-slate-900 dark:text-white">{{ store.admin?.name }}</p>
                <p class="truncate text-sm text-slate-500 dark:text-slate-400" dir="ltr">{{ store.admin?.email }}</p>
                <p v-if="store.admin?.last_login_at" class="mt-0.5 text-xs text-slate-400">آخر دخول {{ formatDateTime(store.admin.last_login_at) }}</p>
            </div>
        </section>

        <form class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)]" novalidate @submit.prevent="save">
            <div class="space-y-4 p-5 sm:p-6">
                <div>
                    <h2 class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                        <AppIcon name="lock" class="text-slate-400" /> تغيير كلمة المرور
                    </h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">استخدم 8 أحرف على الأقل، ويفضّل مزيج من الحروف والأرقام.</p>
                </div>
                <FormField label="كلمة المرور الحالية" required>
                    <input v-model="form.current_password" type="password" autocomplete="current-password" dir="ltr" :class="INPUT" />
                    <p v-if="errors.current_password" class="mt-1 text-xs text-red-600">{{ errors.current_password }}</p>
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="كلمة المرور الجديدة" required>
                        <input v-model="form.password" type="password" autocomplete="new-password" dir="ltr" :class="INPUT" />
                        <p v-if="errors.password" class="mt-1 text-xs text-red-600">{{ errors.password }}</p>
                    </FormField>
                    <FormField label="تأكيد كلمة المرور" required>
                        <input v-model="form.password_confirmation" type="password" autocomplete="new-password" dir="ltr" :class="INPUT" />
                        <p v-if="mismatch" class="mt-1 text-xs text-amber-600">كلمتا المرور غير متطابقتين.</p>
                    </FormField>
                </div>
            </div>
            <div class="flex justify-end rounded-b-2xl border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 px-5 py-3 sm:px-6">
                <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50" :disabled="saving || mismatch">
                    {{ saving ? 'جارٍ الحفظ...' : 'حفظ كلمة المرور' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import FormField from '../../components/ui/FormField.vue';
import { useToast } from '../../composables/useToast';
import { adminAuthApi } from '../../services/adminApi';
import { usePlatformAdminStore } from '../../stores/platformAdmin';
import { apiFieldErrors, apiMessage, formatDateTime } from '../../utils/format';

const INPUT = 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

const store = usePlatformAdminStore();
const toast = useToast();

const form = reactive({ current_password: '', password: '', password_confirmation: '' });
const errors = ref({});
const saving = ref(false);
const mismatch = computed(() => Boolean(form.password_confirmation) && form.password !== form.password_confirmation);

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
