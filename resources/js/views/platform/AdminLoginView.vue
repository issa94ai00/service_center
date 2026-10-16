<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 px-4">
        <div class="w-full max-w-sm rounded-3xl bg-white p-8 shadow-2xl shadow-blue-950/40">
            <div class="mb-6 flex items-center gap-3">
                <img :src="'/icons/icon-192.png'" alt="" class="h-11 w-11 rounded-xl" />
                <div>
                    <h1 class="text-lg font-bold text-slate-900">إدارة المنصة</h1>
                    <p class="text-xs text-slate-500">إدارة المتاجر والاشتراكات</p>
                </div>
            </div>

            <form class="space-y-4" novalidate @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">البريد الإلكتروني</label>
                    <input
                        id="email"
                        v-model.trim="form.email"
                        type="email"
                        autocomplete="username"
                        dir="ltr"
                        required
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    />
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">كلمة المرور</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        dir="ltr"
                        required
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    />
                </div>

                <p v-if="error" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ error }}</p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full rounded-xl bg-blue-600 py-3 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-60"
                >
                    {{ loading ? 'جارٍ الدخول...' : 'تسجيل الدخول' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePlatformAdminStore } from '../../stores/platformAdmin';
import { apiMessage } from '../../utils/format';

const store = usePlatformAdminStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '' });
const loading = ref(false);
const error = ref('');

async function submit() {
    if (!form.email || !form.password) {
        error.value = 'أدخل البريد الإلكتروني وكلمة المرور.';
        return;
    }

    loading.value = true;
    error.value = '';
    try {
        await store.login({ ...form, remember: true });
        router.replace(route.query.redirect || { name: 'tenants' });
    } catch (e) {
        error.value = e?.response?.status === 429
            ? 'محاولات كثيرة. أعد المحاولة بعد لحظات.'
            : apiMessage(e, 'بيانات الدخول غير صحيحة.');
    } finally {
        loading.value = false;
    }
}
</script>
