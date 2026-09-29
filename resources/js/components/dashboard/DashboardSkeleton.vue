<template>
    <!-- Mirrors the real layout so the dashboard does not jump when data lands. -->
    <div class="animate-pulse space-y-5" role="status" aria-live="polite">
        <span class="sr-only">جارٍ تحميل لوحة التحكم</span>

        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="h-3 w-32 rounded bg-slate-200"></div>
            <div class="mt-3 h-10 w-64 rounded bg-slate-200"></div>
            <div class="mt-3 h-2 w-full rounded-full bg-slate-100"></div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="card in 6" :key="card" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="h-3 w-24 rounded bg-slate-200"></div>
                <div class="mt-3 h-6 w-32 rounded bg-slate-200"></div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="h-3 w-40 rounded bg-slate-200"></div>
            <div class="mt-4 h-52 rounded-xl bg-slate-100"></div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div v-for="panel in 2" :key="panel" class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="h-3 w-32 rounded bg-slate-200"></div>
                <div v-for="row in 4" :key="row" class="mt-4 space-y-2">
                    <div class="flex justify-between gap-4">
                        <div class="h-3 w-32 rounded bg-slate-200"></div>
                        <div class="h-3 w-16 rounded bg-slate-200"></div>
                    </div>
                    <div class="h-1.5 w-full rounded-full bg-slate-100"></div>
                </div>
            </div>
        </div>
    </div>
</template>
