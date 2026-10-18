<template>
    <div class="px-4 py-12 text-center">
        <p class="text-3xl" aria-hidden="true">{{ icon }}</p>
        <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">{{ title }}</p>
        <p v-if="hint" class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ hint }}</p>
        <button
            v-if="actionLabel"
            type="button"
            class="mt-4 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-700 print:hidden"
            @click="$emit('action')"
        >
            {{ actionLabel }}
        </button>
    </div>
</template>

<script setup>
defineProps({
    icon: { type: String, default: '📄' },
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    actionLabel: { type: String, default: '' },
});

defineEmits(['action']);
</script>
