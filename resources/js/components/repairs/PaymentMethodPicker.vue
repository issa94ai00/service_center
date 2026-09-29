<template>
    <div class="space-y-1">
        <p class="text-xs font-medium text-slate-500">{{ label }}</p>
        <div class="flex flex-wrap gap-1.5">
            <button
                v-for="method in PAYMENT_METHODS"
                :key="method.value"
                type="button"
                class="rounded-full border px-3 py-1 text-xs font-medium transition"
                :class="modelValue === method.value
                    ? 'border-blue-600 bg-blue-600 text-white'
                    : 'border-slate-300 bg-white text-slate-600 hover:border-blue-400 hover:text-blue-600'"
                @click="$emit('update:modelValue', method.value)"
            >{{ method.label }}</button>
        </div>
    </div>
</template>

<script setup>
import { PAYMENT_METHODS } from '../../constants/paymentMethods';

defineProps({
    modelValue: { type: String, default: 'CASH' },
    label: { type: String, default: 'طريقة الدفع' },
});

defineEmits(['update:modelValue']);
</script>
