<template>
    <div>
        <!-- The three numbers a purchasing decision actually starts from. -->
        <div class="grid gap-3 px-4 pb-4 sm:grid-cols-3">
            <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                <div class="text-xs text-red-700">مواد نافدة</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-red-800">{{ counts.out }}</div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs text-amber-800">قرب النفاد</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-amber-900">{{ counts.low }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">تكلفة إعادة التعبئة التقديرية</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ formatUsd(refillCost) }}</div>
                <div class="mt-0.5 text-[11px] text-slate-400">النقص × سعر التكلفة</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-right text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="px-4 py-3"
                            :class="column.numeric ? 'text-left' : ''"
                            :aria-sort="ariaSort(column.key)"
                        >
                            <button
                                type="button"
                                class="group inline-flex items-center gap-1 transition hover:text-slate-900"
                                @click="toggleSort(column.key)"
                            >
                                {{ column.label }}
                                <span
                                    class="text-[10px]"
                                    :class="sort.key === column.key ? 'text-slate-700' : 'text-slate-300 group-hover:text-slate-400'"
                                >
                                    {{ sort.key === column.key && sort.direction === 'asc' ? '▲' : '▼' }}
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-3 print:hidden">التغطية</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="material in sortedRows" :key="material.id" class="transition hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ material.name }}</div>
                            <div class="text-xs text-slate-400" dir="ltr">{{ material.sku || '—' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="material.is_out_of_stock ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ material.is_out_of_stock ? 'نفد' : 'قرب النفاد' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ material.unit || '—' }}</td>
                        <td
                            class="px-4 py-3 text-left font-semibold tabular-nums"
                            :class="material.is_out_of_stock ? 'text-red-600' : 'text-amber-700'"
                        >
                            {{ formatQty(material.stock_quantity) }}
                        </td>
                        <td class="px-4 py-3 text-left tabular-nums text-slate-600">{{ formatQty(material.min_stock) }}</td>
                        <td class="px-4 py-3 text-left font-medium tabular-nums text-amber-700">
                            {{ formatQty(material.shortfall) }}
                        </td>
                        <td class="px-4 py-3 text-left tabular-nums text-slate-600">{{ formatUsd(material.cost_price) }}</td>
                        <td class="px-4 py-3 text-left tabular-nums text-slate-600">{{ formatUsd(material.sale_price) }}</td>
                        <td class="px-4 py-3 print:hidden">
                            <!-- Coverage against the minimum: the shortfall column read as a picture. -->
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full" :style="coverageStyle(material)"></div>
                                </div>
                                <span class="text-xs tabular-nums text-slate-400">{{ coverageLabel(material) }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { formatUsd, formatQty } from '../../utils/format';

const props = defineProps({
    rows: { type: Array, default: () => [] },
});

const columns = [
    { key: 'name', label: 'المادة' },
    { key: 'status', label: 'الحالة' },
    { key: 'unit', label: 'الوحدة' },
    { key: 'stock_quantity', label: 'الكمية الحالية', numeric: true },
    { key: 'min_stock', label: 'الحد الأدنى', numeric: true },
    { key: 'shortfall', label: 'النقص', numeric: true },
    { key: 'cost_price', label: 'تكلفة', numeric: true },
    { key: 'sale_price', label: 'سعر بيع', numeric: true },
];

// The API already returns the scarcest first; sorting is an override, so it
// starts empty rather than re-stating the server order as a user choice.
const sort = reactive({ key: '', direction: 'desc' });

const counts = computed(() => ({
    out: props.rows.filter((row) => row.is_out_of_stock).length,
    low: props.rows.filter((row) => !row.is_out_of_stock).length,
}));

const refillCost = computed(() =>
    props.rows.reduce((sum, row) => sum + (Number(row.shortfall) || 0) * (Number(row.cost_price) || 0), 0)
);

const sortedRows = computed(() => {
    if (!sort.key) return props.rows;

    const factor = sort.direction === 'asc' ? 1 : -1;

    return [...props.rows].sort((a, b) => factor * compare(a, b, sort.key));
});

function compare(a, b, key) {
    if (key === 'status') {
        return Number(a.is_out_of_stock) - Number(b.is_out_of_stock);
    }

    const left = a[key];
    const right = b[key];

    if (typeof left === 'string' || typeof right === 'string') {
        return String(left ?? '').localeCompare(String(right ?? ''), 'ar');
    }

    return (Number(left) || 0) - (Number(right) || 0);
}

function toggleSort(key) {
    if (sort.key === key) {
        sort.direction = sort.direction === 'asc' ? 'desc' : 'asc';
        return;
    }

    sort.key = key;
    sort.direction = key === 'name' || key === 'unit' ? 'asc' : 'desc';
}

function ariaSort(key) {
    if (sort.key !== key) return 'none';
    return sort.direction === 'asc' ? 'ascending' : 'descending';
}

function coverageRatio(material) {
    const min = Number(material.min_stock) || 0;
    const stock = Number(material.stock_quantity) || 0;
    if (min <= 0) return stock > 0 ? 1 : 0;
    return Math.max(0, Math.min(1, stock / min));
}

function coverageStyle(material) {
    const ratio = coverageRatio(material);
    return {
        // A zero-width bar is invisible; a hairline still says "nothing left".
        width: `${Math.max(2, ratio * 100)}%`,
        background: ratio <= 0 ? '#dc2626' : ratio < 0.5 ? '#f59e0b' : '#0ea5e9',
    };
}

function coverageLabel(material) {
    return `${Math.round(coverageRatio(material) * 100)}٪`;
}
</script>
