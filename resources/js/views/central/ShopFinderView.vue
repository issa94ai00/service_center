<template>
    <div
        class="min-h-screen transition-colors duration-200 px-4 py-8 flex flex-col justify-between"
        :class="isDark ? 'bg-[#070B17] text-slate-100' : 'bg-slate-50 text-slate-800'"
    >
        <!-- Ambient background glow -->
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div
                class="absolute -top-48 inset-x-0 mx-auto h-[480px] w-[min(820px,100%)] rounded-full blur-[130px]"
                :class="isDark ? 'bg-gradient-to-tr from-blue-700/25 via-cyan-500/10 to-transparent' : 'bg-gradient-to-tr from-blue-400/20 via-cyan-300/15 to-transparent'"
            />
        </div>

        <!-- Top navigation bar -->
        <header class="relative z-10 mx-auto flex w-full max-w-md items-center justify-between pb-6">
            <router-link
                :to="{ name: 'landing' }"
                class="inline-flex items-center gap-2 rounded-xl text-xs font-bold transition focus-ring sm:text-sm"
                :class="isDark ? 'text-slate-300 hover:text-white' : 'text-slate-600 hover:text-slate-900'"
            >
                <span class="rtl:rotate-180" aria-hidden="true">←</span>
                <span>{{ t.shopFinder.backHome }}</span>
            </router-link>

            <div class="flex items-center gap-2">
                <!-- Language Toggle -->
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl border px-3 text-xs font-bold transition focus-ring"
                    :class="[
                        isDark
                            ? 'border-white/10 bg-white/5 text-slate-200 hover:bg-white/10'
                            : 'border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50'
                    ]"
                    :title="t.switchLangLabel"
                    :aria-label="t.switchLangLabel"
                    @click="toggleLocale"
                >
                    <span aria-hidden="true">🌐</span>
                    <span>{{ t.otherLangName }}</span>
                </button>

                <!-- Theme Toggle -->
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border text-sm transition focus-ring"
                    :class="[
                        isDark
                            ? 'border-white/10 bg-white/5 text-amber-300 hover:bg-white/10'
                            : 'border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50'
                    ]"
                    :title="isDark ? t.theme.toLight : t.theme.toDark"
                    :aria-label="isDark ? t.theme.toLight : t.theme.toDark"
                    :aria-pressed="isDark"
                    @click="toggleTheme"
                >
                    <span aria-hidden="true">{{ isDark ? '☀️' : '🌙' }}</span>
                </button>
            </div>
        </header>

        <!-- Main Card Container -->
        <main class="relative z-10 mx-auto my-auto w-full max-w-md">
            <router-link :to="{ name: 'landing' }" class="mx-auto mb-6 flex w-fit items-center gap-3 focus-ring">
                <img
                    :src="LOGO"
                    alt=""
                    class="h-11 w-11 rounded-xl ring-1 shadow-md shadow-blue-500/20"
                    :class="isDark ? 'ring-white/15' : 'ring-slate-200'"
                />
                <span class="text-base font-extrabold" :class="isDark ? 'text-white' : 'text-slate-900'">
                    {{ t.brand.name }}
                </span>
            </router-link>

            <div
                class="rounded-3xl border p-6 backdrop-blur-xl sm:p-8 transition-colors"
                :class="[
                    isDark
                        ? 'border-white/10 bg-slate-900/80 shadow-2xl'
                        : 'border-slate-200/90 bg-white/95 shadow-xl shadow-slate-200/60'
                ]"
            >
                <h1 class="text-xl font-black sm:text-2xl" :class="isDark ? 'text-white' : 'text-slate-950'">
                    {{ t.shopFinder.title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                    {{ t.shopFinder.subtitle }}
                </p>

                <form class="mt-6 space-y-4" novalidate @submit.prevent="go">
                    <label for="shop" class="block text-sm font-bold" :class="isDark ? 'text-slate-300' : 'text-slate-700'">
                        {{ t.shopFinder.label }}
                    </label>

                    <div
                        class="flex items-center rounded-2xl border px-4 transition-colors"
                        :class="[
                            error
                                ? 'border-rose-500 ring-1 ring-rose-500/20'
                                : isDark
                                  ? 'border-slate-700 bg-slate-950/80 focus-within:border-cyan-400'
                                  : 'border-slate-300 bg-slate-50 focus-within:border-cyan-500 focus-within:bg-white'
                        ]"
                        dir="ltr"
                    >
                        <input
                            id="shop"
                            v-model="slug"
                            type="text"
                            inputmode="url"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            :placeholder="t.shopFinder.placeholder"
                            class="min-w-0 flex-1 bg-transparent py-3.5 font-mono text-base focus:outline-none"
                            :class="isDark ? 'text-white placeholder:text-slate-600' : 'text-slate-900 placeholder:text-slate-400'"
                            :aria-invalid="Boolean(error)"
                            aria-describedby="shop-help"
                            autofocus
                        />
                        <span class="shrink-0 font-mono text-sm font-semibold" :class="isDark ? 'text-slate-500' : 'text-slate-400'">
                            .{{ tenancy.centralDomain }}
                        </span>
                    </div>

                    <p v-if="error" id="shop-help" class="text-sm font-medium text-rose-500 dark:text-rose-400">
                        {{ error }}
                    </p>

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-gradient-to-l from-cyan-500 to-blue-600 py-3.5 text-base font-extrabold text-white shadow-md shadow-cyan-500/25 transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400"
                    >
                        {{ t.shopFinder.submit }}
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm" :class="isDark ? 'text-slate-400' : 'text-slate-600'">
                {{ t.shopFinder.noShop }}
                <router-link
                    :to="tenancy.signup ? { name: 'register' } : { name: 'landing' }"
                    class="font-bold text-cyan-600 hover:underline dark:text-cyan-400"
                >
                    {{ tenancy.signup ? t.shopFinder.createShop : t.shopFinder.learnMore }}
                </router-link>
            </p>
        </main>

        <!-- Footer -->
        <footer class="relative z-10 py-4 text-center text-xs" :class="isDark ? 'text-slate-500' : 'text-slate-400'">
            © {{ currentYear }} {{ t.brand.company }}
        </footer>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { shopUrl, tenancy } from '../../tenancy';
import { useTheme } from '../../composables/useTheme';
import { useI18n } from '../../composables/useI18n';

const { isDark, toggleTheme } = useTheme();
const { t, toggleLocale } = useI18n();

const LOGO = '/icons/icon-192.png';
const STORAGE_KEY = 'last_shop_slug';
const currentYear = new Date().getFullYear();

const slug = ref('');
const error = ref('');

/** Accepts "myshop", "myshop.repair.damatech.sy" or a pasted URL. */
function normalize(value) {
    let v = value.trim().toLowerCase();
    v = v.replace(/^https?:\/\//, '').split('/')[0];
    const suffix = `.${tenancy.centralDomain}`;
    if (v.endsWith(suffix)) v = v.slice(0, -suffix.length);
    return v;
}

function go() {
    const value = normalize(slug.value);

    if (!/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/.test(value)) {
        error.value = t.value.shopFinder.errorFormat;
        return;
    }

    error.value = '';
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Private mode
    }
    window.location.assign(shopUrl(value));
}

onMounted(() => {
    document.title = `${t.value.shopFinder.title} | ${t.value.brand.name}`;
    try {
        slug.value = localStorage.getItem(STORAGE_KEY) || '';
    } catch {
        slug.value = '';
    }
});
</script>

<style scoped>
.focus-ring:focus-visible {
    outline: 2px solid rgb(6 182 212);
    outline-offset: 2px;
}
</style>
