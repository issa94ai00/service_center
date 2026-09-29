import { ref, shallowRef } from 'vue';

/**
 * Promise-based confirmation.
 *
 *   if (!(await confirm({ title: '...', tone: 'danger' }))) return;
 *
 * The native `confirm()` it replaces cannot be styled, cannot be read in RTL
 * next to the record it refers to, and gives no room to spell out what a delete
 * actually destroys.
 */

const state = ref(null);
let resolver = shallowRef(null);

export function confirm(options = {}) {
    // A second request while one is open resolves the first as "cancelled"
    // rather than stranding its promise forever.
    if (resolver.value) {
        resolver.value(false);
        resolver.value = null;
    }

    state.value = {
        title: options.title ?? 'تأكيد',
        message: options.message ?? '',
        detail: options.detail ?? '',
        confirmLabel: options.confirmLabel ?? 'تأكيد',
        cancelLabel: options.cancelLabel ?? 'إلغاء',
        tone: options.tone ?? 'primary',
    };

    return new Promise((resolve) => {
        resolver.value = resolve;
    });
}

export function useConfirmHost() {
    function settle(value) {
        const resolve = resolver.value;
        resolver.value = null;
        state.value = null;
        resolve?.(value);
    }

    return {
        request: state,
        accept: () => settle(true),
        reject: () => settle(false),
    };
}

export function useConfirm() {
    return { confirm };
}
