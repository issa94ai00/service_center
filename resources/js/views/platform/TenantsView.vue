<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-extrabold text-slate-900">المتاجر</h1>
                <p class="mt-0.5 text-sm text-slate-500">{{ summary }}</p>
            </div>
            <button
                type="button"
                class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700"
                @click="openCreate"
            >
                + متجر جديد
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <input
                v-model="search"
                type="search"
                placeholder="بحث بالاسم أو النطاق أو البريد أو الهاتف"
                class="w-full max-w-sm rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            />
            <div class="flex gap-1 rounded-xl bg-slate-200/70 p-1 text-sm" role="tablist" aria-label="الحالة">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    :aria-selected="status === tab.value"
                    class="rounded-lg px-3 py-1.5 font-medium transition"
                    :class="status === tab.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    @click="status = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div v-if="loading" class="p-10 text-center text-sm text-slate-500">جارٍ التحميل...</div>
            <div v-else-if="!filtered.length" class="p-10 text-center text-sm text-slate-500">
                {{ tenants.length ? 'لا نتائج مطابقة.' : 'لا توجد متاجر بعد. أنشئ أول متجر.' }}
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-start font-semibold">المتجر</th>
                            <th class="px-4 py-3 text-start font-semibold">الحالة</th>
                            <th class="px-4 py-3 text-start font-semibold">الاشتراك حتى</th>
                            <th class="px-4 py-3 text-start font-semibold">المالك</th>
                            <th class="px-4 py-3 text-start font-semibold">HTTPS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="t in filtered"
                            :key="t.id"
                            class="cursor-pointer hover:bg-slate-50"
                            @click="router.push({ name: 'tenant', params: { id: t.id } })"
                        >
                            <td class="px-4 py-3">
                                <router-link :to="{ name: 'tenant', params: { id: t.id } }" class="font-semibold text-slate-900 hover:text-blue-700" @click.stop>
                                    {{ t.name }}
                                </router-link>
                                <a :href="t.url" target="_blank" rel="noopener" class="block font-mono text-xs text-blue-600 hover:underline" dir="ltr" @click.stop>
                                    {{ t.host }}
                                </a>
                            </td>
                            <td class="px-4 py-3"><TenantStatus :tenant="t" /></td>
                            <td class="px-4 py-3 text-slate-600">{{ t.expires_at ? formatDate(t.expires_at) : 'مفتوح' }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <span class="block">{{ t.owner_name || '—' }}</span>
                                <span class="block text-xs text-slate-400" dir="ltr">{{ t.owner_email }}</span>
                            </td>
                            <td class="px-4 py-3"><SslBadge :status="t.ssl_status" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
                        <div class="flex items-center rounded-xl border border-slate-300 focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-500/20" dir="ltr">
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
                    <legend class="mb-2 text-sm font-medium text-slate-700">الأقسام المفعّلة</legend>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <label v-for="(label, key) in meta.features" :key="key" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <input v-model="form.features[key]" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                            {{ label }}
                        </label>
                    </div>
                </fieldset>

                <FormField label="ملاحظات">
                    <textarea v-model="form.notes" rows="2" :class="INPUT" />
                </FormField>

                <p v-if="formError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</p>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
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
import BaseModal from '../../components/ui/BaseModal.vue';
import FormField from '../../components/ui/FormField.vue';
import SslBadge from '../../components/platform/SslBadge.vue';
import TenantStatus from '../../components/platform/TenantStatus.vue';
import { useToast } from '../../composables/useToast';
import { tenantsApi } from '../../services/adminApi';
import { apiFieldErrors, apiMessage, formatDate } from '../../utils/format';

const router = useRouter();
const toast = useToast();

const INPUT = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

const tenants = ref([]);
const meta = ref({ central_domain: '', features: {} });
const loading = ref(true);
const search = ref('');
const status = ref('');

const statusTabs = [
    { value: '', label: 'الكل' },
    { value: 'active', label: 'نشط' },
    { value: 'suspended', label: 'موقوف' },
    { value: 'archived', label: 'مؤرشف' },
];

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();
    return tenants.value.filter((t) => {
        if (status.value && t.status !== status.value) return false;
        if (!term) return true;
        return [t.name, t.slug, t.owner_email, t.contact_phone].some((v) => (v || '').toLowerCase().includes(term));
    });
});

const summary = computed(() => {
    const active = tenants.value.filter((t) => t.is_accessible).length;
    return `${tenants.value.length} متجر — ${active} يعمل حالياً`;
});

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
