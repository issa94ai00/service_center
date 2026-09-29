<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-blue-950 via-blue-900 to-cyan-900 px-4 py-10">
        <!-- Soft glow blobs keep the flat gradient from feeling empty/lifeless. -->
        <div class="pointer-events-none absolute -start-40 -top-40 h-96 w-96 rounded-full bg-cyan-400/15 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute -bottom-48 -end-40 h-[28rem] w-[28rem] rounded-full bg-blue-500/15 blur-3xl" aria-hidden="true" />
        <div class="pointer-events-none absolute start-1/2 top-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-300/5 blur-3xl" aria-hidden="true" />

        <main class="relative w-full max-w-6xl">
            <RouterView />
        </main>
    </div>
</template>

<script setup>
import { RouterView } from 'vue-router';
</script>