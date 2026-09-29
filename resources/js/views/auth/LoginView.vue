<template>
    <div class="overflow-hidden rounded-3xl bg-white shadow-2xl shadow-blue-950/40 lg:grid lg:grid-cols-2">
        <!-- Branding panel (desktop only, stacked above the form on mobile) -->
        <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-blue-700 via-blue-800 to-cyan-900 p-10 lg:flex">
            <div class="pointer-events-none absolute -start-24 -bottom-24 h-72 w-72 rounded-full bg-cyan-400/20 blur-3xl" aria-hidden="true" />
            <div class="pointer-events-none absolute -end-24 -top-24 h-72 w-72 rounded-full bg-blue-400/20 blur-3xl" aria-hidden="true" />

            <div class="relative flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-2xl text-white ring-1 ring-white/20">📱</div>
                <div>
                    <p class="text-base font-bold text-white">نظام الهواتف والإكسسوارات</p>
                    <p class="text-xs text-cyan-200/80">داماتك لتقنية المعلومات</p>
                </div>
            </div>

            <div class="relative space-y-8">
                <div class="space-y-2">
                    <h1 class="text-3xl font-bold leading-tight text-white">مرحباً بك في نظام الإدارة المتكامل</h1>
                    <p class="text-sm leading-relaxed text-blue-100/90">
                        المبيعات، المشتريات، الصندوق، الصيانة، والمخزون — كل عمليات المحل في واجهةٍ واحدة سلسة.
                    </p>
                </div>

                <ul class="space-y-4">
                    <li v-for="item in features" :key="item.title" class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm ring-1 ring-white/15">
                            {{ item.icon }}
                        </span>
                        <span>
                            <span class="block text-sm font-medium text-white">{{ item.title }}</span>
                            <span class="block text-xs text-blue-100/70">{{ item.desc }}</span>
                        </span>
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-blue-200/70">© {{ year }} داماتك لتقنية المعلومات</p>
        </div>

        <!-- Form panel -->
        <div class="flex flex-col justify-center p-8 sm:p-12">
            <div class="mx-auto mb-8 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-cyan-500 text-3xl shadow-lg shadow-blue-500/30 lg:hidden">
                📱
            </div>

            <div>
                <h2 class="text-2xl font-bold text-slate-900">تسجيل الدخول</h2>
                <p class="mt-1 text-sm text-slate-500">أدخل بيانات حسابك للوصول إلى لوحة التحكم</p>
            </div>

            <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">البريد الإلكتروني</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                        </span>
                        <input
                            id="email"
                            v-model.trim="form.email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            required
                            autofocus
                            dir="ltr"
                            :aria-invalid="Boolean(fieldErrors.email)"
                            :aria-describedby="fieldErrors.email ? 'email-error' : undefined"
                            class="w-full rounded-xl border py-2.5 pe-3.5 ps-11 text-left text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
                            :class="fieldErrors.email
                                ? 'border-red-300 bg-red-50/50 focus:border-red-500 focus:ring-red-100'
                                : 'border-slate-300 bg-white focus:border-blue-500 focus:ring-blue-100'"
                            placeholder="admin@damatech.sy"
                            @input="fieldErrors.email = ''"
                        />
                    </div>
                    <p v-if="fieldErrors.email" id="email-error" class="mt-1.5 text-xs font-medium text-red-600">
                        {{ fieldErrors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">كلمة المرور</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </span>
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            required
                            dir="ltr"
                            :aria-invalid="Boolean(fieldErrors.password)"
                            :aria-describedby="fieldErrors.password ? 'password-error' : undefined"
                            class="w-full rounded-xl border py-2.5 pe-11 ps-11 text-left text-sm text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-2"
                            :class="fieldErrors.password
                                ? 'border-red-300 bg-red-50/50 focus:border-red-500 focus:ring-red-100'
                                : 'border-slate-300 bg-white focus:border-blue-500 focus:ring-blue-100'"
                            placeholder="••••••••"
                            @input="fieldErrors.password = ''"
                        />
                        <button
                            type="button"
                            class="absolute end-2 top-1/2 -translate-y-1/2 rounded-lg p-2 text-slate-400 transition hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                            :aria-pressed="showPassword"
                            @click="showPassword = !showPassword"
                        >
                            <svg v-if="showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                            <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </button>
                    </div>
                    <p v-if="fieldErrors.password" id="password-error" class="mt-1.5 text-xs font-medium text-red-600">
                        {{ fieldErrors.password }}
                    </p>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-slate-600">
                        <input
                            v-model="remember"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 accent-blue-600 focus:ring-blue-100"
                        />
                        تذكّرني
                    </label>
                </div>

                <div
                    v-if="error"
                    role="alert"
                    class="flex items-center gap-2.5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.125 4.5a.75.75 0 0 0-1.5 0v5.25a.75.75 0 0 0 1.5 0V6.75Zm.375 9a1.125 1.125 0 1 0 0-2.25 1.125 1.125 0 0 0 0 2.25Z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ error }}</span>
                </div>

                <button
                    type="submit"
                    :disabled="auth.loading"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <svg v-if="auth.loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                    </svg>
                    {{ auth.loading ? 'جارٍ تسجيل الدخول...' : 'تسجيل الدخول' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const REMEMBER_KEY = 'login.email';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({ email: '', password: '' });
const fieldErrors = reactive({ email: '', password: '' });
const error = ref('');
const showPassword = ref(false);
const remember = ref(false);
const year = new Date().getFullYear();

/**
 * The shop keeps the remembered address in the browser rather than on the
 * server, so "remember me" only eases typing — it never grants access. The
 * server-side remember (cookie persistence) is decided by the checkbox.
 */
const storedEmail = readStoredEmail();
if (storedEmail) {
    form.email = storedEmail;
    remember.value = true;
}

const features = [
    { icon: '🧾', title: 'فواتير المبيعات والمشتريات', desc: 'إصدار وطباعة ومتابعة الحسابات من مكان واحد' },
    { icon: '🔧', title: 'أجهزة الصيانة', desc: 'استلام، تتبّع حالة الخبراء، وتسليم بفاتورة' },
    { icon: '📊', title: 'تقارير الصندوق والمخزون', desc: 'صورة يومية فورية عن حركة حسابك' },
];

function readStoredEmail() {
    try {
        return localStorage.getItem(REMEMBER_KEY) || '';
    } catch {
        return '';
    }
}

function persistEmail() {
    try {
        if (remember.value && form.email) {
            localStorage.setItem(REMEMBER_KEY, form.email);
        } else {
            localStorage.removeItem(REMEMBER_KEY);
        }
    } catch {
        /* private windows and blocked site data: the box still works */
    }
}

function validate() {
    fieldErrors.email = '';
    fieldErrors.password = '';

    if (!form.email) {
        fieldErrors.email = 'أدخل البريد الإلكتروني';
    } else if (!EMAIL_RE.test(form.email)) {
        fieldErrors.email = 'صيغة البريد الإلكتروني غير صحيحة';
    }

    if (!form.password) {
        fieldErrors.password = 'أدخل كلمة المرور';
    }

    return !fieldErrors.email && !fieldErrors.password;
}

async function submit() {
    error.value = '';

    if (!validate()) return;

    try {
        await auth.login({
            email: form.email,
            password: form.password,
            remember: remember.value,
        });
        persistEmail();
        router.replace(route.query.redirect || '/');
    } catch (e) {
        if (e?.response?.status === 429) {
            error.value = 'محاولات كثيرة. أعد المحاولة بعد لحظات.';
            return;
        }

        const data = e?.response?.data;
        if (data?.errors) {
            for (const [field, messages] of Object.entries(data.errors)) {
                fieldErrors[field] = messages[0];
            }
        }
        error.value = data?.message ?? 'تعذّر تسجيل الدخول. تحقّق من البيانات.';
    }
}
</script>