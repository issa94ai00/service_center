<template>
    <ol v-if="rows.length" class="space-y-1">
        <li
            v-for="(row, index) in rows"
            :key="row.id || index"
            class="-mx-2 flex items-center gap-3 rounded-xl px-2 py-2.5 transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
        >
            <span
                class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-bold tabular-nums"
                :class="index === 0 ? 'text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'"
                :style="index === 0 ? { background: color } : null"
            >
                {{ index + 1 }}
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <RouterLink
                        v-if="row.to"
                        :to="row.to"
                        class="truncate font-semibold text-slate-800 dark:text-slate-200 transition hover:text-blue-600 dark:hover:text-blue-400 hover:underline"
                    >
                        {{ row.name }}
                    </RouterLink>
                    <span v-else class="truncate font-semibold text-slate-800 dark:text-slate-200">{{ row.name }}</span>
                    <span class="shrink-0 font-bold tabular-nums text-slate-900 dark:text-white">{{ row.value }}</span>
                </div>

                <!-- Magnitude against the leading row, so the bars rank by size not position. -->
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full transition-all duration-500" :style="barStyle(row.amount, index)"></div>
                </div>
                <p v-if="row.meta" class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ row.meta }}</p>
            </div>
        </li>
    </ol>

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

function barStyle(amount, index) {
    const ratio = Math.max(0.02, (Number(amount) || 0) / (max.value || 1));
    // The leader is solid; the rest fade a little so the eye lands on #1 first.
    return { width: `${ratio * 100}%`, background: props.color, opacity: index === 0 ? 1 : 0.55 };
}
</script>
