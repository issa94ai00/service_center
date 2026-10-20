<template>
    <div class="landing min-h-screen overflow-x-clip bg-white font-display text-slate-700 antialiased selection:bg-cyan-500 selection:text-white dark:bg-[#070B17] dark:text-slate-300">
        <!-- Header -->
        <header
            class="sticky top-0 z-50 transition-[background-color,border-color,box-shadow] duration-300"
            :class="scrolled || menuOpen
                ? 'border-b border-slate-200/70 bg-white/80 shadow-[0_1px_24px_-12px_rgba(15,23,42,0.25)] backdrop-blur-xl dark:border-white/10 dark:bg-[#070B17]/80'
                : 'border-b border-transparent'"
        >
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-[72px] lg:px-8">
                <a href="#top" class="flex min-w-0 items-center gap-2.5 rounded-xl focus-ring" @click="menuOpen = false">
                    <img :src="LOGO" alt="" class="h-9 w-9 shrink-0 rounded-xl shadow-sm ring-1 ring-slate-900/10 dark:ring-white/15" />
                    <span class="truncate text-[15px] font-bold text-slate-950 dark:text-white">{{ t.brand.name }}</span>
                </a>

                <nav
                    class="hidden items-center gap-0.5 rounded-full border border-slate-200/80 bg-white/70 p-1 text-sm font-medium shadow-xs backdrop-blur lg:flex dark:border-white/10 dark:bg-white/5"
                    :aria-label="t.nav.features"
                >
                    <a
                        v-for="link in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="rounded-full px-4 py-1.5 transition-colors focus-ring"
                        :class="activeSection === link.id
                            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white'"
                        @click="onLinkClick(link.id)"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="flex shrink-0 items-center gap-1.5">
                    <button
                        type="button"
                        :class="iconBtn"
                        class="sm:w-auto sm:gap-1.5 sm:px-3"
                        :title="t.switchLangLabel"
                        :aria-label="t.switchLangLabel"
                        @click="toggleLocale"
                    >
                        <AppIcon name="globe" class="text-[17px]" />
                        <span class="hidden text-sm font-semibold sm:inline">{{ t.otherLangName }}</span>
                    </button>
                    <button
                        type="button"
                        :class="iconBtn"
                        class="max-sm:hidden"
                        :title="isDark ? t.theme.toLight : t.theme.toDark"
                        :aria-label="isDark ? t.theme.toLight : t.theme.toDark"
                        :aria-pressed="isDark"
                        @click="toggleTheme"
                    >
                        <AppIcon :name="isDark ? 'sun' : 'moon'" class="text-[17px]" />
                    </button>
                    <router-link
                        v-if="signup"
                        :to="{ name: 'login' }"
                        class="hidden rounded-full px-4 py-2 text-sm font-semibold text-slate-700 transition hover:text-slate-950 focus-ring md:inline-flex dark:text-slate-200 dark:hover:text-white"
                    >
                        {{ t.nav.login }}
                    </router-link>
                    <router-link :to="primaryCta.to" :class="btnPrimary" class="px-4 py-2 text-sm max-sm:hidden">
                        {{ primaryCta.short }}
                    </router-link>
                    <button
                        type="button"
                        :class="iconBtn"
                        class="lg:hidden"
                        :aria-expanded="menuOpen"
                        aria-controls="mobile-menu"
                        :aria-label="menuOpen ? t.nav.closeMenu : t.nav.openMenu"
                        @click="menuOpen = !menuOpen"
                    >
                        <AppIcon :name="menuOpen ? 'close' : 'menu'" class="text-[18px]" />
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <Transition name="drop">
                <div
                    v-show="menuOpen"
                    id="mobile-menu"
                    class="max-h-[calc(100dvh-4rem)] overflow-y-auto overscroll-contain border-t border-slate-200/70 px-4 pb-6 pt-2 lg:hidden dark:border-white/10"
                >
                    <nav class="grid gap-0.5 text-base font-medium text-slate-800 dark:text-slate-100">
                        <a
                            v-for="link in navLinks"
                            :key="link.id"
                            :href="`#${link.id}`"
                            class="flex items-center justify-between rounded-xl px-3 py-3 transition hover:bg-slate-100 focus-ring dark:hover:bg-white/5"
                            @click="onLinkClick(link.id)"
                        >
                            {{ link.label }}
                            <AppIcon name="arrow-right" class="text-slate-400 rtl:rotate-180" />
                        </a>
                        <button
                            type="button"
                            class="flex items-center justify-between rounded-xl px-3 py-3 text-start transition hover:bg-slate-100 focus-ring sm:hidden dark:hover:bg-white/5"
                            :aria-pressed="isDark"
                            @click="toggleTheme"
                        >
                            {{ isDark ? t.theme.toLight : t.theme.toDark }}
                            <AppIcon :name="isDark ? 'sun' : 'moon'" class="text-slate-400" />
                        </button>
                    </nav>
                    <div class="mt-3 grid gap-2 border-t border-slate-200/70 pt-4 dark:border-white/10">
                        <router-link :to="primaryCta.to" :class="btnPrimary" class="py-3" @click="menuOpen = false">
                            {{ primaryCta.label }}
                        </router-link>
                        <router-link v-if="signup" :to="{ name: 'login' }" :class="btnGhost" class="py-3" @click="menuOpen = false">
                            {{ t.nav.login }}
                        </router-link>
                    </div>
                </div>
            </Transition>
        </header>

        <main id="top">
            <!-- Hero -->
            <section class="relative isolate overflow-hidden pb-20 pt-12 sm:pt-20 lg:pb-28">
                <!-- Background photo behind the headline, dimmed with the page colour and faded out before the mockup -->
                <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[680px] overflow-hidden sm:h-[760px]" aria-hidden="true">
                    <img :src="heroPhoto" alt="" fetchpriority="high" decoding="async" class="h-full w-full object-cover object-center" />
                    <!-- #0f172a: in dark mode app.css repaints .bg-white (the page) in this colour -->
                    <div class="absolute inset-0 bg-white/85 dark:bg-[#0f172a]/85" />
                    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-white dark:to-[#0f172a]" />
                </div>
                <div class="hero-grid pointer-events-none absolute inset-0 -z-10" aria-hidden="true" />
                <div
                    class="pointer-events-none absolute left-1/2 top-0 -z-10 h-[560px] w-[min(1100px,140%)] -translate-x-1/2 -translate-y-1/3 rounded-full bg-[radial-gradient(closest-side,rgba(34,211,238,0.28),rgba(37,99,235,0.14),transparent)] blur-2xl dark:bg-[radial-gradient(closest-side,rgba(34,211,238,0.18),rgba(37,99,235,0.12),transparent)]"
                    aria-hidden="true"
                />

                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="enter mx-auto max-w-3xl text-center">
                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 py-1 pe-3.5 ps-1 text-xs font-semibold text-slate-700 shadow-xs backdrop-blur sm:text-sm dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                            <span class="rounded-full bg-gradient-to-r from-cyan-500 to-blue-600 px-2.5 py-0.5 text-[11px] font-bold text-white sm:text-xs">
                                <AppIcon name="sparkles" class="inline align-[-2px]" />
                            </span>
                            {{ t.hero.badge }}
                        </span>

                        <h1 class="mt-7 text-balance text-[2.5rem] font-bold leading-[1.15] tracking-tight text-slate-950 sm:text-6xl lg:text-7xl dark:text-white">
                            {{ t.hero.titlePrefix }}
                            <span class="mt-1 block bg-gradient-to-r from-cyan-500 via-sky-500 to-blue-600 bg-clip-text pb-2 text-transparent">
                                {{ t.hero.titleHighlight }}
                            </span>
                        </h1>

                        <p class="mx-auto mt-6 max-w-2xl text-pretty text-base leading-8 text-slate-600 sm:text-lg dark:text-slate-400">
                            {{ t.hero.description }}
                        </p>

                        <div class="mt-9 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                            <router-link :to="primaryCta.to" :class="btnPrimary" class="group gap-2 px-7 py-3.5 text-base">
                                {{ primaryCta.label }}
                                <AppIcon name="arrow-right" class="transition-transform group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1" />
                            </router-link>
                            <router-link v-if="signup" :to="{ name: 'login' }" :class="btnGhost" class="px-7 py-3.5 text-base">
                                {{ t.hero.ctaHaveShop }}
                            </router-link>
                            <a v-else href="#features" :class="btnGhost" class="px-7 py-3.5 text-base">
                                {{ t.hero.ctaExplore }}
                            </a>
                        </div>

                        <ul class="mt-7 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-slate-500 dark:text-slate-400">
                            <li v-for="item in heroPoints" :key="item" class="flex items-center gap-1.5">
                                <span class="grid h-4.5 w-4.5 place-items-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                    <AppIcon name="check" :stroke-width="3" class="text-[11px]" />
                                </span>
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <!-- Product preview: a repair board where ticket #1042 moves through the stages -->
                    <div class="enter enter-delay relative mx-auto mt-16 max-w-5xl sm:mt-20">
                        <div class="absolute -inset-x-6 -inset-y-4 -z-10 rounded-[2.5rem] bg-gradient-to-b from-cyan-400/25 via-blue-500/10 to-transparent blur-2xl dark:from-cyan-500/15" aria-hidden="true" />

                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_30px_80px_-20px_rgba(15,23,42,0.35)] ring-1 ring-slate-900/5 sm:rounded-3xl dark:border-white/10 dark:bg-slate-900 dark:shadow-[0_30px_80px_-20px_rgba(0,0,0,0.8)] dark:ring-white/5">
                            <!-- Window chrome -->
                            <div class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-white/10 dark:bg-slate-950/60">
                                <div class="flex gap-1.5" dir="ltr" aria-hidden="true">
                                    <span class="h-2.5 w-2.5 rounded-full bg-rose-400" />
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-400" />
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400" />
                                </div>
                                <div class="mx-auto flex items-center gap-1.5 rounded-md bg-white px-3 py-1 font-mono text-[11px] text-slate-500 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:ring-white/10" dir="ltr">
                                    <AppIcon name="shield" class="text-emerald-500" />
                                    {{ t.mockup.windowUrl }}
                                </div>
                                <span class="w-10" aria-hidden="true" />
                            </div>

                            <div class="flex">
                                <!-- Sidebar -->
                                <div class="hidden w-14 shrink-0 flex-col items-center gap-2 border-e border-slate-200 bg-slate-50/70 py-4 sm:flex dark:border-white/10 dark:bg-slate-950/40" aria-hidden="true">
                                    <img :src="LOGO" alt="" class="mb-2 h-8 w-8 rounded-lg" />
                                    <span
                                        v-for="(icon, i) in ['grid', 'wrench', 'receipt', 'package', 'wallet', 'chart']"
                                        :key="icon"
                                        class="grid h-9 w-9 place-items-center rounded-lg text-[17px]"
                                        :class="i === 1 ? 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400' : 'text-slate-400'"
                                    >
                                        <AppIcon :name="icon" />
                                    </span>
                                </div>

                                <div class="min-w-0 flex-1 p-3 sm:p-5">
                                    <div class="mb-4 flex items-center justify-between gap-3">
                                        <p class="text-sm font-bold text-slate-900 sm:text-base dark:text-white">{{ t.mockup.boardTitle }}</p>
                                        <div class="flex gap-2 text-[11px] sm:text-xs">
                                            <span class="rounded-lg border border-slate-200 px-2.5 py-1 dark:border-white/10">
                                                <span class="text-slate-500 dark:text-slate-400">{{ t.mockup.activeRepairsLabel }}</span>
                                                <b class="ms-1.5 text-slate-900 dark:text-white">18</b>
                                            </span>
                                            <span class="hidden rounded-lg border border-slate-200 px-2.5 py-1 sm:inline dark:border-white/10">
                                                <span class="text-slate-500 dark:text-slate-400">{{ t.mockup.todaySalesLabel }}</span>
                                                <b class="ms-1.5 font-mono text-cyan-600 dark:text-cyan-400" dir="ltr">$1,240</b>
                                            </span>
                                        </div>
                                    </div>

                                    <ol class="grid grid-cols-4 gap-1.5 sm:gap-3">
                                        <li v-for="(step, col) in t.mockup.steps" :key="step.short" class="min-w-0">
                                            <button
                                                type="button"
                                                class="mb-2 flex w-full items-center gap-1.5 rounded-lg px-1.5 py-1 text-start text-[10px] font-bold transition focus-ring sm:text-xs"
                                                :class="col === activeStep ? 'text-slate-900 dark:text-white' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-200'"
                                                :aria-current="col === activeStep ? 'step' : undefined"
                                                @click="pickStep(col)"
                                            >
                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="stepDot[col]" />
                                                <span class="truncate">{{ step.label }}</span>
                                            </button>
                                            <div class="flex min-h-[156px] flex-col gap-1.5 rounded-xl bg-slate-100/80 p-1.5 sm:min-h-[196px] sm:gap-2 sm:p-2 dark:bg-white/[0.03]">
                                                <TransitionGroup name="ticket">
                                                    <div
                                                        v-if="col === activeStep"
                                                        key="featured"
                                                        class="rounded-lg border border-cyan-400/60 bg-white p-2 shadow-md shadow-cyan-500/10 ring-2 ring-cyan-400/20 sm:p-2.5 dark:bg-slate-800"
                                                    >
                                                        <p class="font-mono text-[10px] font-bold text-cyan-600 dark:text-cyan-400">{{ t.mockup.ticketNo }}</p>
                                                        <p class="mt-0.5 truncate text-[11px] font-bold text-slate-900 rtl:text-right sm:text-xs dark:text-white" dir="ltr">iPhone 13 Pro</p>
                                                        <p class="mt-1 hidden text-[11px] leading-snug text-slate-500 sm:line-clamp-2 dark:text-slate-400">{{ t.mockup.issueDesc }}</p>
                                                    </div>
                                                    <div
                                                        v-for="card in boardCards[col]"
                                                        :key="card.id"
                                                        class="rounded-lg border border-slate-200/80 bg-white p-2 sm:p-2.5 dark:border-white/5 dark:bg-slate-800/60"
                                                    >
                                                        <p class="font-mono text-[10px] text-slate-400">{{ card.id }}</p>
                                                        <p class="mt-0.5 truncate text-[11px] font-semibold text-slate-700 rtl:text-right sm:text-xs dark:text-slate-300" dir="ltr">{{ card.device }}</p>
                                                    </div>
                                                </TransitionGroup>
                                            </div>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <!-- Floating WhatsApp notice once the device is ready -->
                        <Transition name="pop">
                            <div
                                v-if="activeStep === 3"
                                class="absolute -bottom-6 end-3 flex max-w-[280px] items-start gap-3 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur sm:-end-6 sm:p-3.5 dark:border-white/10 dark:bg-slate-800/95"
                                role="status"
                            >
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-500 text-lg text-white">
                                    <AppIcon name="message" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[11px] font-semibold text-slate-400">{{ t.mockup.notifyTitle }}</span>
                                    <span class="block text-xs font-semibold leading-snug text-slate-800 sm:text-sm dark:text-slate-100">{{ t.mockup.notifyText }}</span>
                                </span>
                            </div>
                        </Transition>
                    </div>
                </div>
            </section>

            <!-- Facts band -->
            <section class="border-y border-slate-200/80 bg-slate-50/70 dark:border-white/5 dark:bg-white/[0.02]">
                <dl class="mx-auto grid max-w-7xl grid-cols-2 gap-y-8 px-4 py-10 sm:px-6 lg:grid-cols-4 lg:px-8">
                    <div
                        v-for="(fact, i) in t.factsSection.items"
                        :key="fact.label"
                        class="border-slate-200 px-2 text-center dark:border-white/10"
                        :class="[i % 2 ? 'border-s' : '', i > 0 ? 'lg:border-s' : '']"
                    >
                        <dt class="sr-only">{{ fact.label }}</dt>
                        <dd>
                            <span class="block text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white" dir="ltr">
                                {{ fact.value }}<span v-if="fact.unit" class="ms-1 text-base font-semibold text-slate-500 dark:text-slate-400">{{ fact.unit }}</span>
                            </span>
                            <span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">{{ fact.label }}</span>
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Features (bento) -->
            <section id="features" class="scroll-mt-20 py-20 sm:py-28">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <SectionHead :eyebrow="t.featuresSection.eyebrow" :title="t.featuresSection.title" :text="t.featuresSection.text" />

                    <div class="mt-14 grid grid-cols-1 gap-4 md:grid-cols-3 lg:gap-5">
                        <article
                            v-for="(f, i) in t.featuresSection.items"
                            :key="f.title"
                            class="group relative flex flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 transition duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-slate-200/60 sm:p-8 dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-white/20 dark:hover:shadow-none"
                            :class="f.wide ? 'md:col-span-2' : ''"
                        >
                            <span class="grid h-11 w-11 place-items-center rounded-xl text-[22px]" :class="featureTone[i]">
                                <AppIcon :name="featureIcons[i]" />
                            </span>
                            <h3 class="mt-5 text-lg font-bold text-slate-950 sm:text-xl dark:text-white">{{ f.title }}</h3>
                            <p class="mt-2 max-w-xl text-[15px] leading-7 text-slate-600 dark:text-slate-400">{{ f.text }}</p>

                            <!-- Repair stages as a flow -->
                            <ol v-if="i === 0" class="mt-auto flex flex-wrap items-center gap-1.5 pt-6 text-xs font-semibold">
                                <template v-for="(p, n) in f.points" :key="p">
                                    <li
                                        class="rounded-full px-3 py-1.5"
                                        :class="n === f.points.length - 1
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-700 dark:bg-white/5 dark:text-slate-300'"
                                    >
                                        {{ p }}
                                    </li>
                                    <li v-if="n < f.points.length - 1" class="text-slate-300 dark:text-slate-600" aria-hidden="true">
                                        <AppIcon name="arrow-right" class="rtl:rotate-180" />
                                    </li>
                                </template>
                            </ol>

                            <!-- Inventory: reports + a small chart -->
                            <div v-else-if="f.points" class="mt-auto flex flex-wrap items-end justify-between gap-6 pt-6">
                                <ul class="flex flex-wrap gap-1.5 text-xs font-semibold">
                                    <li v-for="p in f.points" :key="p" class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700 dark:bg-white/5 dark:text-slate-300">
                                        {{ p }}
                                    </li>
                                </ul>
                                <div class="flex h-16 items-end gap-1.5" dir="ltr" aria-hidden="true">
                                    <span
                                        v-for="(h, b) in [35, 55, 42, 70, 58, 85, 100]"
                                        :key="b"
                                        class="w-3 rounded-t bg-gradient-to-t from-emerald-500/40 to-emerald-400 transition-[height] duration-500 group-hover:from-emerald-500/60"
                                        :style="{ height: `${h}%` }"
                                    />
                                </div>
                            </div>

                            <!-- Currencies -->
                            <div v-else-if="featureIcons[i] === 'exchange'" class="mt-auto flex items-center gap-3 pt-6 font-mono text-sm font-bold" dir="ltr" aria-hidden="true">
                                <span class="rounded-xl bg-sky-500/10 px-3 py-2 text-sky-700 dark:text-sky-300">USD</span>
                                <AppIcon name="exchange" class="text-slate-400" />
                                <span class="rounded-xl bg-sky-500/10 px-3 py-2 text-sky-700 dark:text-sky-300">SYP</span>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <!-- Workflow -->
            <section id="workflow" class="scroll-mt-20 bg-slate-950 py-20 text-slate-300 sm:py-28 dark:bg-white/[0.02]">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <SectionHead :eyebrow="t.workflowSection.eyebrow" :title="t.workflowSection.title" :text="t.workflowSection.text" inverted />

                    <ol class="relative mt-16 grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">
                        <span class="absolute inset-x-[12%] top-6 hidden h-px bg-gradient-to-r from-cyan-500/0 via-cyan-500/50 to-cyan-500/0 lg:block" aria-hidden="true" />
                        <li v-for="step in t.workflowSection.steps" :key="step.number" class="relative text-center">
                            <span class="relative mx-auto grid h-12 w-12 place-items-center rounded-2xl border border-cyan-400/30 bg-slate-900 font-mono text-lg font-bold text-cyan-300 shadow-[0_0_30px_-6px_rgba(34,211,238,0.6)]">
                                {{ step.number }}
                            </span>
                            <h3 class="mt-5 text-lg font-bold text-white">{{ step.title }}</h3>
                            <p class="mx-auto mt-2 max-w-xs text-[15px] leading-7 text-slate-400">{{ step.text }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- Modules -->
            <section id="modules" class="scroll-mt-20 py-20 sm:py-28">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <SectionHead :eyebrow="t.nav.modules" :title="t.modulesSection.title" />
                    <ul class="mx-auto mt-12 grid max-w-5xl grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                        <li
                            v-for="(m, i) in t.modulesSection.list"
                            :key="m.label"
                            class="flex flex-col items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-6 text-center text-sm font-semibold text-slate-800 transition hover:border-cyan-400/60 hover:shadow-lg hover:shadow-cyan-500/5 dark:border-white/10 dark:bg-white/[0.03] dark:text-slate-200 dark:hover:border-cyan-400/40"
                        >
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-cyan-500/10 to-blue-600/10 text-[21px] text-cyan-600 dark:text-cyan-400">
                                <AppIcon :name="moduleIcons[i]" />
                            </span>
                            {{ m.label }}
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Why us -->
            <section id="why" class="scroll-mt-20 border-t border-slate-200/80 bg-slate-50/70 py-20 sm:py-28 dark:border-white/5 dark:bg-white/[0.02]">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <SectionHead :eyebrow="t.whyUsSection.eyebrow" :title="t.whyUsSection.title" />
                    <div class="mt-14 grid grid-cols-1 gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="(w, i) in t.whyUsSection.items" :key="w.title">
                            <svg v-if="w.flag" class="h-9 w-[54px] rounded-md shadow-sm ring-1 ring-slate-900/10 dark:ring-white/15" viewBox="0 0 900 600" role="img" :aria-label="w.title">
                                <rect width="900" height="200" fill="#007A3D" />
                                <rect y="200" width="900" height="200" fill="#FFFFFF" />
                                <rect y="400" width="900" height="200" fill="#000000" />
                                <path fill="#CE1126" d="M225.0,238.0 L238.9,280.8 L284.0,280.8 L247.5,307.3 L261.4,350.2 L225.0,323.7 L188.6,350.2 L202.5,307.3 L166.0,280.8 L211.1,280.8Z M450.0,238.0 L463.9,280.8 L509.0,280.8 L472.5,307.3 L486.4,350.2 L450.0,323.7 L413.6,350.2 L427.5,307.3 L391.0,280.8 L436.1,280.8Z M675.0,238.0 L688.9,280.8 L734.0,280.8 L697.5,307.3 L711.4,350.2 L675.0,323.7 L638.6,350.2 L652.5,307.3 L616.0,280.8 L661.1,280.8Z" />
                            </svg>
                            <span v-else class="grid h-11 w-11 place-items-center rounded-xl bg-slate-900 text-[20px] text-white dark:bg-white dark:text-slate-900">
                                <AppIcon :name="whyIcons[i]" />
                            </span>
                            <h3 class="mt-5 text-lg font-bold text-slate-950 dark:text-white">{{ w.title }}</h3>
                            <p class="mt-2 text-[15px] leading-7 text-slate-600 dark:text-slate-400">{{ w.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQ -->
            <section id="faq" class="scroll-mt-20 py-20 sm:py-28">
                <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
                    <div class="lg:col-span-4">
                        <SectionHead :eyebrow="t.faqSection.eyebrow" :title="t.faqSection.title" align="start" />
                    </div>
                    <div class="divide-y divide-slate-200 border-y border-slate-200 lg:col-span-8 dark:divide-white/10 dark:border-white/10">
                        <details
                            v-for="(faq, idx) in allFaqs"
                            :key="faq.q"
                            class="faq group"
                            :open="idx === 0"
                        >
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-6 py-5 text-start text-base font-semibold text-slate-900 transition hover:text-cyan-700 focus-ring sm:text-lg dark:text-white dark:hover:text-cyan-300">
                                {{ faq.q }}
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-slate-200 text-slate-500 transition group-open:rotate-45 group-open:border-cyan-500 group-open:bg-cyan-500 group-open:text-white dark:border-white/15">
                                    <AppIcon name="plus" />
                                </span>
                            </summary>
                            <p class="max-w-2xl pb-6 pe-12 text-[15px] leading-7 text-slate-600 dark:text-slate-400">{{ faq.a }}</p>
                        </details>
                    </div>
                </div>
            </section>

            <!-- Final call to action -->
            <section class="px-4 pb-20 sm:px-6 sm:pb-28 lg:px-8">
                <div class="relative isolate mx-auto max-w-6xl overflow-hidden rounded-[2rem] bg-slate-950 px-6 py-16 text-center sm:px-16 sm:py-20 dark:bg-gradient-to-b dark:from-slate-900 dark:to-slate-950 dark:ring-1 dark:ring-white/10">
                    <div class="hero-grid hero-grid-dark pointer-events-none absolute inset-0 -z-10 opacity-60" aria-hidden="true" />
                    <div class="pointer-events-none absolute -top-40 left-1/2 -z-10 h-80 w-[min(720px,120%)] -translate-x-1/2 rounded-full bg-cyan-500/30 blur-3xl" aria-hidden="true" />
                    <h2 class="mx-auto max-w-2xl text-balance text-3xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
                        {{ t.ctaSection.title }}
                    </h2>
                    <p class="mx-auto mt-5 max-w-xl text-base leading-7 text-slate-300 sm:text-lg">
                        {{ signup ? t.ctaSection.subtitleTrial.replace('{days}', signup.trialDays) : t.ctaSection.subtitleStandard }}
                    </p>
                    <div class="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                        <router-link
                            :to="primaryCta.to"
                            class="group inline-flex items-center justify-center gap-2 rounded-full bg-white px-8 py-3.5 text-base font-bold text-slate-950 transition hover:bg-cyan-50 focus-ring"
                        >
                            {{ primaryCta.label }}
                            <AppIcon name="arrow-right" class="transition-transform group-hover:translate-x-1 rtl:rotate-180 rtl:group-hover:-translate-x-1" />
                        </router-link>
                        <router-link
                            v-if="signup"
                            :to="{ name: 'login' }"
                            class="inline-flex items-center justify-center rounded-full px-8 py-3.5 text-base font-semibold text-white ring-1 ring-white/20 transition hover:bg-white/10 focus-ring"
                        >
                            {{ t.nav.login }}
                        </router-link>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-200 dark:border-white/10">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-12 lg:px-8">
                <div class="md:col-span-6">
                    <div class="flex items-center gap-2.5">
                        <img :src="LOGO" alt="" class="h-9 w-9 rounded-xl" />
                        <span class="font-bold text-slate-950 dark:text-white">{{ t.brand.name }}</span>
                    </div>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">{{ t.brand.tagline }}</p>
                </div>
                <nav class="md:col-span-3" :aria-label="t.footer.product">
                    <p class="text-sm font-bold text-slate-950 dark:text-white">{{ t.footer.product }}</p>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li v-for="link in navLinks" :key="link.id">
                            <a :href="`#${link.id}`" class="text-slate-500 transition hover:text-slate-950 dark:text-slate-400 dark:hover:text-white" @click="onLinkClick(link.id)">{{ link.label }}</a>
                        </li>
                    </ul>
                </nav>
                <nav class="md:col-span-3" :aria-label="t.footer.account">
                    <p class="text-sm font-bold text-slate-950 dark:text-white">{{ t.footer.account }}</p>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li v-if="signup">
                            <router-link :to="{ name: 'register' }" class="text-slate-500 transition hover:text-slate-950 dark:text-slate-400 dark:hover:text-white">{{ t.nav.register }}</router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'login' }" class="text-slate-500 transition hover:text-slate-950 dark:text-slate-400 dark:hover:text-white">{{ t.nav.login }}</router-link>
                        </li>
                    </ul>
                </nav>
            </div>
            <div class="border-t border-slate-200 dark:border-white/10">
                <p class="mx-auto max-w-7xl px-4 py-6 text-center text-xs text-slate-500 sm:px-6 sm:text-start lg:px-8 dark:text-slate-500">
                    © {{ currentYear }} {{ t.brand.company }}. {{ t.footer.copyright }}
                </p>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import { tenancy } from '../../tenancy';
import { useTheme } from '../../composables/useTheme';
import { useI18n } from '../../composables/useI18n';
import heroPhoto from '../../../images/hero-repair.webp';

const { isDark, toggleTheme } = useTheme();
const { t, toggleLocale } = useI18n();

const LOGO = '/icons/icon-192.png';
const currentYear = new Date().getFullYear();
const signup = tenancy.signup;

// Shared button styles
const btnPrimary =
    'inline-flex items-center justify-center rounded-full bg-gradient-to-r from-cyan-500 to-blue-600 font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:shadow-blue-600/40 hover:brightness-110 focus-ring';
const btnGhost =
    'inline-flex items-center justify-center rounded-full bg-white font-semibold text-slate-900 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:ring-slate-300 focus-ring dark:bg-white/5 dark:text-white dark:ring-white/15 dark:hover:bg-white/10';
const iconBtn =
    'inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-ring dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white';

// Icons follow the order of the matching lists in useI18n.
const featureIcons = ['wrench', 'user-cog', 'receipt', 'package', 'exchange', 'shield'];
const featureTone = [
    'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    'bg-sky-500/10 text-sky-600 dark:text-sky-400',
    'bg-rose-500/10 text-rose-600 dark:text-rose-400',
];
const moduleIcons = ['receipt', 'cart', 'wrench', 'tag', 'wallet', 'trending-down', 'users', 'chart'];
const whyIcons = ['zap', 'smartphone', 'printer'];

/** Section heading: eyebrow, title and optional lead text. */
const SectionHead = defineComponent({
    props: { eyebrow: String, title: String, text: String, inverted: Boolean, align: { type: String, default: 'center' } },
    setup(props) {
        return () =>
            h('div', { class: ['max-w-3xl', props.align === 'center' ? 'mx-auto text-center' : 'text-center lg:text-start'] }, [
                h('p', { class: ['text-sm font-semibold', props.inverted ? 'text-cyan-400' : 'text-cyan-600 dark:text-cyan-400'] }, props.eyebrow),
                h('h2', { class: ['mt-3 text-balance text-3xl font-bold leading-tight tracking-tight sm:text-[2.75rem] sm:leading-[1.15]', props.inverted ? 'text-white' : 'text-slate-950 dark:text-white'] }, props.title),
                props.text
                    ? h('p', { class: ['mx-auto mt-5 max-w-2xl text-pretty text-base leading-8 sm:text-lg', props.inverted ? 'text-slate-400' : 'text-slate-600 dark:text-slate-400'] }, props.text)
                    : null,
            ]);
    },
});

const navLinks = computed(() => [
    { id: 'features', label: t.value.nav.features },
    { id: 'workflow', label: t.value.nav.workflow },
    { id: 'modules', label: t.value.nav.modules },
    { id: 'why', label: t.value.nav.whyUs },
    { id: 'faq', label: t.value.nav.faq },
]);

const primaryCta = computed(() =>
    signup
        ? { to: { name: 'register' }, label: t.value.nav.register, short: t.value.nav.quickTrial }
        : { to: { name: 'login' }, label: t.value.hero.ctaLogin, short: t.value.nav.login }
);

const heroPoints = computed(() =>
    signup ? t.value.hero.pointsWithTrial.map((p) => p.replace('{days}', signup.trialDays)) : t.value.hero.pointsStandard
);

const allFaqs = computed(() => {
    const trial = signup && t.value.faqSection.trialFaq;
    const first = trial ? [{ q: trial.q, a: trial.a.replace('{days}', signup.trialDays) }] : [];
    return [...first, ...(t.value.faqSection.items || [])];
});

// ------------------------------------------------------------ Repair board demo
const stepDot = ['bg-slate-400', 'bg-amber-400', 'bg-blue-500', 'bg-emerald-500'];
const boardCards = [
    [{ id: '#1047', device: 'Galaxy A54' }, { id: '#1046', device: 'Redmi Note 12' }],
    [{ id: '#1044', device: 'iPhone 11' }],
    [{ id: '#1040', device: 'Galaxy S23 Ultra' }, { id: '#1039', device: 'Pixel 7' }],
    [{ id: '#1035', device: 'iPhone 14' }],
];
const activeStep = ref(0);
let stepTimer = null;

function stopAutoplay() {
    clearInterval(stepTimer);
    stepTimer = null;
}

function pickStep(idx) {
    stopAutoplay();
    activeStep.value = idx;
}

// ------------------------------------------------------------ Header & scrollspy
const scrolled = ref(false);
const menuOpen = ref(false);
const activeSection = ref('');
let sectionObserver = null;

function onScroll() {
    scrolled.value = window.scrollY > 8;
}

function onLinkClick(id) {
    menuOpen.value = false;
    activeSection.value = id;
}

function setupScrollspy() {
    const elements = navLinks.value.map((l) => document.getElementById(l.id)).filter(Boolean);
    sectionObserver = new IntersectionObserver(
        (entries) => {
            const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio);
            if (visible.length) activeSection.value = visible[0].target.id;
        },
        { rootMargin: '-30% 0px -55% 0px' }
    );
    elements.forEach((el) => sectionObserver.observe(el));
}

function setTitle() {
    document.title = `${t.value.brand.name} | ${t.value.brand.company}`;
}
watch(() => t.value.brand.name, setTitle);

onMounted(() => {
    setTitle();
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    setupScrollspy();

    if (!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        document.documentElement.style.scrollBehavior = 'smooth';
        stepTimer = setInterval(() => {
            activeStep.value = (activeStep.value + 1) % 4;
        }, 2600);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    document.documentElement.style.scrollBehavior = '';
    stopAutoplay();
    sectionObserver?.disconnect();
});
</script>

<style scoped>
.focus-ring:focus-visible {
    outline: 2px solid rgb(6 182 212);
    outline-offset: 2px;
}

/* Faint grid that fades out from the top centre */
.hero-grid {
    background-image:
        linear-gradient(to right, rgb(15 23 42 / 0.06) 1px, transparent 1px),
        linear-gradient(to bottom, rgb(15 23 42 / 0.06) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 70% 60% at 50% 0%, #000 30%, transparent 75%);
}
:global(.dark) .hero-grid,
.hero-grid-dark {
    background-image:
        linear-gradient(to right, rgb(255 255 255 / 0.06) 1px, transparent 1px),
        linear-gradient(to bottom, rgb(255 255 255 / 0.06) 1px, transparent 1px);
}

.faq summary::-webkit-details-marker {
    display: none;
}

/* Hero entrance */
.enter {
    animation: rise 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both;
}
.enter-delay {
    animation-delay: 0.15s;
}
@keyframes rise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
}

.ticket-enter-active,
.ticket-leave-active,
.ticket-move {
    transition: all 0.4s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.ticket-enter-from {
    opacity: 0;
    transform: scale(0.92) translateY(-6px);
}
.ticket-leave-to {
    opacity: 0;
    transform: scale(0.92);
}

.pop-enter-active,
.pop-leave-active {
    transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.pop-enter-from,
.pop-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.96);
}

.drop-enter-active,
.drop-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.drop-enter-from,
.drop-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

@media (prefers-reduced-motion: reduce) {
    .landing *,
    .landing *::before,
    .landing *::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>
