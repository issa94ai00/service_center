<template>
    <form class="mx-auto max-w-4xl space-y-5 pb-32" @submit.prevent="submit">
        <!-- ------------------------------------------------------------ head -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ isEdit ? editTitle : newTitle }}</h1>
                <p class="text-sm text-slate-500">{{ subtitle }}</p>
            </div>
            <button type="button" class="text-sm text-slate-500 transition hover:text-slate-900" @click="$router.back()">
                → عودة
            </button>
        </div>

        <!-- نوع السند أوّل قرار في الشاشة. كان يأتي من رابط القائمة فقط، فمن
             فتحها على النوع الخطأ كان عليه الرجوع والبدء من جديد. -->
        <div
            v-if="!isEdit"
            class="grid grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
            role="group"
            aria-label="نوع السند"
        >
            <button
                v-for="tab in TYPE_TABS"
                :key="tab.value"
                type="button"
                :aria-pressed="form.payment_type === tab.value"
                class="rounded-lg px-2 py-2 text-xs font-semibold transition sm:text-sm"
                :class="form.payment_type === tab.value
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-600 hover:bg-slate-50'"
                @click="switchType(tab.value)"
            >
                <span aria-hidden="true">{{ tab.icon }}</span>
                <span class="ms-1">{{ tab.label }}</span>
            </button>
        </div>

        <!-- ---------------------------------------------------------- basics -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-4">
                    <Field :label="partyLabel" required>
                        <!-- العملاء والموردون يمرّون عبر منتقي بحث: قوائمهم هي التي
                             تطول. خبراء الصيانة يبقون عدداً محدوداً فتكفيهم قائمة. -->
                        <CustomerSelect
                            v-if="isIncoming"
                            ref="partySelect"
                            v-model="form.party_id"
                            :fetcher="salesApi.customerOptions"
                            :customer="loadedParty"
                            :invalid="Boolean(partyError)"
                            recent-label="آخر العملاء الذين اشتروا"
                            @change="onPartyChange"
                        />
                        <SupplierSelect
                            v-else-if="!isTechnician"
                            ref="partySelect"
                            v-model="form.party_id"
                            :supplier="loadedParty"
                            :invalid="Boolean(partyError)"
                            @change="onPartyChange"
                        />
                        <select
                            v-else
                            v-model="form.party_id"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                            :class="partyError ? INVALID_FIELD : VALID_FIELD"
                        >
                            <option value="">اختر {{ partyLabel }}...</option>
                            <option v-for="p in parties" :key="p.id" :value="p.id">
                                {{ p.name }}{{ p.specialty ? ` — ${p.specialty}` : '' }}
                            </option>
                        </select>
                        <p v-if="partyError" class="mt-1 text-xs text-red-600">{{ partyError }}</p>
                    </Field>
                </div>

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
                                @input="amountTouched = Boolean($event.target.value)"
                            />
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">
                                {{ CURRENCY }}
                            </span>
                        </div>
                        <p v-if="errors.amount" class="mt-1 text-xs text-red-600">{{ errors.amount }}</p>
                    </Field>
                </div>

                <!-- ثلاث طرق فقط: أزرار تُقرأ وتُضغط مرة واحدة أسرع من قائمة
                     تُفتح ثم يُختار منها. -->
                <div class="sm:col-span-3">
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

                <div class="sm:col-span-3">
                    <Field label="تاريخ الدفعة">
                        <input
                            v-model="form.payment_date"
                            type="date"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                            :class="errors.payment_date ? INVALID_FIELD : VALID_FIELD"
                        />
                        <p v-if="errors.payment_date" class="mt-1 text-xs text-red-600">{{ errors.payment_date }}</p>
                        <p v-else-if="isFutureDate" class="mt-1 text-xs text-amber-700">التاريخ في المستقبل — تأكّد أنه مقصود</p>
                    </Field>
                </div>

                <div class="sm:col-span-3">
                    <Field label="رقم مرجعي">
                        <input
                            v-model.trim="form.reference_no"
                            type="text"
                            maxlength="50"
                            :placeholder="referencePlaceholder"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                            :class="errors.reference_no ? INVALID_FIELD : VALID_FIELD"
                        />
                        <p v-if="errors.reference_no" class="mt-1 text-xs text-red-600">{{ errors.reference_no }}</p>
                        <!-- الترقيم الآلي يحدث عند الترحيل لا عند الحفظ: قوله هنا
                             يمنع اختراع أرقام يدوية تصطدم بالسلسلة. -->
                        <p v-else class="mt-1 text-xs text-slate-400">اتركه فارغاً ليُرقّم آلياً عند الترحيل</p>
                    </Field>
                </div>

                <div class="sm:col-span-3">
                    <Field label="ملاحظات">
                        <input
                            v-model.trim="form.notes"
                            type="text"
                            placeholder="سبب الدفعة، من استلمها، أي تفصيل يُسأل عنه لاحقاً"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        />
                    </Field>
                </div>
            </div>
        </div>

        <!-- سند الخبير لا يُخصَّص على فواتير: لا شيء يُسدَّد، بل رصيد يُنقص. -->
        <div v-if="isTechnician" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5">
            <h2 class="font-bold text-slate-900">صرف مستحقات</h2>
            <p class="mt-1 text-sm text-slate-600">
                يُنقص هذا السند رصيد الخبير بمقدار
                <strong class="tabular-nums text-slate-900">{{ formatMoney(amountValue) }} {{ CURRENCY }}</strong>
                عند اعتماده. لا يُخصَّص على فواتير.
            </p>
        </div>

        <!-- ------------------------------------------------------ allocation -->
        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-slate-900">
                            التخصيص على الفواتير المفتوحة
                            <span v-if="openInvoices.length" class="text-sm font-normal text-slate-400">
                                ({{ openInvoices.length }})
                            </span>
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ isIncoming ? 'فواتير بيع' : 'فواتير شراء' }} غير مسدّدة، الأقدم أولاً — يجب أن يساوي
                            المخصَّص مبلغ السند كاملاً
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="openInvoices.length"
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-40"
                            :disabled="amountCents <= 0"
                            title="يوزّع المبلغ على الفواتير الأقدم أولاً"
                            @click="distributeFromAmount(true)"
                        >
                            توزيع تلقائي
                        </button>
                        <button
                            v-if="openInvoices.length"
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                            title="يخصّص كامل المتبقي من كل فاتورة ويضبط المبلغ على المجموع"
                            @click="settleEverything"
                        >
                            تسديد الكل ({{ formatMoney(openRemainingTotal) }})
                        </button>
                        <button
                            v-if="allocCents > 0"
                            type="button"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="clearAll"
                        >
                            تصفير
                        </button>
                    </div>
                </div>

                <!-- شريط المطابقة: الخادم يرفض الترحيل إن لم يساوِ المخصَّص المبلغ،
                     فالفرق يجب أن يكون مرئياً وهو يُكتب لا بعد الحفظ. -->
                <div v-if="form.party_id" class="mt-4">
                    <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="text-slate-500">
                            المخصَّص
                            <b class="ms-1 tabular-nums text-slate-900">{{ formatMoney(allocValue) }}</b>
                            <span class="text-slate-400">من {{ formatMoney(amountValue) }}</span>
                        </span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="matchBadgeClass"
                            aria-live="polite"
                        >
                            {{ matchLabel }}
                        </span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="matchBarClass"
                            :style="{ width: matchBarWidth }"
                        ></div>
                    </div>
                    <button
                        v-if="allocCents > 0 && allocCents !== amountCents"
                        type="button"
                        class="mt-2 text-xs font-medium text-blue-600 underline-offset-2 hover:underline"
                        @click="matchAmountToAllocations"
                    >
                        اجعل المبلغ يساوي المخصَّص ({{ formatMoney(allocValue) }})
                    </button>
                </div>

                <!-- البحث يظهر حين تصبح القائمة أطول من شاشة واحدة -->
                <input
                    v-if="openInvoices.length > 6"
                    v-model.trim="invoiceSearch"
                    type="search"
                    placeholder="بحث برقم الفاتورة..."
                    class="mt-3 w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
                <p v-if="hiddenAllocations" class="mt-2 text-xs text-amber-700">
                    البحث يُخفي {{ hiddenAllocations }} فاتورة عليها تخصيص — المجموع أعلاه يحسبها.
                </p>
            </div>

            <TableSkeleton v-if="invoicesLoading" :rows="4" :columns="5" />

            <EmptyState
                v-else-if="!form.party_id"
                icon="👤"
                :title="`اختر ${partyLabel} أولاً`"
                hint="تظهر الفواتير المفتوحة للطرف المحدد هنا"
            />

            <EmptyState
                v-else-if="!openInvoices.length"
                icon="🧾"
                :title="`لا توجد فواتير ${isIncoming ? 'بيع' : 'شراء'} معلقة`"
                :hint="`لا شيء يُسدَّد لهذا ${partyLabel} — كل فواتيره المعتمدة مسدّدة`"
            />

            <div v-else>
                <!-- جدول على الشاشة الواسعة -->
                <table class="hidden w-full text-right text-sm sm:table">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-3 py-2">الفاتورة</th>
                            <th class="px-3 py-2">التاريخ</th>
                            <th class="px-3 py-2 text-left">الإجمالي</th>
                            <th class="px-3 py-2 text-left">المتبقي</th>
                            <th class="w-36 px-3 py-2">المخصَّص</th>
                            <th class="w-10 px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="!visibleInvoices.length" class="text-slate-400">
                            <td colspan="6" class="px-3 py-6 text-center">لا فاتورة مطابقة للبحث</td>
                        </tr>
                        <tr
                            v-for="inv in visibleInvoices"
                            :key="inv.invoice_id"
                            class="transition-colors"
                            :class="allocCentsFor(inv) > 0 ? 'bg-blue-50/50' : ''"
                        >
                            <td class="px-3 py-2 align-middle">
                                <span class="font-medium" dir="ltr">{{ inv.invoice_no }}</span>
                                <span
                                    v-if="inv.off_list"
                                    class="ms-2 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700"
                                >
                                    سُدّدت بسند آخر
                                </span>
                                <span
                                    v-else-if="isFullySettled(inv)"
                                    class="ms-2 rounded bg-emerald-100 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700"
                                >
                                    تُسدَّد بالكامل
                                </span>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <div>{{ formatDate(inv.invoice_date) }}</div>
                                <div class="text-xs text-slate-400">{{ relativeDays(inv.invoice_date) }}</div>
                            </td>
                            <td class="px-3 py-2 text-left align-middle tabular-nums text-slate-500">
                                {{ formatMoney(inv.total) }}
                            </td>
                            <td class="px-3 py-2 text-left align-middle">
                                <!-- المتبقي زرّ لأنه الرقم الذي يُنسخ إلى الخانة
                                     في معظم الأحيان: دفعة تُسدِّد فاتورة كاملة. -->
                                <button
                                    type="button"
                                    class="font-semibold tabular-nums text-slate-900 underline-offset-2 hover:text-blue-700 hover:underline"
                                    title="تخصيص كامل المتبقي"
                                    @click="payFull(inv)"
                                >
                                    {{ formatMoney(inv.remaining) }}
                                </button>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <input
                                    :value="allocFor(inv)"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :max="capFor(inv)"
                                    inputmode="decimal"
                                    :aria-label="`المبلغ المخصص للفاتورة ${inv.invoice_no}`"
                                    class="w-full rounded-lg border px-2 py-1.5 text-sm tabular-nums focus:outline-none focus:ring-2"
                                    :class="allocCentsFor(inv) > 0 ? 'border-blue-300 focus:border-blue-500 focus:ring-blue-100' : VALID_FIELD"
                                    @input="setAlloc(inv, $event.target.value)"
                                />
                            </td>
                            <td class="px-3 py-2 text-left align-middle">
                                <button
                                    v-if="allocCentsFor(inv) > 0"
                                    type="button"
                                    :aria-label="`إلغاء تخصيص الفاتورة ${inv.invoice_no}`"
                                    class="text-slate-400 transition hover:text-red-600"
                                    @click="clearAlloc(inv)"
                                >
                                    ✕
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- بطاقات على الهاتف: ستة أعمدة لا تُقرأ على ٤٠٠ بكسل -->
                <ul class="divide-y divide-slate-100 sm:hidden">
                    <li v-if="!visibleInvoices.length" class="px-4 py-6 text-center text-sm text-slate-400">
                        لا فاتورة مطابقة للبحث
                    </li>
                    <li
                        v-for="inv in visibleInvoices"
                        :key="inv.invoice_id"
                        class="space-y-2 p-4"
                        :class="allocCentsFor(inv) > 0 ? 'bg-blue-50/50' : ''"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium" dir="ltr">{{ inv.invoice_no }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ formatDate(inv.invoice_date) }} · {{ relativeDays(inv.invoice_date) }}
                                </p>
                            </div>
                            <span
                                v-if="inv.off_list"
                                class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700"
                            >
                                سُدّدت بسند آخر
                            </span>
                            <span
                                v-else-if="isFullySettled(inv)"
                                class="shrink-0 rounded bg-emerald-100 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700"
                            >
                                تُسدَّد بالكامل
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>الإجمالي <b class="tabular-nums text-slate-700">{{ formatMoney(inv.total) }}</b></span>
                            <span>المتبقي <b class="tabular-nums text-slate-900">{{ formatMoney(inv.remaining) }}</b></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                :value="allocFor(inv)"
                                type="number"
                                step="0.01"
                                min="0"
                                :max="capFor(inv)"
                                inputmode="decimal"
                                :aria-label="`المبلغ المخصص للفاتورة ${inv.invoice_no}`"
                                class="w-full rounded-lg border px-2 py-2 text-sm tabular-nums focus:outline-none focus:ring-2"
                                :class="allocCentsFor(inv) > 0 ? 'border-blue-300 focus:border-blue-500 focus:ring-blue-100' : VALID_FIELD"
                                @input="setAlloc(inv, $event.target.value)"
                            />
                            <button
                                type="button"
                                class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                @click="payFull(inv)"
                            >
                                كامل
                            </button>
                            <button
                                v-if="allocCentsFor(inv) > 0"
                                type="button"
                                :aria-label="`إلغاء تخصيص الفاتورة ${inv.invoice_no}`"
                                class="shrink-0 px-1 text-slate-400 transition hover:text-red-600"
                                @click="clearAlloc(inv)"
                            >
                                ✕
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

        <!-- شريط ملتصق: المبلغ والحفظ يبقيان في المتناول مهما طالت قائمة الفواتير -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur lg:ps-64">
            <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="text-sm">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-500">
                        <span>
                            {{ isIncoming ? 'مقبوض' : 'مدفوع' }}
                            <b class="tabular-nums text-slate-900">{{ formatMoney(amountValue) }} {{ CURRENCY }}</b>
                        </span>
                        <span v-if="!isTechnician && remainingCents !== 0" :class="remainingCents > 0 ? 'text-amber-700' : 'text-red-600'">
                            {{ remainingCents > 0 ? 'بلا تخصيص' : 'زيادة' }}
                            <b class="tabular-nums">{{ formatMoney(Math.abs(remainingCents) / 100) }}</b>
                        </span>
                    </div>
                    <p v-if="blockedReason" class="mt-0.5 text-xs text-slate-400">{{ blockedReason }}</p>
                    <p v-else class="mt-0.5 text-xs text-emerald-700">جاهز للحفظ كمسودة</p>
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
                        :disabled="saving || Boolean(blockedReason)"
                        class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ saving ? 'جارٍ الحفظ...' : (isEdit ? 'حفظ التعديل' : 'حفظ مسودة') }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Field from '../../components/ui/FormField.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import TableSkeleton from '../../components/ui/TableSkeleton.vue';
import CustomerSelect from '../../components/forms/CustomerSelect.vue';
import SupplierSelect from '../../components/forms/SupplierSelect.vue';
import { paymentsApi, salesApi, techniciansApi } from '../../services/api';
import { useToast } from '../../composables/useToast';
import { PAYMENT_METHODS } from '../../constants/paymentMethods';
import {
    apiFieldErrors,
    apiMessage,
    formatDate,
    formatMoney,
    isoDate,
    relativeDays,
    toLatinDigits,
} from '../../utils/format';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const CURRENCY = 'ل.س';
const VALID_FIELD = 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
const INVALID_FIELD = 'border-red-300 focus:border-red-500 focus:ring-red-100';

const TYPE_TABS = [
    { value: 'CUSTOMER', label: 'قبض من عميل', icon: '💵' },
    { value: 'SUPPLIER', label: 'دفع لمورد', icon: '💸' },
    { value: 'TECHNICIAN', label: 'صرف لخبير', icon: '🔧' },
];
const TYPES = TYPE_TABS.map((tab) => tab.value);

const isEdit = Boolean(route.params.id);
const typeFromQuery = TYPES.includes(route.query.type) ? route.query.type : 'CUSTOMER';

const form = reactive({
    payment_type: typeFromQuery,
    party_id: '',
    amount: '',
    payment_method: 'CASH',
    reference_no: '',
    payment_date: isoDate(),
    notes: '',
});

const parties = ref([]);
const loadedParty = ref(null);
const partySelect = ref(null);
const amountInput = ref(null);
const openInvoices = ref([]);
const allocations = ref({});
const invoicesLoading = ref(false);
const invoiceSearch = ref('');
const saving = ref(false);
const error = ref('');
const errors = ref({});

/**
 * أي الحقلين قاده المستخدم بنفسه.
 *
 * الشاشة تملأ الطرف الآخر تلقائياً: مبلغ يُكتب يتوزّع على الفواتير الأقدم،
 * وفواتير تُختار ترفع المبلغ إلى مجموعها. أوّل تعديل يدوي على أحد الطرفين
 * يُسكت التلقائي في اتجاهه، فلا يُمحى عمل المستخدم من تحت يده.
 */
const amountTouched = ref(false);
const allocTouched = ref(false);

// خانة مبلغ فُرّغت ليست قراراً: تفريغها يعيد تشغيل التلقائي كما كان قبل الكتابة.

const isIncoming = computed(() => form.payment_type === 'CUSTOMER');
const isTechnician = computed(() => form.payment_type === 'TECHNICIAN');

const partyLabel = computed(() =>
    isTechnician.value ? 'خبير الصيانة' : isIncoming.value ? 'العميل' : 'المورد'
);

const newTitle = computed(() =>
    isTechnician.value
        ? 'سند صرف لخبير صيانة'
        : isIncoming.value
            ? 'سند قبض من عميل'
            : 'سند دفع لمورد'
);

const editTitle = computed(() =>
    isTechnician.value ? 'تعديل سند صرف' : isIncoming.value ? 'تعديل سند قبض' : 'تعديل سند دفع'
);

const subtitle = computed(() =>
    isTechnician.value
        ? 'مسودة — تُنقص رصيد الخبير عند الترحيل'
        : `مسودة — تُخصص على فواتير ${isIncoming.value ? 'بيع' : 'شراء'} وتُعتمد عند الترحيل`
);

const referencePlaceholder = computed(() =>
    form.payment_method === 'BANK' ? 'رقم الحوالة أو الإشعار البنكي' : 'رقم الإيصال إن وُجد'
);

const isFutureDate = computed(() => Boolean(form.payment_date) && form.payment_date > isoDate());

const partyError = computed(
    () => errors.value.party
        || errors.value.customer_id
        || errors.value.supplier_id
        || errors.value.technician_id
        || ''
);

// ------------------------------------------------------------------- money
//
// المقارنة بالقروش: 0.1 + 0.2 لا تساوي 0.3 في الفاصلة العائمة، وشرط الخادم
// «المخصَّص = المبلغ» يُقاس بالقروش أصلاً.

function toCents(value) {
    return Math.round((Number(value) || 0) * 100);
}

function fromCents(value) {
    return (value / 100).toFixed(2);
}

const amountCents = computed(() => toCents(form.amount));
const amountValue = computed(() => amountCents.value / 100);

const allocCents = computed(() =>
    openInvoices.value.reduce((sum, inv) => sum + allocCentsFor(inv), 0)
);
const allocValue = computed(() => allocCents.value / 100);
const remainingCents = computed(() => amountCents.value - allocCents.value);

const openRemainingTotal = computed(() =>
    openInvoices.value.reduce((sum, inv) => sum + capCents(inv), 0) / 100
);

const matchLabel = computed(() => {
    if (amountCents.value <= 0) return 'أدخل المبلغ';
    if (allocCents.value === 0) return 'لم يُخصَّص بعد';
    if (remainingCents.value > 0) return `يتبقى ${formatMoney(remainingCents.value / 100)}`;
    if (remainingCents.value < 0) return `زيادة ${formatMoney(-remainingCents.value / 100)}`;
    return 'مطابق ✓';
});

const matchState = computed(() => {
    if (amountCents.value <= 0 || allocCents.value === 0) return 'idle';
    if (remainingCents.value === 0) return 'match';
    return remainingCents.value > 0 ? 'partial' : 'over';
});

const matchBadgeClass = computed(() => ({
    idle: 'bg-slate-100 text-slate-500',
    partial: 'bg-amber-100 text-amber-800',
    match: 'bg-emerald-100 text-emerald-700',
    over: 'bg-red-100 text-red-700',
}[matchState.value]));

const matchBarClass = computed(() => ({
    idle: 'bg-slate-300',
    partial: 'bg-amber-400',
    match: 'bg-emerald-500',
    over: 'bg-red-500',
}[matchState.value]));

const matchBarWidth = computed(() => {
    if (amountCents.value <= 0) return allocCents.value > 0 ? '100%' : '0%';
    const ratio = Math.min(1, allocCents.value / amountCents.value);
    return `${Math.round(ratio * 100)}%`;
});

// -------------------------------------------------------------- allocations

/** سقف الفاتورة: متبقّيها، أو ما خُصِّص لها فعلاً إن كان أكبر (سند قيد التعديل). */
function capCents(inv) {
    return Math.max(toCents(inv.remaining), toCents(inv.cap ?? 0));
}

function capFor(inv) {
    return fromCents(capCents(inv));
}

function allocFor(inv) {
    return allocations.value[inv.invoice_id] ?? '';
}

function allocCentsFor(inv) {
    return toCents(allocations.value[inv.invoice_id]);
}

function isFullySettled(inv) {
    return allocCentsFor(inv) > 0 && allocCentsFor(inv) === capCents(inv);
}

function writeAlloc(inv, valueCents) {
    // الصفر يُكتب خانةً فارغة لا «0.00»: قائمة فيها عشرون سطراً أصفاراً تُقرأ
    // كأنها مليئة، والعين تضيع عن السطرين المخصَّصين فعلاً.
    allocations.value = {
        ...allocations.value,
        [inv.invoice_id]: valueCents > 0 ? fromCents(valueCents) : '',
    };
}

/**
 * تعديل يدوي لسطر: يُثبِّت التخصيص، ويرفع المبلغ معه إن لم يكتبه المستخدم.
 *
 * ما يُكتب يُخزَّن كما كُتب ولا يُعاد تنسيقه: إعادة كتابة «5» إلى «5.00» في
 * الخانة تدفع المؤشر إلى آخرها، فمن أراد ٥٠ حصل على ٥. التنسيق يحدث عند
 * التقريب إلى السقف فقط، وهو تصحيح مقصود يجب أن يُرى.
 */
function setAlloc(inv, value) {
    const typed = toLatinDigits(value);
    const cents = toCents(typed);
    allocTouched.value = true;

    if (cents > capCents(inv)) writeAlloc(inv, capCents(inv));
    else if (cents < 0) writeAlloc(inv, 0);
    else allocations.value = { ...allocations.value, [inv.invoice_id]: typed };

    syncAmountFromAllocations();
}

function payFull(inv) {
    allocTouched.value = true;
    writeAlloc(inv, capCents(inv));
    syncAmountFromAllocations();
}

function clearAlloc(inv) {
    allocTouched.value = true;
    writeAlloc(inv, 0);
    syncAmountFromAllocations();
}

function clearAll() {
    allocTouched.value = true;
    allocations.value = Object.fromEntries(openInvoices.value.map((inv) => [inv.invoice_id, '']));
    syncAmountFromAllocations();
}

/** المبلغ يتبع الفواتير المختارة ما لم يكتبه المستخدم بنفسه. */
function syncAmountFromAllocations() {
    if (amountTouched.value) return;
    form.amount = allocCents.value > 0 ? fromCents(allocCents.value) : '';
}

/** توزيع المبلغ على الأقدم أولاً — وهو ترتيب ورودها من الخادم. */
function distributeFromAmount(manual = false) {
    let left = amountCents.value;
    const next = {};
    for (const inv of openInvoices.value) {
        const take = Math.max(0, Math.min(left, capCents(inv)));
        next[inv.invoice_id] = take > 0 ? fromCents(take) : '';
        left -= take;
    }
    allocations.value = next;

    // الضغط على «توزيع تلقائي» قرار صريح: يُثبِّت التوزيع فلا يعيد المبلغ كتابته.
    if (manual) allocTouched.value = true;
}

function settleEverything() {
    allocTouched.value = true;
    amountTouched.value = true;
    allocations.value = Object.fromEntries(
        openInvoices.value.map((inv) => [inv.invoice_id, fromCents(capCents(inv))])
    );
    form.amount = fromCents(allocCents.value);
}

function matchAmountToAllocations() {
    amountTouched.value = true;
    form.amount = fromCents(allocCents.value);
    errors.value = { ...errors.value, amount: undefined };
}

const visibleInvoices = computed(() => {
    const query = toLatinDigits(invoiceSearch.value).trim().toLowerCase();
    if (!query) return openInvoices.value;
    return openInvoices.value.filter((inv) =>
        String(inv.invoice_no ?? '').toLowerCase().includes(query)
        || String(inv.invoice_date ?? '').includes(query)
    );
});

/** تخصيصات خارج نتيجة البحث: المجموع يحسبها والقائمة لا تُظهرها. */
const hiddenAllocations = computed(() => {
    if (!invoiceSearch.value) return 0;
    const shown = new Set(visibleInvoices.value.map((inv) => inv.invoice_id));
    return openInvoices.value.filter((inv) => !shown.has(inv.invoice_id) && allocCentsFor(inv) > 0).length;
});

// ------------------------------------------------------------------ loading

/**
 * رقم الطلب الأخير.
 *
 * تبديل العميل مرّتين بسرعة يُطلق طلبين؛ الأبطأ لو وصل ثانياً لرسم فواتير
 * عميل لم يعد مختاراً، وعلى سند قيد التعديل ليمحو الأسطر التي أُلحقت بعده.
 */
let invoiceRequest = 0;

async function loadOpenInvoices() {
    const token = ++invoiceRequest;

    if (isTechnician.value || !form.party_id) {
        openInvoices.value = [];
        invoicesLoading.value = false;
        return;
    }

    invoicesLoading.value = true;
    try {
        const list = await paymentsApi.openInvoices({
            party_type: form.payment_type,
            party_id: form.party_id,
        });
        if (token !== invoiceRequest) return;

        openInvoices.value = list;
        for (const inv of list) {
            if (!(inv.invoice_id in allocations.value)) {
                allocations.value = { ...allocations.value, [inv.invoice_id]: '' };
            }
        }
        if (!allocTouched.value && amountCents.value > 0) distributeFromAmount();
    } catch (e) {
        if (token !== invoiceRequest) return;
        openInvoices.value = [];
        error.value = apiMessage(e, 'تعذّر تحميل الفواتير المفتوحة');
    } finally {
        if (token === invoiceRequest) invoicesLoading.value = false;
    }
}

/** العملاء والموردون يبحث عنهم المنتقي بنفسه؛ هنا يبقى الخبراء فقط. */
async function loadParties() {
    parties.value = isTechnician.value ? await techniciansApi.options() : [];
}

function onPartyChange(party) {
    loadedParty.value = party;
    if (party) errors.value = { ...errors.value, party: undefined, customer_id: undefined, supplier_id: undefined };
}

/**
 * تبديل النوع يبدّل كل ما تحته: الطرف وفواتيره. يُكتب في العنوان أيضاً حتى
 * يبقى التحديث أو الرابط المحفوظ على النوع الذي كان معروضاً.
 */
function switchType(type) {
    if (form.payment_type === type) return;
    form.payment_type = type;
    router.replace({ query: { ...route.query, type, technician_id: undefined } });
}

/**
 * تحميل سند محفوظ يكتب الحقول واحداً واحداً، وكل كتابة تُشعل مراقباً يفترض أن
 * المستخدم هو من غيّرها — فيمحو الطرف أو يصفّر تخصيصاته. القفل يُسكت المراقبين
 * حتى تكتمل التهيئة.
 */
const hydrating = ref(isEdit);

watch(() => form.party_id, () => {
    if (hydrating.value) return;

    allocations.value = {};
    allocTouched.value = false;
    invoiceSearch.value = '';
    loadOpenInvoices();

    // بعد اختيار الطرف يأتي المبلغ: نقلة المؤشر إليه توفّر ضغطة في كل سند.
    if (!isEdit && !form.amount) nextTick(() => amountInput.value?.focus());
});

watch(() => form.payment_type, () => {
    if (hydrating.value) return;

    form.party_id = '';
    loadedParty.value = null;
    allocations.value = {};
    openInvoices.value = [];
    allocTouched.value = false;
    errors.value = {};
    error.value = '';
    loadParties();
});

watch(() => form.amount, () => {
    if (hydrating.value) return;

    errors.value = { ...errors.value, amount: undefined };
    if (!allocTouched.value) distributeFromAmount();
});

// ------------------------------------------------------------------- saving

const blockedReason = computed(() => {
    if (!form.party_id) return `اختر ${partyLabel.value}`;
    if (amountCents.value <= 0) return 'أدخل مبلغاً أكبر من صفر';
    if (isTechnician.value) return '';
    if (!openInvoices.value.length) return `لا توجد فواتير ${isIncoming.value ? 'بيع' : 'شراء'} معلقة لتخصيص المبلغ عليها`;
    if (allocCents.value === 0) return 'خصّص المبلغ على فاتورة واحدة على الأقل';
    if (remainingCents.value > 0) return `يتبقى ${formatMoney(remainingCents.value / 100)} بلا تخصيص`;
    if (remainingCents.value < 0) return `المخصَّص يزيد ${formatMoney(-remainingCents.value / 100)} عن المبلغ`;
    return '';
});

function payload() {
    const allocs = openInvoices.value
        .filter((inv) => allocCentsFor(inv) > 0)
        .map((inv) => ({
            invoice_type: isIncoming.value ? 'SALES' : 'PURCHASE',
            sales_invoice_id: isIncoming.value ? inv.invoice_id : undefined,
            purchase_invoice_id: isIncoming.value ? undefined : inv.invoice_id,
            amount: fromCents(allocCentsFor(inv)),
        }));

    return {
        payment_type: form.payment_type,
        customer_id: isIncoming.value ? Number(form.party_id) : undefined,
        supplier_id: form.payment_type === 'SUPPLIER' ? Number(form.party_id) : undefined,
        technician_id: isTechnician.value ? Number(form.party_id) : undefined,
        amount: fromCents(amountCents.value),
        payment_method: form.payment_method,
        reference_no: form.reference_no || null,
        payment_date: form.payment_date,
        notes: form.notes || null,
        ...(isTechnician.value ? {} : { allocations: allocs }),
    };
}

async function submit() {
    error.value = '';
    errors.value = {};

    // المنتقي مكوّن لا <input required>، فالمتصفح لا يوقف إرسالاً بلا طرف — هذا يوقفه.
    if (!form.party_id) {
        errors.value = { party: `اختر ${partyLabel.value} أولاً` };
        partySelect.value?.focus?.();
        return;
    }

    if (amountCents.value <= 0) {
        errors.value = { amount: 'المبلغ مطلوب' };
        amountInput.value?.focus();
        return;
    }

    if (blockedReason.value) {
        error.value = blockedReason.value;
        return;
    }

    saving.value = true;
    try {
        const data = isEdit
            ? await paymentsApi.update(route.params.id, payload())
            : await paymentsApi.store(payload());

        toast.success(data.message || 'تم حفظ المسودة');
        router.replace({ name: 'payments', query: { type: form.payment_type } });
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر الحفظ');
        errors.value = apiFieldErrors(e);
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    try {
        await loadParties();
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر تحميل القوائم');
    }

    // الوصول من زر «صرف مستحقات» في شاشة الخبراء يأتي بالخبير جاهزاً.
    if (!isEdit && route.query.technician_id) {
        form.party_id = Number(route.query.technician_id);
    }

    if (!isEdit) return;

    try {
        const { data: payment } = await paymentsApi.show(route.params.id);

        form.payment_type = payment.payment_type;
        form.party_id = payment.customer_id ?? payment.supplier_id ?? payment.technician_id ?? '';
        form.amount = payment.amount;
        form.payment_method = payment.payment_method;
        form.reference_no = payment.reference_no || '';
        form.payment_date = payment.payment_date;
        form.notes = payment.notes || '';

        // سند محفوظ: طرفاه مكتوبان، فلا تلقائي يعيد حسابهما.
        amountTouched.value = true;
        allocTouched.value = true;
        loadedParty.value = payment.customer ?? payment.supplier ?? null;

        await loadParties();
        await loadOpenInvoices();

        for (const alloc of payment.allocations || []) {
            const invoiceId = alloc.sales_invoice_id ?? alloc.purchase_invoice_id;
            if (!invoiceId) continue;

            // فاتورة سُدّدت بالكامل بسند آخر تسقط من قائمة «المفتوحة»، وسطرها
            // لو غاب عن الشاشة لحُذف تخصيصها بصمت عند أوّل حفظ.
            if (!openInvoices.value.some((inv) => inv.invoice_id === invoiceId)) {
                openInvoices.value = [
                    ...openInvoices.value,
                    {
                        invoice_id: invoiceId,
                        invoice_no: alloc.invoice_no ?? `#${invoiceId}`,
                        invoice_date: alloc.invoice_date,
                        total: alloc.total,
                        remaining: alloc.remaining ?? 0,
                        cap: alloc.amount,
                        off_list: true,
                    },
                ];
            }

            allocations.value = { ...allocations.value, [invoiceId]: String(alloc.amount) };
        }
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر تحميل الدفعة');
    } finally {
        // المراقبون المؤجَّلون من كتابة الحقول يُنفَّذون في هذه الدورة والقفل
        // ما زال مرفوعاً، فيسقطون؛ ثم تُفتح الشاشة لتعديل المستخدم.
        await nextTick();
        hydrating.value = false;
    }
});
</script>
