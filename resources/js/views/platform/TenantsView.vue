<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">المتاجر</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">إدارة المتاجر المشتركة واشتراكاتها وأقسامها</p>
            </div>
            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-blue-600 px-4 text-sm font-bold text-white shadow-sm shadow-blue-600/25 transition hover:bg-blue-700 active:scale-[0.98]"
                @click="openCreate"
            >
                <AppIcon name="plus" class="text-base" />
                متجر جديد
            </button>
        </div>

        <!-- Overview: each card doubles as a filter -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <button
                v-for="card in overview"
                :key="card.key"
                type="button"
                class="group rounded-2xl border bg-white dark:bg-slate-900 p-4 text-start shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition sm:p-5"
                :class="view === card.key
                    ? 'border-blue-500 ring-2 ring-blue-500/20'
                    : 'border-slate-200/70 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'"
                :aria-pressed="view === card.key"
                @click="view = view === card.key ? 'all' : card.key"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[13px] font-medium text-slate-500 dark:text-slate-400">{{ card.label }}</span>
                    <span class="grid h-8 w-8 place-items-center rounded-lg text-[15px]" :class="card.tone">
                        <AppIcon :name="card.icon" />
                    </span>
                </div>
                <p class="mt-2 text-2xl font-extrabold tabular-nums text-slate-900 dark:text-white sm:text-3xl">
                    <span v-if="loading" class="inline-block h-7 w-10 animate-pulse rounded-md bg-slate-200 dark:bg-slate-800"></span>
                    <template v-else>{{ card.count }}</template>
                </p>
                <p class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500">{{ card.hint }}</p>
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 dark:border-slate-800 p-3">
                <label class="relative min-w-0 flex-1 basis-64">
                    <span class="sr-only">بحث</span>
                    <AppIcon name="search" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="بحث بالاسم أو النطاق أو البريد أو الهاتف"
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 py-2 pe-3 ps-9 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:border-blue-500 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                    />
                </label>
                <div class="no-scrollbar flex max-w-full gap-0.5 overflow-x-auto rounded-xl bg-slate-100 dark:bg-slate-800 p-1 text-[13px]" role="tablist" aria-label="الحالة">
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        type="button"
                        role="tab"
                        :aria-selected="status === tab.value"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 font-semibold transition"
                        :class="status === tab.value ? 'bg-white dark:bg-slate-950 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        @click="status = tab.value"
                    >
                        {{ tab.label }}
                        <span class="text-xs tabular-nums text-slate-400">{{ tab.count }}</span>
                    </button>
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="sr-only sm:not-sr-only">ترتيب</span>
                    <select
                        v-model="sort"
                        class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 py-2 pe-8 ps-3 text-[13px] font-semibold text-slate-700 dark:text-slate-200 focus:border-blue-500 focus:outline-none"
                    >
                        <option value="newest">الأحدث</option>
                        <option value="expiry">الأقرب انتهاءً</option>
                        <option value="name">الاسم</option>
                    </select>
                </label>
            </div>

            <div v-if="view !== 'all'" class="flex items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 bg-blue-50/60 dark:bg-blue-950/30 px-4 py-2 text-xs font-semibold text-blue-700 dark:text-blue-300">
                <span>يعرض: {{ overview.find((c) => c.key === view)?.label }}</span>
                <button type="button" class="hover:underline" @click="view = 'all'">إلغاء التصفية</button>
            </div>

            <!-- Loading -->
            <ul v-if="loading" class="divide-y divide-slate-100 dark:divide-slate-800" aria-busy="true">
                <li v-for="n in 3" :key="n" class="flex animate-pulse items-center gap-3 px-4 py-4">
                    <div class="h-10 w-10 rounded-xl bg-slate-200 dark:bg-slate-800"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3.5 w-40 rounded bg-slate-200 dark:bg-slate-800"></div>
                        <div class="h-3 w-56 rounded bg-slate-100 dark:bg-slate-800/60"></div>
                    </div>
                </li>
            </ul>

            <!-- Empty -->
            <div v-else-if="!filtered.length" class="px-6 py-14 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-xl text-slate-400">
                    <AppIcon :name="tenants.length ? 'search' : 'store'" />
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {{ tenants.length ? 'لا نتائج مطابقة' : 'لا توجد متاجر بعد' }}
                </p>
                <p class="mt-1 text-xs text-slate-400">{{ tenants.length ? 'جرّب كلمة بحث أو تصفية أخرى.' : 'أنشئ أول متجر ليظهر هنا.' }}</p>
                <button
                    v-if="tenants.length"
                    type="button"
                    class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                    @click="clearFilters"
                >
                    مسح التصفية
                </button>
            </div>

            <template v-else>
                <!-- Desktop table -->
                <table class="hidden w-full text-sm md:table">
                    <thead class="text-xs text-slate-500 dark:text-slate-400">
                        <tr class="border-b border-slate-100 dark:border-slate-800">
                            <th class="px-4 py-3 text-start font-semibold">المتجر</th>
                            <th class="px-4 py-3 text-start font-semibold">الحالة</th>
                            <th class="px-4 py-3 text-start font-semibold">الاشتراك</th>
                            <th class="hidden px-4 py-3 text-start font-semibold lg:table-cell">المالك</th>
                            <th class="px-4 py-3 text-start font-semibold">HTTPS</th>
                            <th class="w-10"><span class="sr-only">فتح</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="t in filtered"
                            :key="t.id"
                            class="group cursor-pointer transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                            @click="open(t)"
                        >
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-sm font-bold text-white" :class="avatarTone(t.slug)">
                                        {{ initials(t.name) }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <router-link :to="{ name: 'tenant', params: { id: t.id } }" class="truncate font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400" @click.stop>
                                                {{ t.name }}
                                            </router-link>
                                            <span v-if="t.source === 'signup'" class="shrink-0 rounded-full bg-violet-50 dark:bg-violet-950/50 px-2 py-0.5 text-[10px] font-bold text-violet-700 dark:text-violet-300">تسجيل ذاتي</span>
                                        </div>
                                        <a :href="t.url" target="_blank" rel="noopener" class="mt-0.5 inline-flex items-center gap-1 font-mono text-xs text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400" dir="ltr" @click.stop>
                                            {{ t.host }}
                                            <AppIcon name="external" class="text-[11px]" />
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5"><TenantStatus :tenant="t" /></td>
                            <td class="px-4 py-3.5">
                                <p class="text-slate-700 dark:text-slate-200">
                                    {{ t.expires_at ? formatDate(t.expires_at) : 'بلا تاريخ انتهاء' }}
                                    <span v-if="t.plan" class="text-xs text-slate-400">· {{ t.plan }}</span>
                                </p>
                                <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums" :class="subscriptionView(t.expires_at).tone">
                                    {{ subscriptionView(t.expires_at).label }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-3.5 lg:table-cell">
                                <span class="block text-slate-700 dark:text-slate-200">{{ t.owner_name || '—' }}</span>
                                <span class="block text-xs text-slate-400" dir="ltr">{{ t.owner_email }}</span>
                            </td>
                            <td class="px-4 py-3.5"><SslBadge :status="t.ssl_status" /></td>
                            <td class="px-4 py-3.5 text-slate-300 dark:text-slate-600">
                                <AppIcon name="chevron-left" class="transition group-hover:text-slate-500 ltr:rotate-180" />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Phone cards -->
                <ul class="divide-y divide-slate-100 dark:divide-slate-800 md:hidden">
                    <li v-for="t in filtered" :key="t.id">
                        <router-link :to="{ name: 'tenant', params: { id: t.id } }" class="flex gap-3 px-4 py-4 active:bg-slate-50 dark:active:bg-slate-800/50">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-sm font-bold text-white" :class="avatarTone(t.slug)">
                                {{ initials(t.name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate font-bold text-slate-900 dark:text-white">{{ t.name }}</p>
                                    <TenantStatus :tenant="t" class="shrink-0" />
                                </div>
                                <p class="truncate font-mono text-xs text-slate-500 dark:text-slate-400" dir="ltr">{{ t.host }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="subscriptionView(t.expires_at).tone">
                                        {{ subscriptionView(t.expires_at).label }}
                                    </span>
                                    <SslBadge :status="t.ssl_status" />
                                    <span v-if="t.source === 'signup'" class="rounded-full bg-violet-50 dark:bg-violet-950/50 px-2 py-0.5 text-[11px] font-bold text-violet-700 dark:text-violet-300">تسجيل ذاتي</span>
                                </div>
                            </div>
                        </router-link>
                    </li>
                </ul>

                <p class="border-t border-slate-100 dark:border-slate-800 px-4 py-2.5 text-xs text-slate-400">
                    {{ filtered.length }} من {{ tenants.length }} متجر
                </p>
            </template>
        </div>

        <!-- Create -->
        <BaseModal :open="createOpen" title="متجر جديد" subtitle="تُنشأ قاعدة بيانات مستقلة وحساب للمالك وشهادة HTTPS" :close-on-overlay="false" @close="createOpen = false">
            <form class="space-y-4" novalidate @submit.prevent="create">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField label="اسم المتجر" required>
                        <input v-model.trim="form.name" type="text" :class="INPUT" @input="suggestSlug" />
                        <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
                    </FormField>
                    <FormField label="النطاق الفرعي" required>
                        <div class="flex items-center rounded-xl border border-slate-300 dark:border-slate-700 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20" dir="ltr">
                            <input
                                v-model.trim="form.slug"
                                type="text"
                                autocapitalize="off"
                                spellcheck="false"
                                class="min-w-0 flex-1 rounded-xl bg-transparent px-3 py-2 font-mono text-sm focus:outline-none"
                                @input="slugTouched = true"
                            />
                            <span class="shrink-0 pe-3 font-mono text-xs text-slate-400">.{{ meta.central_domain }}</span>
                        </div>
                        <p v-if="errors.slug" class="mt-1 text-xs text-red-600">{{ errors.slug }}</p>
                    </FormField>
                    <FormField label="اسم المالك" required>
                        <input v-model.trim="form.owner_name" type="text" :class="INPUT" />
                        <p v-if="errors.owner_name" class="mt-1 text-xs text-red-600">{{ errors.owner_name }}</p>
                    </FormField>
                    <FormField label="بريد المالك (للدخول)" required>
                        <input v-model.trim="form.owner_email" type="email" dir="ltr" :class="INPUT" />
                        <p v-if="errors.owner_email" class="mt-1 text-xs text-red-600">{{ errors.owner_email }}</p>
                    </FormField>
                    <FormField label="كلمة مرور المالك" required>
                        <div class="flex gap-2">
                            <input v-model="form.owner_password" type="text" dir="ltr" :class="[INPUT, 'font-mono']" autocomplete="off" />
                            <button type="button" class="shrink-0 rounded-xl border border-slate-300 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50" @click="form.owner_password = randomPassword()">
                                توليد
                            </button>
                        </div>
                        <p v-if="errors.owner_password" class="mt-1 text-xs text-red-600">{{ errors.owner_password }}</p>
                    </FormField>
                    <FormField label="هاتف التواصل">
                        <input v-model.trim="form.contact_phone" type="tel" dir="ltr" :class="INPUT" />
                    </FormField>
                    <FormField label="الباقة">
                        <input v-model.trim="form.plan" type="text" :class="INPUT" placeholder="مثال: سنوية" />
                    </FormField>
                    <FormField label="ينتهي الاشتراك في">
                        <input v-model="form.expires_at" type="date" :class="INPUT" />
                        <p class="mt-1 text-xs text-slate-400">اتركه فارغاً لاشتراك مفتوح.</p>
                    </FormField>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">الأقسام المفعّلة</legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <label v-for="(label, key) in meta.features" :key="key" class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 dark:has-[:checked]:bg-blue-950/40">
                            <input v-model="form.features[key]" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                            {{ label }}
                        </label>
                    </div>
                </fieldset>

                <FormField label="ملاحظات">
                    <textarea v-model="form.notes" rows="2" :class="INPUT" />
                </FormField>

                <p v-if="formError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</p>

                <div class="flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800 pt-4">
                    <button type="button" class="rounded-xl px-4 py-2 text-sm text-slate-600 hover:bg-slate-100" :disabled="saving" @click="createOpen = false">إلغاء</button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="saving">
                        {{ saving ? 'جارٍ تجهيز المتجر... (قد يستغرق دقيقة)' : 'إنشاء المتجر' }}
                    </button>
                </div>
            </form>
        </BaseModal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '../../components/ui/AppIcon.vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import FormField from '../../components/ui/FormField.vue';
import SslBadge from '../../components/platform/SslBadge.vue';
import TenantStatus from '../../components/platform/TenantStatus.vue';
import { avatarTone, daysLeft, EXPIRING_SOON_DAYS, initials, isExpiringSoon, needsAttention, subscriptionView } from '../../components/platform/subscription';
import { useToast } from '../../composables/useToast';
import { tenantsApi } from '../../services/adminApi';
import { apiFieldErrors, apiMessage, formatDate } from '../../utils/format';

const router = useRouter();
const toast = useToast();

const INPUT = 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

const tenants = ref([]);
const meta = ref({ central_domain: '', features: {} });
const loading = ref(true);
const search = ref('');
const status = ref('');
/** Overview card the list is narrowed to: all, running, expiring, attention or signups. */
const view = ref('all');
const sort = ref('newest');

const SIGNUP_WINDOW_DAYS = 30;

function isRecentSignup(t) {
    return t.source === 'signup' && t.created_at && Date.now() - new Date(t.created_at).getTime() <= SIGNUP_WINDOW_DAYS * 864e5;
}

const VIEWS = {
    all: () => true,
    running: (t) => t.is_accessible,
    expiring: isExpiringSoon,
    attention: needsAttention,
    signups: isRecentSignup,
};

const overview = computed(() => {
    const list = tenants.value;
    const count = (key) => list.filter(VIEWS[key]).length;
    return [
        { key: 'running', label: 'متاجر تعمل', count: count('running'), hint: `من أصل ${list.length} متجر`, icon: 'store', tone: 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400' },
        { key: 'expiring', label: 'تنتهي قريباً', count: count('expiring'), hint: `خلال ${EXPIRING_SOON_DAYS} يوماً`, icon: 'clock', tone: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400' },
        { key: 'attention', label: 'تحتاج متابعة', count: count('attention'), hint: 'موقوفة أو منتهية الاشتراك', icon: 'alert', tone: 'bg-red-50 dark:bg-red-950/60 text-red-700 dark:text-red-400' },
        { key: 'signups', label: 'تسجيلات جديدة', count: count('signups'), hint: `آخر ${SIGNUP_WINDOW_DAYS} يوماً`, icon: 'sparkles', tone: 'bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-400' },
    ];
});

const statusTabs = computed(() => {
    const count = (value) => tenants.value.filter((t) => !value || t.status === value).length;
    return [
        { value: '', label: 'الكل', count: count('') },
        { value: 'active', label: 'نشط', count: count('active') },
        { value: 'suspended', label: 'موقوف', count: count('suspended') },
        { value: 'archived', label: 'مؤرشف', count: count('archived') },
    ];
});

const SORTS = {
    newest: (a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')),
    // Open subscriptions have no end, so they sink to the bottom.
    expiry: (a, b) => (daysLeft(a.expires_at) ?? Infinity) - (daysLeft(b.expires_at) ?? Infinity),
    name: (a, b) => a.name.localeCompare(b.name, 'ar'),
};

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    return tenants.value
        .filter((t) => {
            if (status.value && t.status !== status.value) return false;
            if (!VIEWS[view.value](t)) return false;
            if (!term) return true;
            return [t.name, t.slug, t.owner_email, t.contact_phone].some((v) => (v || '').toLowerCase().includes(term));
        })
        .sort(SORTS[sort.value]);
});

function open(t) {
    router.push({ name: 'tenant', params: { id: t.id } });
}

function clearFilters() {
    search.value = '';
    status.value = '';
    view.value = 'all';
}

async function load() {
    loading.value = true;
    try {
        [tenants.value, meta.value] = await Promise.all([tenantsApi.list(), tenantsApi.meta()]);
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تحميل المتاجر.'));
    } finally {
        loading.value = false;
    }
}

// ----- create
const createOpen = ref(false);
const saving = ref(false);
const errors = ref({});
const formError = ref('');
const slugTouched = ref(false);
const form = reactive(blankForm());

function blankForm() {
    return {
        name: '',
        slug: '',
        owner_name: '',
        owner_email: '',
        owner_password: '',
        contact_phone: '',
        plan: '',
        expires_at: '',
        notes: '',
        features: {},
    };
}

function randomPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    const bytes = crypto.getRandomValues(new Uint32Array(12));
    return Array.from(bytes, (b) => chars[b % chars.length]).join('');
}

function suggestSlug() {
    if (slugTouched.value) return;
    // Arabic names have no Latin form to borrow; only suggest from Latin text.
    const latin = form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40);
    form.slug = latin;
}

function openCreate() {
    Object.assign(form, blankForm(), {
        owner_password: randomPassword(),
        features: Object.fromEntries(Object.keys(meta.value.features || {}).map((k) => [k, true])),
    });
    errors.value = {};
    formError.value = '';
    slugTouched.value = false;
    createOpen.value = true;
}

async function create() {
    saving.value = true;
    errors.value = {};
    formError.value = '';
    try {
        const payload = { ...form, slug: form.slug.toLowerCase(), expires_at: form.expires_at || null };
        const tenant = await tenantsApi.create(payload);
        createOpen.value = false;
        toast.success(`تم إنشاء ${tenant.name}. كلمة مرور المالك: ${form.owner_password}`, { timeout: 0, title: tenant.host });
        router.push({ name: 'tenant', params: { id: tenant.id } });
    } catch (e) {
        errors.value = apiFieldErrors(e);
        formError.value = Object.keys(errors.value).length ? '' : apiMessage(e, 'تعذّر إنشاء المتجر.');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
