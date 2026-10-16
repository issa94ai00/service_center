<template>
    <div class="min-h-screen bg-[#070B17] px-4 py-10 text-slate-100">
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-48 inset-x-0 mx-auto h-[480px] w-[min(820px,100%)] rounded-full bg-gradient-to-tr from-blue-700/25 via-cyan-500/10 to-transparent blur-[130px]" />
        </div>

        <div class="relative mx-auto w-full max-w-xl">
            <router-link :to="{ name: 'landing' }" class="mx-auto mb-8 flex w-fit items-center gap-3">
                <img :src="LOGO" alt="" class="h-11 w-11 rounded-xl ring-1 ring-white/15" />
                <span class="text-base font-extrabold text-white">نظام الهواتف والإكسسوارات</span>
            </router-link>

            <!-- Done -->
            <div v-if="created" class="rounded-3xl border border-emerald-400/30 bg-slate-900/80 p-6 text-center shadow-2xl backdrop-blur-xl sm:p-10">
                <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-400/15 text-3xl text-emerald-300" aria-hidden="true">✓</div>
                <h1 class="text-2xl font-black text-white">متجرك جاهز!</h1>
                <p class="mt-3 text-sm leading-relaxed text-slate-300">
                    تم إنشاء <strong class="text-white">{{ created.name }}</strong>. فترتك التجريبية مجانية حتى
                    <strong class="text-white">{{ formatDate(created.expires_at) }}</strong>.
                </p>
                <div class="mt-6 rounded-2xl border border-white/10 bg-slate-950/70 p-4 text-start text-sm">
                    <p class="text-slate-400">عنوان متجرك — احفظه، ستدخل منه دائماً:</p>
                    <p class="mt-1 select-all font-mono text-base font-bold text-cyan-300" dir="ltr">{{ created.host }}</p>
                    <p class="mt-3 text-slate-400">ادخل ببريدك <span class="font-mono text-slate-200" dir="ltr">{{ form.email }}</span> وكلمة المرور التي اخترتها.</p>
                </div>
                <p v-if="!created.secure" class="mt-4 text-xs leading-relaxed text-amber-300">
                    نجهّز شهادة الأمان لعنوانك، وقد يستغرق ذلك بضع دقائق. إن لم تفتح الصفحة فوراً أعد المحاولة بعد قليل.
                </p>
                <a
                    :href="created.login_url"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-gradient-to-l from-cyan-400 to-blue-500 py-4 text-base font-bold text-slate-950 shadow-[0_0_24px_rgba(34,211,238,0.3)] transition hover:brightness-110"
                >
                    الدخول إلى متجري
                </a>
            </div>

            <!-- Form -->
            <div v-else class="rounded-3xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
                <h1 class="text-xl font-black text-white sm:text-2xl">أنشئ متجرك مجاناً</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-400">
                    تجربة كاملة لمدة {{ trialDays }} يوماً، بدون بطاقة دفع. يحصل متجرك على عنوان خاص وقاعدة بيانات مستقلة.
                </p>

                <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
                    <!-- Honeypot: hidden from people; bots fill it and are rejected. -->
                    <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                        <label>Website <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" /></label>
                    </div>

                    <fieldset class="space-y-4">
                        <legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-cyan-300/80">المتجر</legend>

                        <Field id="shop_name" label="اسم المتجر" :error="errors.shop_name">
                            <input id="shop_name" v-model.trim="form.shop_name" type="text" autocomplete="organization" :class="INPUT" placeholder="مثال: مركز النور للهواتف" @input="suggestSlug" />
                        </Field>

                        <Field id="slug" label="عنوان المتجر" :error="errors.slug || slugMessage.error">
                            <div
                                class="flex items-center rounded-2xl border bg-slate-950/80 px-4 transition-colors focus-within:border-cyan-400"
                                :class="errors.slug || slugMessage.error ? 'border-rose-500/70' : slugState === 'available' ? 'border-emerald-500/60' : 'border-slate-700'"
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
                                    placeholder="alnour"
                                    class="min-w-0 flex-1 bg-transparent py-3 font-mono text-base text-white placeholder:text-slate-600 focus:outline-none"
                                    @input="onSlugInput"
                                />
                                <span class="shrink-0 font-mono text-xs text-slate-500 sm:text-sm">.{{ tenancy.centralDomain }}</span>
                            </div>
                            <p v-if="slugMessage.ok" class="mt-1.5 text-xs text-emerald-400">{{ slugMessage.ok }}</p>
                            <p v-else-if="slugState === 'checking'" class="mt-1.5 text-xs text-slate-500">جارٍ التحقق...</p>
                            <p v-else-if="!slugMessage.error && !errors.slug" class="mt-1.5 text-xs text-slate-500">أحرف إنجليزية صغيرة وأرقام وشرطة (-). هذا هو العنوان الذي تدخل منه.</p>
                        </Field>
                    </fieldset>

                    <fieldset class="space-y-4">
                        <legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-cyan-300/80">حساب المدير</legend>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field id="owner_name" label="الاسم" :error="errors.owner_name">
                                <input id="owner_name" v-model.trim="form.owner_name" type="text" autocomplete="name" :class="INPUT" />
                            </Field>
                            <Field id="phone" label="رقم الجوال" :error="errors.phone">
                                <input id="phone" v-model.trim="form.phone" type="tel" autocomplete="tel" dir="ltr" placeholder="09xx xxx xxx" :class="INPUT" />
                            </Field>
                        </div>

                        <Field id="email" label="البريد الإلكتروني (للدخول)" :error="errors.email">
                            <input id="email" v-model.trim="form.email" type="email" autocomplete="email" dir="ltr" :class="INPUT" />
                        </Field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field id="password" label="كلمة المرور" :error="errors.password">
                                <div class="relative">
                                    <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" dir="ltr" :class="[INPUT, 'pe-12']" />
                                    <button
                                        type="button"
                                        class="absolute inset-y-0 end-0 px-3 text-xs text-slate-400 hover:text-white"
                                        :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                                        @click="showPassword = !showPassword"
                                    >
                                        {{ showPassword ? 'إخفاء' : 'إظهار' }}
                                    </button>
                                </div>
                            </Field>
                            <Field id="password_confirmation" label="تأكيد كلمة المرور" :error="errors.password_confirmation">
                                <input id="password_confirmation" v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" dir="ltr" :class="INPUT" />
                            </Field>
                        </div>
                        <p class="-mt-2 text-xs text-slate-500">8 أحرف على الأقل.</p>
                    </fieldset>

                    <div>
                        <label class="flex items-start gap-3 text-sm text-slate-300">
                            <input v-model="form.terms" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-600 bg-slate-900 text-cyan-400 focus:ring-cyan-400" />
                            <span>أوافق على شروط الاستخدام، وأعلم أن المتجر يُغلق بعد انتهاء الفترة التجريبية ما لم يُجدَّد الاشتراك.</span>
                        </label>
                        <p v-if="errors.terms" class="mt-1.5 text-xs text-rose-400">{{ errors.terms }}</p>
                    </div>

                    <p v-if="formError" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ formError }}</p>

                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-l from-cyan-400 to-blue-500 py-4 text-base font-bold text-slate-950 shadow-[0_0_24px_rgba(34,211,238,0.3)] transition hover:brightness-110 disabled:cursor-wait disabled:opacity-70 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        <svg v-if="saving" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                        </svg>
                        {{ saving ? 'نجهّز متجرك... قد يستغرق ذلك دقيقة' : 'إنشاء المتجر' }}
                    </button>
                </form>
            </div>

            <p v-if="!created" class="mt-6 text-center text-sm text-slate-500">
                لديك متجر بالفعل؟
                <router-link :to="{ name: 'login' }" class="font-semibold text-cyan-300 hover:text-cyan-200">تسجيل الدخول</router-link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref } from 'vue';
import { signupApi } from '../../services/publicApi';
import { tenancy } from '../../tenancy';
import { apiFieldErrors, apiMessage, formatDate } from '../../utils/format';

const LOGO = '/icons/icon-192.png';
const INPUT = 'w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-base text-white placeholder:text-slate-600 transition-colors focus:border-cyan-400 focus:outline-none';
const SLUG_RE = /^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/;

const Field = defineComponent({
    props: { id: String, label: String, error: String },
    setup: (props, { slots }) => () =>
        h('div', [
            h('label', { for: props.id, class: 'mb-1.5 block text-sm font-medium text-slate-300' }, props.label),
            slots.default?.(),
            props.error ? h('p', { class: 'mt-1.5 text-xs text-rose-400' }, props.error) : null,
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

// ----- subdomain: suggestion + live availability
const slugTouched = ref(false);
const slugState = ref('idle'); // idle | checking | available | taken | reserved | invalid
let slugTimer = null;
let slugSeq = 0;

const slugMessage = computed(() => {
    switch (slugState.value) {
        case 'available': return { ok: `متاح: ${form.slug}.${tenancy.centralDomain}` };
        case 'taken': return { error: 'هذا العنوان مستخدم، جرّب عنواناً آخر.' };
        case 'reserved': return { error: 'هذا العنوان محجوز، جرّب عنواناً آخر.' };
        case 'invalid': return { error: 'أحرف إنجليزية صغيرة وأرقام وشرطة فقط، ولا يبدأ أو ينتهي بشرطة.' };
        default: return {};
    }
});

function suggestSlug() {
    if (slugTouched.value) return;
    // Only Latin text has an obvious subdomain form; Arabic names are left to the visitor.
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

// ----- submit
function validate() {
    const e = {};
    if (form.shop_name.length < 2) e.shop_name = 'اكتب اسم المتجر.';
    if (!SLUG_RE.test(form.slug)) e.slug = 'اختر عنواناً صالحاً للمتجر.';
    if (form.owner_name.length < 2) e.owner_name = 'اكتب اسمك.';
    if (form.phone.replace(/\D/g, '').length < 6) e.phone = 'اكتب رقم جوال صحيح.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = 'اكتب بريداً إلكترونياً صحيحاً.';
    if (form.password.length < 8) e.password = 'كلمة المرور 8 أحرف على الأقل.';
    else if (form.password !== form.password_confirmation) e.password_confirmation = 'كلمتا المرور غير متطابقتين.';
    if (!form.terms) e.terms = 'يجب الموافقة على شروط الاستخدام.';
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
            // Private mode: the finder just won't be pre-filled.
        }
        window.scrollTo({ top: 0 });
    } catch (e) {
        errors.value = apiFieldErrors(e);
        if (Object.keys(errors.value).length) {
            document.getElementById(Object.keys(errors.value)[0])?.focus();
        } else {
            const message = apiMessage(e, 'تعذّر إنشاء المتجر. حاول مرة أخرى.');
            // The rate limiter answers in English; our own limits answer in Arabic.
            formError.value = e?.response?.status === 429 && !/[\u0600-\u06FF]/.test(message)
                ? 'محاولات كثيرة من هذا الجهاز. حاول بعد ساعة.'
                : message;
        }
    } finally {
        saving.value = false;
    }
}
</script>
