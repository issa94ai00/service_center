<template>
    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="view.tone">
        <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ view.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ tenant: { type: Object, required: true } });

const view = computed(() => {
    const t = props.tenant;
    if (t.status === 'archived') return { label: 'مؤرشف', tone: 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' };
    if (t.status === 'suspended') return { label: 'موقوف', tone: 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400' };
    if (t.is_expired) return { label: 'منتهي الاشتراك', tone: 'bg-red-50 dark:bg-red-950/50 text-red-700 dark:text-red-400' };
    return { label: 'نشط', tone: 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400' };
});
</script>
