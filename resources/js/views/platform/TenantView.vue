<template>
    <div class="space-y-6">
        <router-link :to="{ name: 'tenants' }" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
            → كل المتاجر
        </router-link>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">جارٍ التحميل...</div>

        <template v-else-if="tenant">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-extrabold text-slate-900">{{ tenant.name }}</h1>
                        <TenantStatus :tenant="tenant" />
                    </div>
                    <a :href="tenant.url" target="_blank" rel="noopener" class="mt-1 inline-block font-mono text-sm text-blue-600 hover:underline" dir="ltr">{{ tenant.host }} ↗</a>
                    <p class="mt-1 font-mono text-xs text-slate-400" dir="ltr">DB: {{ tenant.database }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="tenant.status === 'active'"
                        type="button"
                        class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100"
                        :disabled="busy"
                        @click="setStatus('suspended')"
                    >
                        إيقاف المتجر
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                        :disabled="busy"
                        @click="setStatus('active')"
                    >
                        تفعيل المتجر
                    </button>
                    <button
                        v-if="tenant.status !== 'archived'"
                        type="button"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        :disabled="busy"
                        @click="setStatus('archived')"
                    >
                        أرشفة
                    </button>
                </div>
            </div>

            <p v-if="tenant.database_error" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ tenant.database_error }}</p>

            <!-- Stats -->
            <div v-if="tenant.stats" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div v-for="s in statCards" :key="s.label" class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs text-slate-500">{{ s.label }}</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ s.value }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Details -->
                <form class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 lg:col-span-2" novalidate @submit.prevent="saveDetails">
                    <h2 class="text-base font-bold text-slate-900">بيانات المتجر والاشتراك</h2>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField label="اسم المتجر" required>
                            <input v-model.trim="details.name" type="text" :class="INPUT" />
                        </FormField>
                        <FormField label="الباقة">
                            <input v-model.trim="details.plan" type="text" :class="INPUT" />
                        </FormField>
                        <FormField label="ينتهي الاشتراك في">
                            <div class="flex gap-2">
                                <input v-model="details.expires_at" type="date" :class="INPUT" />
                                <button type="button" class="shrink-0 rounded-xl border border-slate-300 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50" @click="extend(1)">+ شهر</button>
                                <button type="button" class="shrink-0 rounded-xl border border-slate-300 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50" @click="extend(12)">+ سنة</button>
                            </div>
                            <p class="mt-1 text-xs text-slate-400">فارغ = اشتراك مفتوح. بعد هذا التاريخ يُغلق المتجر تلقائياً.</p>
                        </FormField>
                        <FormField label="هاتف التواصل">
                            <input v-model.trim="details.contact_phone" type="tel" dir="ltr" :class="INPUT" />
                        </FormField>
                        <FormField label="اسم المالك">
                            <input v-model.trim="details.owner_name" type="text" :class="INPUT" />
                        </FormField>
                        <FormField label="بريد المالك">
                            <input v-model.trim="details.owner_email" type="email" dir="ltr" :class="INPUT" />
                        </FormField>
                    </div>
                    <FormField label="ملاحظات">
                        <textarea v-model="details.notes" rows="3" :class="INPUT" />
                    </FormField>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60" :disabled="busy">حفظ</button>
                    </div>
                </form>

                <!-- Features -->
                <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-bold text-slate-900">الأقسام المفعّلة</h2>
                    <p v-if="!tenant.features" class="text-sm text-slate-500">غير متاح.</p>
                    <div v-else class="space-y-2">
                        <label v-for="(label, key) in meta.features" :key="key" class="flex items-center justify-between rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                            <span>{{ label }}</span>
                            <input v-model="features[key]" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                        </label>
                        <button type="button" class="mt-2 w-full rounded-xl border border-blue-200 bg-blue-50 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100" :disabled="busy" @click="saveFeatures">
                            حفظ الأقسام
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Owner password -->
                <form class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" novalidate @submit.prevent="resetPassword">
                    <h2 class="text-base font-bold text-slate-900">كلمة مرور المالك</h2>
                    <p class="text-xs text-slate-500">يعيد تفعيل الحساب ويسجّل خروجه من كل الأجهزة.</p>
                    <input v-model.trim="reset.email" type="email" dir="ltr" :placeholder="tenant.owner_email || 'بريد الحساب'" :class="INPUT" />
                    <input v-model="reset.password" type="text" dir="ltr" placeholder="كلمة المرور الجديدة" autocomplete="off" :class="[INPUT, 'font-mono']" />
                    <button type="submit" class="w-full rounded-xl bg-slate-800 py-2 text-sm font-semibold text-white hover:bg-slate-900" :disabled="busy">تعيين</button>
                </form>

                <!-- Maintenance -->
                <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-bold text-slate-900">الصيانة التقنية</h2>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600">شهادة HTTPS</span>
                        <SslBadge :status="tenant.ssl_status" />
                    </div>
                    <p v-if="tenant.ssl_status !== 'issued'" class="rounded-lg bg-amber-50 p-2 text-xs leading-relaxed text-amber-800">
                        إن تعذّر الإصدار من هنا، نفّذ على الخادم:
                        <code class="mt-1 block select-all font-mono text-[11px]" dir="ltr">php artisan tenants:certificate {{ tenant.slug }}</code>
                    </p>
                    <pre v-if="tenant.ssl_message" class="max-h-32 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-2 text-[11px] text-slate-600" dir="ltr">{{ tenant.ssl_message }}</pre>
                    <button type="button" class="w-full rounded-xl border border-slate-300 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="busy" @click="issueCertificate">
                        {{ tenant.ssl_status === 'issued' ? 'تجديد / إعادة إصدار الشهادة' : 'إصدار الشهادة' }}
                    </button>
                    <button type="button" class="w-full rounded-xl border border-slate-300 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" :disabled="busy" @click="migrate">
                        تحديث قاعدة البيانات
                    </button>
                </div>

                <!-- Danger -->
                <div class="space-y-3 rounded-2xl border border-red-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-base font-bold text-red-700">حذف المتجر</h2>
                    <p v-if="tenant.status !== 'archived'" class="text-sm text-slate-500">أرشف المتجر أولاً لتتمكن من حذفه.</p>
                    <template v-else>
                        <input v-model.trim="del.confirm_slug" type="text" dir="ltr" :placeholder="tenant.slug" :class="[INPUT, 'font-mono']" />
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="del.drop_database" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-red-600" />
                            حذف قاعدة البيانات نهائياً
                        </label>
                        <button
                            type="button"
                            class="w-full rounded-xl bg-red-600 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            :disabled="busy || del.confirm_slug !== tenant.slug"
                            @click="destroy"
                        >
                            حذف المتجر
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import FormField from '../../components/ui/FormField.vue';
import SslBadge from '../../components/platform/SslBadge.vue';
import TenantStatus from '../../components/platform/TenantStatus.vue';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { tenantsApi } from '../../services/adminApi';
import { apiMessage, formatDateTime } from '../../utils/format';

const props = defineProps({ id: { type: [String, Number], required: true } });

const router = useRouter();
const toast = useToast();

const INPUT = 'w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';

const tenant = ref(null);
const meta = ref({ features: {} });
const loading = ref(true);
const busy = ref(false);

const details = reactive({});
const features = reactive({});
const reset = reactive({ email: '', password: '' });
const del = reactive({ confirm_slug: '', drop_database: false });

const statCards = computed(() => {
    const s = tenant.value?.stats || {};
    return [
        { label: 'المستخدمون', value: s.users ?? '—' },
        { label: 'العملاء', value: s.customers ?? '—' },
        { label: 'فواتير المبيعات', value: s.sales_invoices ?? '—' },
        { label: 'أجهزة الصيانة', value: s.repairs ?? '—' },
        { label: 'حجم البيانات', value: s.database_mb != null ? `${s.database_mb} MB` : '—' },
        { label: 'آخر نشاط', value: s.last_activity ? formatDateTime(s.last_activity) : '—' },
    ];
});

function hydrate(data) {
    tenant.value = data;
    Object.assign(details, {
        name: data.name,
        plan: data.plan || '',
        expires_at: data.expires_at || '',
        contact_phone: data.contact_phone || '',
        owner_name: data.owner_name || '',
        owner_email: data.owner_email || '',
        notes: data.notes || '',
    });
    Object.assign(features, data.features || {});
}

async function load() {
    loading.value = true;
    try {
        const [data, m] = await Promise.all([tenantsApi.show(props.id), tenantsApi.meta()]);
        meta.value = m;
        hydrate(data);
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تحميل المتجر.'));
        router.replace({ name: 'tenants' });
    } finally {
        loading.value = false;
    }
}

async function run(action, successMessage) {
    busy.value = true;
    try {
        const result = await action();
        if (successMessage) toast.success(typeof successMessage === 'function' ? successMessage(result) : successMessage);
        return result;
    } catch (e) {
        toast.error(apiMessage(e));
        return null;
    } finally {
        busy.value = false;
    }
}

function extend(months) {
    const today = new Date();
    const base = details.expires_at ? new Date(`${details.expires_at}T00:00:00`) : today;
    const from = base < today ? today : base;
    from.setMonth(from.getMonth() + months);
    details.expires_at = from.toISOString().slice(0, 10);
}

async function saveDetails() {
    const data = await run(() => tenantsApi.update(props.id, { ...details, expires_at: details.expires_at || null }), 'تم الحفظ.');
    if (data) hydrate(data);
}

async function saveFeatures() {
    const data = await run(() => tenantsApi.update(props.id, { features: { ...features } }), 'تم تحديث الأقسام.');
    if (data) hydrate(data);
}

async function setStatus(status) {
    const prompts = {
        suspended: { title: 'إيقاف المتجر؟', message: 'لن يتمكن أي مستخدم في المتجر من الدخول حتى تعيد تفعيله. البيانات تبقى كما هي.', confirmLabel: 'إيقاف', tone: 'danger' },
        archived: { title: 'أرشفة المتجر؟', message: 'يُغلق المتجر ويختفي عنوانه. البيانات تبقى ويمكن إعادة تفعيله لاحقاً.', confirmLabel: 'أرشفة', tone: 'danger' },
    };
    if (prompts[status] && !(await confirm(prompts[status]))) return;

    const data = await run(() => tenantsApi.update(props.id, { status }), 'تم تحديث حالة المتجر.');
    if (data) hydrate(data);
}

async function resetPassword() {
    if (reset.password.length < 8) {
        toast.error('كلمة المرور 8 أحرف على الأقل.');
        return;
    }
    const result = await run(
        () => tenantsApi.resetPassword(props.id, { password: reset.password, email: reset.email || null }),
        (r) => r.message,
    );
    if (result) Object.assign(reset, { email: '', password: '' });
}

async function issueCertificate() {
    const data = await run(() => tenantsApi.issueCertificate(props.id));
    if (data) {
        tenant.value = { ...tenant.value, ssl_status: data.ssl_status, ssl_message: data.ssl_message };
        data.ssl_status === 'issued' ? toast.success('الشهادة مفعّلة.') : toast.error('لم تصدر الشهادة — راجع التفاصيل.');
    }
}

async function migrate() {
    await run(() => tenantsApi.migrate(props.id), (r) => r.message);
}

async function destroy() {
    const ok = await confirm({
        title: 'حذف المتجر نهائياً؟',
        message: del.drop_database ? 'ستُحذف قاعدة بيانات المتجر بكل ما فيها ولا يمكن استرجاعها.' : 'يُحذف المتجر من المنصة وتبقى قاعدة بياناته على الخادم.',
        confirmLabel: 'حذف',
        tone: 'danger',
    });
    if (!ok) return;

    const result = await run(() => tenantsApi.remove(props.id, { ...del }), 'تم حذف المتجر.');
    if (result) router.replace({ name: 'tenants' });
}

onMounted(load);
</script>
