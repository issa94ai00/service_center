<template>
    <div class="text-center">
        <p class="text-3xl">{{ filtered ? '🔍' : '🔧' }}</p>
        <p class="mt-2 text-sm font-medium text-slate-600">
            {{ filtered ? 'لا توجد أجهزة مطابقة لهذه التصفية' : 'لم يتم استلام أي جهاز بعد' }}
        </p>
        <p class="mt-1 text-xs text-slate-400">
            {{ filtered ? 'جرّب توسيع نطاق البحث أو المدة الزمنية' : 'ابدأ باستلام أول جهاز صيانة' }}
        </p>
        <button
            v-if="filtered"
            type="button"
            class="mt-4 rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50"
            @click="$emit('reset')"
        >
            مسح التصفية
        </button>
        <button
            v-else-if="canCreate"
            type="button"
            class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700"
            @click="$emit('create')"
        >
            ＋ استلام جهاز
        </button>
    </div>
</template>

<script setup>
defineProps({
    filtered: { type: Boolean, default: false },
    canCreate: { type: Boolean, default: false },
});

defineEmits(['create', 'reset']);
</script>
