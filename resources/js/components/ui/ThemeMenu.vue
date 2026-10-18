<template>
    <AppDropdown panel-class="w-64 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 shadow-xl">
        <template #trigger="{ toggle, open }">
            <button
                type="button"
                data-dropdown-trigger
                :class="[triggerClass, open ? 'bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white' : '']"
                title="المظهر"
                aria-label="المظهر"
                aria-haspopup="menu"
                :aria-expanded="open"
                @click="toggle"
            >
                <AppIcon :name="isDark ? 'moon' : 'sun'" :class="iconClass" />
            </button>
        </template>

        <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">الوضع</p>
        <div class="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 dark:bg-slate-800 p-1" role="radiogroup" aria-label="الوضع">
            <button
                v-for="m in MODES"
                :key="m.key"
                type="button"
                role="radio"
                :aria-checked="mode === m.key"
                class="flex flex-col items-center gap-1 rounded-lg px-2 py-2 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                :class="
                    mode === m.key
                        ? 'bg-white dark:bg-slate-950 text-blue-700 dark:text-blue-300 shadow-xs'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                "
                @click="setMode(m.key)"
            >
                <AppIcon :name="m.icon" class="h-4.5 w-4.5" />
                {{ m.label }}
            </button>
        </div>

        <p class="mb-2 mt-4 text-xs font-semibold text-slate-500 dark:text-slate-400">لون الواجهة</p>
        <div class="grid grid-cols-6 gap-2" role="radiogroup" aria-label="لون الواجهة">
            <button
                v-for="a in ACCENTS"
                :key="a.key"
                type="button"
                role="radio"
                :aria-checked="accent === a.key"
                :title="a.label"
                :aria-label="a.label"
                class="grid aspect-square place-items-center rounded-full text-white ring-offset-2 ring-offset-white dark:ring-offset-slate-900 transition hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                :class="accent === a.key ? 'ring-2 ring-slate-400 dark:ring-slate-500' : ''"
                :style="{ backgroundColor: a.swatch }"
                @click="setAccent(a.key)"
            >
                <AppIcon v-if="accent === a.key" name="check" class="h-4 w-4" :stroke-width="3" />
            </button>
        </div>
    </AppDropdown>
</template>

<script setup>
import AppDropdown from './AppDropdown.vue';
import AppIcon from './AppIcon.vue';
import { ACCENTS, MODES, useTheme } from '../../composables/useTheme';

defineProps({
    triggerClass: { type: String, default: '' },
    iconClass: { type: String, default: 'h-5 w-5' },
});

const { mode, accent, isDark, setMode, setAccent } = useTheme();
</script>
