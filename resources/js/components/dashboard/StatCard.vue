<template>
    <!-- One cell of the KPI strip; the strip draws the borders between cells. -->
    <component
        :is="to ? RouterLink : 'div'"
        :to="to"
        class="group relative flex min-w-0 flex-col justify-between gap-3 p-4 sm:p-5 transition-colors"
        :class="to ? 'hover:bg-slate-50/80 dark:hover:bg-slate-800/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500/50' : ''"
    >
        <div class="flex items-center justify-between gap-2">
            <span class="flex min-w-0 items-center gap-2 text-[13px] font-medium text-slate-500 dark:text-slate-400">
                <span
                    v-if="icon"
                    class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-[14px]"
                    :class="iconClass || 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400'"
                >
                    <AppIcon :name="icon" />
                </span>
                <span class="truncate">{{ label }}</span>
            </span>
            <AppIcon
                v-if="to"
                name="arrow-up-right"
                class="shrink-0 text-sm text-slate-300 dark:text-slate-600 opacity-0 transition group-hover:opacity-100 rtl:-scale-x-100 print:hidden"
            />
        </div>

        <div class="flex items-end justify-between gap-3">
            <div class="min-w-0">
                <div class="truncate text-xl font-bold tabular-nums tracking-tight sm:text-[1.4rem]" :class="toneClass">
                    {{ value }}
                </div>
                <div class="mt-1.5 flex min-h-5 flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                    <!-- The arrow and the sign carry the direction; colour only echoes it. -->
                    <span
                        v-if="delta !== null && delta !== undefined"
                        class="inline-flex shrink-0 items-center gap-0.5 rounded-full px-1.5 py-0.5 font-semibold tabular-nums"
                        :class="deltaTone"
                        :title="deltaTitle"
                    >
                        <AppIcon :name="flat ? 'arrow-right' : rising ? 'arrow-up-right' : 'arrow-down-left'" :stroke-width="2.4" class="text-[11px]" />
                        <span class="sr-only">{{ flat ? 'بلا تغيير عن الفترة السابقة' : rising ? 'ارتفاع عن الفترة السابقة' : 'انخفاض عن الفترة السابقة' }}</span>
                        {{ flat ? 'ثابت' : formatPercent(Math.abs(delta)) }}
                    </span>
                    <span v-if="hint" class="truncate text-slate-500 dark:text-slate-400">{{ hint }}</span>
                </div>
            </div>

            <!-- Optional trend line across the period -->
            <svg
                v-if="sparkPath"
                class="pointer-events-none hidden h-9 w-20 shrink-0 overflow-visible sm:block"
                :viewBox="`0 0 ${SPARK_W} ${SPARK_H}`"
                preserveAspectRatio="none"
                dir="ltr"
                aria-hidden="true"
            >
                <defs>
                    <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" :stop-color="sparkColor" stop-opacity="0.22" />
                        <stop offset="100%" :stop-color="sparkColor" stop-opacity="0" />
                    </linearGradient>
                </defs>
                <path :d="`${sparkPath} L ${SPARK_W} ${SPARK_H} L 0 ${SPARK_H} Z`" :fill="`url(#${gradientId})`" />
                <path :d="sparkPath" fill="none" :stroke="sparkColor" stroke-width="1.75" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
            </svg>
        </div>
    </component>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '../ui/AppIcon.vue';
import { formatPercent } from '../../utils/format';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: '' },
    tone: { type: String, default: 'text-slate-900' },
    /** AppIcon name. */
    icon: { type: String, default: '' },
    /** Tailwind classes for the icon chip (e.g. `bg-blue-50 text-blue-700`). */
    iconClass: { type: String, default: '' },
    to: { type: [Object, null], default: null },
    /** Change against the previous period as a ratio, or null when unknowable. */
    delta: { type: [Number, null], default: null },
    /** Which direction is good news — purchases going up is neither. */
    goodWhen: { type: String, default: 'neutral' },
    previousLabel: { type: String, default: '' },
    /** Values over the period for a small trend line. */
    spark: { type: Array, default: () => [] },
    sparkColor: { type: String, default: '#2a78d6' },
});

const SPARK_W = 80;
const SPARK_H = 32;

const gradientId = computed(() => 'stat-grad-' + Math.abs(props.label.split('').reduce((acc, c) => (acc << 5) - acc + c.charCodeAt(0), 0)));

const toneClass = computed(() => {
    if (props.tone === 'text-slate-900') {
        return 'text-slate-900 dark:text-white';
    }
    return props.tone;
});

const rising = computed(() => Number(props.delta) > 0);

// Half a percent either way is noise, not a trend.
const flat = computed(() => Math.abs(Number(props.delta)) < 0.005);

const deltaTone = computed(() => {
    if (flat.value || props.goodWhen === 'neutral') {
        return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300';
    }
    const good = props.goodWhen === 'up' ? rising.value : !rising.value;
    return good
        ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400'
        : 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400';
});

const deltaTitle = computed(() =>
    props.previousLabel ? `مقارنة بالفترة السابقة (${props.previousLabel})` : 'مقارنة بالفترة السابقة'
);

const sparkPath = computed(() => {
    const values = props.spark.map((v) => Number(v) || 0);
    if (values.length < 2) return '';
    const min = Math.min(...values);
    const range = Math.max(...values) - min || 1;
    const step = SPARK_W / (values.length - 1);
    return values
        .map((v, i) => `${i ? 'L' : 'M'} ${(i * step).toFixed(1)} ${(SPARK_H - 2 - ((v - min) / range) * (SPARK_H - 6)).toFixed(1)}`)
        .join(' ');
});
</script>
