<template>
    <BaseModal
        :open="open"
        :title="`حساب ${repair?.receipt_no ?? ''}`"
        :subtitle="repair?.customer?.name || 'الحساب المالي لبلاغ الصيانة'"
        :max-width="detailed && !settled ? 'max-w-2xl' : 'max-w-xl'"
        :close-on-overlay="false"
        @close="$emit('close')"
    >
        <div v-if="loading" class="space-y-3">
            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
            <div class="h-32 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <div v-else-if="!summary" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ error || 'تعذّر تحميل حساب البلاغ' }}
        </div>

        <div v-else class="space-y-4">
            <!-- Where the money stands, before anything is asked of the user. -->
            <div class="grid grid-cols-3 gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-center">
                <div>
                    <p class="text-[11px] text-slate-500">{{ settled ? 'قيمة الفاتورة' : 'التكلفة' }}</p>
                    <p class="mt-0.5 text-sm font-bold tabular-nums text-slate-900">{{ money(headlineCost) }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-500">المدفوع</p>
                    <p class="mt-0.5 text-sm font-bold tabular-nums text-emerald-700">{{ money(paid) }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-500">{{ outstanding < 0 ? 'لصالح العميل' : 'المتبقي' }}</p>
                    <p
                        class="mt-0.5 text-sm font-bold tabular-nums"
                        :class="outstanding > 0 ? 'text-amber-700' : outstanding < 0 ? 'text-blue-700' : 'text-slate-400'"
                    >{{ money(Math.abs(outstanding)) }}</p>
                </div>
            </div>

            <!-- Settled: the documents, and whatever is still owed on them. -->
            <div v-if="settled" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm">
                    <span class="font-medium text-emerald-800">
                        ✅ تمت التسوية
                        <span v-if="summary.settled_at" class="text-xs font-normal text-emerald-700">· {{ formatDateTime(summary.settled_at) }}</span>
                    </span>
                    <PrintButton
                        v-if="summary.invoice"
                        kind="invoice"
                        title="طباعة الفاتورة"
                        :label="`🖨️ الفاتورة ${summary.invoice.invoice_no}`"
                        busy-label="جارٍ الإصدار…"
                        :load="(paper) => documentsApi.salesInvoice(summary.invoice.id, paper)"
                        wrapper-class="rounded-lg border border-emerald-300 bg-white text-emerald-800"
                        button-class="rounded-s-lg px-2.5 py-1 text-xs font-medium transition hover:bg-emerald-100 disabled:opacity-50"
                        caret-class="rounded-e-lg border-s border-emerald-300 px-1.5 transition hover:bg-emerald-100 disabled:opacity-50"
                    />
                </div>

                <!-- ما خرج به العميل فعلاً: الأجور والقطع كما قُيِّدت على الفاتورة. -->
                <div v-if="billedLines.length" class="overflow-hidden rounded-xl border border-slate-200">
                    <p class="border-b border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-500">بنود الفاتورة</p>
                    <ul class="divide-y divide-slate-100">
                        <li v-for="(line, i) in billedLines" :key="i" class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                            <span class="flex min-w-0 items-center gap-2">
                                <span
                                    class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                    :class="line.line_type === 'SERVICE' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'"
                                >{{ line.line_type === 'SERVICE' ? 'أجرة' : 'قطعة' }}</span>
                                <span class="truncate text-slate-700">{{ line.description }}</span>
                                <span v-if="Number(line.quantity) !== 1" class="shrink-0 text-slate-400 tabular-nums">
                                    ×{{ formatQty(line.quantity) }}
                                </span>
                            </span>
                            <span class="shrink-0 font-semibold tabular-nums text-slate-800">{{ money(line.line_total) }}</span>
                        </li>
                    </ul>
                </div>

                <p v-if="creditLeft > 0" class="rounded-lg bg-blue-50 px-3 py-2 text-xs text-blue-800">
                    يتبقى للعميل رصيد <strong class="tabular-nums">{{ money(creditLeft) }} {{ currency }}</strong>
                    على حسابه، يمكن استخدامه في فاتورة لاحقة.
                </p>
            </div>

            <!-- Not settled yet: take a deposit now, or close the whole thing. -->
            <div v-else class="flex rounded-xl bg-slate-100 p-1 text-sm">
                <button
                    v-for="mode in MODES"
                    :key="mode.value"
                    type="button"
                    class="flex-1 rounded-lg px-3 py-1.5 font-medium transition"
                    :class="tab === mode.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    @click="tab = mode.value"
                >{{ mode.label }}</button>
            </div>

            <!-- ── قبض دفعة ────────────────────────────────────────────── -->
            <form v-if="showCollect" class="space-y-3" @submit.prevent="collect">
                <Field :label="settled ? 'المبلغ المقبوض على الفاتورة' : 'المبلغ المقبوض الآن'">
                    <div class="relative">
                        <input
                            ref="collectInput"
                            v-model="collectForm.amount"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2.5 pe-14 text-base font-semibold tabular-nums focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        />
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">{{ currency }}</span>
                    </div>
                    <div v-if="outstanding > 0" class="mt-2 flex flex-wrap gap-1.5">
                        <button type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs text-slate-600 transition hover:border-blue-400 hover:text-blue-600"
                            @click="collectForm.amount = String(outstanding)">كامل المتبقي ({{ money(outstanding) }})</button>
                        <button type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs text-slate-600 transition hover:border-blue-400 hover:text-blue-600"
                            @click="collectForm.amount = String(round(outstanding / 2))">النصف</button>
                    </div>
                </Field>

                <MethodPicker v-model="collectForm.payment_method" />

                <p class="text-[11px] text-slate-400">
                    يُصدر سند قبض على سلسلة الصيانة <span dir="ltr" class="font-medium text-slate-500">{{ paymentPrefix }}</span>
                </p>

                <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

                <div class="flex gap-2 pt-1">
                    <button type="button" class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="$emit('close')">إغلاق</button>
                    <button
                        type="submit"
                        :disabled="busy || collectAmount <= 0"
                        class="flex-1 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    >{{ busy ? 'جارٍ التسجيل...' : 'تسجيل القبض' }}</button>
                </div>
            </form>

            <!-- ── تسوية وتسليم ────────────────────────────────────────── -->
            <form v-else-if="!settled" class="space-y-3" @submit.prevent="settle">
                <!-- كيف تُبنى الفاتورة: رقم واحد متّفق عليه، أو تفصيل بالأجور
                     وقطع الغيار. الأول هو المعتاد عند الصندوق، فهو الافتراضي. -->
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold text-slate-500">قيمة الصيانة</p>
                    <button
                        type="button"
                        class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
                        :class="detailed ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-700'"
                        @click="toggleDetailed"
                    >{{ detailed ? '↩ العودة لمبلغ إجمالي' : '🧾 تفصيل الأجور وقطع الغيار' }}</button>
                </div>

                <Field v-if="!detailed" label="التكلفة النهائية">
                    <div class="relative">
                        <input
                            ref="totalInput"
                            v-model="form.total"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full rounded-lg border px-3 py-2.5 pe-14 text-base font-semibold tabular-nums focus:outline-none focus:ring-2"
                            :class="fieldClass('total')"
                        />
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">{{ currency }}</span>
                    </div>
                    <div v-if="costSuggestion > 0" class="mt-2 flex flex-wrap gap-1.5">
                        <button type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs text-slate-600 transition hover:border-blue-400 hover:text-blue-600"
                            @click="setTotal(costSuggestion)">{{ suggestionLabel }} ({{ money(costSuggestion) }})</button>
                        <button v-if="paid > 0" type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs text-slate-600 transition hover:border-blue-400 hover:text-blue-600"
                            @click="setTotal(paid)">بقيمة المدفوع ({{ money(paid) }})</button>
                    </div>
                    <p v-if="errors.total" class="mt-1 text-xs text-red-600">{{ errors.total }}</p>
                </Field>

                <!-- ── بنود الفاتورة ───────────────────────────────────── -->
                <div v-else class="rounded-xl border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-3 py-2">
                        <p class="text-[11px] font-semibold text-slate-500">
                            بنود فاتورة الصيانة
                            <span v-if="lines.length" class="font-normal text-slate-400">({{ lines.length }})</span>
                        </p>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="addLabourLine()"
                        >＋ سطر أجرة</button>
                    </div>

                    <div class="border-b border-slate-100 p-3">
                        <ScanBox
                            placeholder="امسح باركود قطعة الغيار أو ابحث بالاسم..."
                            @pick="addFromPick"
                        />
                    </div>

                    <p v-if="!lines.length" class="px-3 py-6 text-center text-xs text-slate-400">
                        أضف أجرة الورشة وقطع الغيار المستخدمة، أو ارجع إلى مبلغ إجمالي.
                    </p>

                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="(line, i) in lines" :key="line.key" class="space-y-2 p-3">
                            <div class="flex items-start gap-2">
                                <span
                                    class="mt-1.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                    :class="line.line_type === 'SERVICE' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'"
                                >{{ line.line_type === 'SERVICE' ? 'أجرة' : 'قطعة' }}</span>
                                <input
                                    v-if="line.line_type === 'SERVICE'"
                                    v-model="line.description"
                                    type="text"
                                    placeholder="وصف العمل المنجز"
                                    class="min-w-0 flex-1 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
                                />
                                <span v-else class="min-w-0 flex-1 truncate py-1.5 text-sm font-medium text-slate-800">{{ line.description }}</span>
                                <button
                                    type="button"
                                    :aria-label="`حذف السطر ${i + 1}`"
                                    class="shrink-0 px-1 py-1 text-red-500 transition hover:text-red-700"
                                    @click="lines.splice(i, 1)"
                                >✕</button>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 ps-7">
                                <label class="flex items-center gap-1 text-[11px] text-slate-500">
                                    الكمية
                                    <input
                                        v-model="line.quantity"
                                        type="number"
                                        step="0.001"
                                        min="0.001"
                                        class="w-20 rounded-lg border px-2 py-1 text-sm tabular-nums focus:outline-none"
                                        :class="stockWarning(line) ? 'border-red-300' : 'border-slate-300 focus:border-blue-500'"
                                    />
                                </label>
                                <label class="flex items-center gap-1 text-[11px] text-slate-500">
                                    السعر
                                    <input
                                        v-model="line.unit_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-sm tabular-nums focus:border-blue-500 focus:outline-none"
                                    />
                                </label>
                                <span class="ms-auto text-sm font-semibold tabular-nums text-slate-900">
                                    {{ money(lineTotal(line)) }}
                                </span>
                            </div>

                            <p v-if="stockWarning(line)" class="ps-7 text-xs text-red-600">{{ stockWarning(line) }}</p>
                        </li>
                    </ul>

                    <div v-if="lines.length" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/70 px-3 py-2">
                        <span class="text-xs text-slate-500">
                            أجور <strong class="tabular-nums text-slate-700">{{ money(labourTotal) }}</strong>
                            · قطع <strong class="tabular-nums text-slate-700">{{ money(partsTotal) }}</strong>
                        </span>
                        <span class="text-sm font-bold tabular-nums text-slate-900">{{ money(totalAmount) }} {{ currency }}</span>
                    </div>

                    <p v-if="errors.items" class="border-t border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600">{{ errors.items }}</p>
                </div>

                <Field label="المبلغ المحصَّل الآن">
                    <div class="relative">
                        <input
                            v-model="form.collect_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            class="w-full rounded-lg border px-3 py-2.5 pe-14 text-base font-semibold tabular-nums focus:outline-none focus:ring-2"
                            :class="fieldClass('collect_amount')"
                        />
                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">{{ currency }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="collectIsFull ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                            @click="form.collect_amount = String(Math.max(0, dueAfterDeposits))"
                        >سدّد المتبقي ({{ money(Math.max(0, dueAfterDeposits)) }})</button>
                        <button
                            type="button"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="collectAmountSettle === 0 ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                            @click="form.collect_amount = '0'"
                        >على الحساب</button>
                    </div>
                    <p v-if="errors.collect_amount" class="mt-1 text-xs text-red-600">{{ errors.collect_amount }}</p>
                    <p v-else-if="overCollecting" class="mt-1 text-xs text-red-600">
                        المبلغ أكبر من المتبقي ({{ money(Math.max(0, dueAfterDeposits)) }}).
                    </p>
                </Field>

                <MethodPicker v-if="collectAmountSettle > 0" v-model="form.payment_method" />

                <!-- نصيب الخبير يُحسب على أجرة الورشة وحدها؛ قطعة الغيار بضاعة
                     مُعاد بيعها ونسبتها تقتطع من هامش المحل لا من عمل الخبير. -->
                <Field v-if="summary.technician" label="نسبة خبير الصيانة">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-slate-700">{{ summary.technician.name }}</span>
                        <div class="relative w-28">
                            <input
                                v-model="form.commission_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                :placeholder="String(summary.technician.commission_rate)"
                                class="w-full rounded-lg border px-3 py-2 pe-7 text-sm tabular-nums focus:outline-none focus:ring-2"
                                :class="fieldClass('commission_rate')"
                            />
                            <span class="pointer-events-none absolute inset-y-0 end-2.5 flex items-center text-xs text-slate-400">٪</span>
                        </div>
                        <span class="text-xs text-slate-500">
                            نصيبه <strong class="tabular-nums text-slate-800">{{ money(technicianShare) }}</strong>
                            <template v-if="detailed && partsTotal > 0">
                                من الأجور <span class="tabular-nums">{{ money(commissionBase) }}</span>
                            </template>
                        </span>
                    </div>
                    <p v-if="errors.commission_rate" class="mt-1 text-xs text-red-600">{{ errors.commission_rate }}</p>
                </Field>

                <!-- What the button is about to do, in one sentence. Posting an
                     invoice and a receipt is irreversible from this screen, so
                     it should never be a surprise. -->
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs leading-relaxed text-slate-600">
                    <p class="mb-1 font-semibold text-slate-700">ما سيحدث عند التأكيد</p>
                    <ul class="space-y-0.5">
                        <li v-if="totalAmount > 0">
                            • إصدار فاتورة صيانة معتمَدة بـ <strong class="tabular-nums text-slate-800">{{ money(totalAmount) }}</strong>
                            على سلسلة <span dir="ltr" class="font-medium">{{ invoicePrefix }}</span>
                        </li>
                        <li v-else-if="partsCount > 0">
                            • إصدار فاتورة صيانة بقيمة صفر على سلسلة <span dir="ltr" class="font-medium">{{ invoicePrefix }}</span> — لإخراج القطع من المخزون
                        </li>
                        <li v-else>• لا فاتورة — تسليم بدون تكلفة</li>
                        <li v-if="partsCount > 0">
                            • خصم <strong class="text-slate-800">{{ partsCount }}</strong>
                            {{ partsCount === 1 ? 'قطعة غيار' : 'من أصناف قطع الغيار' }} من المخزون
                        </li>
                        <li v-if="appliedDeposit > 0">• خصم العربون المدفوع <strong class="tabular-nums text-slate-800">{{ money(appliedDeposit) }}</strong> من الفاتورة</li>
                        <li v-if="collectAmountSettle > 0">
                            • سند قبض بـ <strong class="tabular-nums text-slate-800">{{ money(collectAmountSettle) }}</strong>
                            ({{ methodLabel(form.payment_method) }}) على سلسلة <span dir="ltr" class="font-medium">{{ paymentPrefix }}</span>
                        </li>
                        <li v-if="remainderAfterSettle > 0">• يبقى على العميل <strong class="tabular-nums text-amber-700">{{ money(remainderAfterSettle) }}</strong> على حسابه</li>
                        <li v-else-if="creditAfterSettle > 0">• يبقى للعميل رصيد <strong class="tabular-nums text-blue-700">{{ money(creditAfterSettle) }}</strong></li>
                        <li v-if="technicianShare > 0">
                            • قيد عمولة <strong class="text-slate-800">{{ summary.technician.name }}</strong>
                            بـ <strong class="tabular-nums text-slate-800">{{ money(technicianShare) }}</strong> في حسابه
                        </li>
                        <li>• تحويل حالة الجهاز إلى «تم التسليم»</li>
                    </ul>
                </div>

                <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

                <div class="flex gap-2 pt-1">
                    <button type="button" class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="$emit('close')">إلغاء</button>
                    <button
                        type="submit"
                        :disabled="busy || !canConfirm"
                        class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50"
                    >{{ busy ? 'جارٍ التسوية...' : 'تسوية وتسليم' }}</button>
                </div>

                <!-- The shop that does not use the financial side should not be
                     forced through it just to hand a device back. -->
                <button
                    type="button"
                    class="w-full rounded-lg px-3 py-1.5 text-xs text-slate-400 transition hover:text-slate-600"
                    :disabled="busy"
                    @click="$emit('deliver-only')"
                >تسليم فقط دون تسوية مالية</button>
            </form>

            <!-- ── سجل الدفعات ─────────────────────────────────────────── -->
            <div v-if="summary.payments.length" class="rounded-xl border border-slate-200">
                <p class="border-b border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-500">سندات القبض على هذا البلاغ</p>
                <ul class="divide-y divide-slate-100">
                    <li v-for="payment in summary.payments" :key="payment.id" class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                        <div class="min-w-0">
                            <span class="font-medium tabular-nums text-slate-800" dir="ltr">{{ payment.reference_no || '—' }}</span>
                            <span class="text-slate-400"> · {{ methodLabel(payment.payment_method) }} · {{ formatDate(payment.payment_date) }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span v-if="payment.status !== 'POSTED'" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500">
                                {{ payment.status === 'CANCELLED' ? 'ملغي' : 'مسودة' }}
                            </span>
                            <span class="font-semibold tabular-nums" :class="payment.status === 'POSTED' ? 'text-emerald-700' : 'text-slate-400 line-through'">
                                {{ money(payment.amount) }}
                            </span>
                        </div>
                    </li>
                </ul>
            </div>

        </div>
    </BaseModal>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import BaseModal from '../ui/BaseModal.vue';
import Field from '../ui/FormField.vue';
import MethodPicker from './PaymentMethodPicker.vue';
import ScanBox from '../forms/ScanBox.vue';
import { documentsApi, repairsApi } from '../../services/api';
import PrintButton from '../ui/PrintButton.vue';
import { paymentMethodLabel } from '../../constants/paymentMethods';
import { apiFieldErrors, apiMessage, formatDate, formatDateTime, formatMoney, formatQty } from '../../utils/format';

const props = defineProps({
    open: Boolean,
    repair: { type: Object, default: null },
    /**
     * Which half of the dialog the caller meant: 'settle' when the user asked
     * to hand the device back, 'collect' when they just want to take money.
     * Empty lets the repair's own status decide.
     */
    intent: { type: String, default: '' },
});

const emit = defineEmits(['close', 'updated', 'deliver-only']);

const MODES = [
    { value: 'settle', label: 'تسوية وتسليم' },
    { value: 'collect', label: 'قبض دفعة' },
];

const loading = ref(false);
const busy = ref(false);
const summary = ref(null);
const error = ref('');
const errors = ref({});
const tab = ref('settle');
const totalInput = ref(null);
const collectInput = ref(null);

/**
 * Whether the bill is being itemised. The lump figure is the common hand-over
 * and stays the default; a job that consumed spare parts switches here so the
 * parts leave the shelf with the device.
 */
const detailed = ref(false);
const lines = ref([]);
let lineSeq = 0;

const form = reactive({ total: '', collect_amount: '', payment_method: 'CASH', commission_rate: '' });
const collectForm = reactive({ amount: '', payment_method: 'CASH' });

const currency = computed(() => summary.value?.currency ?? 'ل.س');
const settled = computed(() => Boolean(summary.value?.is_settled));
const paid = computed(() => Number(summary.value?.paid ?? 0));

const invoicePrefix = computed(() => summary.value?.series?.invoice_prefix ?? 'RS-');
const paymentPrefix = computed(() => summary.value?.series?.payment_prefix ?? 'RCV-');

const billedLines = computed(() => summary.value?.invoice?.items ?? []);

/** Once settled the invoice is the truth; before that, the agreed/quoted cost. */
const headlineCost = computed(() =>
    settled.value && summary.value?.invoice
        ? Number(summary.value.invoice.total)
        : Number(summary.value?.chargeable_cost ?? 0)
);

/** Positive: the customer owes. Negative: the shop holds their money. */
const outstanding = computed(() =>
    settled.value && summary.value?.invoice
        ? Number(summary.value.invoice.remaining)
        : Number(summary.value?.due ?? 0)
);

const creditLeft = computed(() => Number(summary.value?.credit ?? 0));

// ------------------------------------------------------------------- the bill

function lineTotal(line) {
    return round((Number(line.quantity) || 0) * (Number(line.unit_price) || 0));
}

const labourTotal = computed(() =>
    round(lines.value.filter((l) => l.line_type === 'SERVICE').reduce((sum, l) => sum + lineTotal(l), 0))
);
const partsTotal = computed(() =>
    round(lines.value.filter((l) => l.line_type === 'MATERIAL').reduce((sum, l) => sum + lineTotal(l), 0))
);
const partsCount = computed(() => (detailed.value ? lines.value.filter((l) => l.line_type === 'MATERIAL').length : 0));
const linesTotal = computed(() => round(labourTotal.value + partsTotal.value));

/** What the customer is charged, from whichever way the bill was built. */
const totalAmount = computed(() => (detailed.value ? linesTotal.value : Number(form.total) || 0));

/** العمولة على الأجور وحدها؛ بلا تفصيل فالفاتورة كلّها أجرة. */
const commissionBase = computed(() => (detailed.value ? labourTotal.value : totalAmount.value));

/** النسبة السارية على هذا البلاغ: ما كُتب هنا، وإلّا نسبة الخبير المحفوظة. */
const effectiveRate = computed(() => {
    if (!summary.value?.technician) return 0;
    return form.commission_rate === ''
        ? Number(summary.value.technician.commission_rate) || 0
        : Number(form.commission_rate) || 0;
});

const technicianShare = computed(() => round((commissionBase.value * effectiveRate.value) / 100));

const costSuggestion = computed(() => Number(summary.value?.chargeable_cost ?? 0));
const suggestionLabel = computed(() => (summary.value?.final_cost ? 'السعر المتفق' : 'التقدير الأولي'));

const collectAmountSettle = computed(() => Number(form.collect_amount) || 0);
const collectAmount = computed(() => Number(collectForm.amount) || 0);

/** How much of the deposit the new invoice can actually absorb. */
const appliedDeposit = computed(() => Math.min(paid.value, totalAmount.value));
const dueAfterDeposits = computed(() => round(totalAmount.value - appliedDeposit.value));
const remainderAfterSettle = computed(() => round(Math.max(0, dueAfterDeposits.value - collectAmountSettle.value)));
const creditAfterSettle = computed(() => round(Math.max(0, paid.value - totalAmount.value)));
const collectIsFull = computed(() => collectAmountSettle.value > 0 && collectAmountSettle.value === Math.max(0, dueAfterDeposits.value));
const overCollecting = computed(() => collectAmountSettle.value > dueAfterDeposits.value + 0.001);

const showCollect = computed(() => (settled.value ? outstanding.value > 0 : tab.value === 'collect'));

const canConfirm = computed(() => {
    if (overCollecting.value) return false;
    // بنود ناقصة تُرفض على الخادم على أي حال؛ منعها هنا يوفّر الرحلة.
    if (detailed.value) {
        return lines.value.length > 0
            && lines.value.every((l) => Number(l.quantity) > 0 && Number(l.unit_price) >= 0);
    }
    return form.total !== '' && totalAmount.value >= 0;
});

/**
 * قطعة غيار تتجاوز الرصيد: تحذير هنا، ورفض على الخادم عند سياسة مخزون صارمة.
 * الرصيد المعروض هو ما قرأه البحث، فقد يكون تغيّر منذ لحظات — لذا تحذير لا منع.
 */
function stockWarning(line) {
    if (line.line_type !== 'MATERIAL' || line.stock === null || line.stock === undefined) return '';
    const qty = Number(line.quantity) || 0;
    return qty > Number(line.stock)
        ? `الرصيد المتاح ${formatQty(line.stock)} فقط`
        : '';
}

function toggleDetailed() {
    if (detailed.value) {
        // العودة لمبلغ إجمالي: ما بُني من بنود يصبح الرقم المقترح، فلا يضيع
        // ما حُسب — والبنود تبقى محفوظة إن عاد المستخدم إليها.
        form.total = String(linesTotal.value || round(costSuggestion.value));
        detailed.value = false;
        syncCollect();
        return;
    }

    detailed.value = true;
    if (!lines.value.length) {
        addLabourLine(Number(form.total) || costSuggestion.value);
    }
}

function addLabourLine(price = 0) {
    lines.value.push({
        key: `line-${++lineSeq}`,
        line_type: 'SERVICE',
        service_id: null,
        material_id: null,
        description: '',
        quantity: '1',
        unit_price: price > 0 ? String(round(price)) : '',
        stock: null,
    });
}

/**
 * A row picked out of the scan box. A part scanned twice is one line with two
 * on it — re-scanning to count quantity is how a bench actually works.
 */
function addFromPick(row) {
    const existing = row.kind === 'MATERIAL'
        ? lines.value.find((l) => l.line_type === 'MATERIAL' && l.material_id === row.id)
        : null;

    if (existing) {
        existing.quantity = String(round((Number(existing.quantity) || 0) + 1));
        return;
    }

    lines.value.push({
        key: `line-${++lineSeq}`,
        line_type: row.kind,
        service_id: row.kind === 'SERVICE' ? row.id : null,
        material_id: row.kind === 'MATERIAL' ? row.id : null,
        description: row.name,
        quantity: '1',
        unit_price: String(round(Number(row.price) || 0)),
        stock: row.kind === 'MATERIAL' ? Number(row.stock) || 0 : null,
    });
}

/** Whatever is still owed after the deposit is what is handed over at the till. */
function syncCollect() {
    form.collect_amount = String(Math.max(0, round(totalAmount.value - Math.min(paid.value, totalAmount.value))));
}

// A bill being edited line by line keeps the collect box in step, so the clerk
// is never left asking for the amount that was owed three edits ago.
watch(linesTotal, () => {
    if (detailed.value) syncCollect();
});

function round(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function money(value) {
    return formatMoney(value, { fraction: 2 });
}

function methodLabel(value) {
    return paymentMethodLabel(value);
}

function fieldClass(field) {
    return errors.value[field]
        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
}

function setTotal(value) {
    form.total = String(round(value));
    syncCollect();
}

async function load() {
    if (!props.repair) return;

    loading.value = true;
    error.value = '';
    errors.value = {};

    try {
        const data = await repairsApi.settlement(props.repair.id);
        summary.value = data;

        // A device that is ready to go opens on the hand-over; anything still
        // on the bench opens on "take a deposit" — unless the caller said which.
        tab.value = props.intent
            || (props.repair.status === 'READY' || props.repair.status === 'DELIVERED' ? 'settle' : 'collect');

        detailed.value = false;
        lines.value = [];

        const suggested = Number(data.chargeable_cost ?? 0);
        form.total = suggested > 0 ? String(round(suggested)) : '';
        form.collect_amount = suggested > 0
            ? String(Math.max(0, round(suggested - Math.min(Number(data.paid ?? 0), suggested))))
            : '';
        collectForm.amount = '';

        await nextTick();
        (tab.value === 'settle' ? totalInput.value : collectInput.value)?.focus();
    } catch (e) {
        summary.value = null;
        error.value = apiMessage(e, 'تعذّر تحميل حساب البلاغ');
    } finally {
        loading.value = false;
    }
}

/**
 * The lines as the API wants them. Material lines carry their material; a
 * labour line is free text priced by hand, so it names no service.
 */
function itemsPayload() {
    return lines.value.map((line) => ({
        line_type: line.line_type,
        service_id: line.service_id,
        material_id: line.material_id,
        description: line.description || null,
        quantity: line.quantity,
        unit_price: line.unit_price === '' ? 0 : line.unit_price,
    }));
}

async function settle() {
    if (busy.value || !canConfirm.value) return;

    busy.value = true;
    error.value = '';
    errors.value = {};

    try {
        const { data, settlement, message } = await repairsApi.settle(props.repair.id, {
            // التفصيل هو الفاتورة حين يوجد؛ الخادم يسعّرها من البنود ولا
            // يثق بمجموع محسوب في المتصفّح.
            ...(detailed.value ? { items: itemsPayload() } : { total: form.total }),
            collect_amount: collectAmountSettle.value || 0,
            payment_method: form.payment_method,
            // الفراغ يعني «استخدم نسبة الخبير المحفوظة»، فلا يُرسَل أصلاً.
            ...(form.commission_rate === '' ? {} : { commission_rate: form.commission_rate }),
        });
        summary.value = settlement;
        emit('updated', data, settlement, message);
    } catch (e) {
        errors.value = collapseItemErrors(apiFieldErrors(e));
        const field = e?.response?.data?.field;
        if (field) errors.value[field] = e.response.data.message;
        error.value = apiMessage(e, 'تعذّرت التسوية');
    } finally {
        busy.value = false;
    }
}

/**
 * Laravel reports a bad line as `items.2.quantity`, which no field on this form
 * is named. Fold those onto the lines block so the message is shown rather than
 * swallowed.
 */
function collapseItemErrors(fieldErrors) {
    const collapsed = {};

    for (const [key, message] of Object.entries(fieldErrors)) {
        collapsed[key.startsWith('items.') ? 'items' : key] = message;
    }

    return collapsed;
}

async function collect() {
    if (busy.value || collectAmount.value <= 0) return;

    busy.value = true;
    error.value = '';

    try {
        const { data, settlement, message } = await repairsApi.deposit(props.repair.id, {
            amount: collectForm.amount,
            payment_method: collectForm.payment_method,
        });
        summary.value = settlement;
        collectForm.amount = '';
        emit('updated', data, settlement, message);
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر تسجيل القبض');
    } finally {
        busy.value = false;
    }
}

watch(() => [props.open, props.repair?.id], ([open]) => {
    if (open) load();
    else summary.value = null;
}, { immediate: true });
</script>
