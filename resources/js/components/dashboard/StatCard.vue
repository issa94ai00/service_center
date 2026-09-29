<template>
    <component
        :is="to ? RouterLink : 'div'"
        :to="to"
        class="block rounded-2xl border border-slate-200 bg-white p-5 transition"
        :class="to ? 'hover:border-blue-300 hover:shadow-sm' : ''"
    >
        <div class="flex items-center gap-3">
            <span
                v-if="icon"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xl"
                :class="iconClass || 'bg-blue-50'"
                aria-hidden="true"
            >
                {{ icon }}
            </span>
            <span class="min-w-0 flex-1 truncate text-sm text-slate-500">{{ label }}</span>

            <!-- The arrow and the sign carry the direction; colour only echoes it. -->
            <span
                v-if="delta !== null && delta !== undefined"
                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums"
                :class="deltaTone"
                :title="deltaTitle"
            >
                <span aria-hidden="true">{{ flat ? '▬' : rising ? '▲' : '▼' }}</span>
                <span class="sr-only">{{ flat ? 'بلا تغيير عن الفترة السابقة' : rising ? 'ارتفاع عن الفترة السابقة' : 'انخفاض عن الفترة السابقة' }}</span>
                {{ flat ? 'ثابت' : formatPercent(Math.abs(delta)) }}
            </span>
        </div>

        <div class="mt-3 text-2xl font-bold tabular-nums" :class="tone">{{ value }}</div>
        <div v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</div>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { formatPercent } from '../../utils/format';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'text-slate-900' },
    icon: { type: String, default: '' },
    /** Tailwind classes for the icon chip when set (e.g. `bg-blue-50`). */
    iconClass: { type: String, default: '' },
    to: { type: [Object, null], default: null },
    /** Change against the previous period as a ratio, or null when unknowable. */
    delta: { type: [Number, null], default: null },
    /** Which direction is good news — purchases going up is neither. */
    goodWhen: { type: String, default: 'neutral' },
    previousLabel: { type: String, default: '' },
});

const rising = computed(() => Number(props.delta) > 0);

// Half a percent either way is noise, not a trend.
const flat = computed(() => Math.abs(Number(props.delta)) < 0.005);

const deltaTone = computed(() => {
    if (flat.value || props.goodWhen === 'neutral') return 'bg-slate-100 text-slate-600';
    const good = props.goodWhen === 'up' ? rising.value : !rising.value;
    return good ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600';
});

const deltaTitle = computed(() =>
    props.previousLabel ? `مقارنة بالفترة السابقة (${props.previousLabel})` : 'مقارنة بالفترة السابقة'
);
</script>
