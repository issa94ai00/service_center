<template>
    <div class="mx-auto max-w-4xl space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-900">العملات وأسعار الصرف</h1>
            <p class="text-sm text-slate-500">
                الدفاتر والتقارير بالدولار ({{ BASE_SYMBOL }}). العملات المفعّلة هنا يُقبض ويُدفع ويُصرف بها، وتُحوَّل إلى الدولار بسعرها لحظة التسجيل.
            </p>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ error }}
        </div>

        <!-- ------------------------------------------------------------ base -->
        <section v-if="base" class="flex flex-wrap items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50/60 p-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-lg font-bold text-white">{{ base.symbol }}</span>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-900">{{ base.name }} <span class="text-sm font-normal text-slate-500" dir="ltr">{{ base.code }}</span></p>
                <p class="text-xs text-slate-600">العملة الأساسية — كل الأرصدة والفواتير والتقارير بها، وسعرها 1 دائماً.</p>
            </div>
            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800">أساسية</span>
        </section>

        <!-- ----------------------------------------------------------- list -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="font-bold text-slate-900">العملات الأخرى</h2>
                    <p class="text-xs text-slate-500">السعر = كم وحدة من العملة تساوي دولاراً واحداً</p>
                </div>
                <button
                    v-if="!adding"
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
                    @click="startAdd()"
                >
                    + إضافة عملة
                </button>
            </div>

            <!-- إضافة -->
            <form v-if="adding" class="space-y-3 border-b border-slate-100 bg-slate-50 p-5" @submit.prevent="create">
                <div class="flex flex-wrap gap-1.5">
                    <span class="text-xs text-slate-500">اختيار سريع:</span>
                    <button
                        v-for="preset in availablePresets"
                        :key="preset.code"
                        type="button"
                        class="rounded-full border border-slate-300 bg-white px-2.5 py-0.5 text-xs text-slate-700 hover:bg-slate-100"
                        @click="applyPreset(preset)"
                    >
                        {{ preset.name }}
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-6">
                    <label class="sm:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600">الرمز الدولي *</span>
                        <input v-model.trim="draft.code" maxlength="3" dir="ltr" placeholder="SYP" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                    </label>
                    <label class="sm:col-span-2">
                        <span class="mb-1 block text-xs font-medium text-slate-600">الاسم *</span>
                        <input v-model.trim="draft.name" maxlength="50" placeholder="ليرة سورية" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                    </label>
                    <label class="sm:col-span-1">
                        <span class="mb-1 block text-xs font-medium text-slate-600">رمز العرض *</span>
                        <input v-model.trim="draft.symbol" maxlength="10" placeholder="ل.س" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                    </label>
                    <label class="sm:col-span-2">
                        <span class="mb-1 block text-xs font-medium text-slate-600">سعر الصرف *</span>
                        <div class="flex items-center gap-1.5 text-sm">
                            <span class="shrink-0 text-slate-500">1 {{ BASE_SYMBOL }} =</span>
                            <input v-model="draft.exchange_rate" type="number" step="any" min="0" inputmode="decimal" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100" />
                        </div>
                    </label>
                    <label class="sm:col-span-2">
                        <span class="mb-1 block text-xs font-medium text-slate-600">الكسور</span>
                        <select v-model.number="draft.decimals" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                            <option :value="0">بلا كسور (1,300,000)</option>
                            <option :value="1">منزلة واحدة</option>
                            <option :value="2">منزلتان (12.50)</option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 pt-5 text-sm text-slate-700 sm:col-span-4">
                        <input v-model="draft.show_on_invoices" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                        اطبع ما يعادل إجمالي الفاتورة بهذه العملة
                    </label>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-white" @click="adding = false">إلغاء</button>
                    <button type="submit" :disabled="busy" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">إضافة</button>
                </div>
            </form>

            <div v-if="loading" class="py-12 text-center text-sm text-slate-400">جارٍ التحميل...</div>

            <div v-else-if="!others.length && !adding" class="px-5 py-10 text-center text-sm text-slate-500">
                لا عملات غير الدولار بعد — أضف الليرة السورية أو غيرها ليُقبض ويُصرف بها.
            </div>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="c in others" :key="c.id" class="px-5 py-4">
                    <div class="flex flex-wrap items-start gap-3">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold"
                            :class="c.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'"
                        >{{ c.symbol }}</span>

                        <div class="min-w-0 flex-1">
                            <p class="font-semibold" :class="c.is_active ? 'text-slate-900' : 'text-slate-400'">
                                {{ c.name }} <span class="text-xs font-normal text-slate-500" dir="ltr">{{ c.code }}</span>
                                <span v-if="!c.is_active" class="ms-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-500">معطّلة</span>
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ c.rate_updated_at ? `آخر تحديث للسعر ${formatDateTime(c.rate_updated_at)}` : 'لم يُسجَّل تحديث' }}
                                · {{ c.usage_count ? `${c.usage_count} سند` : 'غير مستعملة بعد' }}
                            </p>
                        </div>

                        <!-- السعر يُعدَّل في مكانه: هو ما يتغيّر كل يوم. -->
                        <form class="flex flex-wrap items-center gap-1.5 text-sm" @submit.prevent="saveRate(c)">
                            <span class="shrink-0 text-slate-500">1 {{ BASE_SYMBOL }} =</span>
                            <input
                                v-model="rates[c.id]"
                                type="number"
                                step="any"
                                min="0"
                                inputmode="decimal"
                                :aria-label="`سعر صرف ${c.name}`"
                                class="w-32 rounded-lg border px-2 py-1.5 text-sm font-semibold tabular-nums focus:outline-none focus:ring-2"
                                :class="rateDirty(c) ? 'border-amber-400 focus:ring-amber-100' : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100'"
                            />
                            <span class="shrink-0 text-slate-500">{{ c.symbol }}</span>
                            <button
                                v-if="rateDirty(c)"
                                type="submit"
                                :disabled="busy"
                                class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600 disabled:opacity-50"
                            >
                                حفظ السعر
                            </button>
                        </form>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                        <label class="flex cursor-pointer items-center gap-1.5 text-slate-600">
                            <input type="checkbox" :checked="c.is_active" :disabled="busy" class="h-4 w-4 rounded border-slate-300" @change="patch(c, { is_active: $event.target.checked })" />
                            مفعّلة في السندات
                        </label>
                        <label class="flex cursor-pointer items-center gap-1.5 text-slate-600">
                            <input type="checkbox" :checked="c.show_on_invoices" :disabled="busy" class="h-4 w-4 rounded border-slate-300" @change="patch(c, { show_on_invoices: $event.target.checked })" />
                            تُطبع على الفواتير
                        </label>
                        <span class="text-slate-400">الكسور: {{ c.decimals }}</span>
                        <span class="flex-1"></span>
                        <button type="button" class="font-medium text-blue-600 hover:underline" @click="openHistory(c)">سجلّ الأسعار</button>
                        <button type="button" class="font-medium text-slate-600 hover:underline" @click="startEdit(c)">تعديل</button>
                        <button
                            v-if="!c.usage_count"
                            type="button"
                            class="font-medium text-red-600 hover:underline"
                            :disabled="busy"
                            @click="remove(c)"
                        >
                            حذف
                        </button>
                    </div>

                    <!-- تعديل الاسم والرمز والكسور -->
                    <form v-if="editing === c.id" class="mt-3 grid gap-2 rounded-xl bg-slate-50 p-3 sm:grid-cols-4" @submit.prevent="saveEdit(c)">
                        <input v-model.trim="edit.name" maxlength="50" placeholder="الاسم" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
                        <input v-model.trim="edit.symbol" maxlength="10" placeholder="رمز العرض" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
                        <select v-model.number="edit.decimals" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                            <option :value="0">بلا كسور</option>
                            <option :value="1">منزلة واحدة</option>
                            <option :value="2">منزلتان</option>
                        </select>
                        <div class="flex gap-2">
                            <button type="submit" :disabled="busy" class="flex-1 rounded-lg bg-blue-600 px-3 py-1.5 text-sm text-white disabled:opacity-50">حفظ</button>
                            <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm" @click="editing = null">تراجع</button>
                        </div>
                    </form>
                </li>
            </ul>
        </section>

        <p class="text-xs text-slate-500">
            تغيير السعر يسري على السندات الجديدة فقط. السند المسجَّل يحتفظ بسعره، فلا يتغيّر رصيد ولا تقرير بأثر رجعي.
        </p>

        <BaseModal
            :open="Boolean(history)"
            :title="history ? `سجلّ أسعار ${history.name}` : ''"
            subtitle="آخر 50 تغييراً"
            max-width="max-w-lg"
            @close="history = null"
        >
            <div v-if="historyLoading" class="py-8 text-center text-sm text-slate-400">جارٍ التحميل...</div>
            <p v-else-if="!historyRows.length" class="py-8 text-center text-sm text-slate-400">لا تغييرات مسجّلة</p>
            <div v-else class="overflow-x-auto">
            <table class="w-full text-right text-sm [&_td]:whitespace-nowrap [&_td]:px-2 [&_th]:px-2">
                <thead class="text-xs text-slate-500">
                    <tr><th class="py-2">التاريخ</th><th class="py-2">من</th><th class="py-2">إلى</th><th class="py-2">بواسطة</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in historyRows" :key="r.id">
                        <td class="py-2 text-slate-600">{{ formatDateTime(r.created_at) }}</td>
                        <td class="py-2 tabular-nums text-slate-500">{{ r.previous_rate ? formatRate(r.previous_rate) : '—' }}</td>
                        <td class="py-2 font-semibold tabular-nums">{{ formatRate(r.exchange_rate) }}</td>
                        <td class="py-2 text-slate-600">{{ r.changed_by_name || '—' }}</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </BaseModal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import BaseModal from '../../components/ui/BaseModal.vue';
import { currenciesApi } from '../../services/api';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { formatRate, useCurrencies } from '../../composables/useCurrencies';
import { BASE_SYMBOL } from '../../constants/currency';
import { apiMessage, formatDateTime } from '../../utils/format';

const PRESETS = [
    { code: 'SYP', name: 'ليرة سورية', symbol: 'ل.س', decimals: 0 },
    { code: 'EUR', name: 'يورو', symbol: '€', decimals: 2 },
    { code: 'TRY', name: 'ليرة تركية', symbol: '₺', decimals: 2 },
    { code: 'AED', name: 'درهم إماراتي', symbol: 'د.إ', decimals: 2 },
    { code: 'SAR', name: 'ريال سعودي', symbol: 'ر.س', decimals: 2 },
];

const toast = useToast();
const shared = useCurrencies();

const list = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const adding = ref(false);
const editing = ref(null);
const rates = reactive({});
const draft = reactive({ code: '', name: '', symbol: '', exchange_rate: '', decimals: 2, show_on_invoices: false });
const edit = reactive({ name: '', symbol: '', decimals: 2 });
const history = ref(null);
const historyRows = ref([]);
const historyLoading = ref(false);

const base = computed(() => list.value.find((c) => c.is_base));
const others = computed(() => list.value.filter((c) => !c.is_base));
const availablePresets = computed(() => PRESETS.filter((p) => !list.value.some((c) => c.code === p.code)));

function hydrate(rows) {
    list.value = rows;
    for (const c of rows) rates[c.id] = Number(c.exchange_rate).toString();
}

function rateDirty(c) {
    return rates[c.id] !== '' && Number(rates[c.id]) !== Number(c.exchange_rate);
}

function replace(row) {
    const i = list.value.findIndex((c) => c.id === row.id);
    const merged = { ...list.value[i], ...row };
    list.value.splice(i, 1, merged);
    rates[row.id] = Number(row.exchange_rate).toString();
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        hydrate(await currenciesApi.index());
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر تحميل العملات');
    } finally {
        loading.value = false;
    }
}

async function run(action, success) {
    busy.value = true;
    error.value = '';
    try {
        const result = await action();
        if (success) toast.success(success);
        // Open money forms read the shared list; keep it in step.
        shared.refresh();
        return result;
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر الحفظ');
        return null;
    } finally {
        busy.value = false;
    }
}

function startAdd() {
    Object.assign(draft, { code: '', name: '', symbol: '', exchange_rate: '', decimals: 2, show_on_invoices: false });
    adding.value = true;
}

function applyPreset(preset) {
    Object.assign(draft, preset);
}

async function create() {
    const data = await run(() => currenciesApi.store({ ...draft }), 'تمت إضافة العملة');
    if (!data) return;
    adding.value = false;
    await load();
}

async function saveRate(c) {
    const ok = await confirm({
        title: 'تغيير سعر الصرف',
        message: `1 ${BASE_SYMBOL} = ${formatRate(rates[c.id])} ${c.symbol}`,
        detail: `السعر الحالي ${formatRate(c.exchange_rate)}. يسري على السندات الجديدة فقط.`,
        confirmLabel: 'اعتماد السعر',
        tone: 'primary',
    });
    if (!ok) return;

    const data = await run(() => currenciesApi.update(c.id, { exchange_rate: rates[c.id] }), 'تم تحديث سعر الصرف');
    if (data?.data) replace(data.data);
}

async function patch(c, changes) {
    const data = await run(() => currenciesApi.update(c.id, changes));
    if (data?.data) replace(data.data);
}

function startEdit(c) {
    Object.assign(edit, { name: c.name, symbol: c.symbol, decimals: c.decimals });
    editing.value = c.id;
}

async function saveEdit(c) {
    const data = await run(() => currenciesApi.update(c.id, { ...edit }), 'تم حفظ العملة');
    if (!data?.data) return;
    replace(data.data);
    editing.value = null;
}

async function remove(c) {
    const ok = await confirm({
        title: 'حذف العملة',
        message: `حذف ${c.name}؟`,
        confirmLabel: 'حذف',
        tone: 'danger',
    });
    if (!ok) return;

    const data = await run(() => currenciesApi.destroy(c.id), 'تم حذف العملة');
    if (data) list.value = list.value.filter((row) => row.id !== c.id);
}

async function openHistory(c) {
    history.value = c;
    historyRows.value = [];
    historyLoading.value = true;
    try {
        historyRows.value = await currenciesApi.rates(c.id);
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر تحميل السجلّ');
    } finally {
        historyLoading.value = false;
    }
}

onMounted(load);
</script>
