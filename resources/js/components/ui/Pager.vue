<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-500 print:hidden"
    >
        <!-- "51–100 من 320" answers where you are far better than a page number alone. -->
        <p>
            عرض
            <span class="font-semibold tabular-nums text-slate-700">{{ rangeLabel }}</span>
            من
            <span class="font-semibold tabular-nums text-slate-700">{{ formatCount(meta.total) }}</span>
            {{ unit }}
        </p>

        <div class="flex items-center gap-3">
            <label v-if="perPageOptions.length" class="flex items-center gap-1.5">
                <span>لكل صفحة</span>
                <select
                    :value="meta.per_page"
                    class="rounded-lg border border-slate-300 px-2 py-1 text-xs"
                    @change="$emit('per-page', Number($event.target.value))"
                >
                    <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
                </select>
            </label>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="meta.current_page <= 1"
                    aria-label="الصفحة السابقة"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="$emit('go', meta.current_page - 1)"
                >
                    السابق
                </button>
                <span class="px-2 tabular-nums">
                    {{ formatCount(meta.current_page) }} / {{ formatCount(meta.last_page) }}
                </span>
                <button
                    type="button"
                    :disabled="meta.current_page >= meta.last_page"
                    aria-label="الصفحة التالية"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="$emit('go', meta.current_page + 1)"
                >
                    التالي
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    meta: { type: Object, required: true },
    unit: { type: String, default: 'سجل' },
    perPageOptions: { type: Array, default: () => [25, 50, 100, 200] },
});

defineEmits(['go', 'per-page']);

const counter = new Intl.NumberFormat('ar-SY-u-nu-latn');

function formatCount(value) {
    return counter.format(Number(value) || 0);
}

const rangeLabel = computed(() => {
    const { from, to } = props.meta;
    if (!from || !to) return '0';
    return from === to ? formatCount(from) : `${formatCount(from)}–${formatCount(to)}`;
});
</script>
