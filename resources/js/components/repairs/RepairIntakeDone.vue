<template>
    <div class="space-y-4">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-emerald-900">
                <span class="text-base leading-none">✅</span>
                تم استلام الجهاز برقم
                <span class="tabular-nums" dir="ltr">{{ repair.receipt_no }}</span>
            </p>
            <p class="mt-1 text-xs text-emerald-800">
                {{ [repair.brand, repair.model].filter(Boolean).join(' ') || repair.device_type }}
                <span v-if="repair.customer?.name"> · {{ repair.customer.name }}</span>
            </p>
        </div>

        <!-- The money, said back to the clerk. A deposit booked silently is a
             deposit that gets counted a second time at hand-over. -->
        <div v-if="paid > 0" class="grid grid-cols-3 gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-center">
            <div>
                <p class="text-[11px] text-slate-500">التقدير</p>
                <p class="mt-0.5 text-sm font-bold tabular-nums text-slate-900">{{ money(repair.chargeable_cost) }}</p>
            </div>
            <div>
                <p class="text-[11px] text-slate-500">المقبوض</p>
                <p class="mt-0.5 text-sm font-bold tabular-nums text-emerald-700">{{ money(paid) }}</p>
            </div>
            <div>
                <p class="text-[11px] text-slate-500">{{ due < 0 ? 'لصالح العميل' : 'المتبقي تقديرياً' }}</p>
                <p
                    class="mt-0.5 text-sm font-bold tabular-nums"
                    :class="due > 0 ? 'text-amber-700' : due < 0 ? 'text-blue-700' : 'text-slate-400'"
                >{{ money(Math.abs(due)) }}</p>
            </div>
        </div>

        <!-- What the customer walks out with. Printing is the whole point of
             the counter being open, so it leads rather than hiding in the list. -->
        <div v-if="canPrintReceipt || (paymentId && canPrintPayment)" class="space-y-2">
            <p class="text-xs font-semibold text-slate-500">اطبع للعميل</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <!-- The paper is named on the button: at a counter with both a
                     till printer and an A4 one, it is the first thing to check. -->
                <PrintButton
                    v-if="canPrintReceipt"
                    kind="repair"
                    title="إيصال استلام الجهاز"
                    :load="(paper) => documentsApi.repairReceipt(repair.id, paper)"
                    wrapper-class="rounded-xl border border-blue-200 bg-blue-50 text-blue-800"
                    button-class="flex flex-1 flex-col items-center justify-center rounded-s-xl px-4 py-3 text-sm font-semibold transition hover:bg-blue-100 disabled:opacity-50"
                    caret-class="rounded-e-xl border-s border-blue-200 px-2.5 transition hover:bg-blue-100 disabled:opacity-50"
                >
                    <template #default="{ busy, paper }">
                        <span>🖨️ {{ busy ? 'جارٍ الإصدار…' : 'إيصال استلام الجهاز' }}</span>
                        <span class="text-[11px] font-normal text-blue-700/80">{{ paper.label }}</span>
                    </template>
                </PrintButton>
                <PrintButton
                    v-if="paymentId && canPrintPayment"
                    kind="payment"
                    title="سند القبض"
                    :load="(paper) => documentsApi.paymentReceipt(paymentId, paper)"
                    wrapper-class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800"
                    button-class="flex flex-1 flex-col items-center justify-center rounded-s-xl px-4 py-3 text-sm font-semibold transition hover:bg-emerald-100 disabled:opacity-50"
                    caret-class="rounded-e-xl border-s border-emerald-200 px-2.5 transition hover:bg-emerald-100 disabled:opacity-50"
                >
                    <template #default="{ busy, paper }">
                        <span>🧾 {{ busy ? 'جارٍ الإصدار…' : `سند القبض (${money(paid)})` }}</span>
                        <span class="text-[11px] font-normal text-emerald-700/80">{{ paper.label }}</span>
                    </template>
                </PrintButton>
            </div>
        </div>

        <!-- WhatsApp Notification Quick Action -->
        <div v-if="whatsappUrl" class="rounded-xl border border-emerald-300 bg-emerald-50/70 p-3.5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="text-2xl">💬</span>
                <div>
                    <p class="text-xs font-bold text-emerald-950">إشعار العميل عبر واتساب</p>
                    <p class="text-[11px] text-emerald-800">إرسال تفاصيل الإيصال والتكلفة إلى {{ repair.customer?.phone }}</p>
                </div>
            </div>
            <a
                :href="whatsappUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all shadow-sm"
            >
                <span>إرسال واتساب</span>
                <span dir="ltr">↗</span>
            </a>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-between">
            <button
                type="button"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                @click="$emit('close')"
            >تم</button>
            <button
                type="button"
                class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                @click="$emit('again')"
            >＋ استلام جهاز آخر</button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import PrintButton from '../ui/PrintButton.vue';
import { usePermissions } from '../../composables/usePermissions';
import { documentsApi } from '../../services/api';
import { formatMoney } from '../../utils/format';

const props = defineProps({
    repair: { type: Object, required: true },
    /** The intake deposit's receipt, when money changed hands. */
    paymentId: { type: [Number, String], default: null },
});

defineEmits(['close', 'again']);

const { can } = usePermissions();

// Mirrors the policies behind the two PDF endpoints, so a clerk is never
// offered a button that answers 403.
const canPrintReceipt = computed(() => can('repairs.view'));
const canPrintPayment = computed(() => can('payments.view') || can('statements.view'));

const paid = computed(() => Number(props.repair.paid ?? 0));
const due = computed(() => Number(props.repair.due ?? 0));

function money(value) {
    return formatMoney(value);
}

const whatsappUrl = computed(() => {
    const rawPhone = props.repair.customer?.phone;
    if (!rawPhone) return null;
    const cleanPhone = rawPhone.replace(/\D/g, '');
    if (!cleanPhone) return null;
    const deviceName = [props.repair.brand, props.repair.model].filter(Boolean).join(' ') || props.repair.device_type || 'الجهاز';
    const receiptNo = props.repair.receipt_no || `#${props.repair.id}`;
    const est = props.repair.chargeable_cost ? `${formatMoney(props.repair.chargeable_cost)}` : 'قيد الفحص';
    const msg = `مرحباً بك ${props.repair.customer?.name || 'عميلنا العزيز'}\n` +
        `تم استلام جهازك (${deviceName}) للصيانة بنجاح.\n` +
        `رقم الإيصال: ${receiptNo}\n` +
        `التكلفة التقديرية: ${est}\n` +
        `سنقوم بإشعارك فور اكتمال الصيانة. شكراً لثقتكم بنا!`;
    return `https://api.whatsapp.com/send?phone=${cleanPhone}&text=${encodeURIComponent(msg)}`;
});
</script>
