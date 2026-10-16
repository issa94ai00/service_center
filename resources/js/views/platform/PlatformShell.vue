<template>
    <div class="min-h-screen bg-slate-50">
        <header class="sticky top-0 z-30 border-b border-blue-900/40 bg-gradient-to-l from-blue-950 via-slate-950 to-blue-950 text-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <router-link :to="{ name: 'tenants' }" class="flex items-center gap-3">
                    <img :src="'/icons/icon-192.png'" alt="" class="h-9 w-9 rounded-lg ring-1 ring-white/15" />
                    <span class="leading-tight">
                        <span class="block text-sm font-bold">إدارة المنصة</span>
                        <span class="block text-[11px] text-cyan-200/70" dir="ltr">{{ host }}</span>
                    </span>
                </router-link>

                <nav class="flex items-center gap-1 text-sm">
                    <router-link
                        :to="{ name: 'tenants' }"
                        class="rounded-lg px-3 py-2 text-slate-300 hover:bg-white/10 hover:text-white"
                        exact-active-class="bg-white/10 text-white"
                    >
                        المتاجر
                    </router-link>
                    <router-link
                        :to="{ name: 'account' }"
                        class="rounded-lg px-3 py-2 text-slate-300 hover:bg-white/10 hover:text-white"
                        active-class="bg-white/10 text-white"
                    >
                        حسابي
                    </router-link>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-slate-300 hover:bg-white/10 hover:text-white"
                        @click="logout"
                    >
                        خروج
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
            <RouterView />
        </main>

        <ToastHost />
        <ConfirmHost />
    </div>
</template>

<script setup>
import { RouterView, useRouter } from 'vue-router';
import ConfirmHost from '../../components/ui/ConfirmHost.vue';
import ToastHost from '../../components/ui/ToastHost.vue';
import { usePlatformAdminStore } from '../../stores/platformAdmin';

const store = usePlatformAdminStore();
const router = useRouter();
const host = window.location.host;

async function logout() {
    await store.logout();
    router.replace({ name: 'login' });
}
</script>
