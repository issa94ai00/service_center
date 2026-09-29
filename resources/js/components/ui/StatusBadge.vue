<template>
    <span
        class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
        :class="{
            'bg-slate-100 text-slate-600': status === 'DRAFT',
            'bg-emerald-100 text-emerald-700': status === 'POSTED',
            'bg-red-100 text-red-600': status === 'CANCELLED',
        }"
    >
        {{ labels[status] || status }}
    </span>
</template>

<script setup>
defineProps({
    status: { type: String, required: true },
});

const labels = {
    DRAFT: 'مسودة',
    POSTED: 'معتمدة',
    CANCELLED: 'ملغاة',
};
</script>