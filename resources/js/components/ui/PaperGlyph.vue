<template>
    <!-- A paper drawn to rough proportion: sheets are portrait pages, rolls
         are narrow strips with a torn bottom edge. Recognised at a glance
         faster than reading "80 مم". -->
    <span class="inline-flex shrink-0 items-end justify-center" :style="{ width: `${box}px`, height: `${box}px` }" aria-hidden="true">
        <span
            class="block rounded-[2px] border-[1.5px]"
            :class="active ? 'border-blue-600 bg-blue-50' : 'border-slate-400 bg-white'"
            :style="shape"
        ></span>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    paper: { type: Object, required: true },
    active: { type: Boolean, default: false },
    /** Height of the tallest paper, in px. */
    size: { type: Number, default: 18 },
});

const box = computed(() => props.size);

const shape = computed(() => {
    const h = props.size;
    const ratio = {
        a4: [0.72, 1],
        a5: [0.6, 0.84],
        '80mm': [0.4, 1],
        '58mm': [0.3, 1],
    }[props.paper.value] || [0.7, 1];

    return {
        width: `${Math.round(h * ratio[0])}px`,
        height: `${Math.round(h * ratio[1])}px`,
        borderBottomStyle: props.paper.thermal ? 'dashed' : 'solid',
    };
});
</script>
