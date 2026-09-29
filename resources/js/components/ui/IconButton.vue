<template>
    <button
        type="button"
        :title="title"
        :aria-label="title"
        :disabled="busy"
        class="rounded-lg p-1.5 text-sm leading-none transition disabled:opacity-40"
        :class="danger ? 'hover:bg-red-50' : 'hover:bg-slate-100'"
    >
        <span v-if="busy" class="inline-block animate-pulse">⏳</span>
        <slot v-else />
    </button>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    busy: { type: Boolean, default: false },
    danger: { type: Boolean, default: false },
});
</script>
