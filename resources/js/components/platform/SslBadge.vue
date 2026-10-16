<template>
    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="view.tone">{{ view.label }}</span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ status: { type: String, default: 'pending' } });

const views = {
    issued: { label: 'مفعّل', tone: 'bg-emerald-50 text-emerald-700' },
    failed: { label: 'فشل', tone: 'bg-red-50 text-red-700' },
    skipped: { label: 'غير مُعدّ', tone: 'bg-slate-100 text-slate-500' },
    pending: { label: 'قيد الإصدار', tone: 'bg-amber-50 text-amber-700' },
};

const view = computed(() => views[props.status] || views.pending);
</script>
