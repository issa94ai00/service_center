<template>
    <div id="dashboard-sheet" class="mx-auto max-w-[1440px] space-y-5">
        <!-- Header -->
        <header class="flex flex-wrap items-center justify-between gap-4 print:hidden">
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <span>{{ todayLabel }}</span>
                    <span aria-hidden="true" class="text-slate-300 dark:text-slate-700">•</span>
                    <span class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                        <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        </span>
                        <template v-if="loadedAt">محدّث {{ loadedAtLabel }}</template>
                        <template v-else>بيانات حية</template>
                    </span>
                </p>
                <h2 class="mt-1 truncate text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-[1.75rem]">
                    {{ greeting }}، {{ auth.user?.name }}
                </h2>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="!pwa.installed.value"
                    type="button"
                    :class="btnSecondary"
                    title="تثبيت النظام كتطبيق على هذا الجهاز"
                    @click="pwa.installOrExplain"
                >
                    <AppIcon name="download" class="text-base" />
                    <span class="hidden sm:inline">تثبيت التطبيق</span>
                </button>
                <button
                    type="button"
                    :class="btnIcon"
                    :disabled="loading || refreshing"
                    :title="refreshing ? 'جارٍ التحديث...' : 'تحديث بيانات الشاشة'"
                    :aria-label="refreshing ? 'جارٍ التحديث...' : 'تحديث بيانات الشاشة'"
                    @click="refresh"
                >
                    <AppIcon name="refresh" class="text-lg" :class="refreshing ? 'animate-spin text-blue-600 dark:text-blue-400' : ''" />
                </button>
                <button
                    type="button"
                    :class="btnIcon"
                    :disabled="!data"
                    title="طباعة لوحة التحكم"
                    aria-label="طباعة لوحة التحكم"
                    @click="printSheet"
                >
                    <AppIcon name="printer" class="text-lg" />
                </button>
                <RouterLink
                    v-if="can('reports.view')"
                    :to="{ name: 'reports', query: { tab: 'profit', from: filters.from, to: filters.to } }"
                    class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-900 dark:bg-white px-4 text-sm font-semibold text-white dark:text-slate-900 shadow-sm transition hover:bg-slate-800 dark:hover:bg-slate-100 active:scale-[0.98]"
                >
                    <AppIcon name="chart" class="text-base" />
                    <span>التقرير المالي</span>
                </RouterLink>
            </div>
        </header>

        <!-- Period toolbar -->
        <div class="flex flex-wrap items-center gap-2 print:hidden">
            <div
                class="no-scrollbar flex min-w-0 max-w-full items-center gap-0.5 overflow-x-auto rounded-xl bg-slate-200/60 dark:bg-slate-800/70 p-1"
                role="group"
                aria-label="الفترة"
            >
                <button
                    v-for="preset in presets"
                    :key="preset.key"
                    type="button"
                    class="shrink-0 rounded-lg px-3 py-1.5 text-[13px] font-semibold transition"
                    :class="activePreset === preset.key
                        ? 'bg-white dark:bg-slate-950 text-slate-900 dark:text-white shadow-sm'
                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                    :aria-pressed="activePreset === preset.key"
                    @click="applyPreset(preset)"
                >
                    {{ preset.label }}
                </button>
            </div>

            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <div class="flex w-full min-w-0 items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-2xs sm:w-auto">
                    <button
                        type="button"
                        class="grid h-9 w-9 place-items-center rounded-s-xl text-slate-500 dark:text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white disabled:opacity-40"
                        :disabled="loading || refreshing"
                        title="الانتقال إلى الفترة السابقة بنفس المدة"
                        aria-label="الفترة السابقة"
                        @click="shiftPeriod(-1)"
                    >
                        <AppIcon name="chevron-right" class="text-base ltr:rotate-180" />
                    </button>
                    <AppIcon name="calendar" class="hidden shrink-0 text-base text-slate-400 sm:block" />
                    <label class="flex min-w-0 flex-1 items-center px-1">
                        <span class="sr-only">من تاريخ</span>
                        <input
                            v-model="filters.from"
                            type="date"
                            class="w-full min-w-0 rounded-md bg-transparent sm:w-[8.4rem] px-1.5 py-1.5 text-[13px] font-semibold tabular-nums text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                            @keyup.enter="applyFilters"
                        />
                    </label>
                    <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">–</span>
                    <label class="flex min-w-0 flex-1 items-center px-1">
                        <span class="sr-only">إلى تاريخ</span>
                        <input
                            v-model="filters.to"
                            type="date"
                            :min="filters.from || undefined"
                            class="w-full min-w-0 rounded-md bg-transparent sm:w-[8.4rem] px-1.5 py-1.5 text-[13px] font-semibold tabular-nums text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                            @keyup.enter="applyFilters"
                        />
                    </label>
                    <button
                        type="button"
                        class="grid h-9 w-9 place-items-center rounded-e-xl text-slate-500 dark:text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white disabled:opacity-40"
                        :disabled="loading || refreshing"
                        title="الانتقال إلى الفترة التالية بنفس المدة"
                        aria-label="الفترة التالية"
                        @click="shiftPeriod(1)"
                    >
                        <AppIcon name="chevron-left" class="text-base ltr:rotate-180" />
                    </button>
                </div>

                <span class="rounded-lg px-1.5 text-xs font-bold tabular-nums text-slate-500 dark:text-slate-400">
                    {{ periodDaysCount }} {{ periodDaysCount === 1 ? 'يوم' : periodDaysCount === 2 ? 'يومان' : 'يومًا' }}
                </span>

                <button
                    v-if="dirty || rangeInvalid"
                    type="button"
                    :disabled="rangeInvalid"
                    class="h-9 rounded-xl bg-blue-600 px-4 text-[13px] font-bold text-white shadow-sm shadow-blue-600/30 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40 active:scale-95"
                    @click="applyFilters"
                >
                    تطبيق
                </button>
                <button
                    v-if="canReset"
                    type="button"
                    class="h-9 rounded-xl px-3 text-[13px] font-medium text-slate-500 dark:text-slate-400 transition hover:bg-slate-200/60 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white"
                    @click="resetFilters"
                >
                    إعادة تعيين
                </button>
            </div>

            <p v-if="rangeInvalid" class="flex w-full items-center gap-1.5 text-xs font-bold text-red-600 dark:text-red-400" role="alert">
                <AppIcon name="alert" /> تاريخ البداية بعد تاريخ النهاية
            </p>
            <p v-else-if="dirty" class="w-full text-xs font-semibold text-blue-600 dark:text-blue-400">
                هناك تعديلات في التواريخ بانتظار التطبيق
            </p>
        </div>

        <!-- Quick actions -->
        <nav v-if="quickActions.length" class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0 print:hidden" aria-label="إجراءات سريعة">
            <RouterLink
                v-for="action in quickActions"
                :key="action.label"
                :to="action.to"
                class="group inline-flex shrink-0 items-center gap-2 rounded-full border py-1.5 pe-4 ps-1.5 text-[13px] font-semibold transition"
                :class="action.primary
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm shadow-blue-600/25 hover:bg-blue-700'
                    : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:border-slate-300 dark:hover:border-slate-700 hover:text-slate-900 dark:hover:text-white'"
            >
                <span
                    class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-[14px] transition group-hover:scale-105"
                    :class="action.primary ? 'bg-white/20 text-white' : action.tone"
                >
                    <AppIcon :name="action.primary ? 'plus' : action.icon" />
                </span>
                <span class="whitespace-nowrap">{{ action.label }}</span>
            </RouterLink>
        </nav>

        <!-- Print-only Header Sheet -->
        <div class="hidden border-b border-slate-200 pb-3 print:block">
            <h2 class="text-xl font-bold">لوحة التحكم التنفيذية — {{ auth.user?.name }}</h2>
            <p class="mt-0.5 text-xs text-slate-600">{{ periodLabel }}</p>
            <p class="mt-0.5 text-[11px] text-slate-500">طُبع في {{ printedAt }}</p>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 p-4 text-sm font-semibold text-red-700 dark:text-red-400"
        >
            <div class="flex items-center gap-2">
                <AppIcon name="alert" class="text-lg" />
                <span>{{ error }}</span>
            </div>
            <button
                type="button"
                class="rounded-xl border border-red-300 dark:border-red-800 bg-white dark:bg-slate-900 px-3.5 py-1.5 text-xs font-bold text-red-700 dark:text-red-400 transition hover:bg-red-100 dark:hover:bg-red-950 print:hidden"
                @click="load"
            >
                إعادة المحاولة
            </button>
        </div>

        <DashboardSkeleton v-if="loading && !data" />

        <div
            v-else-if="data"
            class="grid gap-4 transition-opacity lg:grid-cols-12"
            :class="refreshing ? 'pointer-events-none opacity-50' : ''"
            :aria-busy="refreshing"
        >
            <!-- Needs attention -->
            <div v-if="activeAlerts.length" class="no-scrollbar -mx-4 flex items-center gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0 lg:col-span-12">
                <span class="flex shrink-0 items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-slate-400">
                    <AppIcon name="alert" class="text-amber-500" />
                    تحتاج انتباهك
                </span>
                <RouterLink
                    v-for="alert in activeAlerts"
                    :key="alert.label"
                    :to="alert.to"
                    class="inline-flex shrink-0 items-center gap-2 rounded-full border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 py-1 pe-1 ps-3 text-[13px] font-semibold text-slate-700 dark:text-slate-300 transition hover:border-slate-300 dark:hover:border-slate-700 hover:text-slate-900 dark:hover:text-white"
                >
                    <AppIcon :name="alert.icon" class="text-[15px]" :class="alert.iconTone" />
                    <span class="whitespace-nowrap">{{ alert.label }}</span>
                    <span class="grid h-6 min-w-6 place-items-center rounded-full px-2 text-xs font-extrabold tabular-nums" :class="alert.countTone">
                        {{ alert.count }}
                    </span>
                </RouterLink>
            </div>

            <!-- Period profit -->
            <section class="dash-card dashboard-hero relative isolate overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] sm:p-6 lg:col-span-8">
                <div class="hero-glow pointer-events-none absolute -end-32 -top-40 -z-10 h-80 w-80 rounded-full bg-blue-500/10 blur-3xl dark:bg-blue-500/15" aria-hidden="true"></div>

                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">صافي الربح التقديري</p>
                        <p class="mt-0.5 font-mono text-xs text-slate-400 dark:text-slate-500" dir="ltr">{{ data.period.from }} → {{ data.period.to }}</p>
                    </div>
                    <span
                        v-if="data.profit_overview"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="data.profit_overview.is_loss
                            ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400'
                            : 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400'"
                    >
                        <AppIcon :name="data.profit_overview.is_loss ? 'trending-down' : 'trending-up'" />
                        هامش {{ data.profit_overview.net_margin }}٪
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
                    <p
                        class="min-w-0 break-words text-4xl font-extrabold tabular-nums tracking-tight sm:text-5xl"
                        :class="data.profit_overview?.is_loss ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white'"
                    >
                        {{ formatUsd(data.profit_overview?.net_profit ?? tradeMargin) }}
                    </p>

                    <dl class="flex gap-6 text-sm">
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">الإيرادات</dt>
                            <dd class="mt-0.5 font-bold tabular-nums text-slate-900 dark:text-white">{{ formatUsd(data.profit_overview?.revenue ?? data.kpis.sales_period.amount) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">مجمل الربح</dt>
                            <dd class="mt-0.5 font-bold tabular-nums text-slate-900 dark:text-white">
                                {{ formatUsd(data.profit_overview?.gross_profit ?? tradeMargin) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">التدفق النقدي</dt>
                            <dd class="mt-0.5 font-bold tabular-nums" :class="netCashFlow < 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white'">
                                {{ formatUsd(netCashFlow) }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-6 border-t border-slate-100 dark:border-slate-800 pt-5">
                    <ProfitBreakdown v-if="data.profit_overview" :overview="data.profit_overview" />
                </div>
            </section>

            <!-- Overall position -->
            <section class="dash-card flex flex-col justify-between rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] sm:p-6 lg:col-span-4">
                <div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">صافي المركز المالي</p>
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                            <AppIcon name="scale" />
                        </span>
                    </div>
                    <p
                        class="mt-3 break-words text-3xl font-extrabold tabular-nums tracking-tight sm:text-[2.1rem]"
                        :class="Number(data.kpis.net_position) < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                    >
                        {{ formatUsd(data.kpis.net_position) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">الذمم المستحقة لنا ناقص المستحقة علينا، حتى اليوم</p>
                </div>

                <div class="mt-6">
                    <div class="position-track flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                        <div class="h-full rounded-s-full bg-emerald-500 transition-all duration-500" :style="{ width: `${positionSplit.receivables}%` }"></div>
                        <div class="h-full rounded-e-full bg-amber-400 transition-all duration-500" :style="{ width: `${positionSplit.payables}%` }"></div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <component
                            :is="can('reports.view') ? RouterLink : 'div'"
                            :to="can('reports.view') ? { name: 'reports', query: { tab: 'receivables' } } : undefined"
                            class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-3 transition"
                            :class="can('reports.view') ? 'hover:bg-slate-100 dark:hover:bg-slate-800' : ''"
                        >
                            <span class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                لنا (عملاء)
                            </span>
                            <span class="mt-1 block truncate text-[15px] font-bold tabular-nums text-slate-900 dark:text-white">{{ formatUsd(data.kpis.receivables) }}</span>
                        </component>
                        <component
                            :is="can('reports.view') ? RouterLink : 'div'"
                            :to="can('reports.view') ? { name: 'reports', query: { tab: 'payables' } } : undefined"
                            class="rounded-xl bg-slate-50 dark:bg-slate-800/50 p-3 transition"
                            :class="can('reports.view') ? 'hover:bg-slate-100 dark:hover:bg-slate-800' : ''"
                        >
                            <span class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                <span class="h-2 w-2 rounded-full bg-amber-400" aria-hidden="true"></span>
                                علينا (موردون)
                            </span>
                            <span class="mt-1 block truncate text-[15px] font-bold tabular-nums text-slate-900 dark:text-white">{{ formatUsd(data.kpis.payables) }}</span>
                        </component>
                    </div>
                </div>
            </section>

            <!-- KPI strip: one card, cells split by hairlines -->
            <section class="dash-card overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)] lg:col-span-12" aria-label="المؤشرات الرئيسية">
                <div class="-mb-px -me-px grid grid-cols-2 md:grid-cols-3 2xl:grid-cols-6">
                    <StatCard
                        v-for="card in kpiCards"
                        :key="card.label"
                        v-bind="card"
                        :previous-label="comparisonLabel"
                        class="border-b border-e border-slate-100 dark:border-slate-800"
                    />
                </div>
            </section>

            <!-- Trend -->
            <DashCard
                title="حركة المبيعات والمشتريات"
                :subtitle="`الفارق التجاري ${formatUsd(tradeMargin)}`"
                icon="chart"
                tone="bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400"
                class="lg:col-span-8"
            >
                <template #actions>
                    <span class="shrink-0 rounded-lg bg-slate-100 dark:bg-slate-800 px-2 py-1 text-xs font-bold text-slate-600 dark:text-slate-300">
                        {{ data.trend.granularity === 'day' ? 'يومي' : 'شهري' }}
                    </span>
                </template>
                <TrendChart :points="data.trend.points" :series="trendSeries" :format-value="formatUsd" />
            </DashCard>

            <!-- Workshop -->
            <DashCard
                v-if="data.workshop"
                title="ورشة الصيانة"
                subtitle="الوضع الحالي للأجهزة"
                icon="wrench"
                tone="bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400"
                :to="can('repairs.view') ? { name: 'repairs' } : null"
                link-label="الصيانة"
                class="lg:col-span-4"
            >
                <WorkshopPipeline :workshop="data.workshop" :linkable="can('repairs.view')" />
            </DashCard>

            <!-- Rankings: one card, a tab per list -->
            <DashCard
                title="الأعلى في الفترة"
                icon="trending-up"
                tone="bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-400"
                :to="currentRanking.to"
                :link-label="currentRanking.linkLabel"
                :class="data.workshop ? 'lg:col-span-6 xl:col-span-4' : 'lg:order-1 lg:col-span-7'"
            >
                <div class="no-scrollbar -mx-1 mb-4 flex gap-1 overflow-x-auto px-1" role="tablist" aria-label="القوائم">
                    <button
                        v-for="tab in rankingTabs"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                        :class="currentRanking.key === tab.key
                            ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900'
                            : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        :aria-selected="currentRanking.key === tab.key"
                        @click="rankingTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <RankedList
                    :rows="currentRanking.rows"
                    :color="currentRanking.color"
                    :empty-icon="currentRanking.emptyIcon"
                    :empty-title="currentRanking.emptyTitle"
                    empty-hint="جرّب توسيع نطاق الفترة"
                />
                <p
                    v-if="currentRanking.key === 'technicians'"
                    class="mt-3 flex items-center justify-between border-t border-slate-100 dark:border-slate-800 pt-3 text-xs text-slate-500 dark:text-slate-400"
                >
                    <span>مستحقات قائمة للخبراء النشطين</span>
                    <b class="tabular-nums text-slate-800 dark:text-slate-200">{{ formatUsd(data.top_technicians.balance_owed) }}</b>
                </p>
            </DashCard>

            <!-- Recent activity -->
            <DashCard
                title="آخر الحركات"
                icon="clock"
                :class="data.workshop ? 'lg:col-span-6 xl:col-span-4' : 'lg:order-1 lg:col-span-5'"
            >
                <div class="no-scrollbar -mx-1 mb-3 flex gap-1 overflow-x-auto px-1">
                    <button
                        v-for="rf in recentFilters"
                        :key="rf.key"
                        type="button"
                        class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                        :class="recentFilter === rf.key
                            ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900'
                            : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-800 dark:hover:text-slate-200'"
                        :aria-pressed="recentFilter === rf.key"
                        @click="recentFilter = rf.key"
                    >
                        {{ rf.label }}
                    </button>
                </div>

                <ul v-if="filteredRecentDocuments.length" class="-mx-2 space-y-0.5">
                    <li
                        v-for="doc in filteredRecentDocuments"
                        :key="`${doc.kind}-${doc.id}`"
                        class="group flex items-center justify-between gap-3 rounded-xl px-2 py-2 transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-[15px]"
                                :class="doc.direction === 'in' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'"
                            >
                                <AppIcon :name="documentIcon(doc)" />
                            </span>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="documentRoute(doc) ? RouterLink : 'span'"
                                        :to="documentRoute(doc) || undefined"
                                        class="truncate text-sm font-semibold text-slate-900 dark:text-white transition"
                                        :class="documentRoute(doc) ? 'group-hover:text-blue-600 dark:group-hover:text-blue-400' : ''"
                                    >
                                        {{ doc.party || doc.kind_label }}
                                    </component>
                                </div>
                                <div class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="shrink-0">{{ doc.kind_label }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span class="shrink-0 font-mono" dir="ltr">{{ doc.date }}</span>
                                    <template v-if="doc.reference_no">
                                        <span aria-hidden="true">·</span>
                                        <span class="truncate font-mono" dir="ltr">{{ doc.reference_no }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <span class="shrink-0 text-sm font-bold tabular-nums" :class="doc.direction === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-800 dark:text-slate-200'">
                            {{ doc.direction === 'in' ? '+' : '−' }}{{ formatUsd(doc.amount) }}
                        </span>
                    </li>
                </ul>
                <EmptyState v-else icon="🗂" title="لا توجد حركات مطابقة" hint="ستظهر هنا العمليات المعتمدة فور تسجيلها" />
            </DashCard>

            <!-- Low stock -->
            <DashCard
                title="مواد قرب النفاد"
                icon="alert"
                tone="bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400"
                :to="can('reports.view') ? { name: 'reports', query: { tab: 'low-stock' } } : null"
                link-label="النواقص"
                :class="data.workshop ? 'lg:col-span-12 xl:col-span-4' : 'lg:col-span-4'"
            >
                <ul
                    v-if="data.low_stock.length"
                    class="-mx-2 grid gap-x-4"
                    :class="data.workshop ? 'sm:grid-cols-2 xl:grid-cols-1' : ''"
                >
                    <li v-for="material in data.low_stock" :key="material.id" class="group rounded-xl px-2 py-2.5 transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <component
                                    :is="can('reports.view') ? RouterLink : 'span'"
                                    :to="can('reports.view') ? { name: 'reports', query: { tab: 'low-stock', search: material.name } } : undefined"
                                    class="block truncate text-sm font-semibold text-slate-900 dark:text-white"
                                    :class="can('reports.view') ? 'group-hover:text-blue-600 dark:group-hover:text-blue-400' : ''"
                                >
                                    {{ material.name }}
                                </component>
                                <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">الحد الأدنى {{ formatQty(material.min_stock) }} {{ material.unit || '' }}</div>
                            </div>
                            <span
                                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold tabular-nums"
                                :class="material.is_out_of_stock ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300'"
                            >
                                {{ material.is_out_of_stock ? 'نفد' : `${formatQty(material.stock_quantity)} ${material.unit || ''}` }}
                            </span>
                        </div>
                        <div class="mt-2 h-1 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full transition-all duration-500" :style="coverageStyle(material)"></div>
                        </div>
                    </li>
                </ul>
                <EmptyState v-else icon="✅" title="المخزون في وضع آمن" hint="لا توجد أي مادة بلغت حدّها الأدنى حالياً" />
            </DashCard>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import TrendChart from '../../components/charts/TrendChart.vue';
import DashCard from '../../components/dashboard/DashCard.vue';
import DashboardSkeleton from '../../components/dashboard/DashboardSkeleton.vue';
import ProfitBreakdown from '../../components/dashboard/ProfitBreakdown.vue';
import RankedList from '../../components/dashboard/RankedList.vue';
import StatCard from '../../components/dashboard/StatCard.vue';
import WorkshopPipeline from '../../components/dashboard/WorkshopPipeline.vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { dashboardApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { useAuthStore } from '../../stores/auth';
import { useFeaturesStore } from '../../stores/features';
import { usePwaInstall } from '../../composables/usePwaInstall';
import { formatUsd, formatQty, isoDate } from '../../utils/format';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { can } = usePermissions();
const features = useFeaturesStore();
const pwa = usePwaInstall();

// Categorical slots 1 and 2 — validated for CVD separation on a light surface.
const trendSeries = [
    { key: 'sales', label: 'المبيعات', color: '#2a78d6' },
    { key: 'purchases', label: 'المشتريات', color: '#eb6834' },
];

const btnSecondary =
    'inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 text-sm font-semibold text-slate-700 dark:text-slate-200 shadow-2xs transition hover:border-slate-300 dark:hover:border-slate-600 hover:text-slate-900 dark:hover:text-white';
const btnIcon =
    'grid h-10 w-10 place-items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 shadow-2xs transition hover:border-slate-300 dark:hover:border-slate-600 hover:text-slate-900 dark:hover:text-white disabled:opacity-40';

const data = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const error = ref('');
const loadedAt = ref(null);
const rankingTab = ref('customers');
const recentFilter = ref('all');

const recentFilters = [
    { key: 'all', label: 'الكل' },
    { key: 'sales_invoice', label: 'مبيعات' },
    { key: 'purchase_invoice', label: 'مشتريات' },
    { key: 'payment_in', label: 'مقبوضات' },
    { key: 'payment_out', label: 'مدفوعات' },
    { key: 'expense', label: 'مصاريف' },
];

const defaults = () => {
    const today = new Date();
    return {
        from: isoDate(new Date(today.getFullYear(), today.getMonth(), 1)),
        to: isoDate(today),
    };
};

const filters = reactive(defaults());

/** The period behind the figures on screen, for the "unapplied" hint. */
const applied = ref('');

const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour >= 5 && hour < 12 ? 'صباح الخير' : 'مساء الخير';
});

const periodLabel = computed(() => {
    const period = data.value?.period;
    if (!period) return 'نظرة عامة على أداء الأعمال';
    const days = Number(period.days) || 0;
    return `${period.from} — ${period.to} · ${days} ${days === 1 ? 'يوم' : days === 2 ? 'يومان' : days <= 10 ? 'أيام' : 'يومًا'}`;
});

const periodDaysCount = computed(() => {
    if (!filters.from || !filters.to) return 0;
    const start = new Date(filters.from);
    const end = new Date(filters.to);
    const diff = end - start;
    if (diff < 0) return 0;
    return Math.round(diff / 864e5) + 1;
});

const todayLabel = new Intl.DateTimeFormat('ar-SY-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date());

const loadedAtLabel = computed(() =>
    loadedAt.value
        ? new Intl.DateTimeFormat('ar-SY-u-nu-latn', { timeStyle: 'short' }).format(loadedAt.value)
        : ''
);

const printedAt = computed(() =>
    new Intl.DateTimeFormat('ar-SY-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date())
);

/** The period the change badges measure against, named in their tooltip. */
const comparisonLabel = computed(() => {
    const previous = data.value?.comparison;
    return previous ? `${previous.from} — ${previous.to}` : '';
});

const presets = [
    { key: 'today', label: 'اليوم' },
    { key: 'yesterday', label: 'أمس' },
    { key: 'week', label: 'آخر ٧ أيام' },
    { key: 'month', label: 'هذا الشهر' },
    { key: 'last-month', label: 'الشهر الماضي' },
    { key: '30', label: 'آخر ٣٠ يومًا' },
    { key: 'quarter', label: 'هذا الربع' },
    { key: 'year', label: 'هذه السنة' },
];

const activePreset = computed(() =>
    presets.find((preset) => {
        const range = presetRange(preset.key);
        return range.from === filters.from && range.to === filters.to;
    })?.key ?? ''
);

const dirty = computed(() => snapshot(filters) !== applied.value);
const rangeInvalid = computed(() => Boolean(filters.from && filters.to && filters.from > filters.to));
const canReset = computed(() => snapshot(filters) !== snapshot(defaults()));

const quickActions = computed(() =>
    [
        { label: 'فاتورة بيع جديدة', icon: 'receipt', to: { name: 'sales.create' }, permission: 'sales.create', primary: true },
        { label: 'استلام جهاز صيانة', icon: 'wrench', tone: 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400', to: { name: 'repairs', query: { create: 1 } }, permission: 'repairs.create', features: ['repairs'], primary: false },
        { label: 'سند قبض نقدي', icon: 'arrow-down-left', tone: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400', to: { name: 'payments.create', query: { type: 'CUSTOMER' } }, permission: 'payments.create', primary: false },
        { label: 'فاتورة شراء', icon: 'cart', tone: 'bg-orange-50 dark:bg-orange-950/60 text-orange-700 dark:text-orange-400', to: { name: 'purchases.create' }, permission: 'purchases.create', primary: false },
        { label: 'تسجيل مصروف', icon: 'trending-down', tone: 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400', to: { name: 'expenses', query: { create: 1 } }, permission: 'expenses.create', primary: false },
        {
            label: 'صرف مستحقات خبير',
            icon: 'user-cog',
            tone: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400',
            to: { name: 'payments.create', query: { type: 'TECHNICIAN' } },
            permission: ['payments.create', 'technicians.view'],
            features: ['repairs', 'technicians'],
            primary: false,
        },
    ]
        // Switched-off modules leave the dashboard even for Master (can() lets Master through).
        .filter((action) => [].concat(action.permission).every(can))
        .filter((action) => (action.features ?? []).every((f) => features.isEnabled(f)))
);

const positionSplit = computed(() => {
    const receivables = Number(data.value?.kpis.receivables) || 0;
    const payables = Number(data.value?.kpis.payables) || 0;
    const total = receivables + payables;
    if (total <= 0) return { receivables: 0, payables: 0 };
    return {
        receivables: (receivables / total) * 100,
        payables: (payables / total) * 100,
    };
});

const tradeMargin = computed(
    () => (Number(data.value?.kpis.sales_period.amount) || 0) - (Number(data.value?.kpis.purchases_period.amount) || 0)
);

const kpiCards = computed(() => {
    if (!data.value) return [];

    const kpis = data.value.kpis;
    const previous = data.value.comparison?.kpis ?? null;

    return [
        {
            label: 'مبيعات اليوم',
            value: formatUsd(kpis.sales_today.amount),
            hint: `${kpis.sales_today.count} فاتورة معتمدة`,
            icon: 'receipt',
            iconClass: 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 ring-1 ring-blue-500/20',
            to: can('sales.view') ? { name: 'sales' } : null,
        },
        {
            label: 'مبيعات الفترة',
            value: formatUsd(kpis.sales_period.amount),
            hint: `${kpis.sales_period.count} فاتورة`,
            delta: delta(kpis.sales_period.amount, previous?.sales_period.amount),
            goodWhen: 'up',
            icon: 'trending-up',
            iconClass: 'bg-cyan-50 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-400 ring-1 ring-cyan-500/20',
            spark: salesSpark.value,
            sparkColor: trendSeries[0].color,
            to: can('sales.view') ? { name: 'sales' } : null,
        },
        {
            label: 'مشتريات الفترة',
            value: formatUsd(kpis.purchases_period.amount),
            hint: `${kpis.purchases_period.count} فاتورة`,
            delta: delta(kpis.purchases_period.amount, previous?.purchases_period.amount),
            icon: 'cart',
            iconClass: 'bg-orange-50 dark:bg-orange-950/60 text-orange-700 dark:text-orange-400 ring-1 ring-orange-500/20',
            spark: purchasesSpark.value,
            sparkColor: trendSeries[1].color,
            to: can('purchases.view') ? { name: 'purchases' } : null,
        },
        {
            label: 'المقبوضات في الفترة',
            value: formatUsd(kpis.collected_period),
            hint: 'تحصيلات من العملاء',
            delta: delta(kpis.collected_period, previous?.collected_period),
            goodWhen: 'up',
            icon: 'arrow-down-left',
            iconClass: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-500/20',
            to: can('payments.view') ? { name: 'payments', query: { type: 'CUSTOMER' } } : null,
        },
        {
            label: 'المدفوعات في الفترة',
            value: formatUsd(kpis.paid_period),
            hint: 'سداد دفعات الموردين',
            delta: delta(kpis.paid_period, previous?.paid_period),
            icon: 'arrow-up-right',
            iconClass: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 ring-1 ring-amber-500/20',
            to: can('payments.view') ? { name: 'payments', query: { type: 'SUPPLIER' } } : null,
        },
        {
            label: 'مصاريف الفترة',
            value: formatUsd(kpis.expenses_period.amount),
            hint: `${kpis.expenses_period.count} سند مصروف`,
            delta: delta(kpis.expenses_period.amount, previous?.expenses_period.amount),
            icon: 'trending-down',
            iconClass: 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 ring-1 ring-rose-500/20',
            to: can('expenses.view') ? { name: 'expenses' } : null,
        },
    ];
});

const salesSpark = computed(() => (data.value?.trend.points ?? []).map((p) => p.sales));
const purchasesSpark = computed(() => (data.value?.trend.points ?? []).map((p) => p.purchases));

const netCashFlow = computed(
    () =>
        data.value?.profit_overview?.net_cash_flow ??
        (Number(data.value?.kpis.collected_period) || 0) - (Number(data.value?.kpis.paid_period) || 0)
);

const activeAlerts = computed(() => {
    if (!data.value) return [];
    const alerts = data.value.alerts;

    return [
        {
            count: alerts.repairs_ready ?? 0,
            label: 'أجهزة جاهزة للتسليم للعميل',
            icon: 'check-circle',
            iconTone: 'text-emerald-600 dark:text-emerald-400',
            countTone: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-500/20',
            to: { name: 'repairs', query: { status: 'READY' } },
            permission: 'repairs.view',
        },
        {
            count: alerts.repairs_waiting_approval ?? 0,
            label: 'أجهزة بانتظار موافقة العميل',
            icon: 'clock',
            iconTone: 'text-amber-600 dark:text-amber-400',
            countTone: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 ring-1 ring-amber-500/20',
            to: { name: 'repairs', query: { status: 'WAITING_APPROVAL' } },
            permission: 'repairs.view',
        },
        {
            count: alerts.out_of_stock ?? 0,
            label: 'مواد نفدت من المخزون',
            icon: 'x-circle',
            iconTone: 'text-rose-600 dark:text-rose-400',
            countTone: 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 ring-1 ring-rose-500/20',
            to: { name: 'reports', query: { tab: 'low-stock' } },
            permission: 'reports.view',
        },
        {
            count: alerts.low_stock ?? 0,
            label: 'مواد قرب النفاد',
            icon: 'alert',
            iconTone: 'text-amber-600 dark:text-amber-400',
            countTone: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 ring-1 ring-amber-500/20',
            to: { name: 'reports', query: { tab: 'low-stock' } },
            permission: 'reports.view',
        },
        {
            count: alerts.draft_sales ?? 0,
            label: 'فواتير بيع غير معتمدة',
            icon: 'file',
            iconTone: 'text-slate-400 dark:text-slate-500',
            countTone: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700',
            to: { name: 'sales' },
            permission: 'sales.view',
        },
        {
            count: alerts.draft_purchases ?? 0,
            label: 'فواتير شراء غير معتمدة',
            icon: 'file',
            iconTone: 'text-slate-400 dark:text-slate-500',
            countTone: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700',
            to: { name: 'purchases' },
            permission: 'purchases.view',
        },
        {
            count: alerts.draft_payments ?? 0,
            label: 'دفعات غير معتمدة',
            icon: 'file',
            iconTone: 'text-slate-400 dark:text-slate-500',
            countTone: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700',
            to: { name: 'payments' },
            permission: 'payments.view',
        },
    ].filter((alert) => alert.count > 0 && can(alert.permission));
});

const topCustomers = computed(() => {
    const rows = data.value?.top_customers ?? [];
    const total = rows.reduce((sum, row) => sum + (Number(row.total) || 0), 0);

    return rows.map((row) => ({
        id: row.id,
        name: row.name,
        amount: Number(row.total) || 0,
        value: formatUsd(row.total),
        meta: `${row.invoices} فاتورة · ${share(row.total, total)} من مبيعات القائمة`,
        to: can('statements.view') ? { name: 'customers.statement', params: { id: row.id } } : null,
    }));
});

const topMaterials = computed(() => {
    const rows = data.value?.top_materials ?? [];
    const total = rows.reduce((sum, row) => sum + (Number(row.total) || 0), 0);

    return rows.map((row) => ({
        id: row.id,
        name: row.name,
        amount: Number(row.total) || 0,
        value: formatUsd(row.total),
        meta: `الكمية ${formatQty(row.quantity)} · ${share(row.total, total)} من مبيعات القائمة`,
    }));
});

const topBrands = computed(() => {
    const rows = data.value?.top_repair_brands ?? [];
    const total = rows.reduce((sum, row) => sum + (Number(row.count) || 0), 0);

    return rows.map((row) => ({
        id: row.name,
        name: row.name,
        amount: Number(row.count) || 0,
        value: `${formatQty(row.count)} جهاز`,
        meta: `${share(row.count, total)} من أجهزة الصيانة المسجلة`,
    }));
});

const topTechnicians = computed(() => {
    const rows = data.value?.top_technicians?.rows ?? [];

    return rows.map((row) => ({
        id: row.id,
        name: row.name,
        amount: Number(row.commission) || 0,
        value: formatUsd(row.commission),
        meta: `${row.repairs_count} جهاز مسوّى · إيراد ${formatUsd(row.revenue)}`,
        to: can('statements.view') ? { name: 'technicians.statement', params: { id: row.id } } : null,
    }));
});

const topServices = computed(() => {
    const rows = data.value?.top_services ?? [];
    const total = rows.reduce((sum, row) => sum + (Number(row.revenue) || 0), 0);

    return rows.map((row) => ({
        id: row.name,
        name: row.name,
        amount: Number(row.revenue) || 0,
        value: formatUsd(row.revenue),
        meta: `${row.count} مرة · ${share(row.revenue, total)} من إيراد الخدمات في القائمة`,
    }));
});

/** Every shortlist the user may see, one tab each in the rankings card. */
const rankingTabs = computed(() => {
    if (!data.value) return [];

    return [
        { key: 'customers', label: 'العملاء', rows: topCustomers.value, color: '#2a78d6', emptyIcon: '👥', emptyTitle: 'لا توجد مبيعات مسجلة', to: can('customers.view') ? { name: 'customers' } : null, linkLabel: 'دليل العملاء' },
        { key: 'materials', label: 'المواد', rows: topMaterials.value, color: '#8b5cf6', emptyIcon: '📦', emptyTitle: 'لا توجد مواد مباعة', to: can('inventory.view') ? { name: 'inventory' } : null, linkLabel: 'المخزون' },
        data.value.top_services && { key: 'services', label: 'الخدمات', rows: topServices.value, color: '#0ea5e9', emptyIcon: '🛠️', emptyTitle: 'لا توجد خدمات مباعة', to: { name: 'services' }, linkLabel: 'دليل الخدمات' },
        topBrands.value.length && { key: 'brands', label: 'الأجهزة', rows: topBrands.value, color: '#6366f1', emptyIcon: '🔧', emptyTitle: 'لا توجد أجهزة مستلمة', to: can('repairs.view') ? { name: 'repairs' } : null, linkLabel: 'الصيانة' },
        data.value.top_technicians && { key: 'technicians', label: 'الخبراء', rows: topTechnicians.value, color: '#14b8a6', emptyIcon: '🧰', emptyTitle: 'لا توجد أجهزة مسوّاة لخبراء', to: { name: 'technicians' }, linkLabel: 'الخبراء' },
    ].filter(Boolean);
});

// Falls back to the first tab when a refresh drops the one that was open.
const currentRanking = computed(() => rankingTabs.value.find((tab) => tab.key === rankingTab.value) ?? rankingTabs.value[0]);

const filteredRecentDocuments = computed(() => {
    const list = data.value?.recent_documents ?? [];
    if (recentFilter.value === 'all') return list;
    if (recentFilter.value === 'payment_in') return list.filter((d) => d.kind === 'payment' && d.direction === 'in');
    if (recentFilter.value === 'payment_out') return list.filter((d) => d.kind === 'payment' && d.direction === 'out');
    return list.filter((d) => d.kind === recentFilter.value);
});

const DOCUMENT_ROUTES = {
    sales_invoice: { name: 'sales.edit', permission: 'sales.view' },
    purchase_invoice: { name: 'purchases.edit', permission: 'purchases.view' },
    payment: { name: 'payments.edit', permission: 'payments.create' },
};

const DOCUMENT_ICONS = {
    sales_invoice: 'receipt',
    purchase_invoice: 'cart',
    expense: 'trending-down',
};

function documentRoute(doc) {
    const target = DOCUMENT_ROUTES[doc.kind];
    if (!target || !can(target.permission)) return null;
    return { name: target.name, params: { id: doc.id } };
}

function documentIcon(doc) {
    if (doc.kind === 'payment') return doc.direction === 'in' ? 'arrow-down-left' : 'arrow-up-right';
    return DOCUMENT_ICONS[doc.kind] ?? 'file';
}

function share(value, total) {
    if (!total) return '0٪';
    return `${Math.round(((Number(value) || 0) / total) * 100)}٪`;
}

/** Ratio of change, or null when the previous period has nothing to divide by. */
function delta(current, previous) {
    const before = Number(previous);
    if (!Number.isFinite(before) || before <= 0) return null;
    return ((Number(current) || 0) - before) / before;
}

function coverageStyle(material) {
    const min = Number(material.min_stock) || 0;
    const stock = Number(material.stock_quantity) || 0;
    const ratio = min <= 0 ? (stock > 0 ? 1 : 0) : Math.max(0, Math.min(1, stock / min));

    return {
        // A zero-width bar is invisible; a hairline still says "nothing left".
        width: `${Math.max(2, ratio * 100)}%`,
        background: ratio <= 0 ? '#dc2626' : ratio < 0.5 ? '#f59e0b' : '#0ea5e9',
    };
}

// ------------------------------------------------------------------- period

function snapshot(values) {
    return `${values.from}|${values.to}`;
}

function presetRange(key) {
    const today = new Date();

    switch (key) {
        case 'today':
            return { from: isoDate(today), to: isoDate(today) };
        case 'yesterday': {
            const yesterday = new Date(Date.now() - 864e5);
            return { from: isoDate(yesterday), to: isoDate(yesterday) };
        }
        case 'week':
            return { from: isoDate(new Date(Date.now() - 6 * 864e5)), to: isoDate(today) };
        case 'month':
            return { from: isoDate(new Date(today.getFullYear(), today.getMonth(), 1)), to: isoDate(today) };
        case 'last-month':
            return {
                from: isoDate(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
                to: isoDate(new Date(today.getFullYear(), today.getMonth(), 0)),
            };
        case '30':
            return { from: isoDate(new Date(Date.now() - 29 * 864e5)), to: isoDate(today) };
        case 'quarter': {
            const currentQuarter = Math.floor(today.getMonth() / 3);
            const startQuarter = new Date(today.getFullYear(), currentQuarter * 3, 1);
            return { from: isoDate(startQuarter), to: isoDate(today) };
        }
        case 'year':
            return { from: isoDate(new Date(today.getFullYear(), 0, 1)), to: isoDate(today) };
        default:
            return { from: filters.from, to: filters.to };
    }
}

function shiftPeriod(direction) {
    if (!filters.from || !filters.to) return;
    const start = new Date(filters.from);
    const end = new Date(filters.to);
    const diffTime = Math.abs(end - start);
    const diffDays = Math.max(1, Math.round(diffTime / 864e5) + 1);

    const shiftMs = diffDays * 864e5 * direction;
    const newStart = new Date(start.getTime() + shiftMs);
    const newEnd = new Date(end.getTime() + shiftMs);

    filters.from = isoDate(newStart);
    filters.to = isoDate(newEnd);
    applyFilters();
}

function applyPreset(preset) {
    Object.assign(filters, presetRange(preset.key));
    applyFilters();
}

function applyFilters() {
    if (rangeInvalid.value) return;
    load();
}

function resetFilters() {
    Object.assign(filters, defaults());
    load();
}

function refresh() {
    load();
}

function printSheet() {
    window.print();
}

// ----------------------------------------------------------------- url state

function currentQuery() {
    return { from: filters.from, to: filters.to };
}

function readQuery(query) {
    Object.assign(filters, defaults());
    if (query.from) filters.from = String(query.from);
    if (query.to) filters.to = String(query.to);
}

function syncQuery() {
    const query = currentQuery();
    if (JSON.stringify(query) === JSON.stringify(route.query)) return;
    // replace, not push: changing the period should not bury the previous screen.
    router.replace({ query }).catch(() => {});
}

// -------------------------------------------------------------------- loading

/** Only the latest request may write to the screen; a slow earlier one is dropped. */
let requestSeq = 0;

async function load() {
    const seq = ++requestSeq;
    if (data.value) refreshing.value = true;
    else loading.value = true;

    error.value = '';

    try {
        // compare: the previous period's window figures ride along in the same
        // response, so the change badges cost one request instead of two.
        const response = await dashboardApi.index({ from: filters.from, to: filters.to, compare: 1 });
        if (seq !== requestSeq) return;
        data.value = response;
        applied.value = snapshot(filters);
        loadedAt.value = new Date();
        syncQuery();
    } catch (e) {
        if (seq !== requestSeq) return;
        error.value = e?.response?.data?.message || 'تعذّر تحميل لوحة التحكم';
    } finally {
        if (seq === requestSeq) {
            loading.value = false;
            refreshing.value = false;
        }
    }
}

// A tab left open over lunch should not greet the user with stale figures.
const STALE_AFTER_MS = 5 * 60 * 1000;

function onVisibilityChange() {
    if (document.visibilityState !== 'visible' || loading.value || refreshing.value) return;
    if (loadedAt.value && Date.now() - loadedAt.value.getTime() > STALE_AFTER_MS && !dirty.value) load();
}

// Browser back/forward replays a period exactly as it was shared or bookmarked.
watch(
    () => route.query,
    (query) => {
        if (JSON.stringify(query) === JSON.stringify(currentQuery())) return;
        readQuery(query);
        load();
    }
);

onMounted(() => {
    readQuery(route.query);
    load();
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisibilityChange));
</script>

<style>
@media print {
    @page {
        margin: 10mm;
    }

    body {
        background: #fff;
    }

    #dashboard-sheet .dash-card {
        break-inside: avoid;
        box-shadow: none !important;
        border-color: #cbd5e1 !important;
    }

    #dashboard-sheet .hero-glow {
        display: none;
    }
}
</style>
