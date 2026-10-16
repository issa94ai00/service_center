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
    if (t.status === 'archived') return { label: 'مؤرشف', tone: 'bg-slate-100 text-slate-500' };
    if (t.status === 'suspended') return { label: 'موقوف', tone: 'bg-amber-100 text-amber-800' };
    if (t.is_expired) return { label: 'منتهي الاشتراك', tone: 'bg-red-100 text-red-700' };
    return { label: 'نشط', tone: 'bg-emerald-100 text-emerald-700' };
});
</script>
