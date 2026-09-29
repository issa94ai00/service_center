<template>
    <div class="relative">
        <div class="relative">
            <input
                v-model="text"
                type="text"
                role="combobox"
                :aria-expanded="open"
                aria-autocomplete="list"
                autocomplete="off"
                placeholder="اكتب أو اختر ماركة..."
                class="w-full rounded-lg border border-slate-300 py-2 pr-9 pl-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                @focus="open = true"
                @input="onInput"
                @keydown.down.prevent="moveHighlight(1)"
                @keydown.up.prevent="moveHighlight(-1)"
                @keydown.enter.prevent="onEnter"
                @keydown.esc="open = false"
                @blur="onBlur"
            />
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4m16 0a8 8 0 1 0-16 0m16 0a8 8 0 1 1-16 0" />
                </svg>
            </span>
        </div>

        <div
            v-if="open && (suggestions.length || queryNotListed)"
            class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
        >
            <ul class="max-h-52 overflow-y-auto py-1">
                <li v-for="(b, i) in suggestions" :key="b">
                    <button
                        type="button"
                        class="flex w-full px-3 py-2 text-right text-sm text-slate-700 transition hover:bg-blue-50"
                        :class="i === highlightIndex ? 'bg-blue-50' : ''"
                        @mousedown.prevent
                        @click="choose(b)"
                    >
                        {{ b }}
                    </button>
                </li>
            </ul>
            <div v-if="queryNotListed" class="border-t border-slate-100">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2.5 text-right text-sm font-medium text-blue-600 transition hover:bg-blue-50"
                    :class="highlightIndex === suggestions.length ? 'bg-blue-50' : ''"
                    @mousedown.prevent
                    @click="applyQuickAdd"
                >
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold">+</span>
                    إضافة ماركة «{{ text.trim() }}»
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const text = ref(String(props.modelValue ?? ''));
const open = ref(false);
const highlightIndex = ref(-1);

watch(
    () => props.modelValue,
    (v) => {
        text.value = String(v ?? '');
    }
);

const suggestions = computed(() => {
    const current = text.value.trim();
    const q = current.toLowerCase();
    let list = props.options.filter((b) => String(b).length > 0);
    if (q) {
        list = list.filter((b) => b.toLowerCase().includes(q));
    }
    return list.filter((b) => b.trim().toLowerCase() !== current.toLowerCase()).slice(0, 30);
});

const queryNotListed = computed(() => {
    const current = text.value.trim();
    if (!current) return false;
    return !props.options.some((b) => b.trim().toLowerCase() === current.toLowerCase());
});

function onInput() {
    emit('update:modelValue', text.value);
    open.value = true;
    highlightIndex.value = -1;
}

function onBlur() {
    setTimeout(() => {
        open.value = false;
        highlightIndex.value = -1;
    }, 120);
}

function moveHighlight(delta) {
    const total = suggestions.value.length + (queryNotListed.value ? 1 : 0);
    if (!total) return;
    open.value = true;
    highlightIndex.value = (highlightIndex.value + delta + total) % total;
}

function onEnter() {
    if (!open.value) {
        open.value = true;
        return;
    }
    if (suggestions.value.length) {
        const idx = highlightIndex.value >= 0 && highlightIndex.value < suggestions.value.length ? highlightIndex.value : 0;
        choose(suggestions.value[idx]);
    } else if (queryNotListed.value) {
        applyQuickAdd();
    }
}

function choose(s) {
    const value = String(s).trim();
    text.value = value;
    emit('update:modelValue', value);
    open.value = false;
    highlightIndex.value = -1;
}

function applyQuickAdd() {
    choose(text.value);
}
</script>