<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <div class="text-center">
            <div class="text-7xl font-black text-slate-300">404</div>
            <p class="mt-4 text-lg text-slate-600">الصفحة غير موجودة</p>
            <RouterLink
                to="/"
                class="mt-6 inline-block rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
                العودة للوحة التحكم
            </RouterLink>
        </div>
    </div>
</template>

<script setup>
import { RouterLink } from 'vue-router';
</script>