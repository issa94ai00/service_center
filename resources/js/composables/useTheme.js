import { computed, ref } from 'vue';

/**
 * Light/dark theme for the whole app.
 *
 * The choice lives on <html> as a `dark` class (see @custom-variant dark in
 * app.css) and is remembered in localStorage. State is module-level so every
 * toggle on the page agrees, and an inline script in app.blade.php applies the
 * stored choice before Vue mounts to avoid a flash of the wrong theme.
 */

const STORAGE_KEY = 'theme';
const DARK = 'dark';
const LIGHT = 'light';

const theme = ref(readTheme());

function prefersDark() {
    return typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches;
}

function readTheme() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored === DARK || stored === LIGHT) return stored;
    } catch {
        // Private window or blocked storage: fall back to the system preference.
    }
    return prefersDark() ? DARK : LIGHT;
}

function apply() {
    if (typeof document === 'undefined') return;

    document.documentElement.classList.toggle(DARK, theme.value === DARK);
    document.documentElement.style.colorScheme = theme.value;

    // Keep the PWA address bar in step with the header.
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = theme.value === DARK ? '#020617' : '#172554';
}

// Apply on import too, in case the blade script was skipped.
apply();

export function useTheme() {
    const isDark = computed(() => theme.value === DARK);

    function setTheme(next) {
        theme.value = next === DARK ? DARK : LIGHT;
        try {
            localStorage.setItem(STORAGE_KEY, theme.value);
        } catch {
            // Not fatal: the choice holds for this session.
        }
        apply();
    }

    function toggleTheme() {
        setTheme(isDark.value ? LIGHT : DARK);
    }

    return { theme, isDark, setTheme, toggleTheme };
}
