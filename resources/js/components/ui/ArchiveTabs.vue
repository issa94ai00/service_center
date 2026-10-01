<template>
    <!-- Current items vs. the archive. The count stays visible from the
         active tab, so archived items are never "lost". -->
    <div class="flex rounded-lg border border-slate-300 p-0.5" role="tablist" aria-label="عرض">
        <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            role="tab"
            :aria-selected="modelValue === tab.value"
            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition"
            :class="modelValue === tab.value ? 'bg-slate-900 font-medium text-white' : 'text-slate-600 hover:bg-slate-50'"
            @click="$emit('update:modelValue', tab.value)"
        >
            {{ tab.label }}
            <span
                v-if="tab.value === 'archived' && archivedCount > 0"
                class="rounded-full px-1.5 text-[11px] font-semibold tabular-nums"
                :class="modelValue === tab.value ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
            >{{ archivedCount }}</span>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: 'active' },
    archivedCount: { type: Number, default: 0 },
    activeLabel: { type: String, default: 'الحالية' },
});

defineEmits(['update:modelValue']);

const tabs = computed(() => [
    { value: 'active', label: props.activeLabel },
    { value: 'archived', label: '🗄️ الأرشيف' },
]);
</script>
