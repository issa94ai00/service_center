<template>
    <div class="flex min-h-screen items-center justify-center bg-[#070B17] px-4 py-10 text-slate-100">
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-48 inset-x-0 mx-auto h-[480px] w-[min(820px,100%)] rounded-full bg-gradient-to-tr from-blue-700/25 via-cyan-500/10 to-transparent blur-[130px]" />
        </div>

        <div class="relative w-full max-w-md">
            <router-link :to="{ name: 'landing' }" class="mx-auto mb-8 flex w-fit items-center gap-3">
                <img :src="LOGO" alt="" class="h-11 w-11 rounded-xl ring-1 ring-white/15" />
                <span class="text-base font-extrabold text-white">نظام الهواتف والإكسسوارات</span>
            </router-link>

            <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
                <h1 class="text-xl font-black text-white sm:text-2xl">الدخول إلى متجرك</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-400">
                    لكل متجر عنوان خاص به. اكتب اسم متجرك كما في العنوان الذي استلمته.
                </p>

                <form class="mt-6 space-y-4" novalidate @submit.prevent="go">
                    <label for="shop" class="block text-sm font-medium text-slate-300">عنوان المتجر</label>
                    <div
                        class="flex items-center rounded-2xl border bg-slate-950/80 px-4 transition-colors focus-within:border-cyan-400"
                        :class="error ? 'border-rose-500/70' : 'border-slate-700'"
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
                            placeholder="myshop"
                            class="min-w-0 flex-1 bg-transparent py-3.5 font-mono text-base text-white placeholder:text-slate-600 focus:outline-none"
                            :aria-invalid="Boolean(error)"
                            aria-describedby="shop-help"
                            autofocus
                        />
                        <span class="shrink-0 font-mono text-sm text-slate-500">.{{ tenancy.centralDomain }}</span>
                    </div>
                    <p v-if="error" id="shop-help" class="text-sm text-rose-400">{{ error }}</p>

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-gradient-to-l from-cyan-400 to-blue-500 py-3.5 text-base font-bold text-slate-950 shadow-[0_0_24px_rgba(34,211,238,0.3)] transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        متابعة
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-slate-500">
                لا تملك متجراً بعد؟
                <router-link :to="{ name: 'landing' }" class="font-semibold text-cyan-300 hover:text-cyan-200">تعرّف على النظام</router-link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { shopUrl, tenancy } from '../../tenancy';

const LOGO = '/icons/icon-192.png';
const STORAGE_KEY = 'last_shop_slug';

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
        error.value = 'اكتب اسم المتجر بالأحرف الإنجليزية والأرقام فقط، مثل: myshop';
        return;
    }

    error.value = '';
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Private mode: nothing to remember.
    }
    window.location.assign(shopUrl(value));
}

onMounted(() => {
    try {
        slug.value = localStorage.getItem(STORAGE_KEY) || '';
    } catch {
        slug.value = '';
    }
});
</script>
