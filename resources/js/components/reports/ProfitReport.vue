<template>
    <div class="space-y-6 px-4 pb-6">
        <!-- ------------------------------------------------ Main KPI Summary Grid -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <!-- 1. Net Revenue -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">صافي الإيرادات</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-sm">💰</span>
                </div>
                <div class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatUsd(summary.revenue.total) }}
                </div>
                <div class="mt-3 space-y-1.5 border-t border-slate-100 pt-2.5 text-xs text-slate-600">
                    <div class="flex items-center justify-between">
                        <span>مبيعات قطع ومواد:</span>
                        <span class="font-medium tabular-nums">{{ formatUsd(summary.revenue.materials_revenue) }}</span>
                    </div>
                    <div v-if="showRepairs || showServices" class="flex items-center justify-between">
                        <span>{{ serviceFeesLabel }}</span>
                        <span class="font-medium tabular-nums">{{ formatUsd(summary.revenue.services_revenue) }}</span>
                    </div>
                    <div v-if="Number(summary.revenue.discount) > 0" class="flex items-center justify-between text-rose-600">
                        <span>خصومات ممنوحة:</span>
                        <span class="font-medium tabular-nums">-{{ formatUsd(summary.revenue.discount) }}</span>
                    </div>
                </div>
            </div>

            <!-- 2. Cost of Goods Sold (COGS) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">تكلفة المواد المباعة</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 text-sm">📦</span>
                </div>
                <div class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatUsd(summary.cogs.total) }}
                </div>
                <div class="mt-3 space-y-1.5 border-t border-slate-100 pt-2.5 text-xs text-slate-600">
                    <div class="flex items-center justify-between">
                        <span>نسبة التكلفة من المبيعات:</span>
                        <span class="font-medium tabular-nums text-amber-700">{{ cogsShareText }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>مشتريات الفترة (مخزون):</span>
                        <span class="font-medium tabular-nums">{{ formatUsd(summary.purchases.total) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-400">
                        <span>طريقة الاحتساب:</span>
                        <span>تكلفة الشراء الفعلية</span>
                    </div>
                </div>
            </div>

            <!-- 3. Gross Profit -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">إجمالي الربح (التجاري)</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-sm">📊</span>
                </div>
                <div class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatUsd(summary.gross_profit.total) }}
                </div>
                <div class="mt-3 space-y-1.5 border-t border-slate-100 pt-2.5 text-xs text-slate-600">
                    <div class="flex items-center justify-between">
                        <span>هامش الربح الإجمالي:</span>
                        <span
                            class="rounded-full px-2 py-0.5 font-bold tabular-nums text-xs"
                            :class="summary.gross_profit.margin_percent >= 30 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                        >
                            {{ summary.gross_profit.margin_percent }}٪
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>المعادلة:</span>
                        <span class="text-slate-500">الإيرادات − تكلفة المواد</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>عدد الفواتير:</span>
                        <span class="font-medium tabular-nums">{{ formatCount(summary.revenue.invoices_count) }}</span>
                    </div>
                </div>
            </div>

            <!-- 4. Operating Costs (Expenses + Commissions) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ showTechnicians ? 'المصاريف والعمولات' : 'المصاريف التشغيلية' }}</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 text-sm">💳</span>
                </div>
                <div class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatUsd(summary.operating_costs.total) }}
                </div>
                <div class="mt-3 space-y-1.5 border-t border-slate-100 pt-2.5 text-xs text-slate-600">
                    <div class="flex items-center justify-between">
                        <span>مصاريف الصندوق:</span>
                        <span class="font-medium tabular-nums">{{ formatUsd(summary.operating_expenses.total) }}</span>
                    </div>
                    <div v-if="showTechnicians" class="flex items-center justify-between">
                        <span>عمولات فنيي الصيانة:</span>
                        <span class="font-medium tabular-nums">{{ formatUsd(summary.technician_commissions.total) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-400">
                        <span>سندات المصروف:</span>
                        <span>{{ formatCount(summary.operating_expenses.entries_count) }} سند</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ------------------------------------------------ Net Operating Profit Hero Card -->
        <div
            class="relative overflow-hidden rounded-2xl border p-6 shadow-sm transition"
            :class="summary.net_profit.is_loss
                ? 'border-rose-200 bg-gradient-to-br from-rose-50 via-white to-red-50 text-rose-950'
                : 'border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-teal-50 text-emerald-950'"
        >
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0 lg:flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold"
                            :class="summary.net_profit.is_loss
                                ? 'bg-rose-200 text-rose-800'
                                : 'bg-emerald-200 text-emerald-800'"
                        >
                            <span class="h-2 w-2 rounded-full" :class="summary.net_profit.is_loss ? 'bg-rose-600' : 'bg-emerald-600'"></span>
                            {{ summary.net_profit.is_loss ? 'صافي خسارة تشغيلية' : 'صافي الربح التشغيلي' }}
                        </span>
                        <span class="text-xs text-slate-500" dir="ltr">{{ summary.from }} — {{ summary.to }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-baseline gap-3">
                        <div
                            class="break-words text-3xl font-extrabold tracking-tight tabular-nums sm:text-5xl lg:text-4xl xl:text-5xl"
                            :class="summary.net_profit.is_loss ? 'text-rose-700' : 'text-emerald-700'"
                        >
                            {{ formatUsd(summary.net_profit.total) }}
                        </div>
                        <span
                            class="rounded-lg px-2.5 py-1 text-sm font-bold tabular-nums"
                            :class="summary.net_profit.is_loss
                                ? 'bg-rose-100 text-rose-800'
                                : 'bg-emerald-100 text-emerald-800'"
                        >
                            هامش صافي {{ summary.net_profit.margin_percent }}٪
                        </span>
                    </div>

                    <p class="mt-2 text-xs text-slate-600">
                        صافي الربح = إجمالي الربح ({{ formatUsd(summary.gross_profit.total) }}) − المصاريف والعمولات التشغيلية ({{ formatUsd(summary.operating_costs.total) }}).
                    </p>
                </div>

                <!-- Secondary KPI Pills -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:w-auto">
                    <div class="rounded-xl border border-white/60 bg-white/80 p-3 shadow-2xs backdrop-blur-xs">
                        <div class="text-[11px] font-medium text-slate-500">متوسط الربح اليومي</div>
                        <div class="mt-1 text-base font-bold tabular-nums text-slate-900">
                            {{ formatUsd(dailyAverageProfit) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-white/60 bg-white/80 p-3 shadow-2xs backdrop-blur-xs">
                        <div class="text-[11px] font-medium text-slate-500">متوسط ربح الفاتورة</div>
                        <div class="mt-1 text-base font-bold tabular-nums text-slate-900">
                            {{ formatUsd(perInvoiceProfit) }}
                        </div>
                    </div>
                    <div class="col-span-2 rounded-xl border border-white/60 bg-white/80 p-3 shadow-2xs backdrop-blur-xs sm:col-span-1">
                        <div class="text-[11px] font-medium text-slate-500">صافي التدفق النقدي</div>
                        <div
                            class="mt-1 text-base font-bold tabular-nums"
                            :class="Number(summary.cash_flow.net_cash_flow) >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                        >
                            {{ formatUsd(summary.cash_flow.net_cash_flow) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ------------------------------------------------ Cash Flow vs Accrual Insight -->
        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-base">⚖️</span>
                    <h3 class="text-sm font-bold text-slate-900">تحليل التدفق النقدي مقابل الأرباح الدفترية</h3>
                </div>
                <span class="text-xs text-slate-500">الفرق بين ما تحقّق على الورق وما دخل وخرج فعلياً من الصندوق</span>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-3.5">
                    <span class="text-xs text-slate-500">المقبوض نقداً من العملاء</span>
                    <div class="mt-1 text-lg font-bold tabular-nums text-emerald-700">
                        {{ formatUsd(summary.cash_flow.collected) }}
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">
                        من أصل مبيعات {{ formatUsd(summary.revenue.total) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3.5">
                    <span class="text-xs text-slate-500">المدفوع للموردين</span>
                    <div class="mt-1 text-lg font-bold tabular-nums text-slate-800">
                        {{ formatUsd(summary.cash_flow.paid_suppliers) }}
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">سندات دفع للموردين</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3.5">
                    <span class="text-xs text-slate-500">{{ showTechnicians ? 'المدفوع للخبراء والمصاريف' : 'المدفوع للمصاريف' }}</span>
                    <div class="mt-1 text-lg font-bold tabular-nums text-slate-800">
                        {{ formatUsd(totalCashOutflows) }}
                    </div>
                    <p v-if="showTechnicians" class="mt-1 text-[11px] text-slate-400">
                        {{ formatUsd(summary.cash_flow.paid_technicians) }} خبراء + {{ formatUsd(summary.cash_flow.paid_expenses) }} مصاريف
                    </p>
                    <p v-else class="mt-1 text-[11px] text-slate-400">سندات مصاريف الصندوق</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-3.5">
                    <span class="text-xs text-slate-500">ديون عملاء غير مقبوضة</span>
                    <div class="mt-1 text-lg font-bold tabular-nums text-amber-700">
                        {{ formatUsd(summary.revenue.outstanding) }}
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">أرباح معلّقة بذمة الزبائن</p>
                </div>
            </div>
        </div>

        <!-- ------------------------------------------------ Trend Chart -->
        <div v-if="points.length > 1" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">حركة الأرباح والإيرادات عبر الفترة</h3>
                    <p class="text-xs text-slate-500">مقارنة حركة الإيرادات بالتكاليف وصافي الربح المحقّق</p>
                </div>
                <span class="text-xs text-slate-400" dir="ltr">{{ summary.from }} — {{ summary.to }}</span>
            </div>
            <TrendChart
                :points="points"
                :series="series"
                :format-value="formatUsd"
                :height="260"
            />
        </div>

        <!-- ------------------------------------------------ Daily Breakdown Table -->
        <div class="space-y-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">التفصيل اليومي للأرباح</h3>
                    <p class="text-xs text-slate-500">سجل الإيرادات والتكاليف وهامش الربح يوماً بيوم</p>
                </div>
                <div v-if="busiestProfitDay" class="text-xs text-slate-600">
                    أعلى يوم ربح:
                    <span dir="ltr" class="font-semibold text-emerald-700">{{ busiestProfitDay.date }}</span>
                    · {{ formatUsd(busiestProfitDay.net_profit) }}
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full min-w-[760px] text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">التاريخ</th>
                            <th scope="col" class="px-4 py-3 text-left">الفواتير</th>
                            <th scope="col" class="px-4 py-3 text-left">الإيرادات</th>
                            <th scope="col" class="px-4 py-3 text-left">تكلفة المواد</th>
                            <th scope="col" class="px-4 py-3 text-left">إجمالي الربح</th>
                            <th scope="col" class="px-4 py-3 text-left">المصاريف والعمولات</th>
                            <th scope="col" class="px-4 py-3 text-left">صافي الربح</th>
                            <th scope="col" class="px-4 py-3 text-left">الهامش ٪</th>
                            <th scope="col" class="px-4 py-3 print:hidden">الأداء</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="day in days"
                            :key="day.date"
                            class="transition hover:bg-slate-50"
                            :class="day.is_loss ? 'bg-rose-50/30' : ''"
                        >
                            <td class="px-4 py-3 font-medium text-slate-700" dir="ltr">{{ day.date }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-slate-600">{{ formatCount(day.count) }}</td>
                            <td class="px-4 py-3 text-left font-medium tabular-nums text-slate-900">{{ formatUsd(day.revenue) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-amber-700">{{ formatUsd(day.cogs) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-slate-700">{{ formatUsd(day.gross_profit) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-rose-600">{{ formatUsd(day.operating_costs) }}</td>
                            <td
                                class="px-4 py-3 text-left font-bold tabular-nums"
                                :class="day.is_loss ? 'text-rose-600' : 'text-emerald-700'"
                            >
                                {{ formatUsd(day.net_profit) }}
                            </td>
                            <td
                                class="px-4 py-3 text-left font-semibold tabular-nums text-xs"
                                :class="day.is_loss ? 'text-rose-600' : 'text-emerald-700'"
                            >
                                {{ day.margin_percent }}٪
                            </td>
                            <td class="px-4 py-3 print:hidden">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            class="h-full rounded-full"
                                            :class="day.is_loss ? 'bg-rose-500' : 'bg-emerald-500'"
                                            :style="dailyShareStyle(day.net_profit)"
                                        ></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-slate-900">
                        <tr>
                            <td class="px-4 py-3">الإجمالي</td>
                            <td class="px-4 py-3 text-left tabular-nums">{{ formatCount(summary.revenue.invoices_count) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums">{{ formatUsd(summary.revenue.total) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-amber-800">{{ formatUsd(summary.cogs.total) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums">{{ formatUsd(summary.gross_profit.total) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-rose-700">{{ formatUsd(summary.operating_costs.total) }}</td>
                            <td
                                class="px-4 py-3 text-left tabular-nums text-base"
                                :class="summary.net_profit.is_loss ? 'text-rose-700' : 'text-emerald-700'"
                            >
                                {{ formatUsd(summary.net_profit.total) }}
                            </td>
                            <td
                                class="px-4 py-3 text-left tabular-nums"
                                :class="summary.net_profit.is_loss ? 'text-rose-700' : 'text-emerald-700'"
                            >
                                {{ summary.net_profit.margin_percent }}٪
                            </td>
                            <td class="print:hidden"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ------------------------------------------------ Secondary Breakdown Grids -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Top Profitable Materials -->
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">أعلى المواد والقطع ربحية</h3>
                        <p class="text-xs text-slate-500">مرتّبة حسب صافي الربح المحقّق من مبيعاتها</p>
                    </div>
                    <span class="text-xs font-medium text-slate-400">{{ topMaterials.length }} مواد</span>
                </div>

                <div v-if="!topMaterials.length" class="py-8 text-center text-xs text-slate-400">
                    لم تُسجل مبيعات لمواد في هذه الفترة
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[480px] text-right text-xs [&_td]:px-2 [&_th]:px-2 [&_td]:whitespace-nowrap">
                        <thead class="border-b border-slate-100 text-slate-400">
                            <tr>
                                <th class="pb-2 font-medium">المادة</th>
                                <th class="pb-2 text-left font-medium">الكمية</th>
                                <th class="pb-2 text-left font-medium">الإيراد</th>
                                <th class="pb-2 text-left font-medium">التكلفة</th>
                                <th class="pb-2 text-left font-medium">الربح</th>
                                <th class="pb-2 text-left font-medium">الهامش</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in topMaterials" :key="item.id" class="transition hover:bg-slate-50">
                                <td class="py-2.5 font-medium text-slate-800">
                                    <div>{{ item.name }}</div>
                                    <div class="text-[10px] text-slate-400" dir="ltr">{{ item.sku || '—' }}</div>
                                </td>
                                <td class="py-2.5 text-left tabular-nums text-slate-600">
                                    {{ formatQty(item.quantity) }} {{ item.unit || '' }}
                                </td>
                                <td class="py-2.5 text-left tabular-nums text-slate-700">{{ formatUsd(item.revenue) }}</td>
                                <td class="py-2.5 text-left tabular-nums text-slate-500">{{ formatUsd(item.cost) }}</td>
                                <td class="py-2.5 text-left font-bold tabular-nums text-emerald-700">{{ formatUsd(item.profit) }}</td>
                                <td class="py-2.5 text-left font-medium tabular-nums text-emerald-800">{{ item.margin_percent }}٪</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Operating Expenses Breakdown -->
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">توزيع المصاريف حسب الأبواب</h3>
                        <p class="text-xs text-slate-500">تفصيل مبالغ الصرف التشغيلية من الصندوق</p>
                    </div>
                    <span class="text-xs font-semibold tabular-nums text-slate-700">
                        {{ formatUsd(summary.operating_expenses.total) }}
                    </span>
                </div>

                <div v-if="!expenseCategories.length" class="py-8 text-center text-xs text-slate-400">
                    لم تُسجل مصاريف في هذه الفترة
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="cat in expenseCategories"
                        :key="cat.id"
                        class="rounded-xl border border-slate-100 bg-slate-50/50 p-3"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <div class="font-bold text-slate-800">
                                {{ cat.name }}
                                <span class="ms-1 font-normal text-slate-400">({{ cat.entries }} سند)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold tabular-nums text-slate-900">{{ formatUsd(cat.total) }}</span>
                                <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[11px] font-semibold text-rose-800 tabular-nums">
                                    {{ cat.share_percent }}٪
                                </span>
                            </div>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full rounded-full bg-rose-500" :style="{ width: `${cat.share_percent}%` }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ------------------------------------------------ Services & Revenue Composition -->
        <div v-if="showServices && topServices.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900">أعلى خدمات الصيانة إيراداً</h3>
            <p class="text-xs text-slate-500">أجور العمل والصيانة المعتمدة في الفواتير</p>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="service in topServices"
                    :key="service.name"
                    class="rounded-xl border border-slate-100 bg-slate-50/60 p-3"
                >
                    <div class="text-xs font-semibold text-slate-800">{{ service.name }}</div>
                    <div class="mt-1 flex items-baseline justify-between text-xs">
                        <span class="text-slate-500">{{ formatCount(service.count) }} مرة</span>
                        <span class="font-bold tabular-nums text-blue-700">{{ formatUsd(service.revenue) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import TrendChart from '../charts/TrendChart.vue';
import { formatUsd, formatPercent, formatQty } from '../../utils/format';

const props = defineProps({
    summary: { type: Object, required: true },
    showRepairs: { type: Boolean, default: true },
    showTechnicians: { type: Boolean, default: true },
    showServices: { type: Boolean, default: true },
});

const counter = new Intl.NumberFormat('ar-SY-u-nu-latn');

function formatCount(value) {
    return counter.format(Number(value) || 0);
}

const days = computed(() => props.summary.by_day ?? []);
const topMaterials = computed(() => props.summary.top_materials ?? []);
const expenseCategories = computed(() => props.summary.operating_expenses?.by_category ?? []);
const topServices = computed(() => props.summary.top_services ?? []);

const serviceFeesLabel = computed(() => {
    if (props.showRepairs && props.showServices) return 'أجور صيانة وخدمات:';
    return props.showRepairs ? 'أجور صيانة:' : 'أجور خدمات:';
});

const cogsShareText = computed(() => {
    const rev = Number(props.summary.revenue?.total) || 0;
    const cogs = Number(props.summary.cogs?.total) || 0;
    if (rev <= 0 || cogs <= 0) return '0٪';
    return `${Math.round((cogs / rev) * 100)}٪`;
});

const totalCashOutflows = computed(() => {
    const tech = props.showTechnicians ? Number(props.summary.cash_flow?.paid_technicians) || 0 : 0;
    const exp = Number(props.summary.cash_flow?.paid_expenses) || 0;
    return String(tech + exp);
});

const periodDaysCount = computed(() => {
    if (!props.summary.from || !props.summary.to) return days.value.length || 1;
    const start = new Date(props.summary.from);
    const end = new Date(props.summary.to);
    const diff = Math.round((end - start) / 864e5) + 1;
    return Math.max(1, diff);
});

const dailyAverageProfit = computed(() => {
    const total = Number(props.summary.net_profit?.total) || 0;
    return total / periodDaysCount.value;
});

const perInvoiceProfit = computed(() => {
    const count = Number(props.summary.revenue?.invoices_count) || 0;
    const total = Number(props.summary.net_profit?.total) || 0;
    return count > 0 ? total / count : 0;
});

const busiestProfitDay = computed(() => {
    if (!days.value.length) return null;
    return days.value.reduce((best, day) => (Number(day.net_profit) > Number(best.net_profit) ? day : best));
});

const maxDayProfit = computed(() =>
    days.value.reduce((max, day) => Math.max(max, Math.abs(Number(day.net_profit) || 0)), 0)
);

function dailyShareStyle(netProfit) {
    const max = maxDayProfit.value || 1;
    const ratio = Math.max(0.04, Math.min(1, Math.abs(Number(netProfit) || 0) / max));
    return { width: `${ratio * 100}%` };
}

// Chart Series
const series = [
    { key: 'revenue', label: 'الإيرادات', color: '#2563eb' },
    { key: 'costs', label: 'التكلفة والمصاريف', color: '#f59e0b' },
    { key: 'profit', label: 'صافي الربح', color: '#10b981' },
];

const points = computed(() =>
    days.value.map((day) => ({
        key: day.date,
        label: day.date.slice(5),
        revenue: Number(day.revenue) || 0,
        costs: (Number(day.cogs) || 0) + (Number(day.operating_costs) || 0),
        profit: Number(day.net_profit) || 0,
    }))
);
</script>
