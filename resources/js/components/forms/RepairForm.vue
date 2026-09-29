<template>
    <form class="space-y-6" @submit.prevent="submit">
        <!-- 1. Who -->
        <section class="space-y-3">
            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                <span class="text-sm">👤</span> العميل
            </h3>
            <Field label="العميل" required>
                <CustomerSelect
                    v-model="form.customer_id"
                    :customer="repair?.customer ?? null"
                />
                <p v-if="errors.customer_id" class="mt-1 text-xs text-red-600">{{ errors.customer_id }}</p>
            </Field>
        </section>

        <!-- 2. What -->
        <section class="space-y-3">
            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                <span class="text-sm">📱</span> الجهاز
            </h3>

            <Field label="نوع الجهاز" required>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="type in DEVICE_TYPES"
                        :key="type"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition"
                        :class="form.device_type === type
                            ? 'border-blue-600 bg-blue-600 text-white'
                            : 'border-slate-300 bg-white text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                        @click="form.device_type = type"
                    >
                        {{ type }}
                    </button>
                    <button
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition"
                        :class="customType
                            ? 'border-blue-600 bg-blue-600 text-white'
                            : 'border-dashed border-slate-300 bg-white text-slate-500 hover:border-blue-400 hover:text-blue-600'"
                        @click="startCustomType"
                    >
                        نوع آخر…
                    </button>
                </div>
                <input
                    v-if="customType"
                    ref="customTypeInput"
                    v-model.trim="form.device_type"
                    type="text"
                    maxlength="60"
                    placeholder="اكتب نوع الجهاز"
                    class="mt-2 w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                    :class="fieldClass('device_type')"
                />
                <p v-if="errors.device_type" class="mt-1 text-xs text-red-600">{{ errors.device_type }}</p>
            </Field>

            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="الشركة المصنعة">
                    <BrandSelect v-model="form.brand" :options="brands" />
                </Field>
                <Field label="الموديل">
                    <input
                        v-model.trim="form.model"
                        type="text"
                        maxlength="100"
                        placeholder="Galaxy A55"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('model')"
                    />
                </Field>
                <Field label="اللون">
                    <input
                        v-model.trim="form.color"
                        type="text"
                        maxlength="40"
                        placeholder="أسود"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('color')"
                    />
                </Field>
                <Field label="السيريال / IMEI">
                    <input
                        v-model="form.serial_number"
                        type="text"
                        dir="ltr"
                        maxlength="80"
                        class="w-full rounded-lg border px-3 py-2 text-left text-sm tabular-nums focus:outline-none focus:ring-2"
                        :class="fieldClass('serial_number')"
                        @blur="form.serial_number = toLatinDigits(form.serial_number).trim()"
                    />
                    <p v-if="errors.serial_number" class="mt-1 text-xs text-red-600">{{ errors.serial_number }}</p>
                </Field>
            </div>

            <Field label="المرفقات المستلمة مع الجهاز">
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="item in ACCESSORIES"
                        :key="item"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition"
                        :class="hasAccessory(item)
                            ? 'border-blue-600 bg-blue-50 text-blue-700'
                            : 'border-slate-300 bg-white text-slate-600 hover:border-blue-400'"
                        @click="toggleAccessory(item)"
                    >
                        {{ hasAccessory(item) ? '✓ ' : '' }}{{ item }}
                    </button>
                </div>
                <input
                    v-model.trim="form.accessories"
                    type="text"
                    maxlength="500"
                    placeholder="أو اكتبها يدوياً: شاحن، كارت، غطاء..."
                    class="mt-2 w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                    :class="fieldClass('accessories')"
                />
            </Field>
        </section>

        <!-- 3. Why -->
        <section class="space-y-3">
            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                <span class="text-sm">🔧</span> العطل
            </h3>

            <Field label="العطل المذكور من العميل" required>
                <div class="mb-2 flex flex-wrap gap-1.5">
                    <button
                        v-for="issue in COMMON_ISSUES"
                        :key="issue"
                        type="button"
                        class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-600 transition hover:border-blue-400 hover:text-blue-600"
                        @click="appendIssue(issue)"
                    >
                        + {{ issue }}
                    </button>
                </div>
                <textarea
                    v-model.trim="form.reported_issue"
                    rows="3"
                    required
                    maxlength="1000"
                    placeholder="صف العطل كما ذكره العميل"
                    class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                    :class="fieldClass('reported_issue')"
                ></textarea>
                <p v-if="errors.reported_issue" class="mt-1 text-xs text-red-600">{{ errors.reported_issue }}</p>
            </Field>

            <Field label="ملاحظات داخلية (لا تظهر للعميل على الإيصال)">
                <textarea
                    v-model.trim="form.notes"
                    rows="2"
                    maxlength="1000"
                    class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                    :class="fieldClass('notes')"
                ></textarea>
            </Field>
        </section>

        <!-- 4. Where it stands -->
        <section class="space-y-3">
            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                <span class="text-sm">📋</span> الحالة والتكلفة
            </h3>

            <Field label="خبير الصيانة">
                <select v-model="form.technician_id" class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2" :class="fieldClass('technician_id')">
                    <option :value="null">بدون إسناد</option>
                    <option v-for="t in technicians" :key="t.id" :value="t.id">
                        {{ t.name }}{{ t.specialty ? ` — ${t.specialty}` : '' }}
                    </option>
                </select>
                <!-- نصيب الخبير تقدير حتى التسوية: يُحسب على التكلفة النهائية، لا المقدَّرة. -->
                <p v-if="selectedTechnician" class="mt-1 text-xs text-slate-500">
                    نسبته {{ formatPercent(selectedTechnician.commission_rate / 100) }} —
                    نصيبه من التكلفة المقدرة {{ formatMoney(estimatedCommission) }} (يُحتسب نهائياً عند التسوية).
                </p>
                <p v-if="errors.technician_id" class="mt-1 text-xs text-red-600">{{ errors.technician_id }}</p>
            </Field>

            <Field label="حالة الجهاز">
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="s in statusChoices"
                        :key="s.value"
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                        :class="form.status === s.value
                            ? 'border-transparent ring-1 ' + s.tone
                            : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400'"
                        @click="form.status = s.value"
                    >
                        {{ s.icon }} {{ s.label }}
                    </button>
                </div>
            </Field>

            <div class="grid gap-4 sm:grid-cols-2">
                <Field label="التكلفة المقدرة">
                    <div class="relative">
                        <input
                            v-model="form.estimated_cost"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full rounded-lg border px-3 py-2 pe-14 text-sm tabular-nums focus:outline-none focus:ring-2"
                            :class="fieldClass('estimated_cost')"
                        />
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">{{ currency }}</span>
                    </div>
                    <p v-if="errors.estimated_cost" class="mt-1 text-xs text-red-600">{{ errors.estimated_cost }}</p>
                    <p v-else class="mt-1 text-xs text-slate-400">تقدير مبدئي يظهر على إيصال الاستلام</p>
                </Field>

                <!-- The price agreed once the fault is known. It is what the
                     settlement dialog opens with, so it earns its place next to
                     the estimate rather than hiding inside that dialog. -->
                <Field v-if="editing" label="التكلفة النهائية">
                    <div class="relative">
                        <input
                            v-model="form.final_cost"
                            type="number"
                            step="0.01"
                            min="0"
                            :placeholder="form.estimated_cost || '0.00'"
                            class="w-full rounded-lg border px-3 py-2 pe-14 text-sm tabular-nums focus:outline-none focus:ring-2"
                            :class="fieldClass('final_cost')"
                        />
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">{{ currency }}</span>
                    </div>
                    <p v-if="errors.final_cost" class="mt-1 text-xs text-red-600">{{ errors.final_cost }}</p>
                    <p v-else class="mt-1 text-xs text-slate-400">السعر المتفق عليه بعد الفحص — يُستخدم عند التسوية</p>
                </Field>

                <Field label="تاريخ الاستلام">
                    <input
                        v-model="form.received_at"
                        type="datetime-local"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('received_at')"
                    />
                    <p v-if="errors.received_at" class="mt-1 text-xs text-red-600">{{ errors.received_at }}</p>
                </Field>

                <!-- Only when it means something: the previous form showed this
                     on every edit and then silently discarded whatever was typed
                     unless the status happened to be "delivered". -->
                <Field v-if="isDelivered" label="تاريخ التسليم" class="sm:col-span-2">
                    <input
                        v-model="form.delivered_at"
                        type="datetime-local"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('delivered_at')"
                    />
                    <p v-if="errors.delivered_at" class="mt-1 text-xs text-red-600">{{ errors.delivered_at }}</p>
                    <p v-else class="mt-1 text-xs text-slate-400">يُسجَّل وقت الآن تلقائياً إذا تُرك فارغاً</p>
                </Field>
            </div>
        </section>

        <!-- 5. Money on the counter -->
        <section v-if="!editing && canSettle" class="space-y-3">
            <h3 class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                <span class="text-sm">💵</span> العربون (اختياري)
            </h3>

            <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                <!-- Where this customer already stands. A deposit is agreed in
                     the light of the old bill, not in a vacuum — and the clerk
                     should not have to leave the form to find it. -->
                <div
                    v-if="account"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border px-3 py-2 text-xs"
                    :class="accountTone"
                >
                    <span class="font-medium">{{ accountLabel }}</span>
                    <span v-if="account.open_repairs > 0" class="opacity-75">
                        · {{ openRepairsLabel }}
                    </span>
                    <button
                        v-if="priorBalance > 0"
                        type="button"
                        class="ms-auto rounded-full border border-current/30 px-2 py-0.5 font-medium transition hover:bg-white/60"
                        @click="fillFromBalance"
                    >تحصيل الرصيد أيضاً</button>
                </div>
                <p v-else-if="accountLoading" class="h-8 animate-pulse rounded-lg bg-slate-100"></p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="المبلغ المقبوض الآن">
                        <div class="relative">
                            <input
                                v-model="form.deposit_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                inputmode="decimal"
                                placeholder="0.00"
                                class="w-full rounded-lg border bg-white px-3 py-2 pe-14 text-sm font-semibold tabular-nums focus:outline-none focus:ring-2"
                                :class="fieldClass('deposit_amount')"
                            />
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">{{ currency }}</span>
                        </div>
                        <!-- The amounts a counter actually takes: half up
                             front, or the whole quote. Typing them out on a
                             phone keypad is the slow part of the intake. -->
                        <div v-if="estimate > 0" class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="preset in DEPOSIT_PRESETS"
                                :key="preset.label"
                                type="button"
                                class="rounded-full border px-3 py-1 text-xs transition"
                                :class="depositMatches(preset.ratio)
                                    ? 'border-blue-600 bg-blue-50 font-medium text-blue-700'
                                    : 'border-slate-300 bg-white text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                                @click="setDeposit(preset.ratio)"
                            >{{ preset.label }} ({{ formatMoney(estimate * preset.ratio) }})</button>
                            <button
                                v-if="form.deposit_amount !== ''"
                                type="button"
                                class="rounded-full px-3 py-1 text-xs text-slate-500 transition hover:text-red-600"
                                @click="form.deposit_amount = ''"
                            >مسح</button>
                        </div>
                        <p v-if="errors.deposit_amount" class="mt-1 text-xs text-red-600">{{ errors.deposit_amount }}</p>
                    </Field>

                    <MethodPicker v-if="depositAmount > 0" v-model="form.deposit_method" />
                </div>

                <!-- Says what the money will do before it is taken, so nobody
                     has to trust that the deposit landed somewhere sensible. -->
                <div v-if="depositAmount > 0" class="space-y-1.5 rounded-lg bg-white px-3 py-2 text-xs leading-relaxed text-slate-600">
                    <p>
                        يُسجَّل سند قبض بـ <strong class="tabular-nums text-slate-800">{{ formatMoney(depositAmount) }} {{ currency }}</strong>
                        ({{ methodLabel(form.deposit_method) }}) على حساب العميل، ويمكنك طباعته فور الاستلام.
                    </p>
                    <p v-if="estimate > 0">
                        <template v-if="estimateRemaining > 0">
                            يبقى تقديرياً <strong class="tabular-nums text-slate-800">{{ formatMoney(estimateRemaining) }} {{ currency }}</strong>
                            على العميل عند التسليم.
                        </template>
                        <template v-else-if="estimateRemaining < 0">
                            العربون أكبر من التقدير بـ <strong class="tabular-nums text-blue-700">{{ formatMoney(-estimateRemaining) }} {{ currency }}</strong>،
                            يبقى رصيداً للعميل على حسابه.
                        </template>
                        <template v-else>يغطي العربون كامل التقدير.</template>
                    </p>
                    <p v-else class="text-slate-500">
                        لا يوجد تقدير للتكلفة بعد، فيبقى المبلغ رصيداً للعميل حتى تُحتسب التسوية.
                    </p>
                    <!-- The number the customer will ask about at hand-over is
                         their whole account, not this device on its own. -->
                    <p v-if="hasPriorBalance" class="border-t border-slate-100 pt-1.5">
                        وبعد تسليم هذا الجهاز يصبح إجمالي حساب العميل تقديرياً
                        <strong class="tabular-nums" :class="netAfter > 0 ? 'text-amber-700' : 'text-blue-700'">
                            {{ formatMoney(Math.abs(netAfter)) }} {{ currency }}
                        </strong>
                        <span>{{ netAfter > 0 ? ' عليه' : netAfter < 0 ? ' له' : '' }}</span>.
                    </p>
                </div>
            </div>
        </section>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="sticky bottom-0 -mx-5 -mb-5 flex items-center justify-between gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:-mx-6 sm:-mb-5 sm:px-6">
            <p class="hidden text-xs text-slate-400 sm:block">Ctrl + Enter للحفظ</p>
            <div class="flex flex-1 gap-3 sm:flex-none">
                <button
                    type="button"
                    class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 sm:flex-none"
                    @click="$emit('close')"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="loading"
                    class="flex-1 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60 sm:flex-none"
                >
                    {{ loading ? 'جارٍ الحفظ...' : editing ? 'حفظ التعديلات' : 'استلام الجهاز' }}
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Field from '../ui/FormField.vue';
import CustomerSelect from './CustomerSelect.vue';
import { techniciansApi } from '../../services/api';
import BrandSelect from './BrandSelect.vue';
import MethodPicker from '../repairs/PaymentMethodPicker.vue';
import { repairsApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { paymentMethodLabel } from '../../constants/paymentMethods';
import { REPAIR_STATUSES } from '../../constants/repairStatus';
import { apiFieldErrors, apiMessage, formatMoney, formatPercent, nowLocalInput, toDateTimeInput, toLatinDigits } from '../../utils/format';

const props = defineProps({
    repair: { type: Object, default: null },
    currency: { type: String, default: 'ل.س' },
});

const emit = defineEmits(['close', 'saved']);

const DEVICE_TYPES = ['جوال', 'تابلت', 'لابتوب', 'ساعة ذكية', 'سماعة'];
const ACCESSORIES = ['شاحن', 'كارت الذاكرة', 'شريحة', 'غطاء', 'سماعة', 'علبة'];
const COMMON_ISSUES = ['شاشة مكسورة', 'لا يشحن', 'بطارية تنفد بسرعة', 'لا يعمل نهائياً', 'مشكلة صوت', 'دخول ماء', 'كاميرا لا تعمل', 'يعلّق ويعيد التشغيل'];
const DEPOSIT_PRESETS = [
    { label: 'نصف التقدير', ratio: 0.5 },
    { label: 'كامل التقدير', ratio: 1 },
];

const { can } = usePermissions();

const loading = ref(false);
const error = ref('');
const errors = ref({});
const brands = ref([]);
const customType = ref(false);
const customTypeInput = ref(null);
const account = ref(null);
const accountLoading = ref(false);

const technicians = ref([]);

const form = reactive({
    customer_id: props.repair?.customer_id ?? null,
    technician_id: props.repair?.technician_id ?? null,
    device_type: props.repair?.device_type ?? 'جوال',
    brand: props.repair?.brand ?? '',
    model: props.repair?.model ?? '',
    color: props.repair?.color ?? '',
    serial_number: props.repair?.serial_number ?? '',
    accessories: props.repair?.accessories ?? '',
    reported_issue: props.repair?.reported_issue ?? '',
    notes: props.repair?.notes ?? '',
    estimated_cost: props.repair?.estimated_cost ?? '',
    final_cost: props.repair?.final_cost ?? '',
    status: props.repair?.status ?? 'RECEIVED',
    received_at: props.repair ? toDateTimeInput(props.repair.received_at) : nowLocalInput(),
    delivered_at: props.repair ? toDateTimeInput(props.repair.delivered_at) : '',
    // Intake only: money handed over at the counter, booked in the same request
    // that registers the device.
    deposit_amount: '',
    deposit_method: 'CASH',
});

customType.value = !DEVICE_TYPES.includes(form.device_type);

const editing = computed(() => Boolean(props.repair));

// A device being received cannot already be delivered or cancelled, so those
// two only appear once the record exists.
const statusChoices = computed(() =>
    editing.value ? REPAIR_STATUSES : REPAIR_STATUSES.filter((s) => s.open)
);
const isDelivered = computed(() => form.status === 'DELIVERED');

// Taking money is its own grant: a clerk who may only register devices sees no
// deposit field at all rather than one that fails on submit.
const canSettle = computed(() => can('repairs.settle'));
const currencyLabel = computed(() => account.value?.currency || props.currency);
const estimate = computed(() => Number(form.estimated_cost) || 0);

const selectedTechnician = computed(
    () => technicians.value.find((t) => t.id === form.technician_id) ?? null
);

const estimatedCommission = computed(() =>
    selectedTechnician.value ? (estimate.value * Number(selectedTechnician.value.commission_rate || 0)) / 100 : 0
);

/**
 * قائمة الخبراء اختيارية: من لا يملك صلاحية عرضهم يسجّل الجهاز كما كان، بلا
 * إسناد وبلا رسالة خطأ.
 */
async function loadTechnicians() {
    try {
        technicians.value = await techniciansApi.options();
    } catch {
        technicians.value = [];
    }
}
const depositAmount = computed(() => Number(form.deposit_amount) || 0);
const estimateRemaining = computed(() => estimate.value - depositAmount.value);

/** Positive: the customer owes the shop. Negative: the shop holds their money. */
const priorBalance = computed(() => Number(account.value?.balance ?? 0));
const hasPriorBalance = computed(() => Math.abs(priorBalance.value) >= 0.005);
/** Where the account lands once this deposit is booked against this estimate. */
const netAfter = computed(() => round(priorBalance.value + estimate.value - depositAmount.value));

const accountLabel = computed(() => {
    const balance = priorBalance.value;
    if (!hasPriorBalance.value) return 'حساب العميل مسوّى';
    return balance > 0
        ? `على العميل ${formatMoney(balance)} ${currencyLabel.value} من حساب سابق`
        : `رصيد للعميل ${formatMoney(-balance)} ${currencyLabel.value}`;
});

const accountTone = computed(() => {
    if (!hasPriorBalance.value) return 'border-slate-200 bg-white text-slate-500';
    return priorBalance.value > 0
        ? 'border-amber-200 bg-amber-50 text-amber-800'
        : 'border-blue-200 bg-blue-50 text-blue-800';
});

const openRepairsLabel = computed(() => {
    const count = Number(account.value?.open_repairs ?? 0);
    const due = Number(account.value?.open_due ?? 0);
    const devices = count === 1 ? 'جهاز آخر قيد العمل' : `${count} أجهزة أخرى قيد العمل`;

    return due > 0 ? `${devices}، متبقٍ عليها ${formatMoney(due)}` : devices;
});

function round(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function methodLabel(value) {
    return paymentMethodLabel(value);
}

function setDeposit(ratio) {
    if (estimate.value <= 0) return;
    form.deposit_amount = String(round(estimate.value * ratio));
}

/** Whether the amount typed is already exactly what this preset would set. */
function depositMatches(ratio) {
    return estimate.value > 0
        && form.deposit_amount !== ''
        && depositAmount.value === round(estimate.value * ratio);
}

/**
 * "While they are here, take the old bill too": adds what the customer already
 * owes to the deposit, so the whole account is cleared in one receipt rather
 * than chased later.
 */
function fillFromBalance() {
    form.deposit_amount = String(round(Math.max(0, priorBalance.value) + depositAmount.value));
}

/**
 * The customer's standing account, fetched when the picker settles on someone.
 * Failure is silent: it is context for the deposit, never a blocker on
 * registering a device.
 */
async function loadAccount(customerId) {
    if (!customerId || !canSettle.value || editing.value) {
        account.value = null;
        return;
    }

    accountLoading.value = true;
    try {
        const data = await repairsApi.customerAccount(customerId);
        // A slow response for a customer the clerk has already moved off must
        // not paint stale money onto the form.
        if (form.customer_id === customerId) account.value = data;
    } catch {
        account.value = null;
    } finally {
        accountLoading.value = false;
    }
}

watch(() => form.customer_id, (id) => {
    account.value = null;
    loadAccount(id);
});

function fieldClass(field) {
    return errors.value[field]
        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
}

function startCustomType() {
    customType.value = true;
    if (DEVICE_TYPES.includes(form.device_type)) form.device_type = '';
    nextTick(() => customTypeInput.value?.focus());
}

function accessoryList() {
    return form.accessories ? form.accessories.split('،').map((s) => s.trim()).filter(Boolean) : [];
}

function hasAccessory(item) {
    return accessoryList().includes(item);
}

function toggleAccessory(item) {
    const list = accessoryList();
    const index = list.indexOf(item);
    if (index === -1) list.push(item);
    else list.splice(index, 1);
    form.accessories = list.join('، ');
}

function appendIssue(issue) {
    const current = form.reported_issue.trim();
    if (current.includes(issue)) return;
    form.reported_issue = current ? `${current}، ${issue}` : issue;
}

function validate() {
    const found = {};
    if (!form.customer_id) found.customer_id = 'اختر العميل أو أنشئ عميلاً جديداً';
    if (!form.device_type?.trim()) found.device_type = 'نوع الجهاز مطلوب';
    if (!form.reported_issue?.trim()) found.reported_issue = 'وصف العطل مطلوب';
    if (form.delivered_at && form.received_at && form.delivered_at < form.received_at) {
        found.delivered_at = 'تاريخ التسليم قبل تاريخ الاستلام';
    }
    errors.value = found;
    return Object.keys(found).length === 0;
}

async function submit() {
    if (loading.value) return;

    error.value = '';
    if (!validate()) {
        error.value = 'راجع الحقول المُعلَّمة بالأحمر.';
        return;
    }

    loading.value = true;
    try {
        const payload = {
            ...form,
            device_type: form.device_type.trim(),
            estimated_cost: form.estimated_cost === '' ? null : form.estimated_cost,
            brand: form.brand || null,
            model: form.model || null,
            color: form.color || null,
            serial_number: form.serial_number || null,
            accessories: form.accessories || null,
            notes: form.notes || null,
            final_cost: form.final_cost === '' ? null : form.final_cost,
            delivered_at: isDelivered.value ? form.delivered_at || null : null,
        };

        // Editing never carries a deposit: money added later goes through the
        // settlement dialog, where it can see the invoice it belongs to.
        if (props.repair || !canSettle.value || depositAmount.value <= 0) {
            delete payload.deposit_amount;
            delete payload.deposit_method;
        }

        const body = props.repair
            ? await repairsApi.update(props.repair.id, payload)
            : await repairsApi.store(payload);

        // On a create the caller takes over the dialog to offer the receipts;
        // an edit has nothing to print, so it just closes.
        emit('saved', body.data, {
            created: !props.repair,
            paymentId: body.payment_id ?? null,
        });

        if (props.repair) emit('close');
    } catch (e) {
        errors.value = apiFieldErrors(e);
        error.value = apiMessage(e, 'تعذّر الحفظ');
    } finally {
        loading.value = false;
    }
}

function onKeydown(event) {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        submit();
    }
}

onMounted(async () => {
    window.addEventListener('keydown', onKeydown);
    loadTechnicians();

    try {
        brands.value = await repairsApi.brandOptions();
    } catch {
        // The brand field stays free-text; suggestions are a convenience.
    }
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>
