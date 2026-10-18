<template>
    <!-- Where each dollar of revenue went: cost of goods, running costs, and what was left. -->
    <div>
        <div class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true">
            <div
                v-for="part in visibleParts"
                :key="part.key"
                class="h-full first:rounded-s-full last:rounded-e-full transition-all duration-500"
                :style="{ width: `${part.share * 100}%`, background: part.color }"
            ></div>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3" :class="parts.length === 4 ? 'sm:grid-cols-4' : 'sm:grid-cols-3'">
            <div v-for="part in parts" :key="part.key" class="min-w-0">
                <dt class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    <span class="h-2 w-2 shrink-0 rounded-full" :style="{ background: part.color }" aria-hidden="true"></span>
                    <span class="truncate">{{ part.label }}</span>
                </dt>
                <dd class="mt-0.5 flex items-baseline gap-1.5">
                    <span class="truncate text-sm font-bold tabular-nums" :class="part.valueClass || 'text-slate-900 dark:text-white'">{{ formatUsd(part.amount) }}</span>
                    <span v-if="revenue > 0" class="text-[11px] font-medium tabular-nums text-slate-400 dark:text-slate-500">{{ Math.round(part.ofRevenue * 100) }}٪</span>
                </dd>
            </div>
        </dl>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatUsd } from '../../utils/format';

const props = defineProps({
    /** The `profit_overview` block of the dashboard payload. */
    overview: { type: Object, required: true },
});

const revenue = computed(() => Number(props.overview.revenue) || 0);

const parts = computed(() => {
    const net = Number(props.overview.net_profit) || 0;
    const rows = [
        { key: 'cogs', label: 'تكلفة البضاعة', amount: Number(props.overview.cogs) || 0, color: '#94a3b8' },
        { key: 'expenses', label: 'المصاريف', amount: Number(props.overview.operating_expenses) || 0, color: '#f43f5e' },
        { key: 'commissions', label: 'عمولات الخبراء', amount: Number(props.overview.technician_commissions) || 0, color: '#f59e0b' },
        {
            key: 'net',
            label: net < 0 ? 'صافي الخسارة' : 'صافي الربح',
            amount: net,
            color: '#10b981',
            valueClass: net < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400',
        },
    ];

    // A loss has no slice of revenue left to draw, so the bar spans total costs instead.
    const base = Math.max(revenue.value, rows.slice(0, 3).reduce((sum, row) => sum + row.amount, 0)) || 1;

    return rows
        // Commissions only exist with the workshop; hide the row rather than show a permanent zero.
        .filter((row) => row.key !== 'commissions' || row.amount > 0)
        .map((row) => ({
            ...row,
            share: Math.max(0, row.amount) / base,
            ofRevenue: revenue.value ? row.amount / revenue.value : 0,
        }));
});

const visibleParts = computed(() => parts.value.filter((part) => part.share > 0));
</script>
