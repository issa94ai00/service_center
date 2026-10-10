<template>
    <ul v-if="rows.length" class="space-y-3.5">
        <li v-for="(row, index) in rows" :key="row.id || index" class="group">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="flex min-w-0 items-baseline gap-2.5">
                    <span
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold tabular-nums"
                        :class="rankBadgeClass(index)"
                    >
                        {{ index + 1 }}
                    </span>
                    <RouterLink v-if="row.to" :to="row.to" class="truncate font-semibold text-slate-800 transition hover:text-blue-600 hover:underline">
                        {{ row.name }}
                    </RouterLink>
                    <span v-else class="truncate font-semibold text-slate-800">{{ row.name }}</span>
                </span>
                <span class="shrink-0 font-bold tabular-nums text-slate-900">{{ row.value }}</span>
            </div>

            <!-- Magnitude against the leading row, so the bars rank by size not position. -->
            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full transition-all duration-500"
                    :style="barStyle(row.amount)"
                ></div>
            </div>
            <div v-if="row.meta" class="mt-1 flex items-center justify-between text-xs text-slate-400">
                <span>{{ row.meta }}</span>
            </div>
        </li>
    </ul>

    <EmptyState v-else :icon="emptyIcon" :title="emptyTitle" :hint="emptyHint" />
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import EmptyState from '../ui/EmptyState.vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    color: { type: String, default: '#2a78d6' },
    emptyIcon: { type: String, default: '📭' },
    emptyTitle: { type: String, required: true },
    emptyHint: { type: String, default: '' },
});

const max = computed(() => props.rows.reduce((peak, row) => Math.max(peak, Number(row.amount) || 0), 0));

function barStyle(amount) {
    const ratio = Math.max(0.02, (Number(amount) || 0) / (max.value || 1));
    return { width: `${ratio * 100}%`, background: props.color };
}

function rankBadgeClass(index) {
    if (index === 0) return 'bg-amber-100 text-amber-800 ring-1 ring-amber-300';
    if (index === 1) return 'bg-slate-200 text-slate-700 ring-1 ring-slate-300';
    if (index === 2) return 'bg-orange-100 text-orange-800 ring-1 ring-orange-300';
    return 'bg-slate-50 text-slate-400';
}
</script>
