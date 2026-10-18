<template>
    <!-- Mirrors the real layout so the dashboard does not jump when data lands. -->
    <div class="animate-pulse grid gap-4 lg:grid-cols-12" role="status" aria-live="polite">
        <span class="sr-only">جارٍ تحميل لوحة التحكم</span>

        <!-- Profit + position -->
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 lg:col-span-8">
            <div class="h-4 w-40 rounded-md bg-slate-200 dark:bg-slate-800"></div>
            <div class="mt-5 h-11 w-56 rounded-lg bg-slate-200 dark:bg-slate-800"></div>
            <div class="mt-8 h-3 w-full rounded-full bg-slate-100 dark:bg-slate-800"></div>
            <div class="mt-4 grid grid-cols-4 gap-4">
                <div v-for="n in 4" :key="n" class="h-9 rounded-md bg-slate-100 dark:bg-slate-800/60"></div>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 lg:col-span-4">
            <div class="h-4 w-32 rounded-md bg-slate-200 dark:bg-slate-800"></div>
            <div class="mt-5 h-9 w-44 rounded-lg bg-slate-200 dark:bg-slate-800"></div>
            <div class="mt-8 h-2.5 w-full rounded-full bg-slate-100 dark:bg-slate-800"></div>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="h-14 rounded-xl bg-slate-100 dark:bg-slate-800/60"></div>
                <div class="h-14 rounded-xl bg-slate-100 dark:bg-slate-800/60"></div>
            </div>
        </div>

        <!-- KPI strip -->
        <div class="grid grid-cols-2 overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 sm:grid-cols-3 xl:grid-cols-6 lg:col-span-12">
            <div v-for="n in 6" :key="n" class="space-y-3 p-5">
                <div class="h-3.5 w-24 rounded-md bg-slate-200 dark:bg-slate-800"></div>
                <div class="h-7 w-28 rounded-md bg-slate-200 dark:bg-slate-800"></div>
                <div class="h-4 w-16 rounded-full bg-slate-100 dark:bg-slate-800"></div>
            </div>
        </div>

        <!-- Trend + side -->
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 lg:col-span-8">
            <div class="h-4 w-44 rounded-md bg-slate-200 dark:bg-slate-800"></div>
            <div class="mt-6 h-64 rounded-xl bg-slate-100 dark:bg-slate-800/50"></div>
        </div>
        <div class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 lg:col-span-4">
            <div class="h-4 w-36 rounded-md bg-slate-200 dark:bg-slate-800"></div>
            <div v-for="n in 4" :key="n" class="mt-5 h-10 rounded-xl bg-slate-100 dark:bg-slate-800/60"></div>
        </div>
    </div>
</template>
