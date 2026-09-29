<template>
    <!--
        A skeleton of roughly the right shape keeps the page from collapsing to a
        single line of text and jumping back on every filter change.
    -->
    <div class="animate-pulse space-y-3 px-4 py-4" role="status" aria-live="polite">
        <span class="sr-only">جارٍ التحميل</span>
        <div v-for="row in rows" :key="row" class="flex gap-3">
            <div
                v-for="column in columns"
                :key="column"
                class="h-3 rounded bg-slate-200"
                :class="column === 1 ? 'flex-[2]' : 'flex-1'"
                :style="{ opacity: 1 - row * 0.06 }"
            ></div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    rows: { type: Number, default: 6 },
    columns: { type: Number, default: 6 },
});
</script>
