<template>
    <form class="mx-auto max-w-5xl space-y-5 pb-28" @submit.prevent="submit">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ isEdit ? 'تعديل فاتورة شراء' : 'فاتورة شراء جديدة' }}</h1>
                <p class="text-sm text-slate-500">مسودة — تُرقّم وتُعتمد عند الترحيل</p>
            </div>
            <button type="button" class="text-sm text-slate-500 transition hover:text-slate-900" @click="$router.back()">
                → عودة
            </button>
        </div>

        <!-- ------------------------------------------------------- the header -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <Field label="المورد" required>
                    <SupplierSelect
                        ref="supplierSelect"
                        v-model="form.supplier_id"
                        :supplier="loadedSupplier"
                        :invalid="Boolean(errors.supplier_id)"
                        @change="onSupplierChange"
                    />
                    <p v-if="errors.supplier_id" class="mt-1 text-xs text-red-600">{{ errors.supplier_id }}</p>
                </Field>
                <Field label="تاريخ الشراء">
                    <input
                        v-model="form.purchase_date"
                        type="date"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('purchase_date')"
                    />
                </Field>
                <Field label="تاريخ الاستحقاق">
                    <input
                        v-model="form.due_date"
                        type="date"
                        :min="form.purchase_date || undefined"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        :class="fieldClass('due_date')"
                    />
                </Field>
            </div>
        </div>

        <!-- مورد آخر أرخص لبعض البنود: يُقال قبل الطلب لا بعد وصول البضاعة. -->
        <div
            v-if="cheaperSuppliers.length"
            class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
        >
            <p class="mb-2 font-semibold">💡 أسعار أفضل حسب فواتير الشراء السابقة</p>
            <ul class="space-y-1.5">
                <li
                    v-for="s in cheaperSuppliers"
                    :key="s.supplier_id"
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <span>
                        «{{ s.supplier_name }}» الأرخص في {{ s.lines }} من البنود
                        <template v-if="s.saving > 0">
                            — توفير تقديري <b class="tabular-nums">{{ formatMoney(s.saving) }}</b>
                        </template>
                    </span>
                    <button
                        type="button"
                        class="rounded-lg border border-blue-300 bg-white px-3 py-1 text-xs font-semibold text-blue-700 transition hover:bg-blue-100"
                        @click="switchSupplier(s)"
                    >
                        اختيار هذا المورد
                    </button>
                </li>
            </ul>
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
                            v-if="canCreateMaterial"
                            type="button"
                            class="rounded-lg border border-blue-300 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-100"
                            @click="openQuickAdd(null, {})"
                        >
                            ＋ مادة جديدة
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="addBlankLine"
                        >
                            ＋ سطر يدوي
                        </button>
                    </div>
                </div>

                <!-- البضاعة تصل ومعها باركودها: مسحها أسرع وأدقّ من البحث عنها
                     في قائمة منسدلة تضم كل مواد المستودع. والوارد هو أوّل مكان
                     تظهر فيه مادة لم يعرفها المستودع بعد، فالمسح يقود إلى
                     تسجيلها لا إلى رسالة «غير معروف». -->
                <ScanBox
                    ref="scanBox"
                    :include-services="false"
                    price-field="cost_price"
                    :show-stock="false"
                    :allow-create="canCreateMaterial"
                    placeholder="امسح باركود المادة الواردة أو ابحث بالاسم..."
                    @pick="addFromPick"
                    @create="onScanCreate"
                />
            </div>

            <EmptyState
                v-if="!form.items.length"
                icon="📦"
                title="لا توجد بنود بعد"
                hint="امسح باركود المادة الواردة، أو ابحث بالاسم في الحقل أعلاه"
            />

            <div v-else>
                <!-- جدول على الشاشة الواسعة -->
                <table class="hidden w-full text-right text-sm sm:table">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-3 py-2">المادة</th>
                            <th class="w-24 px-3 py-2">الكمية</th>
                            <th class="w-28 px-3 py-2">سعر التكلفة</th>
                            <th class="w-24 px-3 py-2">الخصم</th>
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
                                <MaterialSelect
                                    :ref="(el) => registerPicker(line.key, el)"
                                    :material="line.material"
                                    :invalid="submitted && !line.material_id"
                                    @select="(m) => setMaterial(line, m)"
                                    @create="(term) => openQuickAddForLine(line, term)"
                                />
                                <p v-if="line.is_new" class="mt-1 text-xs font-medium text-emerald-700">
                                    ✓ مادة جديدة — أُضيفت إلى المستودع
                                </p>
                                <p v-else-if="costWarning(line)" class="mt-1 text-xs text-amber-700">{{ costWarning(line) }}</p>
                                <PriceSuggestionHint
                                    :suggestion="suggestions[line.material_id]"
                                    :supplier-id="form.supplier_id"
                                    :current-price="line.unit_price"
                                    @apply="(price) => (line.unit_price = String(price))"
                                />
                            </td>
                            <td class="px-3 py-2 align-top">
                                <input
                                    v-model="line.quantity"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums focus:border-blue-500 focus:outline-none"
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
                                {{ formatMoney(lineTotal(line)) }}
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

                <!-- بطاقات على الهاتف: الجرد يُدخَل واقفاً أمام الرفّ غالباً -->
                <ul class="divide-y divide-slate-100 sm:hidden">
                    <li
                        v-for="(line, i) in form.items"
                        :key="line.key"
                        class="space-y-2 p-4"
                        :class="line.key === flashed ? 'bg-emerald-50' : ''"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span
                                class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-semibold"
                                :class="line.is_new ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ line.is_new ? 'مادة جديدة' : 'مادة' }}
                            </span>
                            <button
                                type="button"
                                :aria-label="`حذف السطر ${i + 1}`"
                                class="text-red-500"
                                @click="removeLine(i)"
                            >
                                ✕
                            </button>
                        </div>

                        <MaterialSelect
                            :material="line.material"
                            :invalid="submitted && !line.material_id"
                            @select="(m) => setMaterial(line, m)"
                            @create="(term) => openQuickAddForLine(line, term)"
                        />
                        <p v-if="!line.is_new && costWarning(line)" class="text-xs text-amber-700">{{ costWarning(line) }}</p>
                        <PriceSuggestionHint
                            :suggestion="suggestions[line.material_id]"
                            :supplier-id="form.supplier_id"
                            :current-price="line.unit_price"
                            @apply="(price) => (line.unit_price = String(price))"
                        />

                        <div class="grid grid-cols-3 gap-2">
                            <label class="text-xs text-slate-500">
                                الكمية
                                <input v-model="line.quantity" type="number" step="0.001" min="0.001" class="mt-0.5 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums" />
                            </label>
                            <label class="text-xs text-slate-500">
                                التكلفة
                                <input v-model="line.unit_price" type="number" step="0.01" min="0" class="mt-0.5 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums" />
                            </label>
                            <label class="text-xs text-slate-500">
                                الخصم
                                <input v-model="line.discount_amount" type="number" step="0.01" min="0" class="mt-0.5 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm tabular-nums" />
                            </label>
                        </div>

                        <p class="text-left text-sm font-semibold tabular-nums text-slate-900">
                            {{ formatMoney(lineTotal(line)) }}
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
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

        <!-- شريط ملتصق: المجموع والحفظ يبقيان في المتناول مهما طالت الفاتورة -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur lg:ps-64">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="text-sm">
                    <div class="flex items-center gap-4 text-slate-500">
                        <span>المجموع <b class="tabular-nums text-slate-800">{{ formatMoney(totals.subtotal) }}</b></span>
                        <span v-if="totals.discount_amount > 0">
                            الخصم <b class="tabular-nums text-red-600">−{{ formatMoney(totals.discount_amount) }}</b>
                        </span>
                    </div>
                    <div class="mt-0.5 text-lg font-bold tabular-nums text-slate-900">
                        الإجمالي {{ formatMoney(totals.total) }}
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

        <!-- المسودة وحدها لا تُدخل شيئاً إلى المستودع ولا تُقيّد ديناً على
             المحل؛ الترحيل هو ما يفعل، وهو الخطوة التي تُنسى. السؤال يأتي هنا
             والفاتورة ما تزال أمام عين من كتبها، لا بعد أيام في قائمة. -->
        <PurchasePostModal
            :open="Boolean(savedInvoice)"
            :invoice="savedInvoice"
            cancel-label="لاحقاً"
            @close="leaveWithoutPosting"
            @posted="onPosted"
            @stale="goToList"
        />

        <MaterialQuickAddModal
            :open="quickOpen"
            :prefill="quickPrefill"
            @close="closeQuickAdd"
            @created="onMaterialCreated"
            @use-existing="onMaterialPicked"
        />
    </form>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Field from '../../components/ui/FormField.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import ScanBox from '../../components/forms/ScanBox.vue';
import SupplierSelect from '../../components/forms/SupplierSelect.vue';
import MaterialSelect from '../../components/forms/MaterialSelect.vue';
import MaterialQuickAddModal from '../../components/forms/MaterialQuickAddModal.vue';
import PurchasePostModal from '../../components/purchases/PurchasePostModal.vue';
import PriceSuggestionHint from '../../components/purchases/PriceSuggestionHint.vue';
import { purchasesApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { useToast } from '../../composables/useToast';
import { apiFieldErrors, apiMessage, formatMoney, isoDate } from '../../utils/format';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { can } = usePermissions();

const isEdit = Boolean(route.params.id);
const canCreateMaterial = can('materials.create');
const canPost = can('purchases.post');

// The pickers search on demand; only what is already on an edited invoice —
// its supplier and its materials — needs handing to them up front.
const loadedSupplier = ref(null);
const supplierSelect = ref(null);
const saving = ref(false);
const submitted = ref(false);
const error = ref('');
const errors = ref({});
const scanBox = ref(null);
const flashed = ref(null);

/** الفاتورة بعد حفظها: وجودها يعني أن السؤال عن الترحيل معروض الآن. */
const savedInvoice = ref(null);

// Row pickers, keyed by line so a deleted row does not leave the ref of the
// row that shifted into its place pointing at a component that is gone.
const pickers = new Map();

const quickOpen = ref(false);
const quickPrefill = ref({});
/** السطر الذي فُتحت النافذة من أجله؛ null يعني «أضف سطراً جديداً». */
let quickTarget = null;

let keySeq = 0;

const form = reactive({
    supplier_id: '',
    purchase_date: isoDate(),
    due_date: '',
    discount_type: 'NONE',
    discount_value: '0',
    notes: '',
    items: [],
});

function fieldClass(field) {
    return errors.value[field]
        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
}

/**
 * A supplier chosen (or cleared) from the picker. Clearing the error the moment
 * it is answered keeps a red field from outliving the problem it described.
 */
function onSupplierChange(supplier) {
    loadedSupplier.value = supplier;
    if (supplier && errors.value.supplier_id) {
        errors.value = { ...errors.value, supplier_id: undefined };
    }
}

// ------------------------------------------------------------------- lines

function registerPicker(key, el) {
    if (el) pickers.set(key, el);
    else pickers.delete(key);
}

function newLine(overrides = {}) {
    return {
        // A stable key, not the array index: keying rows by index makes the
        // inputs of every row below a deleted one shift up onto the wrong data.
        key: ++keySeq,
        material: null,
        material_id: '',
        quantity: '1',
        unit_price: '0.00',
        discount_amount: '0',
        description: null,
        unit_id: null,
        is_new: false,
        ...overrides,
    };
}

function addBlankLine() {
    const line = newLine();
    form.items.push(line);
    nextTick(() => pickers.get(line.key)?.focus());
}

function removeLine(i) {
    pickers.delete(form.items[i].key);
    form.items.splice(i, 1);
}

/**
 * تعليق مادة على سطر.
 *
 * التكلفة المسجّلة تُملأ اقتراحاً لا حكماً: الفاتورة قد تحمل سعراً آخر، لكن
 * كتابة رقم فوق رقم أسرع من كتابته من الصفر. أمّا السطر الذي عُدِّلت تكلفته
 * يدوياً فلا يُكتب فوقها إلّا حين تتغيّر المادة نفسها.
 */
function setMaterial(line, material) {
    line.material = material;
    line.material_id = material?.id ?? '';
    line.is_new = false;

    if (!material) return;

    line.unit_id = material.unit_id ?? null;
    line.description = material.name;
    line.unit_price = String(material.cost_price ?? '0.00');
}

/**
 * إضافة من المسح أو البحث.
 *
 * مسح المادة نفسها مرّتين يعني قطعتين لا سطرين — الوارد يُعدّ بالمسح المتتابع.
 * أمّا السطر الذي عُدِّلت تكلفته أو خصمه يدوياً فلا يُدمج فيه شيء، لأن الدمج
 * يسحب الكمية إلى تكلفة لم تُقصد.
 */
function addFromPick(row) {
    addMaterial(row.raw ?? {
        id: row.id,
        name: row.name,
        sku: row.code,
        barcode: null,
        cost_price: row.price,
        unit_id: row.unit_id,
    });
}

function addMaterial(material, { isNew = false } = {}) {
    const cost = Number(material.cost_price ?? 0);

    const existing = form.items.find(
        (line) => Number(line.material_id) === Number(material.id)
            && Number(line.unit_price) === cost
            && Number(line.discount_amount || 0) === 0
    );

    if (existing) {
        existing.quantity = String(Number(existing.quantity || 0) + 1);
        flash(existing.key);
        return existing;
    }

    const line = newLine();
    setMaterial(line, material);
    line.is_new = isNew;
    form.items.push(line);
    flash(line.key);

    return line;
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

/**
 * تحذير لا منع: التكلفة ترتفع وتنخفض، والفاتورة هي المرجع لا البطاقة. لكن
 * قفزة كبيرة عن التكلفة المسجّلة غالباً خانة زائدة، وكشفها الآن أرخص من
 * اكتشافها في تقرير أرباح بعد شهر.
 */
function costWarning(line) {
    const known = Number(line.material?.cost_price) || 0;
    const entered = Number(line.unit_price) || 0;
    if (known <= 0 || entered <= 0) return '';

    if (entered >= known * 1.5) return `أعلى بكثير من التكلفة المسجّلة (${formatMoney(known)})`;
    if (entered <= known * 0.5) return `أقل بكثير من التكلفة المسجّلة (${formatMoney(known)})`;

    return '';
}

// -------------------------------------------------------- price suggestions

/**
 * أفضل سعر مورد لكل مادة على الفاتورة، مفهرس برقم المادة.
 *
 * يُسأل الخادم عن المواد كلها دفعة واحدة كلما تغيّرت مجموعتها أو تغيّر المورد،
 * بعد مهلة قصيرة كي لا يُطلق المسح المتتابع طلباً مع كل قطعة.
 */
const suggestions = ref({});
let suggestTimer = null;
let suggestSeq = 0;

const materialIds = computed(() =>
    [...new Set(form.items.map((line) => Number(line.material_id)).filter(Boolean))].sort((a, b) => a - b)
);

watch([() => materialIds.value.join(','), () => form.supplier_id], () => {
    clearTimeout(suggestTimer);
    suggestTimer = setTimeout(loadSuggestions, 300);
});

async function loadSuggestions() {
    const seq = ++suggestSeq;

    if (!materialIds.value.length) {
        suggestions.value = {};
        return;
    }

    try {
        const rows = await purchasesApi.priceSuggestions({
            materialIds: materialIds.value,
            supplierId: form.supplier_id,
        });
        // جواب طلب قديم وصل بعد أحدث منه لا يكتب فوقه.
        if (seq !== suggestSeq) return;
        suggestions.value = Object.fromEntries(rows.map((row) => [row.material_id, row]));
    } catch {
        // اقتراح لا أكثر: تعذّره لا يستحق رسالة خطأ فوق فاتورة سليمة.
    }
}

/**
 * الموردون الأرخص من المختار في بند واحد على الأقل، مع توفير تقديري
 * (الفرق بين السعر المكتوب على السطر وأفضل سعر، مضروباً في الكمية).
 */
const cheaperSuppliers = computed(() => {
    const selected = Number(form.supplier_id) || null;
    const bySupplier = new Map();

    for (const line of form.items) {
        const best = suggestions.value[line.material_id]?.best;
        if (!best || best.supplier_id === selected) continue;

        const entry = bySupplier.get(best.supplier_id)
            ?? { supplier_id: best.supplier_id, supplier_name: best.supplier_name, lines: 0, saving: 0 };
        entry.lines += 1;
        const diff = Number(line.unit_price || 0) - Number(best.unit_cost);
        if (diff > 0) entry.saving += diff * Number(line.quantity || 0);
        bySupplier.set(best.supplier_id, entry);
    }

    return [...bySupplier.values()].sort((a, b) => b.lines - a.lines || b.saving - a.saving);
});

function switchSupplier(s) {
    loadedSupplier.value = { id: s.supplier_id, name: s.supplier_name };
    form.supplier_id = s.supplier_id;
    if (errors.value.supplier_id) errors.value = { ...errors.value, supplier_id: undefined };
    toast.success(`تم اختيار «${s.supplier_name}» — راجع أسعار البنود`);
}

// ------------------------------------------------------ quick-add material

/**
 * المادة المجهولة تُسجَّل من هنا لا من شاشة أخرى.
 *
 * الموظّف واقف أمام صندوق بضاعة وصل للتوّ، ونصف ما فيه لم يمرّ بالمستودع من
 * قبل. إرساله إلى شاشة المواد يعني ترك مسودة نصف مكتوبة خلفه، فيعود إليها —
 * إن عاد — وقد نسي أي سطر كان يملأ. النافذة تفتح فوق الفاتورة، وتعيد المادة
 * إلى السطر الذي طلبها.
 */
function openQuickAdd(line, prefill) {
    quickTarget = line;
    quickPrefill.value = prefill;
    quickOpen.value = true;
}

function openQuickAddForLine(line, term) {
    openQuickAdd(line, {
        name: term,
        // تكلفة كُتبت على السطر قبل اختيار المادة تُحمل معها بدل إعادة كتابتها.
        cost_price: Number(line.unit_price) > 0 ? line.unit_price : undefined,
    });
}

/** الرمز الممسوح الذي لم يُطابق شيئاً، أو نصّ بحث بلا نتيجة. */
function onScanCreate({ barcode, name }) {
    openQuickAdd(null, { barcode, name });
}

function closeQuickAdd() {
    quickOpen.value = false;
    quickTarget = null;
    focusScanBox();
}

function onMaterialCreated(material) {
    attach(material, true);
    toast.success(`أُضيفت «${material.name}» إلى المواد وإلى الفاتورة`);
}

/** «استخدام الموجودة» من نافذة التكرار: مادة قائمة لا مادة جديدة. */
function onMaterialPicked(material) {
    attach(material, false);
}

function attach(material, isNew) {
    if (quickTarget) {
        setMaterial(quickTarget, material);
        quickTarget.is_new = isNew;
        flash(quickTarget.key);
    } else {
        addMaterial(material, { isNew });
    }

    quickOpen.value = false;
    quickTarget = null;
    focusScanBox();
}

function focusScanBox() {
    nextTick(() => {
        scanBox.value?.reset(true);
    });
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

// ------------------------------------------------------------------ saving

function payload() {
    return {
        supplier_id: Number(form.supplier_id),
        purchase_date: form.purchase_date,
        due_date: form.due_date || null,
        discount_type: form.discount_type,
        discount_value: form.discount_type === 'NONE' ? '0' : form.discount_value,
        notes: form.notes || null,
        items: form.items.map((line) => ({
            material_id: Number(line.material_id),
            quantity: String(line.quantity),
            unit_price: String(line.unit_price),
            discount_amount: String(line.discount_amount || 0),
            // الوحدة إلزامية على الخادم، ووحدة المادة هي مصدرها الطبيعي.
            unit_id: Number(line.unit_id ?? line.material?.unit_id) || undefined,
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
    submitted.value = true;

    // The picker is a component, not an <input required>, so the browser no
    // longer stops a submit with no supplier — this does.
    if (!form.supplier_id) {
        errors.value = { supplier_id: 'اختر المورد أولاً' };
        error.value = 'اختر المورد قبل حفظ الفاتورة.';
        supplierSelect.value?.focus();
        return;
    }

    if (!form.items.length) {
        error.value = 'أضف بنداً واحداً على الأقل.';
        return;
    }

    const blank = form.items.findIndex((line) => !line.material_id);
    if (blank !== -1) {
        error.value = `السطر ${blank + 1} بلا مادة مُختارة.`;
        pickers.get(form.items[blank].key)?.focus();
        return;
    }

    saving.value = true;

    try {
        const data = isEdit
            ? await purchasesApi.update(route.params.id, payload())
            : await purchasesApi.store(payload());

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
        router.replace({ name: 'purchases' });
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
    router.replace({ name: 'purchases' });
}

function onPosted(invoice) {
    savedInvoice.value = null;
    toast.success(
        invoice?.invoice_no
            ? `تم ترحيل الفاتورة ${invoice.invoice_no} ودخلت البضاعة المستودع`
            : 'تم ترحيل الفاتورة'
    );
    router.replace({ name: 'purchases' });
}

/** «لاحقاً» ليس فشلاً: المسودة محفوظة، وقول ذلك يمنع ظنّ أن الحفظ ضاع. */
function leaveWithoutPosting() {
    savedInvoice.value = null;
    toast.success('حُفظت المسودة — تُرحَّل لاحقاً من قائمة فواتير الشراء');
    router.replace({ name: 'purchases' });
}

// ----------------------------------------------------------------- loading

onMounted(async () => {
    if (isEdit) {
        try {
            const { data: invoice } = await purchasesApi.show(route.params.id);

            form.supplier_id = invoice.supplier_id;
            loadedSupplier.value = invoice.supplier ?? null;
            form.purchase_date = invoice.purchase_date;
            form.due_date = invoice.due_date || '';
            form.discount_type = invoice.discount_type;
            form.discount_value = discountValue(invoice);
            form.notes = invoice.notes || '';
            // The invoice ships its materials with it, so no catalogue-wide
            // preload is needed to render the lines it already holds.
            form.items = invoice.items.map((line) => newLine({
                material: line.material ?? null,
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

    // فاتورة جديدة تبدأ عند حقل المسح: الوارد يُدخَل بالمسح لا بالنقر.
    await nextTick();
    scanBox.value?.focus();
});

function discountValue(invoice) {
    return invoice.discount_type === 'NONE' ? '0' : String(invoice.discount_value);
}
</script>
