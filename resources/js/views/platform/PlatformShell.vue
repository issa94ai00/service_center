<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 dark:border-slate-800 bg-white/85 dark:bg-slate-900/85 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-6">
                    <router-link :to="{ name: 'tenants' }" class="flex shrink-0 items-center gap-2.5">
                        <img :src="'/icons/icon-192.png'" alt="" class="h-9 w-9 rounded-xl shadow-sm" />
                        <span class="hidden leading-tight sm:block">
                            <span class="block text-sm font-extrabold text-slate-900 dark:text-white">إدارة المنصة</span>
                            <span class="block font-mono text-[11px] text-slate-400" dir="ltr">{{ host }}</span>
                        </span>
                    </router-link>

                    <nav class="flex items-center gap-1 text-sm font-semibold" aria-label="التنقل">
                        <router-link
                            v-for="item in nav"
                            :key="item.name"
                            :to="{ name: item.name }"
                            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-slate-500 dark:text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white"
                            :class="isActive(item) ? '!bg-slate-100 dark:!bg-slate-800 !text-slate-900 dark:!text-white' : ''"
                        >
                            <AppIcon :name="item.icon" class="text-base" />
                            <span>{{ item.label }}</span>
                        </router-link>
                    </nav>
                </div>

                <div class="flex shrink-0 items-center gap-1.5">
                    <button
                        type="button"
                        class="grid h-9 w-9 place-items-center rounded-lg text-slate-500 dark:text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white"
                        :title="isDark ? 'التبديل إلى الوضع الفاتح' : 'التبديل إلى الوضع الداكن'"
                        :aria-label="isDark ? 'التبديل إلى الوضع الفاتح' : 'التبديل إلى الوضع الداكن'"
                        :aria-pressed="isDark"
                        @click="toggleTheme"
                    >
                        <AppIcon :name="isDark ? 'sun' : 'moon'" class="text-lg" />
                    </button>

                    <AppDropdown panel-class="w-60">
                        <template #trigger="{ toggle, open }">
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-xl py-1 pe-2 ps-1 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                                :aria-expanded="open"
                                aria-haspopup="menu"
                                @click="toggle"
                            >
                                <span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-sm font-bold text-white">
                                    {{ initial }}
                                </span>
                                <span class="hidden max-w-32 truncate text-sm font-semibold text-slate-700 dark:text-slate-200 md:block">{{ store.admin?.name }}</span>
                                <AppIcon name="chevron-down" class="hidden text-sm text-slate-400 md:block" />
                            </button>
                        </template>
                        <template #default="{ close }">
                            <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl shadow-slate-900/10">
                                <div class="border-b border-slate-100 dark:border-slate-800 px-4 py-3">
                                    <p class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ store.admin?.name }}</p>
                                    <p class="truncate text-xs text-slate-500" dir="ltr">{{ store.admin?.email }}</p>
                                </div>
                                <div class="p-1.5">
                                    <router-link
                                        :to="{ name: 'account' }"
                                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                                        role="menuitem"
                                        @click="close"
                                    >
                                        <AppIcon name="lock" class="text-slate-400" /> الأمان وكلمة المرور
                                    </router-link>
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40"
                                        role="menuitem"
                                        @click="logout"
                                    >
                                        <AppIcon name="logout" class="rtl:-scale-x-100" /> تسجيل الخروج
                                    </button>
                                </div>
                            </div>
                        </template>
                    </AppDropdown>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <RouterView />
        </main>

        <ToastHost />
        <ConfirmHost />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import AppDropdown from '../../components/ui/AppDropdown.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import ConfirmHost from '../../components/ui/ConfirmHost.vue';
import ToastHost from '../../components/ui/ToastHost.vue';
import { usePlatformAdminStore } from '../../stores/platformAdmin';
import { useTheme } from '../../composables/useTheme';

const store = usePlatformAdminStore();
const { isDark, toggleTheme } = useTheme();
const route = useRoute();
const router = useRouter();
const host = window.location.host;

const nav = [
    // A shop's own page still belongs under "shops" in the nav.
    { name: 'tenants', label: 'المتاجر', icon: 'store', also: ['tenant'] },
    { name: 'account', label: 'حسابي', icon: 'user' },
];

const initial = computed(() => (store.admin?.name || store.admin?.email || '؟').trim().charAt(0).toUpperCase());

function isActive(item) {
    return route.name === item.name || (item.also ?? []).includes(route.name);
}

async function logout() {
    await store.logout();
    router.replace({ name: 'login' });
}
</script>
