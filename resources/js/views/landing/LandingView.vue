<template>
    <div class="landing min-h-screen overflow-x-hidden bg-[#070B17] font-sans text-slate-100 selection:bg-cyan-400 selection:text-slate-950">
        <!-- Ambient background -->
        <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-48 inset-x-0 mx-auto h-[520px] w-[min(900px,100%)] rounded-full bg-gradient-to-tr from-blue-700/25 via-cyan-500/10 to-transparent blur-[130px]" />
            <div class="absolute top-[45%] -end-40 h-[520px] w-[520px] rounded-full bg-cyan-500/10 blur-[150px]" />
            <div class="absolute top-[75%] -start-40 h-[520px] w-[520px] rounded-full bg-blue-600/10 blur-[150px]" />
        </div>

        <!-- Header -->
        <header
            class="sticky top-0 z-50 border-b transition-colors duration-300"
            :class="scrolled || menuOpen ? 'border-white/10 bg-[#070B17]/85 backdrop-blur-xl' : 'border-transparent bg-transparent'"
        >
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:h-20 sm:px-6 lg:px-8">
                <a href="#top" class="flex items-center gap-3 rounded-xl focus-ring" @click="menuOpen = false">
                    <img :src="LOGO" alt="" class="h-10 w-10 rounded-xl ring-1 ring-white/15 shadow-lg shadow-blue-500/20" />
                    <span class="leading-tight">
                        <span class="block text-sm font-extrabold text-white sm:text-base">{{ BRAND.name }}</span>
                        <span class="block text-[11px] text-cyan-200/70">{{ BRAND.company }}</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-1 text-sm font-medium text-slate-300 lg:flex" aria-label="أقسام الصفحة">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-2 transition-colors hover:bg-white/5 hover:text-white focus-ring"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="flex items-center gap-2">
                    <router-link
                        v-if="signup"
                        :to="{ name: 'login' }"
                        class="hidden rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-white/5 hover:text-white focus-ring sm:inline-flex"
                    >
                        تسجيل الدخول
                    </router-link>
                    <router-link
                        :to="primaryCta.to"
                        class="hidden rounded-xl bg-gradient-to-l from-cyan-400 to-blue-500 px-5 py-2.5 text-sm font-bold text-slate-950 shadow-[0_0_24px_rgba(34,211,238,0.3)] transition hover:brightness-110 focus-ring sm:inline-flex"
                    >
                        {{ primaryCta.short }}
                    </router-link>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 text-slate-200 hover:bg-white/5 focus-ring lg:hidden"
                        :aria-expanded="menuOpen"
                        aria-controls="mobile-menu"
                        :aria-label="menuOpen ? 'إغلاق القائمة' : 'فتح القائمة'"
                        @click="menuOpen = !menuOpen"
                    >
                        <svg v-if="!menuOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <div v-show="menuOpen" id="mobile-menu" class="border-t border-white/10 px-4 pb-5 pt-3 lg:hidden">
                <nav class="flex flex-col gap-1 text-base font-medium text-slate-200" aria-label="أقسام الصفحة">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-xl px-3 py-3 hover:bg-white/5 focus-ring"
                        @click="menuOpen = false"
                    >
                        {{ link.label }}
                    </a>
                </nav>
                <router-link
                    :to="primaryCta.to"
                    class="mt-3 flex w-full items-center justify-center rounded-xl bg-gradient-to-l from-cyan-400 to-blue-500 py-3 font-bold text-slate-950 focus-ring"
                >
                    {{ primaryCta.label }}
                </router-link>
                <router-link
                    v-if="signup"
                    :to="{ name: 'login' }"
                    class="mt-2 flex w-full items-center justify-center rounded-xl border border-white/15 py-3 font-semibold text-slate-200 focus-ring"
                >
                    تسجيل الدخول
                </router-link>
            </div>
        </header>

        <main id="top" class="relative z-10">
            <!-- Hero -->
            <section class="pb-20 pt-10 sm:pt-16 lg:pb-28 lg:pt-20">
                <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-14 px-4 sm:px-6 lg:grid-cols-12 lg:gap-10 lg:px-8">
                    <div class="space-y-7 text-center lg:col-span-7 lg:text-start">
                        <span class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3.5 py-1.5 text-xs font-semibold text-cyan-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-cyan-300" aria-hidden="true" />
                            نظام متكامل لمحلات الهواتف ومراكز الصيانة
                        </span>

                        <h1 class="text-[2.1rem] font-black leading-[1.25] tracking-tight text-white sm:text-5xl lg:text-[3.6rem]">
                            أدِر محلّك كاملاً
                            <span class="block bg-gradient-to-l from-cyan-300 via-sky-400 to-blue-500 bg-clip-text text-transparent">
                                من الكاونتر حتى التقارير
                            </span>
                        </h1>

                        <p class="mx-auto max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg lg:mx-0">
                            المبيعات والمشتريات، استلام أجهزة الصيانة وتتبّعها، المخزون والصندوق وحسابات الخبراء —
                            في واجهة عربية واحدة سريعة تعمل على الحاسوب والجوال.
                        </p>

                        <div class="flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center lg:justify-start">
                            <router-link
                                :to="primaryCta.to"
                                class="group inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-l from-cyan-400 to-blue-500 px-7 py-4 text-base font-bold text-slate-950 shadow-[0_0_30px_rgba(34,211,238,0.35)] transition hover:brightness-110 focus-ring"
                            >
                                {{ primaryCta.label }}
                                <svg class="h-4 w-4 transition-transform group-hover:-translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            </router-link>
                            <router-link
                                v-if="signup"
                                :to="{ name: 'login' }"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-7 py-4 text-base font-semibold text-white transition hover:bg-white/10 focus-ring"
                            >
                                لديّ متجر — تسجيل الدخول
                            </router-link>
                            <a
                                v-else-if="contactUrl"
                                :href="contactUrl"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-7 py-4 text-base font-semibold text-white transition hover:bg-white/10 focus-ring"
                            >
                                اطلب نسخة لمحلّك
                            </a>
                            <a
                                v-else
                                href="#features"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-7 py-4 text-base font-semibold text-white transition hover:bg-white/10 focus-ring"
                            >
                                استعرض المزايا
                            </a>
                        </div>

                        <ul class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-slate-400 lg:justify-start">
                            <li v-for="item in heroPoints" :key="item" class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.58l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd" /></svg>
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <!-- Live repair-ticket mockup -->
                    <div class="relative mx-auto w-full max-w-md lg:col-span-5 lg:max-w-none">
                        <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-tr from-cyan-500/20 to-blue-600/20 blur-2xl" aria-hidden="true" />
                        <div class="relative rounded-3xl border border-white/10 bg-slate-900/80 p-5 shadow-[0_24px_70px_rgba(0,0,0,0.6)] backdrop-blur-xl sm:p-6">
                            <div class="mb-5 flex items-center justify-between border-b border-white/10 pb-4">
                                <div class="flex items-center gap-2" aria-hidden="true">
                                    <span class="h-3 w-3 rounded-full bg-rose-500/80" />
                                    <span class="h-3 w-3 rounded-full bg-amber-400/80" />
                                    <span class="h-3 w-3 rounded-full bg-emerald-500/80" />
                                </div>
                                <span class="font-mono text-xs text-slate-400" dir="ltr">repair.damatech.sy</span>
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-4">
                                    <div class="mb-3 flex items-center justify-between gap-2">
                                        <span class="font-mono text-sm font-bold text-cyan-300">#1042</span>
                                        <Transition name="fade" mode="out-in">
                                            <span
                                                :key="activeStep"
                                                class="rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                                :class="demoSteps[activeStep].badge"
                                            >
                                                {{ demoSteps[activeStep].label }}
                                            </span>
                                        </Transition>
                                    </div>

                                    <p class="text-base font-bold text-white">iPhone 13 Pro — أزرق</p>
                                    <p class="mt-1 text-xs text-slate-400">العطل: الشاشة لا تستجيب للّمس</p>
                                    <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                                        <span class="rounded-md border border-slate-700 bg-slate-800/80 px-2 py-0.5 text-slate-300">مع الغطاء</span>
                                        <span class="rounded-md border border-slate-700 bg-slate-800/80 px-2 py-0.5 text-slate-300">بدون شاحن</span>
                                        <span class="rounded-md border border-slate-700 bg-slate-800/80 px-2 py-0.5 font-mono text-slate-400" dir="ltr">IMEI 3568…5012</span>
                                    </div>

                                    <div class="mt-4 border-t border-slate-800 pt-4">
                                        <p class="mb-2 text-[11px] text-slate-400">مسار الجهاز — اضغط على أي مرحلة</p>
                                        <ol class="grid grid-cols-4 gap-1.5 text-center text-[11px]">
                                            <li v-for="(step, idx) in demoSteps" :key="step.short">
                                                <button
                                                    type="button"
                                                    class="w-full rounded-lg border px-1 py-1.5 font-medium transition-colors focus-ring"
                                                    :class="idx <= activeStep ? 'border-cyan-400/40 bg-cyan-400/15 text-cyan-200' : 'border-slate-800 bg-slate-900 text-slate-500 hover:text-slate-300'"
                                                    :aria-current="idx === activeStep ? 'step' : undefined"
                                                    @click="pickStep(idx)"
                                                >
                                                    {{ step.short }}
                                                </button>
                                            </li>
                                        </ol>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-3.5">
                                        <p class="text-[11px] text-slate-400">أجهزة قيد الصيانة</p>
                                        <p class="mt-1 text-2xl font-black text-white">18</p>
                                        <p class="mt-0.5 text-[11px] text-emerald-400">6 جاهزة للتسليم</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-3.5">
                                        <p class="text-[11px] text-slate-400">مبيعات اليوم</p>
                                        <p class="mt-1 font-mono text-2xl font-black text-cyan-300" dir="ltr">$1,240</p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">27 فاتورة</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Modules strip -->
            <section class="border-y border-white/5 bg-white/[0.02] py-8" aria-label="أقسام النظام">
                <ul class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-2.5 px-4 sm:gap-3">
                    <li
                        v-for="m in modules"
                        :key="m.label"
                        class="flex items-center gap-2 rounded-full border border-white/10 bg-slate-900/60 px-4 py-2 text-sm text-slate-300"
                    >
                        <span aria-hidden="true">{{ m.icon }}</span>
                        {{ m.label }}
                    </li>
                </ul>
            </section>

            <!-- Features -->
            <section id="features" class="scroll-mt-24 py-20 sm:py-28">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <SectionHeading
                        eyebrow="المزايا"
                        title="كل ما يحتاجه محلّك، بدون برامج متفرقة"
                        text="صُمّم النظام حول يوم العمل الفعلي في محل الهواتف: زبون على الكاونتر، جهاز للصيانة، فاتورة شراء من المورّد، وصندوق يجب أن يُطابق آخر النهار."
                    />

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                        <article
                            v-for="f in features"
                            :key="f.title"
                            class="group relative overflow-hidden rounded-3xl border border-white/10 bg-slate-900/50 p-7 transition-colors duration-300 hover:border-cyan-400/40"
                            :class="f.wide ? 'md:col-span-2' : ''"
                        >
                            <span
                                class="mb-5 inline-flex h-11 w-11 items-center justify-center rounded-xl border text-xl"
                                :class="f.tone"
                                aria-hidden="true"
                            >{{ f.icon }}</span>
                            <h3 class="text-xl font-bold text-white">{{ f.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ f.text }}</p>
                            <ul v-if="f.points" class="mt-5 flex flex-wrap gap-2 text-xs">
                                <li v-for="p in f.points" :key="p" class="rounded-lg border border-slate-800 bg-slate-950/70 px-2.5 py-1.5 text-slate-300">
                                    {{ p }}
                                </li>
                            </ul>
                        </article>
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section id="workflow" class="scroll-mt-24 border-y border-white/5 bg-gradient-to-b from-transparent via-slate-950/70 to-transparent py-20 sm:py-28">
                <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <SectionHeading
                        eyebrow="مسار الصيانة"
                        title="من استلام الجهاز حتى تسليمه، في أربع خطوات"
                        text="كل جهاز له سجل واضح: من استلمه، ما العطل، من الخبير المسؤول، وكم دُفع منه."
                    />

                    <ol class="relative grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <li
                            v-for="(step, idx) in workflow"
                            :key="step.title"
                            class="relative rounded-3xl border border-white/10 bg-slate-900/50 p-6"
                        >
                            <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-cyan-400 to-blue-500 font-mono text-base font-black text-slate-950">
                                {{ idx + 1 }}
                            </span>
                            <h3 class="text-lg font-bold text-white">{{ step.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ step.text }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <!-- Why -->
            <section id="why" class="scroll-mt-24 py-20 sm:py-28">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <SectionHeading
                        eyebrow="لماذا هذا النظام"
                        title="مبني للعمل اليومي، لا للعرض فقط"
                    />
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="w in whyItems" :key="w.title" class="rounded-3xl border border-white/10 bg-slate-900/50 p-6">
                            <svg v-if="w.flag" class="h-8 w-12 rounded-[3px] shadow ring-1 ring-white/15" viewBox="0 0 900 600" role="img" aria-label="علم سوريا">
                                <rect width="900" height="200" fill="#007A3D" />
                                <rect y="200" width="900" height="200" fill="#FFFFFF" />
                                <rect y="400" width="900" height="200" fill="#000000" />
                                <path fill="#CE1126" d="M225.0,238.0 L238.9,280.8 L284.0,280.8 L247.5,307.3 L261.4,350.2 L225.0,323.7 L188.6,350.2 L202.5,307.3 L166.0,280.8 L211.1,280.8Z M450.0,238.0 L463.9,280.8 L509.0,280.8 L472.5,307.3 L486.4,350.2 L450.0,323.7 L413.6,350.2 L427.5,307.3 L391.0,280.8 L436.1,280.8Z M675.0,238.0 L688.9,280.8 L734.0,280.8 L697.5,307.3 L711.4,350.2 L675.0,323.7 L638.6,350.2 L652.5,307.3 L616.0,280.8 L661.1,280.8Z" />
                            </svg>
                            <div v-else class="text-3xl" aria-hidden="true">{{ w.icon }}</div>
                            <h3 class="mt-4 text-lg font-bold text-white">{{ w.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ w.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQ -->
            <section id="faq" class="scroll-mt-24 border-t border-white/5 bg-slate-950/40 py-20 sm:py-28">
                <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <SectionHeading eyebrow="أسئلة شائعة" title="إجابات سريعة" />

                    <div class="space-y-3">
                        <div
                            v-for="(faq, idx) in faqs"
                            :key="faq.q"
                            class="overflow-hidden rounded-2xl border transition-colors"
                            :class="openFaq === idx ? 'border-cyan-400/30 bg-slate-900/80' : 'border-white/10 bg-slate-900/50'"
                        >
                            <h3>
                                <button
                                    :id="`faq-q-${idx}`"
                                    type="button"
                                    class="flex w-full items-center justify-between gap-4 p-5 text-start text-base font-bold text-white hover:text-cyan-200 focus-ring sm:p-6"
                                    :aria-expanded="openFaq === idx"
                                    :aria-controls="`faq-a-${idx}`"
                                    @click="openFaq = openFaq === idx ? -1 : idx"
                                >
                                    <span>{{ faq.q }}</span>
                                    <svg
                                        class="h-5 w-5 shrink-0 text-cyan-300 transition-transform duration-200"
                                        :class="openFaq === idx ? 'rotate-180' : ''"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"
                                    ><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                            </h3>
                            <div
                                v-show="openFaq === idx"
                                :id="`faq-a-${idx}`"
                                role="region"
                                :aria-labelledby="`faq-q-${idx}`"
                                class="px-5 pb-5 text-sm leading-relaxed text-slate-300 sm:px-6 sm:pb-6"
                            >
                                {{ faq.a }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Final CTA -->
            <section class="py-20 sm:py-24">
                <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div class="relative overflow-hidden rounded-3xl border border-cyan-400/30 bg-gradient-to-br from-blue-800/60 via-slate-900 to-cyan-900/50 px-6 py-12 text-center shadow-[0_0_80px_rgba(34,211,238,0.15)] sm:px-14 sm:py-16">
                        <h2 class="text-2xl font-black leading-snug text-white sm:text-4xl">جاهز لتبدأ يومك من لوحة واحدة؟</h2>
                        <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-slate-300">
                            <template v-if="signup">أنشئ متجرك الآن وجرّب النظام كاملاً مجاناً لمدة {{ signup.trialDays }} يوماً.</template>
                            <template v-else>سجّل الدخول بحسابك وتابع المبيعات والصيانة والصندوق من أي جهاز.</template>
                        </p>
                        <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                            <router-link
                                :to="primaryCta.to"
                                class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-l from-cyan-400 to-blue-500 px-8 py-4 text-base font-bold text-slate-950 shadow-xl shadow-cyan-500/25 transition hover:brightness-110 focus-ring"
                            >
                                {{ primaryCta.label }}
                            </router-link>
                            <router-link
                                v-if="signup"
                                :to="{ name: 'login' }"
                                class="inline-flex items-center justify-center rounded-2xl border border-white/15 bg-white/5 px-8 py-4 text-base font-semibold text-white transition hover:bg-white/10 focus-ring"
                            >
                                تسجيل الدخول
                            </router-link>
                            <a
                                v-else-if="contactUrl"
                                :href="contactUrl"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center justify-center rounded-2xl border border-white/15 bg-white/5 px-8 py-4 text-base font-semibold text-white transition hover:bg-white/10 focus-ring"
                            >
                                تواصل معنا
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="relative z-10 border-t border-white/5 bg-[#05080F] py-10 text-sm text-slate-500">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 text-center sm:flex-row sm:px-6 sm:text-start lg:px-8">
                <div class="flex items-center gap-3">
                    <img :src="LOGO" alt="" class="h-8 w-8 rounded-lg opacity-90" />
                    <span class="font-semibold text-slate-300">{{ BRAND.name }}</span>
                </div>
                <p>© {{ currentYear }} {{ BRAND.company }}. جميع الحقوق محفوظة.</p>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref } from 'vue';
import { tenancy } from '../../tenancy';

/**
 * Public-facing details. Fill `whatsapp` (international format, digits only,
 * e.g. '9639XXXXXXXX') to show the "request a copy" / "contact us" buttons.
 */
const BRAND = {
    name: 'نظام الهواتف والإكسسوارات',
    company: 'داماتك لتقنية المعلومات',
    whatsapp: '',
};

const contactUrl = computed(() => {
    const phone = BRAND.whatsapp.replace(/\D/g, '');
    if (!phone) return '';
    const text = encodeURIComponent('مرحباً، أرغب بالاستفسار عن نظام الهواتف والإكسسوارات لمحلي.');
    return `https://wa.me/${phone}?text=${text}`;
});

// Bound, not a static src: the icon lives in public/ and must not go through the bundler.
const LOGO = '/icons/icon-192.png';
const currentYear = new Date().getFullYear();

const SectionHeading = defineComponent({
    props: { eyebrow: String, title: String, text: String },
    setup: (props) => () =>
        h('div', { class: 'mx-auto mb-12 max-w-3xl space-y-4 text-center sm:mb-16' }, [
            h('span', { class: 'inline-block rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-xs font-semibold text-cyan-200' }, props.eyebrow),
            h('h2', { class: 'text-2xl font-black leading-snug text-white sm:text-4xl' }, props.title),
            props.text ? h('p', { class: 'text-base leading-relaxed text-slate-400' }, props.text) : null,
        ]),
});

const navLinks = [
    { href: '#features', label: 'المزايا' },
    { href: '#workflow', label: 'مسار الصيانة' },
    { href: '#why', label: 'لماذا نحن' },
    { href: '#faq', label: 'الأسئلة الشائعة' },
];

// Self-service sign-up, offered only on the public site (tenancy.signup).
const signup = tenancy.signup;

const primaryCta = signup
    ? { to: { name: 'register' }, label: 'أنشئ متجرك مجاناً', short: 'جرّب مجاناً' }
    : { to: { name: 'login' }, label: 'الدخول إلى النظام', short: 'تسجيل الدخول' };

const heroPoints = signup
    ? [`تجربة مجانية ${signup.trialDays} يوماً`, 'بدون بطاقة دفع', 'متجرك جاهز خلال دقيقة']
    : ['يعمل من المتصفح ويُثبَّت كتطبيق', 'طباعة إيصالات وفواتير', 'صلاحيات لكل موظف'];

const modules = [
    { icon: '🧾', label: 'المبيعات' },
    { icon: '📦', label: 'المشتريات' },
    { icon: '🔧', label: 'الصيانة' },
    { icon: '🏷️', label: 'المخزون والمواد' },
    { icon: '💵', label: 'الصندوق والدفعات' },
    { icon: '📉', label: 'المصاريف' },
    { icon: '👥', label: 'العملاء والموردون' },
    { icon: '📈', label: 'التقارير' },
];

const features = [
    {
        icon: '🔧',
        tone: 'border-cyan-400/20 bg-cyan-400/10',
        wide: true,
        title: 'استلام أجهزة الصيانة وتتبّعها',
        text: 'سجّل الجهاز والعطل والملحقات المستلمة والسيريال/IMEI خلال ثوانٍ، واطبع إيصال الاستلام للزبون، ثم أرسل له إشعار واتساب بضغطة زر.',
        points: ['مستلَم', 'قيد الفحص', 'قيد الإصلاح', 'بانتظار موافقة العميل', 'جاهز للتسليم', 'تم التسليم'],
    },
    {
        icon: '🧑‍🔧',
        tone: 'border-amber-400/20 bg-amber-400/10',
        title: 'حسابات خبراء الصيانة',
        text: 'حدّد نسبة كل خبير من قيمة الإصلاح، وتابع مستحقاته ورصيده في كشف حساب واضح.',
    },
    {
        icon: '🧾',
        tone: 'border-blue-400/20 bg-blue-400/10',
        title: 'فواتير بيع وشراء سريعة',
        text: 'أضف المواد بالبحث أو بقارئ الباركود، واحفظ الفاتورة نقداً أو آجلاً على حساب الزبون أو المورّد.',
    },
    {
        icon: '🏷️',
        tone: 'border-emerald-400/20 bg-emerald-400/10',
        wide: true,
        title: 'مخزون دقيق وأرصدة واضحة',
        text: 'كل بيع وشراء يُحدّث المخزون مباشرة. تقارير الأرباح، حركة المواد، المواد الناقصة، وأرصدة الزبائن والموردين جاهزة لأي فترة تختارها.',
        points: ['تقرير الأرباح', 'حركة المخزون', 'المواد الناقصة', 'أرصدة الحسابات', 'ملخص الفترة'],
    },
    {
        icon: '💱',
        tone: 'border-sky-400/20 bg-sky-400/10',
        wide: true,
        title: 'صندوق ودفعات بعدّة عملات',
        text: 'سجّل المقبوضات والمدفوعات والمصاريف بالعملة التي تتعامل بها، مع احتساب تلقائي بالعملة الأساسية حتى يبقى الصندوق مطابقاً.',
    },
    {
        icon: '🔐',
        tone: 'border-rose-400/20 bg-rose-400/10',
        title: 'أدوار وصلاحيات',
        text: 'كل موظف يرى ما يخصّه فقط: موظف الكاونتر، خبير الصيانة، أو المدير.',
    },
];

const workflow = [
    { title: 'الاستلام', text: 'بيانات الزبون والجهاز والعطل والملحقات، مع إيصال مطبوع.' },
    { title: 'الفحص والإصلاح', text: 'إسناد الجهاز لخبير وتحديث حالته وتكلفته التقديرية.' },
    { title: 'إبلاغ الزبون', text: 'رسالة واتساب جاهزة عند كل مرحلة مهمة، بدون اتصالات متكررة.' },
    { title: 'التسليم والتحصيل', text: 'تسوية المبلغ، تسجيله في الصندوق، واحتساب حصة الخبير.' },
];

const whyItems = [
    { icon: '⚡', title: 'سريع على الكاونتر', text: 'واجهة مختصرة تقلل النقرات في أكثر العمليات تكراراً.' },
    { icon: '📱', title: 'على أي جهاز', text: 'يعمل على الحاسوب والتابلت والجوال، ويمكن تثبيته كتطبيق.' },
    { icon: '🖨️', title: 'طباعة مرنة', text: 'إعدادات طباعة لكل جهاز، من الطابعة الحرارية إلى A4.' },
    { flag: true, title: 'عربي بالكامل', text: 'مصمم بالعربية من اليوم الأول، مع دعم فني محلي.' },
];

const faqs = [
    ...(signup
        ? [{
            q: 'كيف أبدأ؟ وماذا يحدث بعد الفترة التجريبية؟',
            a: `أنشئ متجرك من زر "أنشئ متجرك مجاناً" فتحصل على عنوان خاص بمتجرك وقاعدة بيانات مستقلة خلال دقيقة. التجربة مجانية لمدة ${signup.trialDays} يوماً وبكل المزايا، وقبل انتهائها تواصل معنا لتفعيل الاشتراك دون أن تفقد أي بيانات.`,
        }]
        : []),
    {
        q: 'هل أحتاج لتثبيت برنامج على الحاسوب؟',
        a: 'لا. يعمل النظام من المتصفح مباشرة، ويمكنك تثبيته على الجوال أو الحاسوب كتطبيق من خيار "التثبيت" في المتصفح.',
    },
    {
        q: 'هل يمكن لأكثر من موظف العمل في نفس الوقت؟',
        a: 'نعم، لكل موظف حسابه وصلاحياته، ويعمل الجميع على نفس البيانات لحظياً.',
    },
    {
        q: 'هل يدعم طابعات الإيصالات الحرارية؟',
        a: 'نعم، يمكن ضبط إعدادات الطباعة لكل جهاز على حدة، بما يناسب الطابعات الحرارية أو طابعات A4.',
    },
    {
        q: 'كيف أبلّغ الزبون عن جاهزية جهازه؟',
        a: 'بعد استلام الجهاز أو تغيير حالته يُجهّز النظام رسالة واتساب بتفاصيل الجهاز، ترسلها للزبون بضغطة واحدة.',
    },
    {
        q: 'هل بياناتي محفوظة إذا انقطع الإنترنت؟',
        a: 'البيانات محفوظة على الخادم وليس على جهازك. عند انقطاع الاتصال يُظهر النظام تنبيهاً بدلاً من عرض أرقام قديمة، فتبقى الأسعار والأرصدة دقيقة دائماً.',
    },
];
const openFaq = ref(0);

// Hero mockup: the ticket walks through the repair statuses on its own until
// the visitor picks a step.
const demoSteps = [
    { short: 'استلام', label: 'مستلَم', badge: 'border-slate-500/40 bg-slate-500/15 text-slate-200' },
    { short: 'فحص', label: 'قيد الفحص', badge: 'border-amber-400/40 bg-amber-400/15 text-amber-200' },
    { short: 'إصلاح', label: 'قيد الإصلاح', badge: 'border-blue-400/40 bg-blue-400/15 text-blue-200' },
    { short: 'جاهز', label: 'جاهز للتسليم', badge: 'border-emerald-400/40 bg-emerald-400/15 text-emerald-200' },
];
const activeStep = ref(1);
let stepTimer = null;

function stopAutoplay() {
    if (stepTimer) clearInterval(stepTimer);
    stepTimer = null;
}

function pickStep(idx) {
    stopAutoplay();
    activeStep.value = idx;
}

// Header turns solid once the page scrolls under it.
const scrolled = ref(false);
const menuOpen = ref(false);
function onScroll() {
    scrolled.value = window.scrollY > 8;
}

onMounted(() => {
    document.title = `${BRAND.name} | ${BRAND.company}`;
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (!reduceMotion) {
        document.documentElement.style.scrollBehavior = 'smooth';
        stepTimer = setInterval(() => {
            activeStep.value = (activeStep.value + 1) % demoSteps.length;
        }, 2600);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    document.documentElement.style.scrollBehavior = '';
    stopAutoplay();
});
</script>

<style scoped>
.focus-ring:focus-visible {
    outline: 2px solid rgb(103 232 249);
    outline-offset: 2px;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .landing * {
        transition: none !important;
    }
}
</style>
