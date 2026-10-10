<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-right text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3">التاريخ</th>
                    <th scope="col" class="px-4 py-3">المادة</th>
                    <th scope="col" class="px-4 py-3">نوع الحركة</th>
                    <th scope="col" class="px-4 py-3">المرجع</th>
                    <th scope="col" class="px-4 py-3 text-left">الكمية</th>
                    <th v-if="hasRunning" scope="col" class="px-4 py-3 text-left">الرصيد بعد الحركة</th>
                    <th scope="col" class="px-4 py-3 text-left">القيمة</th>
                    <th scope="col" class="px-4 py-3">أُنشئت بواسطة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr
                    v-for="row in rows"
                    :key="row.id"
                    class="transition hover:bg-slate-50"
                    :class="row.is_reversal ? 'bg-amber-50/40' : ''"
                >
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600" dir="ltr">{{ row.movement_date }}</td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-900">{{ row.material.name }}</div>
                        <div class="text-xs text-slate-400" dir="ltr">{{ row.material.sku || '—' }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="typeTone(row.type)">
                            {{ row.type_label }}
                        </span>
                        <span v-if="row.is_reversal" class="mr-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                            عكسية
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-500" dir="ltr">{{ row.reference_no || '—' }}</td>
                    <td class="px-4 py-3 text-left font-semibold tabular-nums" :class="isInbound(row) ? 'text-emerald-700' : 'text-red-600'">
                        <!-- Direction is carried by an arrow and a sign, never by colour alone. -->
                        <span aria-hidden="true">{{ isInbound(row) ? '▲' : '▼' }}</span>
                        {{ formatQty(row.signed_quantity) }}
                        <span v-if="row.material.unit" class="text-xs font-normal text-slate-400">{{ row.material.unit }}</span>
                    </td>
                    <td v-if="hasRunning" class="px-4 py-3 text-left font-semibold tabular-nums text-slate-900">
                        {{ formatQty(row.running_quantity) }}
                    </td>
                    <td class="px-4 py-3 text-left tabular-nums text-slate-600">{{ lineValue(row) }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ row.created_by || '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatUsd, formatQty } from '../../utils/format';

const props = defineProps({
    data: { type: Object, required: true },
});

const rows = computed(() => props.data.rows ?? []);
const hasRunning = computed(() => Boolean(props.data.has_running_quantity));

const tones = {
    PURCHASE: 'bg-emerald-100 text-emerald-800',
    SALE: 'bg-blue-100 text-blue-800',
    ADJUSTMENT: 'bg-slate-100 text-slate-600',
    PURCHASE_RETURN: 'bg-orange-100 text-orange-800',
    SALE_RETURN: 'bg-violet-100 text-violet-800',
    OPENING: 'bg-cyan-100 text-cyan-800',
};

function typeTone(type) {
    return tones[type] ?? 'bg-slate-100 text-slate-600';
}

function isInbound(row) {
    return Number(row.signed_quantity) >= 0;
}

/** Movements priced at zero (most adjustments) would only add a column of dashes. */
function lineValue(row) {
    const price = Number(row.unit_price) || 0;
    if (price <= 0) return '—';
    return formatUsd(Math.abs(Number(row.signed_quantity) || 0) * price);
}
</script>
