<template>
    <section class="dash-card flex min-w-0 flex-col rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
        <header v-if="title" class="flex items-start justify-between gap-3 px-5 pt-5">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    v-if="icon"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-[17px]"
                    :class="tone"
                >
                    <AppIcon :name="icon" />
                </span>
                <div class="min-w-0">
                    <h3 class="truncate text-[15px] font-bold text-slate-900 dark:text-white">{{ title }}</h3>
                    <p v-if="subtitle" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
                </div>
            </div>
            <slot name="actions">
                <RouterLink
                    v-if="to"
                    :to="to"
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 dark:text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-blue-400 print:hidden"
                >
                    {{ linkLabel }}
                    <AppIcon name="arrow-up-right" class="rtl:-scale-x-100" />
                </RouterLink>
            </slot>
        </header>
        <div class="min-w-0 flex-1" :class="flush ? 'pt-4' : 'p-5'">
            <slot />
        </div>
    </section>
</template>

<script setup>
import { RouterLink } from 'vue-router';
import AppIcon from '../ui/AppIcon.vue';

/** A titled dashboard card: icon chip, heading, and an optional "open" link or custom actions. */
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    icon: { type: String, default: '' },
    tone: { type: String, default: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' },
    to: { type: Object, default: null },
    linkLabel: { type: String, default: '' },
    /** Let the body run edge to edge (lists, tables) instead of padding it. */
    flush: { type: Boolean, default: false },
});
</script>
