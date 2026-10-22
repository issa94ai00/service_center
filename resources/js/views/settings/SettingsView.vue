<template>
    <div class="mx-auto max-w-4xl space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-900">الإعدادات</h1>
            <p class="text-sm text-slate-500">بيانات الشركة والعملة والسياسات المالية وترقيم المستندات والطباعة</p>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ error }}
        </div>

        <div v-if="loading" class="py-16 text-center text-sm text-slate-400">جارٍ التحميل...</div>

        <form v-else class="space-y-5" @submit.prevent="save">
            <section v-for="group in groups" :key="group.key" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 select-none items-center justify-center rounded-xl text-lg"
                            :class="groupChip(group.key)"
                            aria-hidden="true"
                        >{{ groupIcons[group.key] || '⚙️' }}</span>
                        <div>
                            <h2 class="font-bold text-slate-900">{{ group.label }}</h2>
                            <p class="text-xs text-slate-500">{{ group.hint }}</p>
                        </div>
                    </div>
                    <span
                        v-if="group.key === 'features'"
                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-300"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                        صلاحية المدير العام (Master)
                    </span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="setting in group.settings"
                        :key="setting.key"
                        :class="meta[setting.key]?.wide ? 'sm:col-span-2' : ''"
                    >
                        <label class="mb-1 block text-sm font-medium text-slate-700">
                            {{ meta[setting.key]?.label || setting.key }}
                            <span v-if="meta[setting.key]?.required" class="text-red-500">*</span>
                        </label>

                        <!-- boolean -->
                        <label v-if="setting.type === 'bool'" class="flex cursor-pointer items-center gap-3 pt-1">
                            <input v-model="values[setting.key]" type="checkbox" class="peer sr-only" />
                            <span
                                class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-slate-300 transition-colors after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300 rtl:peer-checked:after:-translate-x-full"
                                aria-hidden="true"
                            ></span>
                            <span class="text-sm text-slate-700">{{ meta[setting.key]?.on || 'مفعّل' }}</span>
                        </label>

                        <!-- constrained choice -->
                        <select
                            v-else-if="meta[setting.key]?.options"
                            v-model="values[setting.key]"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        >
                            <option v-for="opt in meta[setting.key].options" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>

                        <!-- free text -->
                        <input
                            v-else
                            v-model="values[setting.key]"
                            :type="setting.type === 'int' || setting.type === 'decimal' ? 'number' : 'text'"
                            :dir="meta[setting.key]?.ltr ? 'ltr' : 'rtl'"
                            :placeholder="meta[setting.key]?.placeholder || ''"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        />

                        <p v-if="fieldErrors[setting.key]" class="mt-1 text-xs text-red-600">
                            {{ fieldErrors[setting.key] }}
                        </p>
                        <p v-else-if="meta[setting.key]?.hint" class="mt-1 text-xs text-slate-400">
                            {{ meta[setting.key].hint }}
                        </p>
                    </div>
                </div>
            </section>

            <PrintSettingsSection v-if="hasPrintSettings" :values="values" :errors="fieldErrors" :dirty="dirty" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="dirty"
                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-100"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                        تغييرات غير محفوظة
                    </span>
                    <p class="text-xs text-slate-400">
                        تُطبَّق التغييرات فورًا على الفواتير والمستندات الجديدة، ولا تُعدّل المستندات المُرحّلة سابقًا.
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:opacity-40"
                        :disabled="saving || !dirty"
                        @click="reset"
                    >
                        تراجع
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg px-5 py-2 text-sm font-medium text-white transition focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 disabled:opacity-40"
                        :class="justSaved ? 'bg-emerald-600' : 'bg-blue-600 hover:bg-blue-700'"
                        :disabled="saving || !dirty"
                    >
                        {{ saving ? 'جارٍ الحفظ...' : (justSaved ? 'تم الحفظ ✓' : 'حفظ الإعدادات') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { settingsApi } from '../../services/api';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { usePrintPreferences } from '../../composables/usePrintPreferences';
import { useAuthStore } from '../../stores/auth';
import { useFeaturesStore } from '../../stores/features';
import PrintSettingsSection from '../../components/print/PrintSettingsSection.vue';

// Presentation for each known key. Unknown keys still render, labelled by key,
// so a setting added later is editable before this map is updated.
const meta = {
    company_name: { label: 'اسم الشركة', required: true, wide: true, hint: 'يظهر في ترويسة كل فاتورة وكشف حساب' },
    company_address: { label: 'العنوان', wide: true },
    company_phone: { label: 'الهاتف', ltr: true, placeholder: '011...' },
    company_email: { label: 'البريد الإلكتروني', ltr: true, placeholder: 'info@example.com' },
    company_tax_number: { label: 'الرقم الضريبي', ltr: true },
    currency_name: { label: 'اسم العملة الأساسية', required: true, hint: 'يُستخدم في تفقيط المبالغ على السندات' },
    currency_symbol: { label: 'رمز العملة الأساسية', required: true, hint: 'الدفاتر بالدولار — العملات الأخرى من شاشة «العملات وأسعار الصرف»' },
    currency_code: { label: 'كود العملة الأساسية', ltr: true, hint: 'ثابت: USD' },
    allow_overpayment: {
        label: 'السماح بالدفع الزائد',
        on: 'اسمح بدفعة تتجاوز رصيد الفاتورة',
        hint: 'عند التعطيل تُرفض أي دفعة أكبر من المتبقي',
    },
    inventory_sale_policy: {
        label: 'سياسة المخزون',
        options: [
            { value: 'strict', label: 'صارمة — امنع البيع عند عدم توفر الكمية' },
            { value: 'permissive', label: 'متساهلة — اسمح برصيد سالب' },
        ],
        wide: true,
        hint: 'تؤثر على ترحيل فواتير البيع وتسويات المخزون',
    },
    inventory_cost_method: {
        label: 'طريقة حساب التكلفة الافتراضية',
        options: [
            { value: 'MANUAL', label: 'يدوية — التكلفة كما تُدخل ولا تتغيّر تلقائياً' },
            { value: 'WEIGHTED_AVERAGE', label: 'المتوسط المرحّل — تُحدَّث مع كل عملية توريد' },
            { value: 'LAST_PURCHASE', label: 'آخر سعر شراء — تُستبدل بسعر آخر فاتورة شراء' },
            { value: 'FIFO', label: 'الوارد أولاً (FIFO) — تُصرف أقدم دفعات الشراء بسعرها الفعلي' },
        ],
        wide: true,
        hint: 'الافتراضي للمواد الجديدة — يمكن تخصيص الطريقة لكل مادة من نموذج المادة',
    },
    invoice_prefix: { label: 'بادئة فواتير البيع', ltr: true, placeholder: 'SL-' },
    purchase_prefix: { label: 'بادئة فواتير الشراء', ltr: true, placeholder: 'PR-' },
    payment_prefix: { label: 'بادئة السندات', ltr: true, placeholder: 'PAY-' },
    adjustment_prefix: { label: 'بادئة تسويات المخزون', ltr: true, placeholder: 'ADJ-' },
    sales_return_prefix: { label: 'بادئة مرتجعات المبيعات', ltr: true, placeholder: 'SRT-' },
    purchase_return_prefix: { label: 'بادئة مرتجعات المشتريات', ltr: true, placeholder: 'PRT-' },
    repair_receipt_prefix: { label: 'بادئة إيصالات استلام الصيانة', ltr: true, placeholder: 'RPR-' },
    repair_invoice_prefix: {
        label: 'بادئة فواتير الصيانة',
        ltr: true,
        placeholder: 'RS-',
        hint: 'سلسلة ترقيم مستقلة عن فواتير البيع، تُرقَّم بها فواتير تسوية الصيانة',
    },
    repair_payment_prefix: {
        label: 'بادئة سندات قبض الصيانة',
        ltr: true,
        placeholder: 'RCV-',
        hint: 'سلسلة مستقلة عن سندات المبيعات، تُرقَّم بها العرابين والتحصيل عند التسليم',
    },
    expense_prefix: {
        label: 'بادئة سندات المصاريف',
        ltr: true,
        placeholder: 'EXP-',
        hint: 'سلسلة مستقلة، تُرقَّم بها مصاريف الصندوق من إيجار وكهرباء ونثريات',
    },
    material_sku_prefix: {
        label: 'بادئة رموز المواد التلقائية',
        ltr: true,
        placeholder: 'M-',
        hint: 'تُستخدم حين تُضاف مادة جديدة بلا رمز — مثل الإضافة السريعة من فاتورة الشراء',
    },

    // System features toggles (Master / features.manage)
    feature_repairs: {
        label: 'قسم الصيانة والأجهزة (Repairs)',
        hint: 'إدارة أجهزة وبطاقات وإيصالات وفواتير وتسوية الصيانة',
        on: 'مفعّل في النظام',
    },
    feature_technicians: {
        label: 'خبراء وفنيو الصيانة (Technicians)',
        hint: 'إدارة الفنيين وحساب نسب أجور الصيانة وكشوفات الحساب',
        on: 'مفعّل في النظام',
    },
    feature_purchases: {
        label: 'فواتير الشراء (Purchases)',
        hint: 'إدارة فواتير الشراء وعمليات التوريد واقتراح الأسعار',
        on: 'مفعّل في النظام',
    },
    feature_expenses: {
        label: 'سندات المصاريف (Expenses)',
        hint: 'إدارة مصاريف الصندوق من إيجارات وفواتير ونثريات',
        on: 'مفعّل في النظام',
    },
    feature_reports: {
        label: 'التقارير المالية والأرباح (Reports)',
        hint: 'عرض تقارير الأرباح والخسائر والتدفقات والمؤشرات المالية',
        on: 'مفعّل في النظام',
    },
    feature_services: {
        label: 'دليل الخدمات ووحدات القياس (Services & Units)',
        hint: 'إدارة الخدمات المقدمة ووحدات القياس المعتمدة',
        on: 'مفعّل في النظام',
    },
    feature_inventory: {
        label: 'المخزون والجرد (Inventory)',
        hint: 'إدارة تسويات ومطابقة كميات المخزون وحركات المستودع',
        on: 'مفعّل في النظام',
    },
    feature_sales: {
        label: 'فواتير البيع (Sales)',
        hint: 'إدارة فواتير بيع المواد والخدمات للعملاء',
        on: 'مفعّل في النظام',
    },
};

const groupMeta = {
    general: { label: 'بيانات الشركة والعملة', hint: 'تظهر على المستندات المطبوعة' },
    financial: { label: 'السياسات المالية والمخزون', hint: 'تحكم قواعد الترحيل والدفع' },
    numbers: { label: 'ترقيم المستندات', hint: 'تُطبّق على المستندات الجديدة فقط — الأرقام المُصدرة لا تتغيّر' },
    features: {
        label: 'ميزات ووحدات النظام (System Features)',
        hint: 'تمكين أو تعطيل الوحدات والوظائف الرئيسية للنظام (خاص بالمدير العام Master)',
    },
};

const groupIcons = { general: '🏢', financial: '⚖️', numbers: '🔢', features: '⚡' };

function groupChip(key) {
    return {
        general: 'bg-blue-100',
        financial: 'bg-violet-100',
        numbers: 'bg-cyan-100',
        features: 'bg-amber-100 text-amber-700',
    }[key] || 'bg-slate-100';
}

const auth = useAuthStore();
const featuresStore = useFeaturesStore();
const canManageFeatures = computed(() => auth.hasPermission('features.manage'));

const settings = ref([]);
const values = reactive({});
const original = reactive({});
const fieldErrors = reactive({});
const loading = ref(true);
const saving = ref(false);
const justSaved = ref(false);
const error = ref('');
const toast = useToast();
const printPreferences = usePrintPreferences();

// Print settings get their own section with paper pickers and a test page,
// rather than the generic one-input-per-key layout.
const CUSTOM_GROUPS = ['print'];
const hasPrintSettings = computed(() => settings.value.some((s) => s.group === 'print'));

const groups = computed(() => {
    const order = ['general', 'financial', 'numbers', 'features'];
    const byGroup = {};

    for (const setting of settings.value) {
        if (CUSTOM_GROUPS.includes(setting.group)) continue;
        if (setting.group === 'features' && !canManageFeatures.value) continue;
        (byGroup[setting.group] ??= []).push(setting);
    }

    return Object.keys(byGroup)
        .sort((a, b) => {
            const ai = order.indexOf(a);
            const bi = order.indexOf(b);
            return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
        })
        .map((key) => ({
            key,
            label: groupMeta[key]?.label || key,
            hint: groupMeta[key]?.hint || '',
            settings: byGroup[key],
        }));
});

const dirty = computed(() =>
    settings.value.some((s) => String(values[s.key] ?? '') !== String(original[s.key] ?? ''))
);

function hydrate(list) {
    settings.value = list;

    for (const setting of list) {
        const value = setting.type === 'bool' ? Boolean(setting.value) : (setting.value ?? '');
        values[setting.key] = value;
        original[setting.key] = value;
    }
}

function reset() {
    for (const setting of settings.value) {
        values[setting.key] = original[setting.key];
    }
    Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        hydrate(await settingsApi.index());
    } catch (e) {
        error.value = e?.response?.status === 403
            ? 'لا تملك صلاحية عرض الإعدادات'
            : 'تعذّر تحميل الإعدادات';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k]);

    try {
        // Booleans go over the wire as 1/0 so they store as "1"/"0".
        const payload = {};
        for (const setting of settings.value) {
            if (setting.group === 'features' && !canManageFeatures.value) continue;
            payload[setting.key] = setting.type === 'bool'
                ? (values[setting.key] ? 1 : 0)
                : values[setting.key];
        }

        const response = await settingsApi.update(payload);
        hydrate(response.data ?? []);

        // Sync features store
        if (canManageFeatures.value) {
            const featMap = {};
            for (const s of (response.data ?? [])) {
                if (s.group === 'features') {
                    featMap[s.key] = s.value === '1' || s.value === 1 || s.value === true;
                }
            }
            if (Object.keys(featMap).length) {
                featuresStore.setFeatures(featMap);
            }
        }

        // Print buttons cache the shop defaults; let them see the new ones.
        printPreferences.refresh();

        toast.success('تم حفظ الإعدادات.');
        justSaved.value = true;
        setTimeout(() => (justSaved.value = false), 2000);
    } catch (e) {
        const data = e?.response?.data;

        if (data?.errors) {
            for (const [field, messages] of Object.entries(data.errors)) {
                fieldErrors[field.replace(/^values\./, '')] = messages[0];
            }
            error.value = 'تحقّق من الحقول المعلّمة بالأحمر.';
        } else {
            error.value = data?.message || 'تعذّر حفظ الإعدادات';
        }
    } finally {
        saving.value = false;
    }
}

// ---------- losing unsaved changes -----------------------------------------

function onBeforeUnload(event) {
    if (!dirty.value) return;
    event.preventDefault();
    event.returnValue = '';
}

onBeforeRouteLeave(async () => {
    if (!dirty.value) return true;
    return confirm({
        title: 'تغييرات غير محفوظة',
        message: 'لديك تغييرات لم تُحفظ بعد. هل تريد مغادرتها وفقدانها؟',
        confirmLabel: 'مغادرة وفقدانها',
        cancelLabel: 'البقاء',
        tone: 'primary',
    });
});

onMounted(() => {
    load();
    window.addEventListener('beforeunload', onBeforeUnload);
});

onUnmounted(() => window.removeEventListener('beforeunload', onBeforeUnload));
</script>
