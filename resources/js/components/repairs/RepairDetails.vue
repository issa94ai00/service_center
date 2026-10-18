<template>
    <div class="space-y-3">
        <dl class="grid gap-x-6 gap-y-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="entry in entries" :key="entry.label" :class="entry.wide ? 'sm:col-span-2' : ''">
                <dt class="text-[11px] font-medium text-slate-400">{{ entry.label }}</dt>
                <dd class="whitespace-pre-line text-sm text-slate-700" :dir="entry.ltr ? 'ltr' : undefined">{{ entry.value }}</dd>
            </div>
        </dl>

        <!-- Lock Pattern Visual Box for Technicians if recorded in notes -->
        <div v-if="patternSequence.length" class="flex items-center gap-4 p-3.5 rounded-xl bg-slate-900 text-white border border-slate-800">
            <div class="grid grid-cols-3 gap-2 w-16 h-16 p-2 bg-slate-950 rounded-xl border border-slate-800 shrink-0 select-none">
                <span
                    v-for="dot in 9"
                    :key="dot"
                    class="m-auto flex h-2.5 w-2.5 rounded-full"
                    :class="patternSequence.includes(dot) ? 'bg-cyan-400 ring-2 ring-cyan-400/40' : 'bg-slate-700'"
                ></span>
            </div>
            <div class="space-y-0.5">
                <span class="text-xs font-bold text-cyan-300 block">نمط قفل شاشة الجهاز (فحص الورشة):</span>
                <span class="text-xs font-mono text-slate-200 tracking-wider" dir="ltr">{{ patternSequence.join(' ➔ ') }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatDateTime, formatMoney } from '../../utils/format';

const props = defineProps({ row: { type: Object, required: true } });

/** Money is only worth a row once some has actually moved. */
const paid = computed(() => Number(props.row.paid ?? 0));
const due = computed(() => Number(props.row.due ?? 0));

const patternSequence = computed(() => {
    const notes = props.row.notes || '';
    const match = notes.match(/\[نمط القفل:\s*([\d-]+)\]/);
    if (!match) return [];
    return match[1].split('-').map(Number).filter((n) => n >= 1 && n <= 9);
});

const entries = computed(() =>
    [
        { label: 'العطل المذكور', value: props.row.reported_issue, wide: true },
        { label: 'ملاحظات وفحص الاستلام', value: props.row.notes, wide: true },
        { label: 'المرفقات المستلمة', value: props.row.accessories },
        { label: 'نوع الجهاز', value: props.row.device_type },
        { label: 'اللون', value: props.row.color },
        { label: 'السيريال / IMEI', value: props.row.serial_number, ltr: true },
        { label: 'تاريخ الاستلام', value: formatDateTime(props.row.received_at) },
        { label: 'تاريخ التسليم', value: props.row.delivered_at ? formatDateTime(props.row.delivered_at) : '' },
        { label: 'التكلفة المقدرة', value: props.row.estimated_cost != null ? formatMoney(props.row.estimated_cost) : '' },
        { label: 'التكلفة النهائية', value: props.row.final_cost != null ? formatMoney(props.row.final_cost) : '' },
        { label: 'المدفوع', value: paid.value > 0 ? formatMoney(paid.value) : '' },
        {
            label: due.value < 0 ? 'رصيد للعميل' : 'المتبقي',
            value: paid.value > 0 && Math.abs(due.value) >= 0.005 ? formatMoney(Math.abs(due.value)) : '',
        },
        { label: 'فاتورة التسوية', value: props.row.invoice_no ?? '', ltr: true },
    ].filter((entry) => entry.value)
);
</script>
