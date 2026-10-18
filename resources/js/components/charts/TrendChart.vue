<template>
    <div class="w-full">
        <!-- Legend: identity is never carried by the line colour alone. -->
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <span v-for="s in series" :key="s.key" class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                    <span class="h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-slate-900 shadow-xs" :style="{ background: s.color }" aria-hidden="true"></span>
                    {{ s.label }}
                </span>
            </div>
            <button
                type="button"
                class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 shadow-2xs hover:bg-slate-50 dark:hover:bg-slate-700/80 transition print:hidden"
                @click="asTable = !asTable"
            >
                {{ asTable ? 'عرض الرسم البياني' : 'عرض كجدول' }}
            </button>
        </div>

        <!-- Table view: the same numbers, always reachable without colour. -->
        <div v-if="asTable" class="max-h-72 overflow-auto rounded-xl border border-slate-200 dark:border-slate-800">
            <table class="w-full text-right text-sm">
                <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800/95 text-xs font-semibold text-slate-500 dark:text-slate-400 backdrop-blur-xs">
                    <tr>
                        <th class="px-3 py-2.5">الفترة</th>
                        <th v-for="s in series" :key="s.key" class="px-3 py-2.5 text-left">{{ s.label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900 tabular-nums">
                    <tr v-for="point in points" :key="point.key" class="hover:bg-slate-50/60 dark:hover:bg-slate-800/50 transition">
                        <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300" dir="ltr">{{ point.key }}</td>
                        <td v-for="s in series" :key="s.key" class="px-3 py-2.5 text-left font-medium text-slate-900 dark:text-white">
                            {{ formatValue(point[s.key]) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else ref="wrapper" class="relative w-full overflow-hidden" dir="ltr" style="touch-action: pan-y">
            <svg
                :width="width"
                :height="height"
                :viewBox="`0 0 ${width} ${height}`"
                role="img"
                :aria-label="ariaLabel"
                class="block"
                @pointermove="onPointerMove"
                @pointerleave="hoverIndex = null"
            >
                <!-- SVG Gradient Defs -->
                <defs>
                    <linearGradient v-for="s in series" :key="`grad-${s.key}`" :id="`trend-area-${s.key}`" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" :stop-color="s.color" stop-opacity="0.20" />
                        <stop offset="90%" :stop-color="s.color" stop-opacity="0.0" />
                    </linearGradient>
                </defs>

                <!-- Recessive grid and axis -->
                <g>
                    <line
                        v-for="tick in yTicks"
                        :key="`grid-${tick.value}`"
                        :x1="pad.left"
                        :x2="width - pad.right"
                        :y1="tick.y"
                        :y2="tick.y"
                        class="stroke-slate-200/80 dark:stroke-slate-800/80"
                        stroke-width="1"
                    />
                    <text
                        v-for="tick in yTicks"
                        :key="`ylabel-${tick.value}`"
                        :x="pad.left - 8"
                        :y="tick.y + 4"
                        text-anchor="end"
                        class="fill-slate-400 dark:fill-slate-500 font-medium"
                        style="font-size: 10px"
                    >
                        {{ compact(tick.value) }}
                    </text>
                </g>

                <g>
                    <text
                        v-for="tick in xTicks"
                        :key="`xlabel-${tick.index}`"
                        :x="tick.x"
                        :y="height - pad.bottom + 16"
                        text-anchor="middle"
                        class="fill-slate-400 dark:fill-slate-500 font-medium"
                        style="font-size: 10px"
                    >
                        {{ tick.label }}
                    </text>
                </g>

                <!-- Crosshair sits under the marks so it never hides a value -->
                <line
                    v-if="hoveredPoint"
                    :x1="xFor(hoverIndex)"
                    :x2="xFor(hoverIndex)"
                    :y1="pad.top"
                    :y2="height - pad.bottom"
                    class="stroke-slate-400 dark:stroke-slate-500"
                    stroke-width="1"
                    stroke-dasharray="3 3"
                />

                <!-- Gradient area fills -->
                <g v-for="s in series" :key="`area-${s.key}`">
                    <path :d="areaPathFor(s.key)" :fill="`url(#trend-area-${s.key})`" />
                </g>

                <!-- Series Lines and Markers -->
                <g v-for="s in series" :key="`line-${s.key}`">
                    <path :d="pathFor(s.key)" fill="none" :stroke="s.color" stroke-width="2.25"
                        stroke-linejoin="round" stroke-linecap="round" />
                    <!-- Surface ring keeps overlapping markers separable -->
                    <circle
                        v-if="hoveredPoint"
                        :cx="xFor(hoverIndex)"
                        :cy="yFor(hoveredPoint[s.key])"
                        r="5.5"
                        :fill="s.color"
                        class="stroke-white dark:stroke-slate-900"
                        stroke-width="2.5"
                    />
                </g>

                <!-- Direct labels on the final point, in ink not series colour -->
                <g v-if="!hoveredPoint">
                    <g v-for="label in directLabels" :key="`label-${label.key}`">
                        <circle :cx="label.x" :cy="label.cy" r="4" :fill="label.color" />
                        <text
                            :x="label.x - 8"
                            :y="label.labelY - 8"
                            text-anchor="end"
                            class="fill-slate-500 dark:fill-slate-400 font-medium"
                            style="font-size: 10px"
                        >
                            {{ label.label }}
                        </text>
                    </g>
                </g>

                <text
                    v-if="isEmpty"
                    :x="width / 2"
                    :y="height / 2"
                    text-anchor="middle"
                    class="fill-slate-400 dark:fill-slate-500"
                    style="font-size: 12px"
                >
                    لا توجد حركة في هذه الفترة
                </text>
            </svg>

            <div
                v-if="hoveredPoint"
                class="pointer-events-none absolute z-10 min-w-40 rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md px-3.5 py-2.5 text-xs shadow-xl shadow-slate-900/10 dark:shadow-black/50"
                :style="tooltipStyle"
                dir="rtl"
            >
                <div class="mb-1.5 font-bold text-slate-900 dark:text-white" dir="ltr">{{ hoveredPoint.key }}</div>
                <div v-for="s in series" :key="s.key" class="flex items-center justify-between gap-4 py-0.5">
                    <span class="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                        <span class="h-2 w-2 rounded-full" :style="{ background: s.color }" aria-hidden="true"></span>
                        {{ s.label }}
                    </span>
                    <span class="tabular-nums font-bold text-slate-900 dark:text-white">{{ formatValue(hoveredPoint[s.key]) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] },
    series: { type: Array, required: true },
    formatValue: { type: Function, required: true },
    height: { type: Number, default: 260 },
});

const wrapper = ref(null);
const width = ref(720);
const hoverIndex = ref(null);
const asTable = ref(false);

const pad = { top: 18, right: 24, bottom: 28, left: 56 };

let observer = null;

onMounted(() => {
    if (!wrapper.value || typeof ResizeObserver === 'undefined') return;
    observer = new ResizeObserver(([entry]) => {
        width.value = Math.max(240, Math.floor(entry.contentRect.width));
    });
    observer.observe(wrapper.value);
});

// The wrapper is re-created after toggling the table view; keep observing the live one.
watch(wrapper, (el, old) => {
    if (!observer) return;
    if (old) observer.unobserve(old);
    if (el) observer.observe(el);
});

onBeforeUnmount(() => observer?.disconnect());

const plotWidth = computed(() => Math.max(1, width.value - pad.left - pad.right));
const plotHeight = computed(() => Math.max(1, props.height - pad.top - pad.bottom));

const maxValue = computed(() => {
    let max = 0;
    for (const point of props.points) {
        for (const s of props.series) {
            max = Math.max(max, Number(point[s.key]) || 0);
        }
    }
    return max;
});

const isEmpty = computed(() => maxValue.value === 0);

// Round the axis top up to a readable step so ticks land on whole numbers.
const axisMax = computed(() => {
    if (maxValue.value <= 0) return 1;
    const magnitude = 10 ** Math.floor(Math.log10(maxValue.value));
    return Math.ceil(maxValue.value / (magnitude / 2)) * (magnitude / 2);
});

const yTicks = computed(() => {
    // With no activity the axis has no range to divide — one baseline tick
    // beats printing "0 | 0 | 1 | 1 | 1" from a placeholder maximum.
    if (isEmpty.value) return [{ value: 0, y: yFor(0) }];

    const count = 4;
    return Array.from({ length: count + 1 }, (_, i) => {
        const value = (axisMax.value / count) * i;
        return { value, y: yFor(value) };
    });
});

const xTicks = computed(() => {
    const total = props.points.length;
    if (!total) return [];
    // Cap the label count so dense periods do not collide.
    const maxLabels = Math.max(2, Math.min(8, Math.floor(plotWidth.value / 56)));
    const step = Math.max(1, Math.ceil(total / maxLabels));
    const ticks = [];
    for (let i = 0; i < total; i += step) {
        ticks.push({ index: i, x: xFor(i), label: props.points[i].label });
    }
    return ticks;
});

const hoveredPoint = computed(() =>
    hoverIndex.value === null ? null : props.points[hoverIndex.value] ?? null
);

const tooltipStyle = computed(() => {
    const x = xFor(hoverIndex.value);
    // Flip the tooltip to the other side near the right edge.
    const flip = x > width.value - 160;
    return {
        left: `${Math.min(Math.max(0, flip ? x - 150 : x + 14), Math.max(0, width.value - 150))}px`,
        top: `${pad.top}px`,
    };
});

const ariaLabel = computed(() => {
    const names = props.series.map((s) => s.label).join(' و');
    return `رسم بياني يقارن ${names} على مدى ${props.points.length} فترة`;
});

function xFor(index) {
    const total = props.points.length;
    if (total <= 1) return pad.left + plotWidth.value / 2;
    return pad.left + (plotWidth.value * index) / (total - 1);
}

function yFor(value) {
    const ratio = (Number(value) || 0) / axisMax.value;
    return pad.top + plotHeight.value - ratio * plotHeight.value;
}

function pathFor(key) {
    if (!props.points.length) return '';
    return props.points
        .map((point, index) => `${index === 0 ? 'M' : 'L'}${xFor(index)},${yFor(point[key])}`)
        .join(' ');
}

function areaPathFor(key) {
    if (!props.points.length) return '';
    const line = pathFor(key);
    if (!line) return '';
    const lastX = xFor(props.points.length - 1);
    const firstX = xFor(0);
    const bottomY = pad.top + plotHeight.value;
    return `${line} L ${lastX} ${bottomY} L ${firstX} ${bottomY} Z`;
}

/**
 * Direct labels for the final point, nudged apart when two series finish close
 * together — otherwise a quiet period (both series at zero) stacks them exactly.
 */
const directLabels = computed(() => {
    if (isEmpty.value || !props.points.length) return [];

    const last = props.points[props.points.length - 1];
    const x = xFor(props.points.length - 1);
    const minGap = 14;

    return props.series
        .map((s) => ({ ...s, x, cy: yFor(last[s.key]) }))
        .sort((a, b) => a.cy - b.cy)
        .map((entry, index, all) => {
            const previous = all[index - 1];
            const labelY = previous && entry.cy - previous.labelY < minGap
                ? previous.labelY + minGap
                : entry.cy;

            return { ...entry, labelY };
        });
});

function onPointerMove(event) {
    const total = props.points.length;
    if (!total) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    const x = event.clientX - bounds.left;
    const ratio = (x - pad.left) / plotWidth.value;
    hoverIndex.value = Math.min(total - 1, Math.max(0, Math.round(ratio * (total - 1))));
}

function compact(value) {
    const number = Number(value) || 0;
    if (number >= 1e6) return `${(number / 1e6).toFixed(1)} م`;
    if (number >= 1e3) return `${Math.round(number / 1e3)} ألف`;
    return String(Math.round(number));
}
</script>
