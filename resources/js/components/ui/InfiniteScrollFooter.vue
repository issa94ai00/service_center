<template>
    <!-- Infinite scroll: asks for the next page as this footer nears the viewport. -->
    <div
        v-if="total && shown"
        ref="root"
        class="flex items-center justify-center gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-500 print:hidden"
    >
        <template v-if="loadingMore">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-blue-600" aria-hidden="true"></span>
            جارٍ تحميل المزيد...
        </template>
        <template v-else-if="error">
            <span class="text-red-700">{{ error }}</span>
            <button
                type="button"
                class="rounded-lg border border-red-300 px-3 py-1 font-medium text-red-700 transition hover:bg-red-50"
                @click="$emit('more')"
            >إعادة المحاولة</button>
        </template>
        <template v-else-if="hasMore">
            <span>عرض {{ shown }} من {{ total }} {{ unit }}</span>
            <button
                type="button"
                class="rounded-lg border border-slate-300 px-3 py-1 font-medium text-slate-600 transition hover:bg-slate-50"
                @click="$emit('more')"
            >تحميل المزيد</button>
        </template>
        <span v-else>عُرض الكل — {{ total }} {{ unit }}</span>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    shown: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    hasMore: { type: Boolean, default: false },
    loadingMore: { type: Boolean, default: false },
    /** A fresh load is running; hold off until it lands. */
    busy: { type: Boolean, default: false },
    error: { type: String, default: '' },
    unit: { type: String, default: '' },
});

const emit = defineEmits(['more']);

const root = ref(null);
let observer = null;

function ready() {
    return props.hasMore && !props.loadingMore && !props.busy && !props.error;
}

/**
 * (Re)observing fires the callback once with the current position, so a page
 * too short to push the footer off screen keeps filling until it does.
 */
async function observe() {
    await nextTick();
    observer?.disconnect();
    if (!root.value || typeof IntersectionObserver === 'undefined') return;

    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting) && ready()) emit('more');
        },
        { rootMargin: '0px 0px 400px 0px' }
    );
    observer.observe(root.value);
}

watch(
    () => [root.value, props.loadingMore, props.busy, props.hasMore],
    () => observe(),
    { immediate: true }
);

onBeforeUnmount(() => observer?.disconnect());
</script>
