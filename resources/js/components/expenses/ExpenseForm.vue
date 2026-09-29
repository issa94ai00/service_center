<template>
    <form class="space-y-4" @submit.prevent="submit()">
        <div class="grid gap-4 sm:grid-cols-6">
            <!-- المبلغ أولاً وأكبر: هو الرقم الذي جاء المستخدم ليكتبه، وكل ما
                 عداه إمّا افتراضي جاهز أو ضغطة واحدة. -->
            <div class="sm:col-span-2">
                <Field label="المبلغ" required>
                    <div class="relative">
                        <input
                            ref="amountInput"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            placeholder="0.00"
                            class="w-full rounded-lg border px-3 py-2 pe-14 text-lg font-bold tabular-nums focus:outline-none focus:ring-2"
                            :class="errors.amount ? INVALID_FIELD : VALID_FIELD"
                        />
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">
                            {{ CURRENCY }}
                        </span>
                    </div>
                    <p v-if="errors.amount" class="mt-1 text-xs text-red-600">{{ errors.amount }}</p>
                </Field>
            </div>

            <div class="sm:col-span-2">
                <Field label="طريقة الدفع">
                    <div class="grid grid-cols-3 gap-1 rounded-lg border border-slate-300 p-0.5">
                        <button
                            v-for="method in PAYMENT_METHODS"
                            :key="method.value"
                            type="button"
                            :aria-pressed="form.payment_method === method.value"
                            class="rounded-md px-2 py-1.5 text-xs font-medium transition sm:text-sm"
                            :class="form.payment_method === method.value
                                ? 'bg-slate-900 text-white'
                                : 'text-slate-600 hover:bg-slate-50'"
                            @click="form.payment_method = method.value"
                        >
                            {{ method.label }}
                        </button>
                    </div>
                </Field>
            </div>

            <div class="sm:col-span-2">
                <Field label="تاريخ الصرف">
                    <div class="flex gap-1.5">
                        <input
                            v-model="form.expense_date"
                            type="date"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                            :class="errors.expense_date ? INVALID_FIELD : VALID_FIELD"
                        />
                        <!-- أغلب المصاريف تُسجَّل يوم صرفها أو في اليوم التالي،
                             فالزرّان يغنيان عن فتح التقويم. -->
                        <button
                            v-for="shortcut in DATE_SHORTCUTS"
                            :key="shortcut.label"
                            type="button"
                            class="shrink-0 rounded-lg border px-2.5 py-2 text-xs font-medium transition"
                            :class="form.expense_date === shortcut.value()
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                            @click="form.expense_date = shortcut.value()"
                        >
                            {{ shortcut.label }}
                        </button>
                    </div>
                    <p v-if="errors.expense_date" class="mt-1 text-xs text-red-600">{{ errors.expense_date }}</p>
                    <p v-else-if="isFutureDate" class="mt-1 text-xs text-amber-700">التاريخ في المستقبل — تأكّد أنه مقصود</p>
                </Field>
            </div>

            <!-- الأبواب أزرار لا قائمة منسدلة: باب الصرف هو ثاني ما يقرّره
                 المستخدم، وقراءته كلّه دفعة واحدة أسرع من فتح قائمة والبحث. -->
            <div class="sm:col-span-6">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <label class="block text-sm font-medium text-slate-700">
                        باب الصرف<span class="text-red-500"> *</span>
                    </label>
                    <button
                        v-if="canManageCategories"
                        type="button"
                        class="text-xs font-medium text-blue-600 underline-offset-2 hover:underline"
                        @click="$emit('manage-categories')"
                    >
                        إدارة الأبواب
                    </button>
                </div>

                <div v-if="!categories.length" class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-center text-sm text-slate-500">
                    لا توجد أبواب صرف بعد.
                    <button
                        v-if="canManageCategories"
                        type="button"
                        class="font-medium text-blue-600 underline-offset-2 hover:underline"
                        @click="$emit('manage-categories')"
                    >
                        أضف أول باب
                    </button>
                </div>

                <div v-else class="flex flex-wrap gap-1.5">
                    <button
                        v-for="category in visibleCategories"
                        :key="category.id"
                        type="button"
                        :aria-pressed="form.expense_category_id === category.id"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition sm:text-sm"
                        :class="form.expense_category_id === category.id
                            ? 'border-slate-900 bg-slate-900 text-white'
                            : errors.expense_category_id
                                ? 'border-red-300 text-red-700 hover:bg-red-50'
                                : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                        @click="pickCategory(category.id)"
                    >
                        {{ category.name }}
                    </button>
                    <!-- الأبواب النادرة تبقى خلف زرّ واحد بدل أن تدفع المستعملة
                         يومياً إلى السطر الثاني. -->
                    <button
                        v-if="hiddenCategories.length && !showAllCategories"
                        type="button"
                        class="rounded-full border border-dashed border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-50"
                        @click="showAllCategories = true"
                    >
                        + {{ hiddenCategories.length }} باب آخر
                    </button>
                </div>
                <p v-if="errors.expense_category_id" class="mt-1 text-xs text-red-600">{{ errors.expense_category_id }}</p>
            </div>

            <div class="sm:col-span-3">
                <Field label="المستفيد">
                    <input
                        v-model.trim="form.payee"
                        type="text"
                        maxlength="150"
                        list="expense-payee-suggestions"
                        placeholder="شركة الكهرباء، صاحب المحل، السائق..."
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="errors.payee ? INVALID_FIELD : VALID_FIELD"
                    />
                    <datalist id="expense-payee-suggestions">
                        <option v-for="name in payeeSuggestions" :key="name" :value="name" />
                    </datalist>
                    <p v-if="errors.payee" class="mt-1 text-xs text-red-600">{{ errors.payee }}</p>
                </Field>
            </div>

            <div class="sm:col-span-3">
                <Field label="البيان">
                    <input
                        v-model.trim="form.notes"
                        type="text"
                        maxlength="2000"
                        placeholder="تفصيل يُسأل عنه لاحقاً — رقم الفاتورة، الشهر المدفوع..."
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="errors.notes ? INVALID_FIELD : VALID_FIELD"
                    />
                    <p v-if="errors.notes" class="mt-1 text-xs text-red-600">{{ errors.notes }}</p>
                </Field>
            </div>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
            <p class="text-xs" :class="blockedReason ? 'text-slate-400' : 'text-emerald-700'">
                {{ blockedReason || summaryLine }}
            </p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="isEdit"
                    type="button"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="$emit('close')"
                >
                    إلغاء
                </button>
                <!-- تسجيل مصروف نادراً ما يكون واحداً: فواتير آخر الشهر تُدخَل
                     دفعة واحدة، فهذا الزرّ يُبقي الشاشة مفتوحة والباب محفوظاً. -->
                <button
                    v-else
                    type="button"
                    :disabled="saving || Boolean(blockedReason)"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                    @click="submit(true)"
                >
                    حفظ وتسجيل آخر
                </button>
                <button
                    type="submit"
                    :disabled="saving || Boolean(blockedReason)"
                    class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ saving ? 'جارٍ الحفظ...' : (isEdit ? 'حفظ التعديل' : 'تسجيل المصروف') }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import Field from '../ui/FormField.vue';
import { expensesApi } from '../../services/api';
import { PAYMENT_METHODS, paymentMethodLabel } from '../../constants/paymentMethods';
import { apiFieldErrors, apiMessage, formatMoney, isoDate, toLatinDigits } from '../../utils/format';

const props = defineProps({
    /** سند قائم يُصحَّح، أو null لتسجيل جديد. */
    expense: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    /** أسماء مستفيدين سبق صرفها، لإكمال ما يُكتب. */
    payeeSuggestions: { type: Array, default: () => [] },
    canManageCategories: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
});

const emit = defineEmits(['saved', 'close', 'manage-categories']);

const CURRENCY = 'ل.س';
const VALID_FIELD = 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
const INVALID_FIELD = 'border-red-300 focus:border-red-500 focus:ring-red-100';
/** كم باباً يبقى ظاهراً قبل أن يُطوى الباقي خلف زرّ. */
const VISIBLE_CATEGORIES = 8;

const DATE_SHORTCUTS = [
    { label: 'اليوم', value: () => isoDate() },
    { label: 'أمس', value: () => isoDate(new Date(Date.now() - 86400000)) },
];

const isEdit = computed(() => Boolean(props.expense?.id));

const amountInput = ref(null);
const saving = ref(false);
const error = ref('');
const errors = ref({});
const showAllCategories = ref(false);

const form = reactive({
    expense_category_id: props.expense?.expense_category_id ?? '',
    amount: props.expense?.amount ?? '',
    payment_method: props.expense?.payment_method ?? 'CASH',
    expense_date: props.expense?.expense_date ?? isoDate(),
    payee: props.expense?.payee ?? '',
    notes: props.expense?.notes ?? '',
});

/**
 * الأبواب تصل مرتّبة بالأكثر استعمالاً. الباب المختار يبقى ظاهراً دائماً حتى
 * لو كان نادراً — وإلّا اختفى اختيار المستخدم من تحت عينه عند التعديل.
 */
const visibleCategories = computed(() => {
    if (showAllCategories.value) return props.categories;

    const head = props.categories.slice(0, VISIBLE_CATEGORIES);
    const picked = props.categories.find((category) => category.id === form.expense_category_id);

    return picked && !head.some((category) => category.id === picked.id)
        ? [...head, picked]
        : head;
});

const hiddenCategories = computed(() =>
    props.categories.filter((category) => !visibleCategories.value.some((shown) => shown.id === category.id))
);

const amountCents = computed(() => Math.round((Number(toLatinDigits(form.amount)) || 0) * 100));

const isFutureDate = computed(() => Boolean(form.expense_date) && form.expense_date > isoDate());

const blockedReason = computed(() => {
    if (amountCents.value <= 0) return 'أدخل مبلغاً أكبر من صفر';
    if (!form.expense_category_id) return 'اختر باب الصرف';
    if (!form.expense_date) return 'أدخل تاريخ الصرف';
    return '';
});

const summaryLine = computed(() => {
    const category = props.categories.find((item) => item.id === form.expense_category_id);

    return `سيُسجَّل ${formatMoney(amountCents.value / 100)} ${CURRENCY} · ${category?.name ?? ''} · ${paymentMethodLabel(form.payment_method)}`;
});

// باب عُطِّل بينما الشاشة مفتوحة يسقط من القائمة؛ اختياره لا يجوز أن يبقى
// معلّقاً على رقم لم يعد معروضاً.
watch(() => props.categories, (list) => {
    if (form.expense_category_id && !list.some((category) => category.id === form.expense_category_id)) {
        form.expense_category_id = '';
    }
});

function pickCategory(id) {
    form.expense_category_id = id;
    errors.value = { ...errors.value, expense_category_id: undefined };
}

function payload() {
    return {
        expense_category_id: Number(form.expense_category_id),
        amount: (amountCents.value / 100).toFixed(2),
        payment_method: form.payment_method,
        expense_date: form.expense_date,
        payee: form.payee || null,
        notes: form.notes || null,
    };
}

/**
 * @param {boolean} again  يُبقي الشاشة مفتوحة للسند التالي بدل إغلاقها.
 */
async function submit(again = false) {
    error.value = '';
    errors.value = {};

    if (blockedReason.value) {
        error.value = blockedReason.value;
        if (amountCents.value <= 0) amountInput.value?.focus();
        return;
    }

    saving.value = true;
    try {
        const data = isEdit.value
            ? await expensesApi.update(props.expense.id, payload())
            : await expensesApi.store(payload());

        emit('saved', { expense: data.data, message: data.message, again });

        if (!isEdit.value && again) {
            // الباب والطريقة والتاريخ تبقى: من يُدخل فواتير الشهر يُدخلها
            // تحت الباب نفسه وفي الجلسة نفسها.
            form.amount = '';
            form.payee = '';
            form.notes = '';
            await nextTick();
            amountInput.value?.focus();
        }
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر حفظ المصروف');
        errors.value = apiFieldErrors(e);
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    if (props.autofocus) amountInput.value?.focus();
});

defineExpose({ focus: () => amountInput.value?.focus() });
</script>
