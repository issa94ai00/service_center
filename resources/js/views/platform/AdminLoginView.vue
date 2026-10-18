<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-10">
        <div class="pointer-events-none absolute inset-x-0 -top-40 mx-auto h-[32rem] w-[32rem] rounded-full bg-blue-600/25 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-40 -end-20 h-96 w-96 rounded-full bg-indigo-600/20 blur-3xl" aria-hidden="true"></div>
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.07] [background-image:linear-gradient(to_right,#fff_1px,transparent_1px),linear-gradient(to_bottom,#fff_1px,transparent_1px)] [background-size:44px_44px] [mask-image:radial-gradient(ellipse_at_center,black,transparent_70%)]"
            aria-hidden="true"
        ></div>

        <div class="relative w-full max-w-sm">
            <div class="mb-8 text-center">
                <img :src="'/icons/icon-192.png'" alt="" class="mx-auto h-14 w-14 rounded-2xl shadow-lg shadow-blue-900/50 ring-1 ring-white/10" />
                <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-white">إدارة المنصة</h1>
                <p class="mt-1 text-sm text-slate-400">إدارة المتاجر والاشتراكات</p>
            </div>

            <form class="space-y-4 rounded-2xl border border-white/10 bg-white/[0.04] p-6 shadow-2xl backdrop-blur-xl sm:p-7" novalidate @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">البريد الإلكتروني</label>
                    <input
                        id="email"
                        v-model.trim="form.email"
                        type="email"
                        autocomplete="username"
                        dir="ltr"
                        required
                        autofocus
                        :class="FIELD"
                    />
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">كلمة المرور</label>
                    <div class="relative">
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            dir="ltr"
                            required
                            :class="[FIELD, 'pe-11']"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 end-0 grid w-11 place-items-center text-xs font-semibold text-slate-400 hover:text-white"
                            :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                            :aria-pressed="showPassword"
                            @click="showPassword = !showPassword"
                        >
                            {{ showPassword ? 'إخفاء' : 'إظهار' }}
                        </button>
                    </div>
                </div>

                <p v-if="error" class="flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300" role="alert">
                    <AppIcon name="alert" class="shrink-0" /> {{ error }}
                </p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/30 transition hover:bg-blue-500 disabled:opacity-60"
                >
                    <AppIcon v-if="loading" name="refresh" class="animate-spin" />
                    {{ loading ? 'جارٍ الدخول...' : 'تسجيل الدخول' }}
                </button>
            </form>

            <p class="mt-6 text-center font-mono text-xs text-slate-500" dir="ltr">{{ host }}</p>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppIcon from '../../components/ui/AppIcon.vue';
import { usePlatformAdminStore } from '../../stores/platformAdmin';
import { apiMessage } from '../../utils/format';

const store = usePlatformAdminStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '' });
const loading = ref(false);
const error = ref('');
const showPassword = ref(false);
const host = window.location.host;

const FIELD = 'w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-sm text-white placeholder:text-slate-500 transition focus:border-blue-400 focus:bg-white/10 focus:outline-none focus:ring-2 focus:ring-blue-500/30';

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
