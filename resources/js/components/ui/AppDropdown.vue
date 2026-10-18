<template>
    <div ref="root" class="relative">
        <slot name="trigger" :open="isOpen" :toggle="toggle" />

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="isOpen"
                ref="panel"
                class="absolute z-50 mt-2"
                :class="[align === 'start' ? 'start-0' : 'end-0', panelClass]"
                role="menu"
            >
                <slot :close="close" />
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    panelClass: { type: String, default: '' },
    align: { type: String, default: 'end' },
});

const root = ref(null);
const isOpen = ref(false);

function toggle() {
    isOpen.value = !isOpen.value;
}

function close() {
    isOpen.value = false;
}

function onPointerDown(e) {
    if (root.value && !root.value.contains(e.target)) close();
}

function onKeydown(e) {
    if (e.key !== 'Escape') return;
    close();
    root.value?.querySelector('[data-dropdown-trigger]')?.focus();
}

watch(isOpen, (nowOpen) => {
    if (typeof document === 'undefined') return;

    if (nowOpen) {
        document.addEventListener('pointerdown', onPointerDown, true);
        document.addEventListener('keydown', onKeydown);
    } else {
        document.removeEventListener('pointerdown', onPointerDown, true);
        document.removeEventListener('keydown', onKeydown);
    }
});

onBeforeUnmount(() => {
    if (typeof document === 'undefined') return;
    document.removeEventListener('pointerdown', onPointerDown, true);
    document.removeEventListener('keydown', onKeydown);
});
</script>
