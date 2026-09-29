<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 backdrop-blur-sm"
                :style="{ zIndex: zIndex }"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                @click.self="requestClose"
            >
                <div
                    class="my-4 flex max-h-[calc(100vh-2rem)] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:my-8"
                    :class="maxWidth"
                >
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-bold text-slate-900 sm:text-lg">{{ title }}</h2>
                            <p v-if="subtitle" class="mt-0.5 truncate text-xs text-slate-500">{{ subtitle }}</p>
                        </div>
                        <button
                            type="button"
                            class="-me-2 shrink-0 rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                            aria-label="إغلاق"
                            @click="requestClose"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, toRef } from 'vue';
import { useModalLayer } from '../../composables/useModalLayer';

const props = defineProps({
    open: Boolean,
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-2xl' },
    /** Set false when a half-typed form should not be lost to a stray click. */
    closeOnOverlay: { type: Boolean, default: true },
    closeOnEsc: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

function requestClose() {
    if (props.closeOnOverlay) emit('close');
}

function onEscape() {
    if (props.closeOnEsc) emit('close');
}

const { depth } = useModalLayer(toRef(props, 'open'), onEscape);
const zIndex = computed(() => 50 + depth.value * 10);
</script>
