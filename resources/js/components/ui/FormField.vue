<template>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">
            {{ label }}<span v-if="required" class="text-red-500"> *</span>
        </label>
        <slot />
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
});
</script>