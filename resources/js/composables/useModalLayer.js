import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Shared bookkeeping for stacked modals.
 *
 * Module-level so every dialog on the page — including one opened from inside
 * another, such as the quick-add customer form over the receive-device form —
 * agrees on who is on top. That decides three things a single modal cannot
 * decide alone: who owns the Escape key, who stacks above whom, and when the
 * page behind is allowed to scroll again.
 */
const stack = [];

export function useModalLayer(isOpen, onEscape) {
    const token = Symbol('modal');
    const depth = ref(0);

    function onKeydown(event) {
        if (event.key !== 'Escape') return;
        if (stack[stack.length - 1] !== token) return;
        event.stopPropagation();
        onEscape();
    }

    function enter() {
        if (stack.includes(token)) return;
        stack.push(token);
        depth.value = stack.length - 1;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeydown, true);
    }

    function leave() {
        const index = stack.indexOf(token);
        if (index === -1) return;
        stack.splice(index, 1);
        window.removeEventListener('keydown', onKeydown, true);
        if (stack.length === 0) {
            document.body.style.overflow = '';
        }
    }

    watch(isOpen, (value) => (value ? enter() : leave()), { immediate: true });
    onBeforeUnmount(leave);

    return { depth };
}
