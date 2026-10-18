import { computed, ref } from 'vue';

/**
 * Appearance for the whole app: a light/dark/system mode plus an accent color.
 *
 * Dark mode lives on <html> as a `dark` class (see @custom-variant dark in
 * app.css); the accent lives on <html> as `data-accent`, which remaps the
 * `--color-blue-*` scale the UI is built on. Both are remembered in
 * localStorage. State is module-level so every control on the page agrees, and
 * an inline script in app.blade.php applies the stored choice before Vue mounts
 * to avoid a flash of the wrong theme.
 */

const MODE_KEY = 'theme';
const ACCENT_KEY = 'accent';
const DARK = 'dark';
const LIGHT = 'light';
const SYSTEM = 'system';

// Keep in step with the html[data-accent] blocks in app.css and the blade script.
// `bar` is the 950 shade, used for the PWA address bar in light mode.
export const ACCENTS = [
    { key: 'blue', label: 'أزرق', swatch: '#2563eb', bar: '#172554' },
    { key: 'indigo', label: 'نيلي', swatch: '#4f46e5', bar: '#1e1b4b' },
    { key: 'violet', label: 'بنفسجي', swatch: '#7c3aed', bar: '#2e1065' },
    { key: 'teal', label: 'فيروزي', swatch: '#0f766e', bar: '#042f2e' },
    { key: 'emerald', label: 'زمردي', swatch: '#047857', bar: '#022c22' },
    { key: 'orange', label: 'برتقالي', swatch: '#c2410c', bar: '#431407' },
];

export const MODES = [
    { key: LIGHT, label: 'فاتح', icon: 'sun' },
    { key: DARK, label: 'داكن', icon: 'moon' },
    { key: SYSTEM, label: 'تلقائي', icon: 'monitor' },
];

const media = typeof window !== 'undefined' ? window.matchMedia?.('(prefers-color-scheme: dark)') : null;

const mode = ref(read(MODE_KEY, [DARK, LIGHT], SYSTEM));
const accent = ref(read(ACCENT_KEY, ACCENTS.map((a) => a.key), 'blue'));
const systemDark = ref(!!media?.matches);

const resolved = computed(() => (mode.value === SYSTEM ? (systemDark.value ? DARK : LIGHT) : mode.value));

function read(key, allowed, fallback) {
    try {
        const stored = localStorage.getItem(key);
        if (allowed.includes(stored)) return stored;
    } catch {
        // Private window or blocked storage: use the default.
    }
    return fallback;
}

function store(key, value, isDefault) {
    try {
        if (isDefault) localStorage.removeItem(key);
        else localStorage.setItem(key, value);
    } catch {
        // Not fatal: the choice holds for this session.
    }
}

function apply() {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    const dark = resolved.value === DARK;

    // Switch without every transition on the page animating to the new colors.
    root.classList.add('theme-switching');
    root.classList.toggle(DARK, dark);
    root.style.colorScheme = resolved.value;
    if (accent.value === 'blue') delete root.dataset.accent;
    else root.dataset.accent = accent.value;
    requestAnimationFrame(() => requestAnimationFrame(() => root.classList.remove('theme-switching')));

    // Keep the PWA address bar in step with the header.
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = dark ? '#020617' : ACCENTS.find((a) => a.key === accent.value).bar;
}

media?.addEventListener?.('change', (e) => {
    systemDark.value = e.matches;
    if (mode.value === SYSTEM) apply();
});

// Apply on import too, in case the blade script was skipped.
apply();

export function useTheme() {
    const isDark = computed(() => resolved.value === DARK);

    function setMode(next) {
        mode.value = [DARK, LIGHT].includes(next) ? next : SYSTEM;
        store(MODE_KEY, mode.value, mode.value === SYSTEM);
        apply();
    }

    function setAccent(next) {
        accent.value = ACCENTS.some((a) => a.key === next) ? next : 'blue';
        store(ACCENT_KEY, accent.value, accent.value === 'blue');
        apply();
    }

    function toggleTheme() {
        setMode(isDark.value ? LIGHT : DARK);
    }

    return { mode, accent, isDark, setMode, setAccent, toggleTheme, setTheme: setMode };
}
