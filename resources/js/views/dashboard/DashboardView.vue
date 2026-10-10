<template>
    <div id="dashboard-sheet" class="space-y-6">
        <!-- Top Executive Bar -->
        <div class="flex flex-wrap items-center justify-between gap-4 print:hidden">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                        {{ greeting }}، {{ auth.user?.name }}
                    </h2>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" aria-hidden="true"></span>
                        بيانات حية
                    </span>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                    <span>{{ periodLabel }}</span>
                    <span v-if="loadedAt" class="text-xs text-slate-400">· آخر تحديث {{ loadedAtLabel }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="!pwa.installed.value"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-blue-200 bg-blue-50 px-3.5 py-2 text-sm font-semibold text-blue-700 shadow-xs transition hover:bg-blue-100"
                    title="تثبيت النظام كتطبيق على هذا الجهاز"
                    @click="pwa.installOrExplain"
                >
                    <span aria-hidden="true">📲</span>
                    <span>تثبيت التطبيق</span>
                </button>

                <button
                    type="button"
                    :disabled="loading || refreshing"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 hover:text-blue-600 disabled:opacity-40"
                    title="تحديث بيانات الشاشة"
                    @click="refresh"
                >
                    <span :class="refreshing ? 'animate-spin' : ''" aria-hidden="true">⟳</span>
                    <span>{{ refreshing ? 'جارٍ التحديث...' : 'تحديث' }}</span>
                </button>

                <button
                    type="button"
                    :disabled="!data"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-40"
                    title="طباعة لوحة التحكم"
                    @click="printSheet"
                >
                    <span aria-hidden="true">🖨</span>
                    <span>طباعة</span>
                </button>

                <RouterLink
                    v-if="can('reports.view')"
                    :to="{ name: 'reports', query: { tab: 'profit', from: filters.from, to: filters.to } }"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-blue-700 hover:shadow-md"
                >
                    <span aria-hidden="true">📊</span>
                    <span>التقرير المالي المفصل</span>
                    <span aria-hidden="true" class="text-blue-200">↗</span>
                </RouterLink>
            </div>
        </div>

        <!-- Period Control & Navigation Bar -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-xs print:hidden">
            <!-- Row 1: Presets & Shift navigation -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3.5">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="me-1 text-xs font-bold text-slate-400">الفترة:</span>
                    <button
                        v-for="preset in presets"
                        :key="preset.key"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-semibold transition"
                        :class="activePreset === preset.key
                            ? 'bg-blue-600 text-white shadow-xs ring-2 ring-blue-300'
                            : 'border border-slate-200 bg-slate-50/70 text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        @click="applyPreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <!-- Shift period navigation steppers -->
                <div class="flex items-center gap-1.5 text-xs font-medium text-slate-600">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs transition hover:bg-slate-50 hover:text-blue-600 disabled:opacity-40"
                        :disabled="loading || refreshing"
                        title="الانتقال إلى الفترة السابقة بنفس المدة"
                        @click="shiftPeriod(-1)"
                    >
                        <span>◀ الفترة السابقة</span>
                    </button>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 font-bold tabular-nums text-slate-700">
                        {{ periodDaysCount }} {{ periodDaysCount === 1 ? 'يوم' : periodDaysCount === 2 ? 'يومان' : 'يومًا' }}
                    </span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs transition hover:bg-slate-50 hover:text-blue-600 disabled:opacity-40"
                        :disabled="loading || refreshing"
                        title="الانتقال إلى الفترة التالية بنفس المدة"
                        @click="shiftPeriod(1)"
                    >
                        <span>الفترة التالية ▶</span>
                    </button>
                </div>
            </div>

            <!-- Row 2: Custom Date Pickers & Action Buttons -->
            <div class="mt-3.5 flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="text-xs font-bold text-slate-600">
                        من تاريخ
                        <input
                            v-model="filters.from"
                            type="date"
                            class="mt-1 block rounded-xl border border-slate-300 bg-slate-50/50 px-3 py-2 text-sm font-medium text-slate-900 shadow-2xs transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100"
                            @keyup.enter="applyFilters"
                        />
                    </label>

                    <label class="text-xs font-bold text-slate-600">
                        إلى تاريخ
                        <input
                            v-model="filters.to"
                            type="date"
                            :min="filters.from || undefined"
                            class="mt-1 block rounded-xl border border-slate-300 bg-slate-50/50 px-3 py-2 text-sm font-medium text-slate-900 shadow-2xs transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100"
                            @keyup.enter="applyFilters"
                        />
                    </label>

                    <div class="flex items-center gap-2 pb-0.5">
                        <button
                            type="button"
                            :disabled="rangeInvalid"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
                            :class="dirty && !rangeInvalid ? 'ring-3 ring-blue-300 animate-pulse' : ''"
                            @click="applyFilters"
                        >
                            <span>تطبيق الفلترة</span>
                        </button>

                        <button
                            v-if="canReset"
                            type="button"
                            class="rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-600 shadow-xs transition hover:bg-slate-50 hover:text-slate-900"
                            @click="resetFilters"
                        >
                            إعادة تعيين
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span v-if="rangeInvalid" class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-600" role="alert">
                        ⚠️ تاريخ البداية بعد تاريخ النهاية
                    </span>
                    <span v-else-if="dirty" class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                        ⚡ هناك تعديلات في التواريخ بانتظار التطبيق
                    </span>
                </div>
            </div>
        </div>

        <!-- Quick Action Cards (Daily Workflow) -->
        <div v-if="quickActions.length" class="flex flex-wrap items-center gap-2.5 print:hidden">
            <RouterLink
                v-for="(action, index) in quickActions"
                :key="action.label"
                :to="action.to"
                class="group inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold transition shadow-xs"
                :class="action.primary
                    ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:bg-slate-50 hover:text-blue-600'"
            >
                <span class="text-sm transition group-hover:scale-110" aria-hidden="true">{{ action.icon }}</span>
                <span>{{ action.label }}</span>
            </RouterLink>
        </div>

        <!-- Print-only Header Sheet -->
        <div class="hidden border-b border-slate-200 pb-3 print:block">
            <h2 class="text-xl font-bold">لوحة التحكم التنفيذية — {{ auth.user?.name }}</h2>
            <p class="mt-0.5 text-xs text-slate-600">{{ periodLabel }}</p>
            <p class="mt-0.5 text-[11px] text-slate-500">طُبع في {{ printedAt }}</p>
        </div>

        <!-- Error Banner -->
        <div
            v-if="error"
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700 shadow-xs"
        >
            <div class="flex items-center gap-2">
                <span class="text-lg">⚠️</span>
                <span>{{ error }}</span>
            </div>
            <button
                type="button"
                class="rounded-lg border border-red-300 bg-white px-3.5 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-100 print:hidden"
                @click="load"
            >
                إعادة المحاولة
            </button>
        </div>

        <!-- Skeleton Loading -->
        <DashboardSkeleton v-if="loading && !data" />

        <!-- Main Dashboard Content -->
        <div
            v-else-if="data"
            class="space-y-6 transition-opacity"
            :class="refreshing ? 'opacity-50 pointer-events-none' : ''"
            :aria-busy="refreshing"
        >
            <!-- Operational Alerts Bar -->
            <div v-if="activeAlerts.length" class="flex flex-wrap items-center gap-2.5">
                <RouterLink
                    v-for="alert in activeAlerts"
                    :key="alert.label"
                    :to="alert.to"
                    class="group inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-bold transition shadow-xs"
                    :class="alert.tone"
                >
                    <span class="text-base transition group-hover:scale-110" aria-hidden="true">{{ alert.icon }}</span>
                    <span>{{ alert.label }}</span>
                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-xs font-black tabular-nums shadow-2xs ring-1 ring-black/5">
                        {{ alert.count }}
                    </span>
                </RouterLink>
            </div>

            <!-- Executive Cockpit (Dual Hero Cards) -->
            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Hero Card 1: صافي المركز المالي التراكمي -->
                <div class="dashboard-hero relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 p-6 text-white shadow-xl shadow-slate-950/20 sm:p-7">
                    <div class="hero-blob pointer-events-none absolute -end-16 -top-20 h-52 w-52 rounded-full bg-blue-500/20 blur-3xl" aria-hidden="true" />
                    <div class="hero-blob pointer-events-none absolute -start-12 -bottom-24 h-52 w-52 rounded-full bg-indigo-500/20 blur-3xl" aria-hidden="true" />

                    <div class="relative flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-2xl ring-1 ring-white/15" aria-hidden="true">
                                ⚖️
                            </span>
                            <div>
                                <div class="text-xs font-semibold tracking-wide text-blue-200/80">صافي المركز المالي العام</div>
                                <div class="text-[11px] text-slate-400">إجمالي الأرصدة التراكمية (الذمم)</div>
                            </div>
                        </div>
                        <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-slate-300">
                            الوضع الراهن
                        </span>
                    </div>

                    <div class="relative mt-4">
                        <div
                            class="break-words text-3xl font-black tabular-nums sm:text-4xl lg:text-3xl xl:text-4xl"
                            :class="Number(data.kpis.net_position) < 0 ? 'text-red-300' : 'text-emerald-300'"
                        >
                            {{ formatUsd(data.kpis.net_position) }}
                        </div>
                    </div>

                    <!-- Split Progress Bar -->
                    <div class="relative mt-5">
                        <div class="position-track flex h-2.5 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                            <div class="h-full bg-emerald-400 transition-all duration-500" :style="{ width: `${positionSplit.receivables}%` }"></div>
                            <div class="h-full bg-amber-400 transition-all duration-500" :style="{ width: `${positionSplit.payables}%` }"></div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs">
                            <component
                                :is="can('reports.view') ? RouterLink : 'span'"
                                :to="can('reports.view') ? { name: 'reports', query: { tab: 'receivables' } } : undefined"
                                class="flex items-center gap-2 text-slate-300"
                                :class="can('reports.view') ? 'transition hover:text-white hover:underline' : ''"
                            >
                                <span class="h-2 w-2 rounded-full bg-emerald-400" aria-hidden="true"></span>
                                <span>مستحق لنا (عملاء):</span>
                                <b class="tabular-nums font-bold text-white">{{ formatUsd(data.kpis.receivables) }}</b>
                            </component>

                            <component
                                :is="can('reports.view') ? RouterLink : 'span'"
                                :to="can('reports.view') ? { name: 'reports', query: { tab: 'payables' } } : undefined"
                                class="flex items-center gap-2 text-slate-300"
                                :class="can('reports.view') ? 'transition hover:text-white hover:underline' : ''"
                            >
                                <span class="h-2 w-2 rounded-full bg-amber-400" aria-hidden="true"></span>
                                <span>مستحق علينا (موردون):</span>
                                <b class="tabular-nums font-bold text-white">{{ formatUsd(data.kpis.payables) }}</b>
                            </component>
                        </div>
                    </div>
                </div>

                <!-- Hero Card 2: نبض الأرباح والسيولة للفترة المحددة -->
                <div class="dashboard-hero relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-950 via-indigo-950 to-slate-900 p-6 text-white shadow-xl shadow-indigo-950/20 sm:p-7">
                    <div class="hero-blob pointer-events-none absolute -end-16 -top-20 h-52 w-52 rounded-full bg-cyan-400/20 blur-3xl" aria-hidden="true" />
                    <div class="hero-blob pointer-events-none absolute -start-12 -bottom-24 h-52 w-52 rounded-full bg-blue-400/20 blur-3xl" aria-hidden="true" />

                    <div class="relative flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-2xl ring-1 ring-white/15" aria-hidden="true">
                                💰
                            </span>
                            <div>
                                <div class="text-xs font-semibold tracking-wide text-cyan-200/80">أداء الفترة المحددة</div>
                                <div class="text-[11px] text-slate-400">{{ data.period.from }} — {{ data.period.to }} ({{ data.period.days }} يومًا)</div>
                            </div>
                        </div>

                        <span
                            v-if="data.profit_overview"
                            class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                            :class="data.profit_overview.is_loss ? 'bg-red-500/20 text-red-300' : 'bg-emerald-500/20 text-emerald-300'"
                        >
                            {{ data.profit_overview.is_loss ? 'صافي خسارة' : 'صافي ربح' }}
                            {{ data.profit_overview.net_margin }}٪
                        </span>
                    </div>

                    <div class="relative mt-4 flex flex-wrap items-baseline justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs text-slate-300">صافي الربح التقديري للفترة</div>
                            <div
                                class="mt-0.5 break-words text-3xl font-black tabular-nums sm:text-4xl lg:text-3xl xl:text-4xl"
                                :class="data.profit_overview?.is_loss ? 'text-red-300' : 'text-emerald-300'"
                            >
                                {{ formatUsd(data.profit_overview?.net_profit ?? tradeMargin) }}
                            </div>
                        </div>

                        <div class="text-start sm:text-end">
                            <div class="text-xs text-slate-300">صافي التدفق النقدي</div>
                            <div
                                class="mt-0.5 text-xl font-bold tabular-nums"
                                :class="Number(data.profit_overview?.net_cash_flow ?? 0) < 0 ? 'text-amber-300' : 'text-cyan-300'"
                            >
                                {{ formatUsd(data.profit_overview?.net_cash_flow ?? ((Number(data.kpis.collected_period) || 0) - (Number(data.kpis.paid_period) || 0))) }}
                            </div>
                        </div>
                    </div>

                    <!-- Mini Key Figures Grid -->
                    <div class="relative mt-5 grid grid-cols-2 gap-3 border-t border-white/10 pt-3 text-xs sm:grid-cols-3">
                        <div>
                            <span class="block text-slate-400">إجمالي المبيعات</span>
                            <span class="font-bold tabular-nums text-white">{{ formatUsd(data.kpis.sales_period.amount) }}</span>
                        </div>
                        <div>
                            <span class="block text-slate-400">إجمالي المشتريات</span>
                            <span class="font-bold tabular-nums text-white">{{ formatUsd(data.kpis.purchases_period.amount) }}</span>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <span class="block text-slate-400">مصاريف التشغيل</span>
                            <span class="font-bold tabular-nums text-rose-300">{{ formatUsd(data.kpis.expenses_period.amount) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI Category Filter Pills -->
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-400">عرض المؤشرات:</span>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1 text-xs font-bold transition"
                        :class="kpiCategory === 'all'
                            ? 'bg-slate-900 text-white'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100'"
                        @click="kpiCategory = 'all'"
                    >
                        الكل ({{ allKpiCount }})
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1 text-xs font-bold transition"
                        :class="kpiCategory === 'finance'
                            ? 'bg-blue-600 text-white'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100'"
                        @click="kpiCategory = 'finance'"
                    >
                        💰 الحركة المالية والتجارية ({{ kpiCards.length }})
                    </button>
                    <button
                        v-if="workshopKpis.length"
                        type="button"
                        class="rounded-lg px-3 py-1 text-xs font-bold transition"
                        :class="kpiCategory === 'workshop'
                            ? 'bg-indigo-600 text-white'
                            : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-100'"
                        @click="kpiCategory = 'workshop'"
                    >
                        🔧 ورشة الصيانة والأجهزة ({{ workshopKpis.length }})
                    </button>
                </div>
            </div>

            <!-- Financial KPIs Row -->
            <div v-if="kpiCategory === 'all' || kpiCategory === 'finance'" class="space-y-3">
                <div v-if="kpiCategory === 'all'" class="flex items-center gap-2 text-xs font-bold text-slate-500">
                    <span>💰 الحركة المالية والتجارية في الفترة</span>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard
                        v-for="card in kpiCards"
                        :key="card.label"
                        :label="card.label"
                        :value="card.value"
                        :hint="card.hint"
                        :icon="card.icon"
                        :icon-class="card.iconClass"
                        :tone="card.tone"
                        :to="card.to"
                        :delta="card.delta"
                        :good-when="card.goodWhen"
                        :previous-label="comparisonLabel"
                    />
                </div>
            </div>

            <!-- Workshop KPIs Row -->
            <div v-if="(kpiCategory === 'all' || kpiCategory === 'workshop') && workshopKpis.length" class="space-y-3">
                <div v-if="kpiCategory === 'all'" class="flex items-center gap-2 text-xs font-bold text-slate-500">
                    <span>🔧 ورشة الصيانة والأجهزة</span>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        v-for="card in workshopKpis"
                        :key="card.label"
                        :label="card.label"
                        :value="card.value"
                        :hint="card.hint"
                        :icon="card.icon"
                        :icon-class="card.iconClass"
                        :tone="card.tone"
                        :to="card.to"
                    />
                </div>
            </div>

            <!-- Trend Section: Sales vs Purchases -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="font-bold text-slate-900 sm:text-base">حركة المبيعات والمشتريات خلال الفترة</h3>
                        <p class="mt-0.5 text-xs text-slate-500">تتبع يومي/شهري لتطور الإيرادات والمشتريات</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 text-xs">
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">
                            {{ data.trend.granularity === 'day' ? 'تدرّج يومي' : 'تدرّج شهري' }}
                        </span>
                        <span class="text-slate-400" dir="ltr">{{ data.period.from }} — {{ data.period.to }}</span>
                    </div>
                </div>

                <!-- Trend Summary Pills -->
                <div class="mb-4 flex flex-wrap items-center gap-3 text-xs">
                    <span class="flex items-center gap-1.5 rounded-lg bg-blue-50 px-3 py-1.5 font-medium text-blue-800">
                        <span class="h-2 w-2 rounded-full bg-blue-600" aria-hidden="true"></span>
                        المبيعات: <b class="tabular-nums font-bold">{{ formatUsd(data.kpis.sales_period.amount) }}</b>
                    </span>
                    <span class="flex items-center gap-1.5 rounded-lg bg-orange-50 px-3 py-1.5 font-medium text-orange-800">
                        <span class="h-2 w-2 rounded-full bg-orange-600" aria-hidden="true"></span>
                        المشتريات: <b class="tabular-nums font-bold">{{ formatUsd(data.kpis.purchases_period.amount) }}</b>
                    </span>
                    <span
                        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium"
                        :class="tradeMargin < 0 ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-800'"
                    >
                        الفارق التجاري: <b class="tabular-nums font-bold">{{ formatUsd(tradeMargin) }}</b>
                    </span>
                </div>

                <TrendChart :points="data.trend.points" :series="trendSeries" :format-value="formatUsd" />
            </div>

            <!-- Rankings Section: Top Customers, Top Materials, and the module sections the user may see -->
            <div class="grid gap-5" :class="rankingGridClass">
                <!-- Top customers -->
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                    <div class="mb-3.5 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">👥</span>
                            <h3 class="font-bold text-slate-900">أعلى العملاء في الفترة</h3>
                        </div>
                        <RouterLink
                            v-if="can('customers.view')"
                            :to="{ name: 'customers' }"
                            class="text-xs font-semibold text-blue-600 hover:underline"
                        >
                            دليل العملاء ↗
                        </RouterLink>
                    </div>
                    <RankedList
                        :rows="topCustomers"
                        empty-icon="👥"
                        empty-title="لا توجد مبيعات مسجلة"
                        empty-hint="جرّب توسيع نطاق الفترة"
                    />
                </div>

                <!-- Top materials -->
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                    <div class="mb-3.5 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📦</span>
                            <h3 class="font-bold text-slate-900">أعلى المواد مبيعًا</h3>
                        </div>
                        <RouterLink
                            v-if="can('inventory.view')"
                            :to="{ name: 'inventory' }"
                            class="text-xs font-semibold text-purple-600 hover:underline"
                        >
                            المخزون ↗
                        </RouterLink>
                    </div>
                    <RankedList
                        :rows="topMaterials"
                        color="#7c3aed"
                        empty-icon="📦"
                        empty-title="لا توجد مواد مباعة"
                        empty-hint="جرّب توسيع نطاق الفترة"
                    />
                </div>

                <!-- Top Workshop Brands (If available) -->
                <div v-if="topBrands.length" class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                    <div class="mb-3.5 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🔧</span>
                            <h3 class="font-bold text-slate-900">أكثر أجهزة الصيانة بالورشة</h3>
                        </div>
                        <RouterLink
                            v-if="can('repairs.view')"
                            :to="{ name: 'repairs' }"
                            class="text-xs font-semibold text-indigo-600 hover:underline"
                        >
                            الصيانة ↗
                        </RouterLink>
                    </div>
                    <RankedList
                        :rows="topBrands"
                        color="#4f46e5"
                        empty-icon="🔧"
                        empty-title="لا توجد أجهزة مستلمة"
                        empty-hint="جرّب توسيع نطاق الفترة"
                    />
                </div>

                <!-- Top technicians (technicians.view) -->
                <div v-if="data.top_technicians" class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                    <div class="mb-3.5 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🧰</span>
                            <h3 class="font-bold text-slate-900">عمولات خبراء الصيانة</h3>
                        </div>
                        <RouterLink
                            :to="{ name: 'technicians' }"
                            class="text-xs font-semibold text-teal-600 hover:underline"
                        >
                            الخبراء ↗
                        </RouterLink>
                    </div>
                    <RankedList
                        :rows="topTechnicians"
                        color="#0d9488"
                        empty-icon="🧰"
                        empty-title="لا توجد أجهزة مسوّاة لخبراء"
                        empty-hint="جرّب توسيع نطاق الفترة"
                    />
                    <p class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500">
                        <span>مستحقات قائمة للخبراء النشطين</span>
                        <b class="tabular-nums text-slate-800">{{ formatUsd(data.top_technicians.balance_owed) }}</b>
                    </p>
                </div>

                <!-- Top services (services.view) -->
                <div v-if="data.top_services" class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                    <div class="mb-3.5 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🛠️</span>
                            <h3 class="font-bold text-slate-900">أكثر الخدمات مبيعًا</h3>
                        </div>
                        <RouterLink
                            :to="{ name: 'services' }"
                            class="text-xs font-semibold text-sky-600 hover:underline"
                        >
                            دليل الخدمات ↗
                        </RouterLink>
                    </div>
                    <RankedList
                        :rows="topServices"
                        color="#0284c7"
                        empty-icon="🛠️"
                        empty-title="لا توجد خدمات مباعة"
                        empty-hint="جرّب توسيع نطاق الفترة"
                    />
                </div>
            </div>

            <!-- Bottom Row: Recent activity & Low stock watchlist -->
            <div class="grid gap-5 lg:grid-cols-2">
                <!-- Recent activity stream with tabs -->
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                    <div class="mb-3.5 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">⚡</span>
                            <h3 class="font-bold text-slate-900">آخر الحركات المعتمدة</h3>
                        </div>

                        <!-- Type filters -->
                        <div class="flex flex-wrap items-center gap-1">
                            <button
                                v-for="rf in recentFilters"
                                :key="rf.key"
                                type="button"
                                class="rounded-md px-2 py-0.5 text-[11px] font-semibold transition"
                                :class="recentFilter === rf.key
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                @click="recentFilter = rf.key"
                            >
                                {{ rf.label }}
                            </button>
                        </div>
                    </div>

                    <ul v-if="filteredRecentDocuments.length" class="divide-y divide-slate-100">
                        <li
                            v-for="doc in filteredRecentDocuments"
                            :key="`${doc.kind}-${doc.id}`"
                            class="group flex items-center justify-between gap-3 py-2.5 transition hover:bg-slate-50/50"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-base shadow-2xs"
                                    :class="doc.direction === 'in' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700'"
                                    aria-hidden="true"
                                >
                                    {{ documentIcon(doc) }}
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <component
                                            :is="documentRoute(doc) ? RouterLink : 'span'"
                                            :to="documentRoute(doc) || undefined"
                                            class="text-sm font-semibold text-slate-900 transition"
                                            :class="documentRoute(doc) ? 'group-hover:text-blue-600 group-hover:underline' : ''"
                                        >
                                            {{ doc.kind_label }}
                                        </component>
                                        <span v-if="doc.reference_no" class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-500" dir="ltr">
                                            {{ doc.reference_no }}
                                        </span>
                                    </div>
                                    <div class="truncate text-xs text-slate-500">
                                        {{ doc.party || '—' }} · <span dir="ltr">{{ doc.date }}</span>
                                    </div>
                                </div>
                            </div>

                            <span
                                class="shrink-0 text-sm font-black tabular-nums"
                                :class="doc.direction === 'in' ? 'text-emerald-700' : 'text-slate-800'"
                            >
                                {{ doc.direction === 'in' ? '+' : '−' }}{{ formatUsd(doc.amount) }}
                            </span>
                        </li>
                    </ul>
                    <EmptyState v-else icon="🗂" title="لا توجد حركات مطابقة" hint="ستظهر هنا العمليات المعتمدة فور تسجيلها" />
                </div>

                <!-- Low stock watchlist -->
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs">
                    <div class="mb-3.5 flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">⚠️</span>
                            <h3 class="font-bold text-slate-900">مواد قرب النفاد والمخزون الحرج</h3>
                        </div>
                        <RouterLink
                            v-if="can('reports.view')"
                            :to="{ name: 'reports', query: { tab: 'low-stock' } }"
                            class="text-xs font-semibold text-amber-700 hover:underline"
                        >
                            تقرير النواقص ↗
                        </RouterLink>
                    </div>

                    <ul v-if="data.low_stock.length" class="divide-y divide-slate-100">
                        <li v-for="material in data.low_stock" :key="material.id" class="group py-2.5 transition hover:bg-slate-50/50">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <component
                                        :is="can('reports.view') ? RouterLink : 'span'"
                                        :to="can('reports.view')
                                            ? { name: 'reports', query: { tab: 'low-stock', search: material.name } }
                                            : undefined"
                                        class="block truncate text-sm font-semibold text-slate-900"
                                        :class="can('reports.view') ? 'group-hover:text-blue-600 group-hover:underline' : ''"
                                    >
                                        {{ material.name }}
                                    </component>
                                    <div class="text-xs text-slate-400">الحد الأدنى الآمن: {{ formatQty(material.min_stock) }} {{ material.unit || '' }}</div>
                                </div>
                                <span
                                    class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-bold tabular-nums shadow-2xs"
                                    :class="material.is_out_of_stock ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200'"
                                >
                                    <span aria-hidden="true">{{ material.is_out_of_stock ? '⛔ نفد' : '⚠ متوفر' }}</span>
                                    {{ formatQty(material.stock_quantity) }} {{ material.unit || '' }}
                                </span>
                            </div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full transition-all duration-500" :style="coverageStyle(material)"></div>
                            </div>
                        </li>
                    </ul>
                    <EmptyState v-else icon="✅" title="المخزون في وضع آمن" hint="لا توجد أي مادة بلغت حدّها الأدنى حالياً" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import TrendChart from '../../components/charts/TrendChart.vue';
import DashboardSkeleton from '../../components/dashboard/DashboardSkeleton.vue';
import RankedList from '../../components/dashboard/RankedList.vue';
import StatCard from '../../components/dashboard/StatCard.vue';
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

const data = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const error = ref('');
const loadedAt = ref(null);
const kpiCategory = ref('all');
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
        { label: 'فاتورة بيع جديدة', icon: '🧾', to: { name: 'sales.create' }, permission: 'sales.create', primary: true },
        { label: 'استلام جهاز صيانة', icon: '🔧', to: { name: 'repairs', query: { create: 1 } }, permission: 'repairs.create', features: ['repairs'], primary: false },
        { label: 'سند قبض نقدي', icon: '💵', to: { name: 'payments.create', query: { type: 'CUSTOMER' } }, permission: 'payments.create', primary: false },
        { label: 'فاتورة شراء', icon: '📥', to: { name: 'purchases.create' }, permission: 'purchases.create', primary: false },
        { label: 'تسجيل مصروف', icon: '📤', to: { name: 'expenses', query: { create: 1 } }, permission: 'expenses.create', primary: false },
        {
            label: 'صرف مستحقات خبير',
            icon: '🧰',
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
            icon: '🧾',
            iconClass: 'bg-blue-50 text-blue-600',
            to: can('sales.view') ? { name: 'sales' } : null,
        },
        {
            label: 'مبيعات الفترة',
            value: formatUsd(kpis.sales_period.amount),
            hint: `${kpis.sales_period.count} فاتورة`,
            delta: delta(kpis.sales_period.amount, previous?.sales_period.amount),
            goodWhen: 'up',
            icon: '📈',
            iconClass: 'bg-cyan-50 text-cyan-600',
            to: can('sales.view') ? { name: 'sales' } : null,
        },
        {
            label: 'مشتريات الفترة',
            value: formatUsd(kpis.purchases_period.amount),
            hint: `${kpis.purchases_period.count} فاتورة`,
            delta: delta(kpis.purchases_period.amount, previous?.purchases_period.amount),
            icon: '📥',
            iconClass: 'bg-orange-50 text-orange-600',
            to: can('purchases.view') ? { name: 'purchases' } : null,
        },
        {
            label: 'المقبوضات في الفترة',
            value: formatUsd(kpis.collected_period),
            hint: 'تحصيلات من العملاء',
            delta: delta(kpis.collected_period, previous?.collected_period),
            goodWhen: 'up',
            icon: '💵',
            iconClass: 'bg-emerald-50 text-emerald-600',
            to: can('payments.view') ? { name: 'payments', query: { type: 'CUSTOMER' } } : null,
        },
        {
            label: 'المدفوعات في الفترة',
            value: formatUsd(kpis.paid_period),
            hint: 'سداد دفعات الموردين',
            delta: delta(kpis.paid_period, previous?.paid_period),
            icon: '💸',
            iconClass: 'bg-amber-50 text-amber-600',
            to: can('payments.view') ? { name: 'payments', query: { type: 'SUPPLIER' } } : null,
        },
        {
            label: 'مصاريف الفترة',
            value: formatUsd(kpis.expenses_period.amount),
            hint: `${kpis.expenses_period.count} سند مصروف`,
            delta: delta(kpis.expenses_period.amount, previous?.expenses_period.amount),
            icon: '📤',
            iconClass: 'bg-rose-50 text-rose-600',
            to: can('expenses.view') ? { name: 'expenses' } : null,
        },
    ];
});

const workshopKpis = computed(() => {
    if (!data.value?.workshop) return [];
    const ws = data.value.workshop;

    return [
        {
            label: 'أجهزة مستلمة في الفترة',
            value: `${ws.received_count} جهاز`,
            hint: 'وارد الصيانة الجديد',
            icon: '📥',
            iconClass: 'bg-blue-50 text-blue-600',
            to: can('repairs.view') ? { name: 'repairs' } : null,
        },
        {
            label: 'أجهزة تم تسليمها',
            value: `${ws.delivered_count} جهاز`,
            hint: `إيراد: ${formatUsd(ws.delivered_revenue)}`,
            icon: '✅',
            iconClass: 'bg-emerald-50 text-emerald-600',
            to: can('repairs.view') ? { name: 'repairs', query: { status: 'DELIVERED' } } : null,
        },
        {
            label: 'جاهزة بانتظار التسليم',
            value: `${ws.ready_for_pickup} جهاز`,
            hint: 'مكتملة ومجهزة للتسليم',
            icon: '📦',
            iconClass: 'bg-cyan-50 text-cyan-600',
            to: can('repairs.view') ? { name: 'repairs', query: { status: 'READY' } } : null,
        },
        {
            label: 'أجهزة قيد العمل بالورشة',
            value: `${ws.active_in_shop} جهاز`,
            hint: 'قيد الفحص أو الإصلاح',
            icon: '🔧',
            iconClass: 'bg-amber-50 text-amber-600',
            to: can('repairs.view') ? { name: 'repairs' } : null,
        },
    ];
});

const allKpiCount = computed(() => kpiCards.value.length + workshopKpis.value.length);

const activeAlerts = computed(() => {
    if (!data.value) return [];
    const alerts = data.value.alerts;

    return [
        {
            count: alerts.repairs_ready ?? 0,
            label: 'أجهزة جاهزة للتسليم للعميل',
            icon: '🎉',
            tone: 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 hover:shadow-sm',
            to: { name: 'repairs', query: { status: 'READY' } },
            permission: 'repairs.view',
        },
        {
            count: alerts.repairs_waiting_approval ?? 0,
            label: 'أجهزة بانتظار موافقة العميل',
            icon: '⏳',
            tone: 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 hover:shadow-sm',
            to: { name: 'repairs', query: { status: 'WAITING_APPROVAL' } },
            permission: 'repairs.view',
        },
        {
            count: alerts.out_of_stock ?? 0,
            label: 'مواد نفدت من المخزون',
            icon: '⛔',
            tone: 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 hover:shadow-sm',
            to: { name: 'reports', query: { tab: 'low-stock' } },
            permission: 'reports.view',
        },
        {
            count: alerts.low_stock ?? 0,
            label: 'مواد قرب النفاد',
            icon: '⚠',
            tone: 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 hover:shadow-sm',
            to: { name: 'reports', query: { tab: 'low-stock' } },
            permission: 'reports.view',
        },
        {
            count: alerts.draft_sales ?? 0,
            label: 'فواتير بيع غير معتمدة',
            icon: '📝',
            tone: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:shadow-sm',
            to: { name: 'sales' },
            permission: 'sales.view',
        },
        {
            count: alerts.draft_purchases ?? 0,
            label: 'فواتير شراء غير معتمدة',
            icon: '📝',
            tone: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:shadow-sm',
            to: { name: 'purchases' },
            permission: 'purchases.view',
        },
        {
            count: alerts.draft_payments ?? 0,
            label: 'دفعات غير معتمدة',
            icon: '📝',
            tone: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:shadow-sm',
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

// One column per ranking card on wide screens, wrapping once there are more than four.
const rankingGridClass = computed(() => {
    const cards = 2
        + (topBrands.value.length ? 1 : 0)
        + (data.value?.top_technicians ? 1 : 0)
        + (data.value?.top_services ? 1 : 0);

    return {
        2: 'lg:grid-cols-2',
        3: 'lg:grid-cols-2 xl:grid-cols-3',
        4: 'lg:grid-cols-2 2xl:grid-cols-4',
        5: 'lg:grid-cols-2 xl:grid-cols-3',
    }[cards];
});

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
    sales_invoice: '🧾',
    purchase_invoice: '📥',
    payment: '💵',
};

function documentRoute(doc) {
    const target = DOCUMENT_ROUTES[doc.kind];
    if (!target || !can(target.permission)) return null;
    return { name: target.name, params: { id: doc.id } };
}

function documentIcon(doc) {
    if (doc.kind === 'payment') return doc.direction === 'in' ? '💵' : '💸';
    return DOCUMENT_ICONS[doc.kind] ?? '📄';
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

    #dashboard-sheet {
        gap: 0;
    }

    #dashboard-sheet .rounded-2xl,
    #dashboard-sheet .rounded-3xl {
        break-inside: avoid;
        box-shadow: none !important;
        border-color: #cbd5e1 !important;
    }

    /*
     * The hero's gradient does not print, so its white text would vanish on a
     * white page — force dark ink and drop the decorative glows instead.
     */
    #dashboard-sheet .dashboard-hero,
    #dashboard-sheet .dashboard-hero * {
        color: #0f172a !important;
        background: transparent !important;
    }

    #dashboard-sheet .dashboard-hero .hero-blob {
        display: none;
    }

    #dashboard-sheet .dashboard-hero .position-track {
        background: #f1f5f9;
    }
}
</style>
