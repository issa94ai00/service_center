<template>
    <ul v-if="rows.length" class="space-y-3">
        <li v-for="(row, index) in rows" :key="row.id">
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="flex min-w-0 items-baseline gap-2">
                    <span class="w-4 shrink-0 text-xs tabular-nums text-slate-400">{{ index + 1 }}</span>
                    <RouterLink v-if="row.to" :to="row.to" class="truncate font-medium text-slate-900 hover:underline">
                        {{ row.name }}
                    </RouterLink>
                    <span v-else class="truncate font-medium text-slate-900">{{ row.name }}</span>
                </span>
                <span class="shrink-0 font-semibold tabular-nums text-slate-900">{{ row.value }}</span>
            </div>

            <!-- Magnitude against the leading row, so the bars rank by size not position. -->
            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full" :style="barStyle(row.amount)"></div>
            </div>
            <div v-if="row.meta" class="mt-1 text-xs text-slate-400">{{ row.meta }}</div>
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
</script>
