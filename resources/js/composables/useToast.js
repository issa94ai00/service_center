import { readonly, ref } from 'vue';

/**
 * Small app-wide toast queue.
 *
 * Replaces `alert()` for save/delete outcomes: a native alert blocks the whole
 * tab and cannot say "saved" without the user clicking OK, which is a poor fit
 * for a counter workflow where devices are logged one after another.
 */

const items = ref([]);
let seq = 0;

/**
 * `action: { label, onClick }` adds one button — "تراجع" after an archive —
 * that runs once and closes the toast.
 */
function push(type, message, { timeout = 4000, title = '', action = null } = {}) {
    const id = ++seq;
    items.value.push({ id, type, message, title, action });

    if (timeout > 0) {
        setTimeout(() => dismiss(id), timeout);
    }

    return id;
}

export function dismiss(id) {
    items.value = items.value.filter((t) => t.id !== id);
}

export function useToast() {
    return {
        toasts: readonly(items),
        dismiss,
        success: (message, options) => push('success', message, options),
        error: (message, options) => push('error', message, { timeout: 6000, ...options }),
        info: (message, options) => push('info', message, options),
    };
}
