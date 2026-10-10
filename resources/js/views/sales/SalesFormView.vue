<template>
    <form class="mx-auto max-w-5xl space-y-5 pb-40 sm:pb-28" @submit.prevent="submit">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ isEdit ? 'تعديل فاتورة بيع' : 'فاتورة بيع جديدة' }}</h1>
                <p class="text-sm text-slate-500">مسودة — تُرقّم وتُعتمد عند الترحيل</p>
            </div>
            <button type="button" class="text-sm text-slate-500 transition hover:text-slate-900" @click="$router.back()">
                → عودة
            </button>
        </div>

        <!-- ------------------------------------------------------- the header -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <Field label="العميل" required>
                    <CustomerSelect
                        ref="customerSelect"
                        v-model="form.customer_id"
                        :fetcher="salesApi.customerOptions"
                        :customer="loadedCustomer"
                        :invalid="Boolean(errors.customer_id)"
                        recent-label="آخر العملاء الذين اشتروا"
                        @change="onCustomerChange"
                    />
                    <p v-if="errors.customer_id" class="mt-1 text-xs text-red-600">{{ errors.customer_id }}</p>
                </Field>
                <Field label="تاريخ الفاتورة">
                    <input
                        v-model="form.invoice_date"
                        type="date"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('invoice_date')"
                    />
                </Field>
                <Field label="تاريخ الاستحقاق">
                    <input
                        v-model="form.due_date"
                        type="date"
                        :min="form.invoice_date || undefined"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('due_date')"
                    />
                </Field>
            </div>
        </div>

        <!-- -------------------------------------------------------- the lines -->
        <div class="rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-slate-900">
                        البنود
                        <span v-if="form.items.length" class="text-sm font-normal text-slate-400">
                            ({{ form.items.length }})
                        </span>
                    </h2>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="addBlankLine('SERVICE')"
                        >
                            ＋ سطر خدمة
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="addBlankLine('MATERIAL')"
                        >
                            ＋ سطر مادة
                        </button>
                    </div>
                </div>

                <!-- المسح أوّل ما تقع عليه العين: هذا هو مسار العمل الطبيعي
                     عند الصندوق، وإضافة سطر يدوي هي الاستثناء. -->
                <ScanBox ref="scanBox" @pick="addFromPick" />
            </div>

            <EmptyState
                v-if="!form.items.length"
                icon="🧾"
                title="لا توجد بنود بعد"
                hint="امسح باركود المادة، أو ابحث بالاسم في الحقل أعلاه"
            />

            <div v-else>
                <!-- جدول على الشاشة الواسعة -->
                <table class="hidden w-full text-right text-sm md:table">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="w-16 px-3 py-2">النوع</th>
                            <th class="px-3 py-2">العنصر</th>
                            <th class="w-24 px-3 py-2">الكمية</th>
                            <th class="w-28 px-3 py-2">سعر الوحدة ({{ BASE_SYMBOL }})</th>
                            <th class="w-24 px-3 py-2">الخصم ({{ BASE_SYMBOL }})</th>
                            <th class="w-32 px-3 py-2 text-left">الإجمالي</th>
                            <th class="w-10 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="(line, i) in form.items"
                            :key="line.key"
                            :class="line.key === flashed ? 'bg-emerald-50 transition-colors duration-1000' : ''"
                        >
                            <td class="px-3 py-2 align-top">
                                <span
                                    class="rounded px-1.5 py-0.5 text-[11px] font-semibold"
                                    :class="line.line_type === 'SERVICE' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'"
                                >
                                    {{ line.line_type === 'SERVICE' ? 'خدمة' : 'مادة' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <select
                                    v-if="line.line_type === 'SERVICE'"
                                    v-model="line.service_id"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
                                    @change="onServiceChange(line)"
                                >
                                    <option value="" disabled>اختر الخدمة...</option>
                                    <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <select
                                    v-else
                                    v-model="line.material_id"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
                                    @change="onMaterialChange(line)"
                                >
                                    <option value="" disabled>اختر المادة...</option>
                                    <option v-for="m in materials" :key="m.id" :value="m.id">
                                        {{ m.name }} ({{ m.sku }})
                                    </option>
                                </select>
                                <p v-if="stockWarning(line)" class="mt-1 text-xs text-red-600">{{ stockWarning(line) }}</p>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <input
                                    v-model="line.quantity"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    required
                                    class="w-full rounded-lg border px-2 py-1.5 text-sm tabular-nums focus:outline-none"
                                    :class="stockWarning(line) ? 'border-red-300' : 'border-slate-300 focus:border-blue-500'"
                                />
                            </td>
                            <td class="px-3 py-2 align-top">
                                <input
                                    v-model="line.unit_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums focus:border-blue-500 focus:outline-none"
                                />
                            </td>
                            <td class="px-3 py-2 align-top">
                                <input
                                    v-model="line.discount_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums focus:border-blue-500 focus:outline-none"
                                />
                            </td>
                            <td class="px-3 py-2 text-left align-top font-semibold tabular-nums">
                                {{ formatUsd(lineTotal(line)) }}
                            </td>
                            <td class="px-3 py-2 text-left align-top">
                                <button
                                    type="button"
                                    :aria-label="`حذف السطر ${i + 1}`"
                                    class="text-red-500 transition hover:text-red-700"
                                    @click="removeLine(i)"
                                >
                                    ✕
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- بطاقات على الهاتف: جدول بسبعة أعمدة لا يُقرأ على ٤٠٠ بكسل -->
                <ul class="divide-y divide-slate-100 md:hidden">
                    <li
                        v-for="(line, i) in form.items"
                        :key="line.key"
                        class="space-y-2 p-4"
                        :class="line.key === flashed ? 'bg-emerald-50' : ''"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span
                                class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-semibold"
                                :class="line.line_type === 'SERVICE' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ line.line_type === 'SERVICE' ? 'خدمة' : 'مادة' }}
                            </span>
                            <button
                                type="button"
                                :aria-label="`حذف السطر ${i + 1}`"
                                class="-m-2 flex h-10 w-10 shrink-0 items-center justify-center text-red-500"
                                @click="removeLine(i)"
                            >
                                ✕
                            </button>
                        </div>

                        <select
                            v-if="line.line_type === 'SERVICE'"
                            v-model="line.service_id"
                            required
                            class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm"
                            @change="onServiceChange(line)"
                        >
                            <option value="" disabled>اختر الخدمة...</option>
                            <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <select
                            v-else
                            v-model="line.material_id"
                            required
                            class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm"
                            @change="onMaterialChange(line)"
                        >
                            <option value="" disabled>اختر المادة...</option>
                            <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }} ({{ m.sku }})</option>
                        </select>
                        <p v-if="stockWarning(line)" class="text-xs text-red-600">{{ stockWarning(line) }}</p>

                        <div class="grid grid-cols-3 gap-2">
                            <label class="text-xs text-slate-500">
                                الكمية
                                <input v-model="line.quantity" type="number" step="0.001" min="0.001" class="mt-0.5 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums" />
                            </label>
                            <label class="text-xs text-slate-500">
                                السعر ({{ BASE_SYMBOL }})
                                <input v-model="line.unit_price" type="number" step="0.01" min="0" class="mt-0.5 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums" />
                            </label>
                            <label class="text-xs text-slate-500">
                                الخصم ({{ BASE_SYMBOL }})
                                <input v-model="line.discount_amount" type="number" step="0.01" min="0" class="mt-0.5 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums" />
                            </label>
                        </div>

                        <p class="text-left text-sm font-semibold tabular-nums text-slate-900">
                            {{ formatUsd(lineTotal(line)) }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ------------------------------------------------------- the footer -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <Field label="نوع الخصم">
                    <select v-model="form.discount_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="NONE">بدون خصم</option>
                        <option value="PERCENT">نسبة مئوية</option>
                        <option value="FIXED">مبلغ ثابت</option>
                    </select>
                </Field>
                <Field label="قيمة الخصم">
                    <input
                        v-model="form.discount_value"
                        type="number"
                        step="0.01"
                        min="0"
                        :disabled="form.discount_type === 'NONE'"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums disabled:bg-slate-50 disabled:text-slate-400"
                    />
                    <p v-if="discountCapped" class="mt-1 text-xs text-amber-700">
                        الخصم أكبر من المجموع — طُبِّق بحدّ المجموع.
                    </p>
                </Field>
                <Field label="ملاحظات">
                    <input v-model.trim="form.notes" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </Field>
            </div>

            <!-- البيع عند الصندوق يُدفع في لحظته. الرقم يُكتب هنا حيث يُعدّ المال
                 فعلاً، ويتحوّل إلى سند قبض عند الترحيل لا قبله: المسودة لا تقبض
                 مالاً ولا تُقيّد شيئاً، فحفظ مبلغ عليها يعني قبضاً لم يحدث. -->
            <div v-if="canCollect" class="mt-4 border-t border-slate-100 pt-4">
                <div class="flex flex-wrap items-end gap-3">
                    <Field label="المدفوع الآن">
                        <div class="relative w-44">
                            <input
                                v-model="form.paid_now"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                class="w-full rounded-lg border px-3 py-2 pe-12 text-sm font-semibold tabular-nums focus:outline-none focus:ring-2"
                                :class="overPaid
                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                    : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100'"
                            />
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">$</span>
                        </div>
                    </Field>
                    <div class="flex flex-wrap gap-1.5 pb-1">
                        <button
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="paidIsFull ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                            @click="form.paid_now = totals.total.toFixed(2)"
                        >دفع كامل ({{ formatUsd(totals.total) }})</button>
                        <button
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="paidNow === 0 ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                            @click="form.paid_now = ''"
                        >على الحساب</button>
                    </div>
                    <p class="pb-2 text-xs text-slate-500">
                        <template v-if="overPaid">
                            <span class="text-red-600">المبلغ أكبر من إجمالي الفاتورة.</span>
                        </template>
                        <template v-else-if="paidNow > 0">
                            يتبقّى <strong class="tabular-nums text-slate-800">{{ formatUsd(Math.max(0, totals.total - paidNow)) }}</strong>
                            — يُسجَّل سند القبض عند ترحيل الفاتورة.
                        </template>
                        <template v-else>
                            اتركه فارغاً إن كان البيع على الحساب.
                        </template>
                    </p>
                </div>
            </div>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

        <!-- شريط ملتصق: المجموع والحفظ يبقيان في المتناول مهما طالت الفاتورة -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur lg:ps-[var(--sidebar-w,16rem)]">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="text-sm">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-slate-500">
                        <span>المجموع <b class="tabular-nums text-slate-800">{{ formatUsd(totals.subtotal) }}</b></span>
                        <span v-if="totals.discount_amount > 0">
                            الخصم <b class="tabular-nums text-red-600">−{{ formatUsd(totals.discount_amount) }}</b>
                        </span>
                        <span v-if="paidNow > 0">
                            المدفوع <b class="tabular-nums text-emerald-700">{{ formatUsd(paidNow) }}</b>
                        </span>
                    </div>
                    <div class="mt-0.5 text-lg font-bold tabular-nums text-slate-900">
                        الإجمالي {{ formatUsd(totals.total) }}
                        <span v-if="paidNow > 0 && !overPaid" class="text-sm font-medium text-slate-500">
                            · يتبقّى {{ formatUsd(Math.max(0, totals.total - paidNow)) }}
                        </span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        @click="$router.back()"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="saving || Boolean(savedInvoice) || !form.items.length"
                        class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ saving ? 'جارٍ الحفظ...' : (isEdit ? 'حفظ التعديل' : 'حفظ مسودة') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- المسودة وحدها لا تُخرج مادة من المستودع ولا تُقيّد ذمّة على العميل؛
             الترحيل هو ما يفعل، وهو الخطوة التي تُنسى. السؤال يأتي هنا والفاتورة
             ما تزال أمام عين من كتبها، لا بعد أيام في قائمة. -->
        <SalePostModal
            :open="Boolean(savedInvoice)"
            :invoice="savedInvoice"
            :collect="form.paid_now"
            cancel-label="لاحقاً"
            @close="leaveWithoutPosting"
            @posted="onPosted"
            @stale="goToList"
        />
    </form>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Field from '../../components/ui/FormField.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import ScanBox from '../../components/forms/ScanBox.vue';
import CustomerSelect from '../../components/forms/CustomerSelect.vue';
import SalePostModal from '../../components/sales/SalePostModal.vue';
import { salesApi, servicesApi, materialsApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { useToast } from '../../composables/useToast';
import { apiFieldErrors, apiMessage, formatUsd, formatQty, isoDate } from '../../utils/format';
import { BASE_SYMBOL } from '../../constants/currency';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { can } = usePermissions();

const isEdit = Boolean(route.params.id);
const canPost = can('sales.post');
// القبض يُنشئ سند قبض ويعتمده مع الفاتورة، فيلزمه ما يلزم السند في شاشته.
const canCollect = canPost && can('payments.create') && can('payments.confirm');

// The picker searches on demand; only the customer already on an edited
// invoice needs handing to it up front.
const loadedCustomer = ref(null);
const customerSelect = ref(null);
const services = ref([]);
const materials = ref([]);
const saving = ref(false);
const error = ref('');
const errors = ref({});
const scanBox = ref(null);
const flashed = ref(null);
/** المسودة المحفوظة للتوّ، ما دام سؤال ترحيلها معروضاً. */
const savedInvoice = ref(null);

let keySeq = 0;

const form = reactive({
    customer_id: '',
    invoice_date: isoDate(),
    due_date: '',
    discount_type: 'NONE',
    discount_value: '0',
    notes: '',
    items: [],
    // ليس من حقول المسودة: لا يُرسل مع الفاتورة بل يُملأ به حقل القبض في نافذة
    // الترحيل، حيث يصير سنداً.
    paid_now: '',
});

/**
 * A customer chosen (or cleared) from the picker. Clearing the error the moment
 * it is answered keeps a red field from outliving the problem it described.
 */
function onCustomerChange(customer) {
    loadedCustomer.value = customer;
    if (customer && errors.value.customer_id) {
        errors.value = { ...errors.value, customer_id: undefined };
    }
}

function fieldClass(field) {
    return errors.value[field]
        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
}

// ------------------------------------------------------------------- lines

function newLine(lineType, overrides = {}) {
    return {
        key: ++keySeq,
        line_type: lineType,
        service_id: '',
        material_id: '',
        quantity: '1',
        unit_price: '0.00',
        discount_amount: '0',
        description: null,
        unit_id: null,
        ...overrides,
    };
}

function addBlankLine(type) {
    form.items.push(newLine(type));
}

function removeLine(i) {
    form.items.splice(i, 1);
}

/**
 * إضافة من المسح أو البحث.
 *
 * مسح المادة نفسها مرّتين يعني قطعتين لا سطرين: عند الصندوق تُمسح ثلاث علب
 * من الشاحن الواحد تباعاً، وثلاثة أسطر متطابقة تُربك الفاتورة والمخزون معاً.
 * أمّا السطر الذي عُدِّل سعره أو خصمه يدوياً فلا يُدمج فيه شيء، لأن الدمج
 * يسحب الكمية إلى سعر لم يُقصد.
 */
function addFromPick(row) {
    const idField = row.kind === 'SERVICE' ? 'service_id' : 'material_id';

    const existing = form.items.find(
        (line) => line.line_type === row.kind
            && Number(line[idField]) === row.id
            && Number(line.unit_price) === Number(row.price)
            && Number(line.discount_amount || 0) === 0
    );

    if (existing) {
        existing.quantity = String(Number(existing.quantity || 0) + 1);
        flash(existing.key);
        return;
    }

    const line = newLine(row.kind, {
        [idField]: row.id,
        unit_price: String(row.price ?? '0.00'),
        unit_id: row.unit_id ?? null,
        description: row.name,
    });

    form.items.push(line);
    flash(line.key);
}

/** ومضة خضراء قصيرة: المسح لا يُحدث صوتاً، فالسطر يجب أن يُري نفسه. */
function flash(key) {
    flashed.value = key;
    setTimeout(() => {
        if (flashed.value === key) flashed.value = null;
    }, 1200);
}

function lineTotal(line) {
    return Number(line.quantity || 0) * Number(line.unit_price || 0) - Number(line.discount_amount || 0);
}

function onServiceChange(line) {
    const s = services.value.find((x) => x.id === Number(line.service_id));
    if (!s) return;
    line.unit_price = s.default_price;
    line.unit_id = s.unit_id;
    line.description = s.name;
    line.material_id = '';
}

function onMaterialChange(line) {
    const m = materials.value.find((x) => x.id === Number(line.material_id));
    if (!m) return;
    line.unit_price = m.sale_price;
    line.unit_id = m.unit_id;
    line.description = m.name;
    line.service_id = '';
}

/**
 * تحذير لا منع: سياسة المخزون يحكمها الخادم عند الترحيل (inventory_sale_policy)،
 * وقد تسمح بالبيع على المكشوف. الشاشة تُظهر الخطر ولا تقرّر نيابة عنه.
 */
function stockWarning(line) {
    if (line.line_type !== 'MATERIAL' || !line.material_id) return '';

    const material = materials.value.find((m) => m.id === Number(line.material_id));
    if (!material) return '';

    const stock = Number(material.stock_quantity) || 0;
    const wanted = Number(line.quantity) || 0;
    if (wanted <= stock) return '';

    return stock <= 0
        ? 'نفد من المخزون'
        : `المتوفّر ${formatQty(stock)} فقط`;
}

// ------------------------------------------------------------------ totals

const totals = computed(() => {
    const subtotal = form.items.reduce((sum, line) => sum + Math.max(0, lineTotal(line)), 0);

    let discount = 0;
    if (form.discount_type === 'PERCENT') discount = (subtotal * Number(form.discount_value || 0)) / 100;
    else if (form.discount_type === 'FIXED') discount = Number(form.discount_value || 0);

    const capped = discount > subtotal;
    if (capped) discount = subtotal;

    return { subtotal, discount_amount: discount, total: subtotal - discount, capped };
});

const discountCapped = computed(() => totals.value.capped && form.discount_type !== 'NONE');

// ------------------------------------------------------------ money taken

const paidNow = computed(() => Math.max(0, Number(form.paid_now) || 0));

/** قرش من فروق الفاصلة العشرية لا يُعدّ زيادة. */
const overPaid = computed(() => paidNow.value - totals.value.total > 0.005);

const paidIsFull = computed(() =>
    paidNow.value > 0 && Math.abs(paidNow.value - totals.value.total) < 0.005
);

// ------------------------------------------------------------------ saving

function payload() {
    return {
        customer_id: Number(form.customer_id),
        invoice_date: form.invoice_date,
        due_date: form.due_date || null,
        discount_type: form.discount_type,
        discount_value: form.discount_type === 'NONE' ? '0' : form.discount_value,
        notes: form.notes || null,
        items: form.items.map((line) => ({
            line_type: line.line_type,
            service_id: line.line_type === 'SERVICE' ? Number(line.service_id) : undefined,
            material_id: line.line_type === 'MATERIAL' ? Number(line.material_id) : undefined,
            quantity: String(line.quantity),
            unit_price: String(line.unit_price),
            discount_amount: String(line.discount_amount || 0),
            unit_id: line.unit_id ? Number(line.unit_id) : undefined,
            description: line.description || null,
        })),
    };
}

async function submit() {
    // المسودة محفوظة والسؤال عن ترحيلها معروض: النموذج خلف النافذة ما يزال
    // نموذجاً، وحفظه ثانيةً يُنشئ فاتورة ثانية بالبضاعة نفسها.
    if (saving.value || savedInvoice.value) return;

    error.value = '';
    errors.value = {};

    // The picker is a component, not an <input required>, so the browser no
    // longer stops a submit with no customer — this does.
    if (!form.customer_id) {
        errors.value = { customer_id: 'اختر العميل أولاً' };
        error.value = 'اختر العميل قبل حفظ الفاتورة.';
        customerSelect.value?.focus();
        return;
    }

    if (!form.items.length) {
        error.value = 'أضف بنداً واحداً على الأقل.';
        return;
    }

    const blank = form.items.findIndex(
        (line) => !(line.line_type === 'SERVICE' ? line.service_id : line.material_id)
    );
    if (blank !== -1) {
        error.value = `السطر ${blank + 1} بلا عنصر مُختار.`;
        return;
    }

    saving.value = true;

    try {
        const data = isEdit
            ? await salesApi.update(route.params.id, payload())
            : await salesApi.store(payload());

        const invoice = data.data ?? null;

        // من لا يملك صلاحية الترحيل لا يُسأل عنه؛ سؤالٌ جوابه الوحيد «لا»
        // ليس سؤالاً.
        if (canPost && invoice?.id && invoice.status === 'DRAFT') {
            // خبر الحفظ يُقال أوّلاً: النافذة تسأل عن خطوة تالية، ومن لا يرى
            // تأكيداً للحفظ يقرأ السؤال على أنه شرط له.
            toast.success(data.message || 'تم حفظ المسودة');
            savedInvoice.value = invoice;
            return;
        }

        toast.success(data.message || 'تم حفظ المسودة');
        router.replace({ name: 'sales' });
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر الحفظ');
        errors.value = apiFieldErrors(e);
    } finally {
        saving.value = false;
    }
}

// ------------------------------------------------------- posting after save

function goToList() {
    savedInvoice.value = null;
    router.replace({ name: 'sales' });
}

function onPosted(invoice) {
    savedInvoice.value = null;

    const no = invoice?.invoice_no ? ` ${invoice.invoice_no}` : '';
    const paid = Number(invoice?.paid_amount) || 0;
    const remaining = Number(invoice?.remaining) || 0;

    // ما يريد البائع سماعه بعد الترحيل هو حال المال: مسدَّدة، أم بقي منها كم.
    if (paid > 0 && remaining <= 0) toast.success(`تم ترحيل الفاتورة${no} مسدَّدةً بالكامل`);
    else if (paid > 0) toast.success(`تم ترحيل الفاتورة${no} — قُبض ${formatUsd(paid)} وتبقّى ${formatUsd(remaining)}`);
    else toast.success(`تم ترحيل الفاتورة${no} وخُصم المخزون وقُيّدت على العميل`);

    router.replace({ name: 'sales' });
}

/**
 * «لاحقاً» ليس فشلاً: المسودة محفوظة، وقول ذلك يمنع ظنّ أن الحفظ ضاع.
 *
 * أمّا من كتب مبلغاً مدفوعاً ثم أجّل، فيجب أن يُقال له صراحةً إنّ المال لم
 * يُسجَّل: المسودة لا تحمل قبضاً، واكتشاف ذلك بعد يومين في كشف الحساب أسوأ.
 */
function leaveWithoutPosting() {
    savedInvoice.value = null;
    toast.success(
        paidNow.value > 0
            ? 'حُفظت المسودة دون ترحيل — لم يُسجَّل أي قبض، يُسجَّل عند الترحيل'
            : 'حُفظت المسودة — تُرحَّل لاحقاً من قائمة فواتير البيع'
    );
    router.replace({ name: 'sales' });
}

// ----------------------------------------------------------------- loading

onMounted(async () => {
    try {
        const servicePromise = servicesApi.index({ per_page: 500 }).catch(() => ({ data: [] }));
        const materialPromise = materialsApi.index({ per_page: 500 }).catch(() => ({ data: [] }));
        const [serviceList, materialList] = await Promise.all([servicePromise, materialPromise]);

        services.value = serviceList.data ?? serviceList ?? [];
        materials.value = materialList.data ?? materialList ?? [];
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر تحميل القوائم');
    }

    if (isEdit) {
        try {
            const { data: invoice } = await salesApi.show(route.params.id);

            form.customer_id = invoice.customer_id;
            loadedCustomer.value = invoice.customer ?? null;
            form.invoice_date = invoice.invoice_date;
            form.due_date = invoice.due_date || '';
            form.discount_type = invoice.discount_type;
            form.discount_value = discountValue(invoice);
            form.notes = invoice.notes || '';
            form.items = invoice.items.map((line) => newLine(line.line_type, {
                service_id: line.service_id ?? '',
                material_id: line.material_id ?? '',
                quantity: line.quantity,
                unit_price: line.unit_price,
                discount_amount: line.discount_amount,
                description: line.description,
                unit_id: line.unit_id,
            }));
        } catch (e) {
            error.value = apiMessage(e, 'تعذّر تحميل الفاتورة');
        }

        return;
    }

    // فاتورة جديدة تبدأ عند حقل المسح، فالبيع يبدأ بمسح لا بنقرة.
    await nextTick();
    scanBox.value?.focus();
});

function discountValue(invoice) {
    return invoice.discount_type === 'NONE' ? '0' : String(invoice.discount_value);
}
</script>
