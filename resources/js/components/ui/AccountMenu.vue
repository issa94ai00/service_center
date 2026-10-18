<template>
    <AppDropdown panel-class="w-64 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-1.5 shadow-xl">
        <template #trigger="{ toggle, open }">
            <button
                type="button"
                data-dropdown-trigger
                class="flex h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 px-1.5 text-start shadow-2xs transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                :class="open ? 'bg-slate-50 dark:bg-slate-800' : 'bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800'"
                aria-haspopup="menu"
                :aria-expanded="open"
                aria-label="حساب المستخدم"
                @click="toggle"
            >
                <span
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-tr from-blue-600 to-cyan-500 text-xs font-bold text-white shadow-xs"
                    aria-hidden="true"
                >
                    {{ initials }}
                </span>

                <span class="hidden min-w-0 flex-col lg:flex">
                    <span class="truncate text-sm font-semibold leading-tight text-slate-800 dark:text-slate-100">
                        {{ auth.user?.name }}
                    </span>
                    <span class="truncate text-[11px] leading-tight text-slate-500 dark:text-slate-400">{{ roleName }}</span>
                </span>

                <AppIcon
                    name="chevron-down"
                    class="hidden h-4 w-4 shrink-0 text-slate-400 dark:text-slate-500 transition-transform lg:block"
                    :class="open ? 'rotate-180' : ''"
                />
            </button>
        </template>

        <template #default="{ close }">
            <div class="flex items-center gap-2.5 border-b border-slate-100 dark:border-slate-800 px-3 pb-2.5 pt-2">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-cyan-500 text-sm font-bold text-white shadow-xs"
                    aria-hidden="true"
                >
                    {{ initials }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ auth.user?.name }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth.user?.email || roleName }}</p>
                </div>
            </div>

            <div class="pt-1">
                <RouterLink
                    to="/profile"
                    class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                    @click="close"
                >
                    <AppIcon name="user" class="h-4.5 w-4.5 text-slate-400 dark:text-slate-500" />
                    الملف الشخصي
                </RouterLink>

                <button
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                    @click="openPrint(close)"
                >
                    <AppIcon name="printer" class="h-4.5 w-4.5 text-slate-400 dark:text-slate-500" />
                    تفضيلات الطباعة
                </button>

                <button
                    v-if="!pwa.installed.value"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 transition hover:bg-slate-100 dark:hover:bg-slate-800"
                    @click="install(close)"
                >
                    <AppIcon name="download" class="h-4.5 w-4.5 text-slate-400 dark:text-slate-500" />
                    تثبيت التطبيق
                </button>

                <button
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-rose-600 dark:text-rose-400 transition hover:bg-rose-50 dark:hover:bg-rose-950/40"
                    @click="doLogout(close)"
                >
                    <AppIcon name="logout" class="h-4.5 w-4.5" />
                    تسجيل الخروج
                </button>
            </div>
        </template>
    </AppDropdown>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AppDropdown from './AppDropdown.vue';
import AppIcon from './AppIcon.vue';
import { useAuthStore } from '../../stores/auth';
import { usePwaInstall } from '../../composables/usePwaInstall';
import { openPrintPreferences } from '../../composables/usePrintPreferences';

const auth = useAuthStore();
const pwa = usePwaInstall();
const router = useRouter();

const initials = computed(() => {
    const name = auth.user?.name || '';
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('');
});

const roleName = computed(() => {
    const roles = auth.user?.roles || [];
    if (!roles.length) return 'مستخدم';
    return roles[0].name || roles[0].slug || 'مستخدم';
});

function openPrint(close) {
    close();
    openPrintPreferences();
}

function install(close) {
    close();
    pwa.installOrExplain();
}

async function doLogout(close) {
    close();
    await auth.logout();
    router.replace({ name: 'login' });
}
</script>
