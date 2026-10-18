<template>
    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold" :class="view.tone">
        <AppIcon :name="status === 'issued' ? 'lock' : status === 'failed' ? 'x-circle' : 'clock'" class="text-[12px]" />
        {{ view.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';
import AppIcon from '../ui/AppIcon.vue';

const props = defineProps({ status: { type: String, default: 'pending' } });

const views = {
    issued: { label: 'مفعّل', tone: 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400' },
    failed: { label: 'فشل', tone: 'bg-red-50 dark:bg-red-950/50 text-red-700 dark:text-red-400' },
    skipped: { label: 'غير مُعدّ', tone: 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' },
    pending: { label: 'قيد الإصدار', tone: 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400' },
};

const view = computed(() => views[props.status] || views.pending);
</script>
