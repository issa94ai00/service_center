<template>
    <!-- Split button: the main half prints on this device's paper for the
         document kind in one click; the caret prints once on another paper,
         or switches between preview and direct printing. -->
    <span class="inline-flex items-stretch" :class="wrapperClass ?? preset.wrapper">
        <button
            type="button"
            :class="buttonClass ?? preset.button"
            :disabled="busy || disabled"
            :title="`${title} — ${currentPaper.label}`"
            :aria-label="`${title} على ${currentPaper.label}`"
            @click="print()"
        >
            <slot :busy="busy" :paper="currentPaper">
                <span v-if="variant === 'icon'">
                    <span v-if="busy" class="inline-block animate-pulse">⏳</span>
                    <template v-else>🖨️</template>
                </span>
                <template v-else>{{ busy ? busyLabel : label }}</template>
            </slot>
        </button>
        <button
            ref="caret"
            type="button"
            :class="caretClass ?? preset.caret"
            :disabled="busy || disabled"
            aria-haspopup="menu"
            :aria-expanded="menuOpen"
            title="اختر حجم الورق"
            @click="toggleMenu"
        >
            <span class="text-[10px] leading-none" aria-hidden="true">▾</span>
            <span class="sr-only">خيارات الطباعة</span>
        </button>

        <Teleport to="body">
            <div
                v-if="menuOpen"
                ref="menu"
                role="menu"
                dir="rtl"
                class="fixed z-[200] max-h-[min(20rem,calc(100dvh-1rem))] w-64 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 text-right shadow-xl ring-1 ring-black/5"
                :style="menuStyle"
                @keydown.esc.prevent="closeMenu(true)"
                @keydown.down.prevent="moveFocus(1)"
                @keydown.up.prevent="moveFocus(-1)"
            >
                <p class="px-2.5 pb-1 pt-1.5 text-[11px] font-semibold text-slate-400">اطبع على</p>

                <button
                    v-for="paper in papersFor(kind)"
                    :key="paper.value"
                    type="button"
                    role="menuitem"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-700 transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                    @click="print(paper.value)"
                >
                    <PaperGlyph :paper="paper" :active="paper.value === currentPaper.value" />
                    <span class="flex-1" :class="paper.value === currentPaper.value ? 'font-semibold text-slate-900' : ''">
                        {{ paper.label }}
                    </span>
                    <span
                        v-if="paper.value === currentPaper.value"
                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="deviceOverride(kind) ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700'"
                    >{{ deviceOverride(kind) ? 'هذا الجهاز' : 'الافتراضي' }}</span>
                </button>

                <div class="my-1.5 border-t border-slate-100"></div>

                <button
                    type="button"
                    role="menuitemcheckbox"
                    :aria-checked="mode === 'direct'"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-700 transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                    @click="setMode(mode === 'direct' ? 'preview' : 'direct')"
                >
                    <span
                        class="relative inline-flex h-4 w-7 shrink-0 rounded-full transition-colors"
                        :class="mode === 'direct' ? 'bg-blue-600' : 'bg-slate-300'"
                        aria-hidden="true"
                    >
                        <span
                            class="absolute top-0.5 h-3 w-3 rounded-full bg-white shadow transition-all"
                            :class="mode === 'direct' ? 'left-0.5' : 'right-0.5'"
                        ></span>
                    </span>
                    <span class="flex-1">
                        طباعة مباشرة
                        <span class="block text-[11px] text-slate-400">بلا معاينة — تفتح نافذة الطابعة فورًا</span>
                    </span>
                </button>

                <button
                    type="button"
                    role="menuitem"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-600 transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                    @click="openPreferences"
                >
                    <span class="w-[18px] text-center" aria-hidden="true">⚙️</span>
                    <span class="flex-1">تفضيلات الطباعة لهذا الجهاز</span>
                </button>
            </div>
        </Teleport>
    </span>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import PaperGlyph from './PaperGlyph.vue';
import { usePdf } from '../../composables/usePdf';
import { openPrintPreferences, usePrintPreferences } from '../../composables/usePrintPreferences';
import { useToast } from '../../composables/useToast';

const props = defineProps({
    /** invoice | payment | repair | statement — picks the default paper. */
    kind: { type: String, required: true },
    /** (paper) => Promise<blob URL>. Receives the chosen paper value. */
    load: { type: Function, required: true },
    label: { type: String, default: 'طباعة' },
    busyLabel: { type: String, default: '...' },
    title: { type: String, default: 'طباعة' },
    disabled: { type: Boolean, default: false },
    /** link | icon | outline | button */
    variant: { type: String, default: 'link' },
    /** Override the preset classes for a one-off look. */
    wrapperClass: { type: [String, Array, Object], default: null },
    buttonClass: { type: [String, Array, Object], default: null },
    caretClass: { type: [String, Array, Object], default: null },
});

const emit = defineEmits(['error']);

const PRESETS = {
    link: {
        wrapper: 'gap-0.5',
        button: 'text-slate-500 hover:text-slate-900 disabled:opacity-40',
        caret: 'inline-flex min-h-7 min-w-7 items-center justify-center rounded px-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-40',
    },
    icon: {
        wrapper: '',
        button: 'rounded-s-lg p-1.5 text-sm leading-none transition hover:bg-slate-100 disabled:opacity-40',
        caret: 'inline-flex min-w-7 items-center justify-center rounded-e-lg px-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-40',
    },
    outline: {
        wrapper: 'rounded-lg border border-slate-300',
        button: 'rounded-s-lg px-3 py-1.5 text-xs text-slate-600 transition hover:bg-slate-50 disabled:opacity-40',
        caret: 'inline-flex min-w-7 items-center justify-center rounded-e-lg border-s border-slate-300 px-1.5 text-slate-500 transition hover:bg-slate-50 disabled:opacity-40',
    },
    button: {
        wrapper: 'rounded-lg border border-slate-300 bg-white',
        button: 'rounded-s-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50 disabled:opacity-40',
        caret: 'rounded-e-lg border-s border-slate-300 px-2 text-slate-500 transition hover:bg-slate-50 disabled:opacity-40',
    },
};

const preset = computed(() => PRESETS[props.variant] || PRESETS.link);

const { papersFor, paperFor, paperInfo, deviceOverride, mode, setMode } = usePrintPreferences();
const { generating, pdfError, openPdf } = usePdf();
const toast = useToast();

const busy = computed(() => generating.value !== null);
const currentPaper = computed(() => paperInfo(paperFor(props.kind)));

watch(pdfError, (message) => {
    if (!message) return;
    toast.error(message);
    emit('error', message);
});

function print(paper) {
    closeMenu();
    const chosen = paper || paperFor(props.kind);
    openPdf(props.kind, () => props.load(chosen));
}

// ---------- menu --------------------------------------------------------------

const caret = ref(null);
const menu = ref(null);
const menuOpen = ref(false);
const menuStyle = ref({});

function place() {
    const rect = caret.value?.getBoundingClientRect();
    if (!rect) return;

    const width = 256;
    const gap = 6;
    // RTL: hang the menu from the caret's right edge, kept on screen.
    const right = Math.min(Math.max(window.innerWidth - rect.right, 8), window.innerWidth - width - 8);
    const below = window.innerHeight - rect.bottom;

    menuStyle.value = below > 300 || below > rect.top
        ? { right: `${right}px`, top: `${rect.bottom + gap}px` }
        : { right: `${right}px`, bottom: `${window.innerHeight - rect.top + gap}px` };
}

async function toggleMenu() {
    if (menuOpen.value) return closeMenu();

    place();
    menuOpen.value = true;
    await nextTick();
    menu.value?.querySelector('[role^="menuitem"]')?.focus();

    document.addEventListener('mousedown', onOutside, true);
    window.addEventListener('scroll', onViewportChange, true);
    window.addEventListener('resize', onViewportChange);
}

function closeMenu(returnFocus = false) {
    if (!menuOpen.value) return;
    menuOpen.value = false;
    document.removeEventListener('mousedown', onOutside, true);
    window.removeEventListener('scroll', onViewportChange, true);
    window.removeEventListener('resize', onViewportChange);
    if (returnFocus) caret.value?.focus();
}

function onOutside(event) {
    if (menu.value?.contains(event.target) || caret.value?.contains(event.target)) return;
    closeMenu();
}

function onViewportChange(event) {
    // Scrolling inside the menu itself is fine.
    if (event?.type === 'scroll' && menu.value?.contains(event.target)) return;
    closeMenu();
}

function moveFocus(step) {
    const items = [...(menu.value?.querySelectorAll('[role^="menuitem"]') || [])];
    const index = items.indexOf(document.activeElement);
    items[(index + step + items.length) % items.length]?.focus();
}

function openPreferences() {
    closeMenu();
    openPrintPreferences();
}

onBeforeUnmount(() => closeMenu());
</script>
