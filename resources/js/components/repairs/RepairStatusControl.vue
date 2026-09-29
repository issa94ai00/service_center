<template>
    <span class="relative inline-flex">
        <span
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1"
            :class="[meta.tone, busy ? 'opacity-50' : '']"
        >
            <span class="h-1.5 w-1.5 rounded-full" :class="meta.dot"></span>
            {{ row.status_label || meta.label }}
            <span v-if="editable" class="text-[9px] opacity-60">▾</span>
        </span>

        <!-- A transparent native <select> over the badge: the full keyboard and
             mobile wheel behaviour, none of the default chrome. Changing status
             is the most repeated action here, so it should not cost a round trip
             through the edit form. -->
        <select
            v-if="editable"
            class="absolute inset-0 cursor-pointer opacity-0"
            aria-label="تغيير حالة الجهاز"
            :disabled="busy"
            :value="row.status"
            @change="$emit('change', row, $event.target.value)"
        >
            <option v-for="s in REPAIR_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { REPAIR_STATUSES, repairStatus } from '../../constants/repairStatus';

const props = defineProps({
    row: { type: Object, required: true },
    editable: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});

defineEmits(['change']);

const meta = computed(() => repairStatus(props.row.status));
</script>
