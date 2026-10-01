<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[100] flex flex-col items-center gap-2 p-4 sm:items-start sm:p-6">
            <TransitionGroup
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
                move-class="transition duration-150"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border px-4 py-3 shadow-lg"
                    :class="tone(toast.type)"
                    role="status"
                    aria-live="polite"
                >
                    <span class="mt-0.5 shrink-0 text-base leading-none">{{ icon(toast.type) }}</span>
                    <div class="min-w-0 flex-1">
                        <p v-if="toast.title" class="text-sm font-semibold">{{ toast.title }}</p>
                        <p class="text-sm leading-snug">{{ toast.message }}</p>
                    </div>
                    <button
                        v-if="toast.action"
                        type="button"
                        class="shrink-0 rounded-lg px-2 py-0.5 text-sm font-semibold underline-offset-2 hover:underline"
                        @click="runAction(toast)"
                    >
                        {{ toast.action.label }}
                    </button>
                    <button
                        type="button"
                        class="shrink-0 rounded p-1 opacity-50 transition hover:opacity-100"
                        aria-label="إغلاق"
                        @click="dismiss(toast.id)"
                    >
                        ✕
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { useToast } from '../../composables/useToast';

const { toasts, dismiss } = useToast();

function runAction(toast) {
    dismiss(toast.id);
    toast.action.onClick?.();
}

const TONES = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    error: 'border-red-200 bg-red-50 text-red-700',
    info: 'border-slate-200 bg-white text-slate-700',
};

const ICONS = { success: '✅', error: '⚠️', info: 'ℹ️' };

function tone(type) {
    return TONES[type] ?? TONES.info;
}

function icon(type) {
    return ICONS[type] ?? ICONS.info;
}
</script>
