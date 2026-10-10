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
                v-if="request"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
                role="alertdialog"
                aria-modal="true"
                @click.self="reject"
            >
                <div class="max-h-[calc(100dvh-2rem)] w-full max-w-md overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start gap-4">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xl"
                            :class="request.tone === 'danger' ? 'bg-red-100' : 'bg-blue-100'"
                        >
                            {{ request.tone === 'danger' ? '🗑️' : '❓' }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold text-slate-900">{{ request.title }}</h2>
                            <p v-if="request.message" class="mt-1 text-sm text-slate-600">{{ request.message }}</p>
                            <p v-if="request.detail" class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                {{ request.detail }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="reject"
                        >
                            {{ request.cancelLabel }}
                        </button>
                        <button
                            ref="confirmButton"
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-white transition"
                            :class="request.tone === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"
                            @click="accept"
                        >
                            {{ request.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useConfirmHost } from '../../composables/useConfirm';

const { request, accept, reject } = useConfirmHost();
const confirmButton = ref(null);

watch(request, (value) => {
    if (value) {
        nextTick(() => confirmButton.value?.focus());
    }
});

function onKeydown(event) {
    if (!request.value) return;
    if (event.key === 'Escape') {
        event.stopPropagation();
        reject();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown, true));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown, true));
</script>
