<template>
    <div
        class="min-h-screen transition-colors duration-200 px-4 py-8 flex flex-col justify-between"
        :class="isDark ? 'bg-[#070B17] text-slate-100' : 'bg-slate-50 text-slate-800'"
    >
        <!-- Ambient background glow -->
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div
                class="absolute -top-48 inset-x-0 mx-auto h-[480px] w-[min(820px,100%)] rounded-full blur-[130px]"
                :class="isDark ? 'bg-gradient-to-tr from-blue-700/25 via-cyan-500/10 to-transparent' : 'bg-gradient-to-tr from-blue-400/20 via-cyan-300/15 to-transparent'"
            />
        </div>

        <!-- Top navigation bar -->
        <header class="relative z-10 mx-auto flex w-full max-w-xl items-center justify-between pb-6">
            <router-link
                :to="{ name: 'landing' }"
                class="inline-flex items-center gap-2 rounded-xl text-xs font-bold transition focus-ring sm:text-sm"
                :class="isDark ? 'text-slate-300 hover:text-white' : 'text-slate-600 hover:text-slate-900'"
            >
                <span class="rtl:rotate-180" aria-hidden="true">←</span>
                <span>{{ t.shopFinder.backHome }}</span>
            </router-link>

            <div class="flex items-center gap-2">
                <!-- Language Toggle -->
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border px-3 text-xs font-bold transition focus-ring"
                    :class="[
                        isDark
                            ? 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
                            : 'border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50'
                    ]"
                    :title="t.switchLangLabel"
                    :aria-label="t.switchLangLabel"
                    @click="toggleLocale"
                >
                    <span aria-hidden="true">🌐</span>
                    <span>{{ t.otherLangName }}</span>
                </button>

                <!-- Theme Toggle -->
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border text-sm transition focus-ring"
                    :class="[
                        isDark
                            ? 'border-white/10 bg-white/5 text-amber-300 hover:bg-white/10'
                            : 'border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50'
                    ]"
                    :title="isDark ? t.theme.toLight : t.theme.toDark"
                    :aria-label="isDark ? t.theme.toLight : t.theme.toDark"
                    :aria-pressed="isDark"
                    @click="toggleTheme"
                >
                    <span aria-hidden="true">{{ isDark ? '☀️' : '🌙' }}</span>
                </button>
            </div>
        </header>

        <!-- Main Container -->
        <main class="relative z-10 mx-auto my-auto w-full max-w-xl">
            <!-- Brand Link -->
            <router-link :to="{ name: 'landing' }" class="mx-auto mb-6 flex w-fit items-center gap-3 focus-ring">
                <img
                    :src="LOGO"
                    alt=""
                    class="h-11 w-11 rounded-xl ring-1 shadow-md shadow-blue-500/20"
                    :class="isDark ? 'ring-white/15' : 'ring-slate-200'"
                />
                <span class="text-base font-extrabold" :class="isDark ? 'text-white' : 'text-slate-900'">
                    {{ t.brand.name }}
                </span>
            </router-link>

            <!-- Success Screen -->
            <div
                v-if="created"
                class="rounded-3xl border p-6 text-center backdrop-blur-xl sm:p-10 transition-colors"
                :class="[
                    isDark
                        ? 'border-emerald-400/30 bg-slate-900/80 shadow-2xl'
                        : 'border-emerald-300 bg-white shadow-xl shadow-emerald-500/10'
                ]"
            >
                <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-500/15 text-3xl font-black text-emerald-500" aria-hidden="true">
                    ✓
                </div>
                <h1 class="text-2xl font-black" :class="isDark ? 'text-white' : 'text-slate-950'">
                    {{ t.register.successTitle }}
                </h1>
                <p class="mt-3 text-sm leading-relaxed" :class="isDark ? 'text-slate-300' : 'text-slate-600'">
                    {{ t.register.successSubtitle.replace('{date}', formatDate(created.expires_at)) }}
                </p>

                <div
                    class="mt-6 rounded-2xl border p-4 text-start text-sm transition-colors"
                    :class="isDark ? 'border-white/10 bg-slate-950/70' : 'border-slate-200 bg-slate-50'"
                >
                    <p class="font-semibold" :class="isDark ? 'text-slate-400' : 'text-slate-500'">
                        {{ t.register.savedAddressNotice }}
                    </p>
                    <p class="mt-1 select-all font-mono text-base font-black text-cyan-600 dark:text-cyan-300" dir="ltr">
                        {{ created.host }}
                    </p>
                    <p class="mt-3 text-xs" :class="isDark ? 'text-slate-400' : 'text-slate-500'">
                        {{ t.register.loginCredentialsNotice }} (<span class="font-mono font-semibold" dir="ltr">{{ form.email }}</span>)
                    </p>
                </div>

                <p v-if="!created.secure" class="mt-4 text-xs leading-relaxed text-amber-600 dark:text-amber-300">
                    {{ t.register.sslWaitNotice }}
                </p>

                <a
                    :href="created.login_url"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-gradient-to-l from-cyan-500 to-blue-600 py-4 text-base font-extrabold text-white shadow-md shadow-cyan-500/25 transition hover:brightness-110 focus-ring"
                >
                    {{ t.register.enterShopButton }}
                </a>
            </div>

            <!-- Registration Form -->
            <div
                v-else
                class="rounded-3xl border p-6 backdrop-blur-xl sm:p-8 transition-colors"
                :class="[
                    isDark
                        ? 'border-white/10 bg-slate-900/80 shadow-2xl'
                        : 'border-slate-200/90 bg-white/95 shadow-xl shadow-slate-200/60'
                ]"
            >
                <h1 class="text-xl font-black sm:text-2xl" :class="isDark ? 'text-white' : 'text-slate-950'">
                    {{ t.register.title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                    {{ t.register.subtitle.replace('{days}', trialDays) }}
                </p>

                <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
                    <!-- Honeypot -->
                    <!-- Clipped in place rather than pushed off-screen: a -9999px offset widens the page in RTL. -->
                    <div class="pointer-events-none absolute h-px w-px overflow-hidden opacity-0 [clip-path:inset(50%)]" aria-hidden="true">
                        <label>Website <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" /></label>
                    </div>

                    <!-- Shop Details Fieldset -->
                    <fieldset class="space-y-4">
                        <legend class="mb-1 text-xs font-black uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                            {{ t.register.shopSection }}
                        </legend>

                        <Field id="shop_name" :label="t.register.shopNameLabel" :error="errors.shop_name">
                            <input
                                id="shop_name"
                                v-model.trim="form.shop_name"
                                type="text"
                                autocomplete="organization"
                                :class="inputClass"
                                :placeholder="t.register.shopNamePlaceholder"
                                @input="suggestSlug"
                            />
                        </Field>

                        <Field id="slug" :label="t.register.slugLabel" :error="errors.slug || slugMessage.error">
                            <div
                                class="flex items-center rounded-2xl border px-4 transition-colors"
                                :class="[
                                    errors.slug || slugMessage.error
                                        ? 'border-rose-500 ring-1 ring-rose-500/20'
                                        : slugState === 'available'
                                          ? 'border-emerald-500 ring-1 ring-emerald-500/20'
                                          : isDark
                                            ? 'border-slate-700 bg-slate-950/80 focus-within:border-cyan-400'
                                            : 'border-slate-300 bg-slate-50 focus-within:border-cyan-500 focus-within:bg-white'
                                ]"
                                dir="ltr"
                            >
                                <input
                                    id="slug"
                                    v-model.trim="form.slug"
                                    type="text"
                                    inputmode="url"
                                    autocapitalize="off"
                                    autocomplete="off"
                                    spellcheck="false"
                                    :placeholder="t.register.slugPlaceholder"
                                    class="min-w-0 flex-1 bg-transparent py-3 font-mono text-base focus:outline-none"
                                    :class="isDark ? 'text-white placeholder:text-slate-600' : 'text-slate-900 placeholder:text-slate-400'"
                                    @input="onSlugInput"
                                />
                                <span class="shrink-0 font-mono text-xs font-semibold sm:text-sm" :class="isDark ? 'text-slate-500' : 'text-slate-400'">
                                    .{{ tenancy.centralDomain }}
                                </span>
                            </div>
                            <p v-if="slugMessage.ok" class="mt-1.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ slugMessage.ok }}
                            </p>
                            <p v-else-if="slugState === 'checking'" class="mt-1.5 text-xs text-slate-500">
                                {{ t.register.slugChecking }}
                            </p>
                            <p v-else-if="!slugMessage.error && !errors.slug" class="mt-1.5 text-xs text-slate-500">
                                {{ t.register.slugHint }}
                            </p>
                        </Field>
                    </fieldset>

                    <!-- Owner Account Fieldset -->
                    <fieldset class="space-y-4">
                        <legend class="mb-1 text-xs font-black uppercase tracking-wider text-cyan-600 dark:text-cyan-400">
                            {{ t.register.ownerSection }}
                        </legend>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field id="owner_name" :label="t.register.ownerNameLabel" :error="errors.owner_name">
                                <input id="owner_name" v-model.trim="form.owner_name" type="text" autocomplete="name" :class="inputClass" />
                            </Field>
                            <Field id="phone" :label="t.register.ownerPhoneLabel" :error="errors.phone">
                                <input id="phone" v-model.trim="form.phone" type="tel" autocomplete="tel" dir="ltr" placeholder="09xx xxx xxx" :class="inputClass" />
                            </Field>
                        </div>

                        <Field id="email" :label="t.register.ownerEmailLabel" :error="errors.email">
                            <input id="email" v-model.trim="form.email" type="email" autocomplete="email" dir="ltr" :class="inputClass" />
                        </Field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field id="password" :label="t.register.passwordLabel" :error="errors.password">
                                <div class="relative">
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        autocomplete="new-password"
                                        dir="ltr"
                                        :class="[inputClass, 'pe-14']"
                                    />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 end-0 px-3 text-xs font-bold transition focus-ring rounded-r-2xl"
                                        :class="isDark ? 'text-slate-400 hover:text-white' : 'text-slate-500 hover:text-slate-900'"
                                        :aria-label="showPassword ? t.register.hidePassword : t.register.showPassword"
                                        @click="showPassword = !showPassword"
                                    >
                                        {{ showPassword ? t.register.hidePassword : t.register.showPassword }}
                                    </button>
                                </div>
                            </Field>
                            <Field id="password_confirmation" :label="t.register.passwordConfirmLabel" :error="errors.password_confirmation">
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    dir="ltr"
                                    :class="inputClass"
                                />
                            </Field>
                        </div>
                        <p class="-mt-2 text-xs text-slate-500">{{ t.register.passwordHint }}</p>
                    </fieldset>

                    <!-- Terms & Agreement -->
                    <div>
                        <label class="flex items-start gap-3 text-sm font-medium" :class="isDark ? 'text-slate-300' : 'text-slate-700'">
                            <input
                                v-model="form.terms"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-400 text-cyan-600 focus:ring-cyan-500"
                            />
                            <span>{{ t.register.termsAgreement }}</span>
                        </label>
                        <p v-if="errors.terms" class="mt-1.5 text-xs font-medium text-rose-500 dark:text-rose-400">
                            {{ errors.terms }}
                        </p>
                    </div>

                    <!-- General Error Alert -->
                    <p
                        v-if="formError"
                        class="rounded-2xl border px-4 py-3 text-sm font-medium"
                        :class="isDark ? 'border-rose-500/30 bg-rose-500/10 text-rose-200' : 'border-rose-200 bg-rose-50 text-rose-700'"
                        role="alert"
                    >
                        {{ formError }}
                    </p>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-l from-cyan-500 to-blue-600 py-4 text-base font-extrabold text-white shadow-md shadow-cyan-500/25 transition hover:brightness-110 disabled:cursor-wait disabled:opacity-70 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400"
                    >
                        <svg v-if="saving" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                        </svg>
                        <span>{{ saving ? t.register.submittingButton : t.register.submitButton }}</span>
                    </button>
                </form>
            </div>

            <!-- Login footer link -->
            <p v-if="!created" class="mt-6 text-center text-sm" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                {{ t.register.alreadyHaveShop }}
                <router-link :to="{ name: 'login' }" class="font-bold text-cyan-600 hover:underline dark:text-cyan-400">
                    {{ t.register.loginLink }}
                </router-link>
            </p>
        </main>

        <!-- Footer -->
        <footer class="relative z-10 py-4 text-center text-xs" :class="isDark ? 'text-slate-500' : 'text-slate-400'">
            © {{ currentYear }} {{ t.brand.company }}
        </footer>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import { signupApi } from '../../services/publicApi';
import { tenancy } from '../../tenancy';
import { apiFieldErrors, apiMessage, formatDate } from '../../utils/format';
import { useTheme } from '../../composables/useTheme';
import { useI18n } from '../../composables/useI18n';

const { isDark, toggleTheme } = useTheme();
const { t, isAr, toggleLocale } = useI18n();

const LOGO = '/icons/icon-192.png';
const SLUG_RE = /^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/;
const currentYear = new Date().getFullYear();

const inputClass = computed(() => [
    'w-full rounded-2xl border px-4 py-3 text-base transition-colors focus:outline-none',
    isDark.value
        ? 'border-slate-700 bg-slate-950/80 text-white placeholder:text-slate-600 focus:border-cyan-400'
        : 'border-slate-300 bg-slate-50 text-slate-900 placeholder:text-slate-400 focus:border-cyan-500 focus:bg-white',
]);

const Field = defineComponent({
    props: { id: String, label: String, error: String },
    setup: (props, { slots }) => () =>
        h('div', [
            h('label', { for: props.id, class: ['mb-1.5 block text-sm font-bold', isDark.value ? 'text-slate-300' : 'text-slate-700'] }, props.label),
            slots.default?.(),
            props.error ? h('p', { class: 'mt-1.5 text-xs font-semibold text-rose-500 dark:text-rose-400' }, props.error) : null,
        ]),
});

const trialDays = tenancy.signup?.trialDays ?? 14;

const form = reactive({
    shop_name: '',
    slug: '',
    owner_name: '',
    phone: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
    website: '',
});
const errors = ref({});
const formError = ref('');
const saving = ref(false);
const showPassword = ref(false);
const created = ref(null);

// Subdomain suggestion and live check
const slugTouched = ref(false);
const slugState = ref('idle');
let slugTimer = null;
let slugSeq = 0;

const slugMessage = computed(() => {
    switch (slugState.value) {
        case 'available':
            return { ok: `${t.value.register.slugAvailable} (${form.slug}.${tenancy.centralDomain})` };
        case 'taken':
            return { error: t.value.register.slugTaken };
        case 'reserved':
            return { error: t.value.register.slugTaken };
        case 'invalid':
            return { error: t.value.shopFinder.errorFormat };
        default:
            return {};
    }
});

function suggestSlug() {
    if (slugTouched.value) return;
    form.slug = form.shop_name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
    scheduleSlugCheck();
}

function onSlugInput() {
    slugTouched.value = true;
    form.slug = form.slug.toLowerCase();
    delete errors.value.slug;
    scheduleSlugCheck();
}

function scheduleSlugCheck() {
    clearTimeout(slugTimer);
    const slug = form.slug;
    if (!slug) {
        slugState.value = 'idle';
        return;
    }
    if (!SLUG_RE.test(slug)) {
        slugState.value = 'invalid';
        return;
    }
    slugState.value = 'checking';
    slugTimer = setTimeout(async () => {
        const seq = ++slugSeq;
        try {
            const result = await signupApi.checkSlug(slug);
            if (seq === slugSeq && slug === form.slug) {
                slugState.value = result.available ? 'available' : result.reason;
            }
        } catch {
            if (seq === slugSeq) slugState.value = 'idle';
        }
    }, 350);
}

function validate() {
    const e = {};
    const isArabic = isAr.value;
    if (form.shop_name.length < 2) {
        e.shop_name = isArabic ? 'اكتب اسم المتجر.' : 'Enter shop name.';
    }
    if (!SLUG_RE.test(form.slug)) {
        e.slug = isArabic ? 'اختر عنواناً صالحاً للمتجر.' : 'Choose a valid shop subdomain.';
    }
    if (form.owner_name.length < 2) {
        e.owner_name = isArabic ? 'اكتب اسمك الكامل.' : 'Enter your full name.';
    }
    if (form.phone.replace(/\D/g, '').length < 6) {
        e.phone = isArabic ? 'اكتب رقم جوال صحيح.' : 'Enter a valid phone number.';
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        e.email = isArabic ? 'اكتب بريداً إلكترونياً صحيحاً.' : 'Enter a valid email address.';
    }
    if (form.password.length < 8) {
        e.password = isArabic ? 'كلمة المرور 8 أحرف على الأقل.' : 'Password must be at least 8 characters.';
    } else if (form.password !== form.password_confirmation) {
        e.password_confirmation = isArabic ? 'كلمتا المرور غير متطابقتين.' : 'Passwords do not match.';
    }
    if (!form.terms) {
        e.terms = isArabic ? 'يجب الموافقة على شروط الاستخدام.' : 'You must accept the terms of service.';
    }
    return e;
}

async function submit() {
    formError.value = '';
    errors.value = validate();
    if (['taken', 'reserved', 'invalid'].includes(slugState.value)) {
        errors.value.slug = slugMessage.value.error;
    }
    if (Object.keys(errors.value).length) {
        document.getElementById(Object.keys(errors.value)[0])?.focus();
        return;
    }

    saving.value = true;
    try {
        created.value = await signupApi.register({ ...form, slug: form.slug.toLowerCase() });
        try {
            localStorage.setItem('last_shop_slug', form.slug.toLowerCase());
        } catch {
            // Private mode
        }
        window.scrollTo({ top: 0 });
    } catch (e) {
        errors.value = apiFieldErrors(e);
        if (Object.keys(errors.value).length) {
            document.getElementById(Object.keys(errors.value)[0])?.focus();
        } else {
            const fallback = isAr.value ? 'تعذّر إنشاء المتجر. حاول مرة أخرى.' : 'Unable to create shop. Please try again.';
            const message = apiMessage(e, fallback);
            formError.value = e?.response?.status === 429
                ? (isAr.value ? 'محاولات كثيرة من هذا الجهاز. حاول بعد ساعة.' : 'Too many attempts. Please try again in an hour.')
                : message;
        }
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    document.title = `${t.value.register.title} | ${t.value.brand.name}`;
});
</script>

<style scoped>
.focus-ring:focus-visible {
    outline: 2px solid rgb(6 182 212);
    outline-offset: 2px;
}
</style>
