<template>
    <div id="statement-sheet" class="mx-auto max-w-6xl space-y-5">
        <!-- ------------------------------------------------ Action & Navigation Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 active:scale-95"
                    @click="$router.back()"
                >
                    <span aria-hidden="true">←</span>
                    <span>عودة</span>
                </button>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xl" aria-hidden="true">{{ kind.icon }}</span>
                        <h1 class="text-xl font-bold text-slate-900">كشف حساب {{ kind.label }}</h1>
                        <span
                            v-if="statement"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="balanceBadgeClass(statement.closing_balance)"
                        >
                            {{ balanceHint(statement.closing_balance) }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ statement?.party?.name || 'جارٍ التحميل...' }}
                        <span v-if="statement?.party?.company" class="mx-1.5 text-slate-300">•</span>
                        <span v-if="statement?.party?.company" class="text-slate-600">{{ statement.party.company }}</span>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Refresh Button -->
                <button
                    type="button"
                    :disabled="loading"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-2xs transition hover:bg-slate-50 disabled:opacity-40"
                    title="تحديث البيانات"
                    @click="load"
                >
                    <span :class="{ 'animate-spin': loading }">🔄</span>
                    <span class="hidden sm:inline">تحديث</span>
                </button>

                <!-- CSV / Excel Export -->
                <button
                    v-if="statement && statement.rows.length"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-emerald-700 shadow-2xs transition hover:bg-emerald-50 active:scale-95"
                    title="تصدير كشف الحساب إلى ملف Excel / CSV"
                    @click="exportCsv"
                >
                    <span>📊</span>
                    <span>تصدير Excel</span>
                </button>

                <!-- Load all for printing if paginated -->
                <button
                    v-if="hasMore"
                    type="button"
                    :disabled="loadingAll"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 shadow-2xs transition hover:bg-amber-100 disabled:opacity-50"
                    title="تحميل كافة حركات الكشف للطباعة أو المعاينة الكاملة"
                    @click="loadAllRows"
                >
                    <span v-if="loadingAll" class="animate-spin">⏳</span>
                    <span v-else>📥</span>
                    <span>تحميل الكل للطباعة ({{ meta?.total }} حركة)</span>
                </button>

                <!-- Web Browser Print -->
                <button
                    type="button"
                    :disabled="!statement"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 disabled:opacity-40 active:scale-95"
                    title="طباعة عبر المتصفح (Ctrl+P)"
                    @click="printSheet"
                >
                    <span>🖨️</span>
                    <span>طباعة الصفحة</span>
                </button>

                <!-- Official PDF Button -->
                <PrintButton
                    v-if="kind.pdf"
                    kind="statement"
                    title="كشف PDF رسمي"
                    label="📄 كشف PDF رسمي"
                    busy-label="جارٍ التجهيز..."
                    :disabled="!statement"
                    :load="loadPdf"
                    wrapper-class="rounded-xl bg-blue-600 text-white shadow-2xs transition hover:bg-blue-700"
                    button-class="rounded-s-xl px-4 py-2 text-sm font-semibold transition hover:bg-blue-700 disabled:opacity-40"
                    caret-class="rounded-e-xl border-s border-blue-500 px-2.5 transition hover:bg-blue-700 disabled:opacity-40"
                />
            </div>
        </div>

        <!-- ------------------------------------------------ Print Only Header -->
        <div v-if="statement" class="hidden space-y-4 border-b-2 border-slate-900 pb-4 print:block">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">📱</span>
                        <h1 class="text-xl font-black text-slate-900">دماتك للاتصالات وأنظمة الصيانة</h1>
                    </div>
                    <h2 class="mt-1 text-sm font-bold text-slate-800">
                        كشف حساب مالي تفصيلي — {{ kind.label }}
                    </h2>
                </div>
                <div class="text-left text-xs leading-relaxed text-slate-600">
                    <div><strong>تاريخ الطباعة:</strong> <span dir="ltr">{{ printedAt }}</span></div>
                    <div><strong>العملة الأساسية:</strong> {{ BASE_SYMBOL }}</div>
                </div>
            </div>

            <!-- Metadata Box -->
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="rounded border border-slate-300 bg-slate-50 p-2.5">
                    <div class="text-sm font-bold text-slate-900">{{ statement.party.name }}</div>
                    <div v-if="statement.party.company" class="mt-0.5 text-slate-700">
                        الشركة / النشاط: {{ statement.party.company }}
                    </div>
                    <div v-if="statement.party.phone" class="mt-0.5 text-slate-700" dir="ltr">
                        الهاتف: {{ statement.party.phone }}
                    </div>
                </div>
                <div class="rounded border border-slate-300 bg-slate-50 p-2.5">
                    <div>
                        <strong>فترة الكشف:</strong>
                        من <span dir="ltr">{{ statement.period.from || 'بداية التعامل' }}</span>
                        إلى <span dir="ltr">{{ statement.period.to || 'اليوم' }}</span>
                    </div>
                    <div class="mt-1">
                        <strong>إجمالي الحركات:</strong> {{ meta?.total ?? statement.rows.length }} حركة
                    </div>
                    <div class="mt-1 font-bold text-slate-900">
                        حالة الرصيد: {{ balanceHint(statement.closing_balance) }} —
                        <span dir="ltr">{{ formatMoney(statement.closing_balance) }}</span>
                    </div>
                </div>
            </div>

            <!-- Printed Summary Strip -->
            <div class="grid grid-cols-4 gap-2 rounded border border-slate-300 bg-slate-100 p-2 text-center text-xs font-semibold text-slate-800">
                <div>الرصيد الافتتاحي: <span dir="ltr">{{ formatMoney(statement.opening_balance) }}</span></div>
                <div>إجمالي المدين: <span dir="ltr">{{ formatMoney(statement.totals.debit) }}</span></div>
                <div>إجمالي الدائن: <span dir="ltr">{{ formatMoney(statement.totals.credit) }}</span></div>
                <div>الرصيد النهائي: <span dir="ltr">{{ formatMoney(statement.closing_balance) }}</span></div>
            </div>

            <div v-if="hasMore" class="rounded border border-amber-300 bg-amber-50 p-2 text-[11px] text-amber-900">
                تنبيه: يتضمن هذا الكشف المعروض أول {{ statement.rows.length }} حركة فقط من أصل {{ meta?.total }}. لطباعة الكشف كاملاً، اضغط زر "كشف PDF" أو "تحميل الكل للطباعة".
            </div>
        </div>

        <!-- ------------------------------------------------ Main Panel -->
        <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-xs print:border-0 print:p-0 print:shadow-none">
            <!-- Filter Bar & Quick Date Presets -->
            <div class="space-y-3.5 border-b border-slate-100 pb-4 print:hidden">
                <!-- Date Presets -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-slate-500">فترات سريعة:</span>
                    <button
                        v-for="preset in presets"
                        :key="preset.key"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="activePreset === preset.key
                            ? 'bg-blue-600 font-semibold text-white shadow-2xs'
                            : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                        @click="selectPreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <!-- Custom Date Inputs & Filters Row -->
                <div class="flex flex-wrap items-end gap-3">
                    <label class="text-xs font-medium text-slate-700">
                        من تاريخ
                        <input
                            v-model="filters.from"
                            type="date"
                            class="mt-1 block rounded-xl border border-slate-300 px-3 py-1.5 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            @keyup.enter="applyFilters"
                        />
                    </label>
                    <label class="text-xs font-medium text-slate-700">
                        إلى تاريخ
                        <input
                            v-model="filters.to"
                            type="date"
                            class="mt-1 block rounded-xl border border-slate-300 px-3 py-1.5 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            @keyup.enter="applyFilters"
                        />
                    </label>
                    <button
                        type="button"
                        class="rounded-xl bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white shadow-2xs transition hover:bg-blue-700 active:scale-95"
                        @click="applyFilters"
                    >
                        عرض
                    </button>
                    <button
                        v-if="filters.from || filters.to"
                        type="button"
                        class="rounded-xl border border-slate-200 px-3.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900"
                        @click="resetFilters"
                    >
                        إلغاء الفلترة
                    </button>

                    <!-- Real-time Row Search -->
                    <div class="ms-auto flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-slate-400" aria-hidden="true">
                                🔍
                            </span>
                            <input
                                v-model="searchQuery"
                                type="search"
                                placeholder="تصفية الحركات (مرجع، بيان...)"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-1.5 pr-8 pl-7 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute inset-y-0 left-0 flex items-center pl-2 text-xs text-slate-400 hover:text-slate-700"
                                @click="searchQuery = ''"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Type filter dropdown -->
                        <select
                            v-model="typeFilter"
                            class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="all">كافة الأنواع</option>
                            <option value="invoices">فواتير فقط</option>
                            <option value="payments">دفعات وسندات</option>
                            <option value="repairs">صيانة فقط</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <template v-if="statement">
                <!-- ------------------------------------------------ Financial Summary Cards -->
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 print:hidden">
                    <!-- Opening Balance Card -->
                    <div class="rounded-2xl border border-slate-200/90 bg-slate-50/40 p-4 transition hover:shadow-xs">
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>الرصيد الافتتاحي</span>
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600">بداية الفترة</span>
                        </div>
                        <div class="mt-2 text-lg font-bold text-slate-900" dir="ltr">
                            {{ formatMoney(statement.opening_balance) }}
                        </div>
                        <div class="mt-1 text-[11px] text-slate-400">
                            ما قبل {{ statement.period.from || 'بداية الحساب' }}
                        </div>
                    </div>

                    <!-- Total Debit Card -->
                    <div class="rounded-2xl border border-blue-100 bg-blue-50/30 p-4 transition hover:shadow-xs">
                        <div class="flex items-center justify-between text-xs text-blue-700">
                            <span class="font-semibold">إجمالي المدين (+)</span>
                            <span class="text-xs">📈</span>
                        </div>
                        <div class="mt-2 text-lg font-bold text-blue-900" dir="ltr">
                            {{ formatMoney(statement.totals.debit) }}
                        </div>
                        <div class="mt-1 text-[11px] text-blue-600/80">
                            مجموع القيود المدينة للفترة
                        </div>
                    </div>

                    <!-- Total Credit Card -->
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/30 p-4 transition hover:shadow-xs">
                        <div class="flex items-center justify-between text-xs text-emerald-700">
                            <span class="font-semibold">إجمالي الدائن (-)</span>
                            <span class="text-xs">📉</span>
                        </div>
                        <div class="mt-2 text-lg font-bold text-emerald-900" dir="ltr">
                            {{ formatMoney(statement.totals.credit) }}
                        </div>
                        <div class="mt-1 text-[11px] text-emerald-600/80">
                            مجموع القيود الدائنة للفترة
                        </div>
                    </div>

                    <!-- Period Net Movement Card -->
                    <div class="rounded-2xl border border-slate-200/90 bg-slate-50/40 p-4 transition hover:shadow-xs">
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>صافي حركة الفترة</span>
                            <span class="text-xs">⚖️</span>
                        </div>
                        <div class="mt-2 text-lg font-bold" :class="netMovement >= 0 ? 'text-blue-900' : 'text-emerald-900'" dir="ltr">
                            {{ formatMoney(Math.abs(netMovement)) }}
                        </div>
                        <div class="mt-1 text-[11px]" :class="netMovement >= 0 ? 'text-blue-600' : 'text-emerald-600'">
                            {{ netMovement > 0 ? 'صافي زيادة مديونية (مدين)' : netMovement < 0 ? 'صافي سداد/تخفيض (دائن)' : 'متوازن' }}
                        </div>
                    </div>

                    <!-- Closing Balance Card (Key Indicator) -->
                    <div
                        class="rounded-2xl border p-4 transition hover:shadow-xs"
                        :class="balanceCardTone(statement.closing_balance)"
                    >
                        <div class="flex items-center justify-between text-xs font-semibold opacity-80">
                            <span>الرصيد الختامي</span>
                            <span class="text-xs">💼</span>
                        </div>
                        <div class="mt-2 text-xl font-black" dir="ltr">
                            {{ formatMoney(statement.closing_balance) }}
                        </div>
                        <div class="mt-1 text-xs font-bold opacity-90">
                            {{ balanceHint(statement.closing_balance) }}
                        </div>
                    </div>
                </div>

                <!-- ------------------------------------------------ Statement Transactions Table -->
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200/80 print:border-0">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-slate-50 text-xs font-bold text-slate-600 print:bg-slate-100 print:text-black">
                            <tr class="border-b border-slate-200">
                                <th class="px-3.5 py-3 text-right">التاريخ</th>
                                <th class="px-3.5 py-3 text-right">البيان والحركة</th>
                                <th class="px-3.5 py-3 text-center">المرجع</th>
                                <th class="px-3.5 py-3 text-left">مدين</th>
                                <th class="px-3.5 py-3 text-left">دائن</th>
                                <th class="px-3.5 py-3 text-left">الرصيد التراكمي</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 print:divide-slate-200">
                            <!-- Loading row -->
                            <tr v-if="loading" class="text-slate-400">
                                <td colspan="6" class="px-3 py-10 text-center">
                                    <span class="inline-block animate-spin text-lg">⏳</span>
                                    <span class="mt-2 block text-xs">جارٍ تحميل الحركات...</span>
                                </td>
                            </tr>

                            <template v-else>
                                <!-- Opening Balance Prior Row -->
                                <tr class="bg-slate-50/70 font-semibold text-slate-700 print:bg-slate-50">
                                    <td class="px-3.5 py-2.5 text-xs text-slate-600" dir="ltr">
                                        {{ statement.period.from || '—' }}
                                    </td>
                                    <td class="px-3.5 py-2.5" colspan="2">
                                        <div class="flex items-center gap-2">
                                            <span class="rounded bg-slate-200/80 px-2 py-0.5 text-[11px] font-semibold text-slate-800">
                                                رصيد ما قبله
                                            </span>
                                            <span class="text-xs text-slate-500">رصيد افتتاحي لبداية الصفحة</span>
                                        </div>
                                    </td>
                                    <td class="px-3.5 py-2.5 text-left text-xs text-slate-400">—</td>
                                    <td class="px-3.5 py-2.5 text-left text-xs text-slate-400">—</td>
                                    <td class="px-3.5 py-2.5 text-left font-bold" :class="amountClass(statement.page_opening_balance)" dir="ltr">
                                        {{ formatMoney(statement.page_opening_balance) }}
                                    </td>
                                </tr>

                                <!-- Empty State -->
                                <tr v-if="!displayedRows.length" class="text-slate-400">
                                    <td colspan="6" class="px-3 py-12 text-center">
                                        <div class="text-3xl">📭</div>
                                        <div class="mt-2 text-sm font-semibold text-slate-600">لا توجد حركات مسجلة</div>
                                        <div class="text-xs text-slate-400">
                                            {{ searchQuery ? 'لا توجد حركات تطابق التصفية الحالية' : 'لم يتم تسجيل حركات في الفترة المحددة' }}
                                        </div>
                                    </td>
                                </tr>

                                <!-- Transaction Rows -->
                                <tr
                                    v-for="row in displayedRows"
                                    :key="row.id"
                                    class="align-top transition-colors hover:bg-slate-50/80 print:hover:bg-transparent"
                                >
                                    <!-- Date -->
                                    <td class="px-3.5 py-2.5 text-xs whitespace-nowrap text-slate-600" dir="ltr">
                                        {{ row.transaction_date }}
                                    </td>

                                    <!-- Description & Type Badge -->
                                    <td class="px-3.5 py-2.5 text-slate-900">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="inline-flex items-center rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                                                :class="transactionBadgeClass(row)"
                                            >
                                                {{ row.type_label }}
                                            </span>
                                        </div>
                                        <div v-if="row.description" class="mt-1 text-xs text-slate-500 leading-normal">
                                            {{ row.description }}
                                        </div>
                                    </td>

                                    <!-- Reference Link -->
                                    <td class="px-3.5 py-2.5 text-center whitespace-nowrap">
                                        <RouterLink
                                            v-if="row.reference_no && referenceRoute(row.reference, row)"
                                            :to="referenceRoute(row.reference, row)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 transition hover:bg-blue-100 hover:text-blue-700 print:bg-transparent print:p-0 print:text-black"
                                            :title="`فتح ${row.type_label} ${row.reference_no}`"
                                        >
                                            <span dir="ltr">{{ row.reference_no }}</span>
                                            <span class="text-[10px] print:hidden" aria-hidden="true">↗</span>
                                        </RouterLink>
                                        <span v-else class="text-xs text-slate-400" dir="ltr">
                                            {{ row.reference_no || '—' }}
                                        </span>
                                    </td>

                                    <!-- Debit -->
                                    <td class="px-3.5 py-2.5 text-left text-xs font-medium text-slate-900" dir="ltr">
                                        <span v-if="Number(row.debit)" class="font-semibold text-blue-900">
                                            {{ formatAmount(row.debit) }}
                                        </span>
                                        <span v-else class="text-slate-300">—</span>
                                    </td>

                                    <!-- Credit -->
                                    <td class="px-3.5 py-2.5 text-left text-xs font-medium text-slate-900" dir="ltr">
                                        <span v-if="Number(row.credit)" class="font-semibold text-emerald-900">
                                            {{ formatAmount(row.credit) }}
                                        </span>
                                        <span v-else class="text-slate-300">—</span>
                                    </td>

                                    <!-- Running Balance -->
                                    <td
                                        class="px-3.5 py-2.5 text-left text-xs font-bold"
                                        :class="amountClass(row.running_balance)"
                                        dir="ltr"
                                    >
                                        {{ formatMoney(row.running_balance) }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>

                        <!-- Footer Period Totals -->
                        <tfoot v-if="!loading" class="border-t-2 border-slate-300 bg-slate-50 text-xs font-bold text-slate-900 print:bg-slate-100">
                            <tr>
                                <td class="px-3.5 py-3" colspan="3">
                                    <div class="flex items-center justify-between">
                                        <span>إجمالي الفترة المحددة</span>
                                        <span class="text-[11px] font-normal text-slate-500">
                                            ({{ meta?.total ?? statement.rows.length }} حركة مسجلة)
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3.5 py-3 text-left font-bold text-blue-900" dir="ltr">
                                    {{ formatMoney(statement.totals.debit) }}
                                </td>
                                <td class="px-3.5 py-3 text-left font-bold text-emerald-900" dir="ltr">
                                    {{ formatMoney(statement.totals.credit) }}
                                </td>
                                <td class="px-3.5 py-3 text-left text-sm font-black" :class="amountClass(statement.closing_balance)" dir="ltr">
                                    {{ formatMoney(statement.closing_balance) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Infinite Scroll Controls -->
                <div class="print:hidden">
                    <InfiniteScrollFooter
                        class="mt-3"
                        :shown="statement.rows.length"
                        :total="meta?.total ?? 0"
                        :has-more="hasMore"
                        :loading-more="loadingMore"
                        :busy="loading"
                        :error="moreError"
                        unit="حركة"
                        @more="loadMore"
                    />
                </div>
            </template>

            <!-- Loading Skeleton -->
            <div v-else-if="loading" class="space-y-4 py-12 text-center text-sm text-slate-400">
                <div class="inline-block animate-spin text-3xl">⏳</div>
                <div class="font-medium text-slate-600">جارٍ تجهيز كشف الحساب...</div>
            </div>
        </div>

        <!-- ------------------------------------------------ Print Only Signature & Legal Area -->
        <div v-if="statement" class="hidden pt-6 print:block">
            <div class="grid grid-cols-2 gap-12 text-center text-xs">
                <div class="rounded border border-slate-300 p-4">
                    <div class="font-bold text-slate-800">توقيع {{ kind.label }} (مطابقة الرصيد)</div>
                    <div class="mt-8 border-t border-dashed border-slate-400 pt-2 text-[11px] text-slate-500">
                        الاسم والتوقيع: .......................................
                    </div>
                </div>
                <div class="rounded border border-slate-300 p-4">
                    <div class="font-bold text-slate-800">توقيع المحاسب / الإدارة المالية</div>
                    <div class="mt-8 border-t border-dashed border-slate-400 pt-2 text-[11px] text-slate-500">
                        الختم والمصادقة: .......................................
                    </div>
                </div>
            </div>
            <div class="mt-4 text-center text-[10px] text-slate-400">
                هذا المستند صادر عن النظام المحاسبي الآلي — دماتك لأنظمة الصيانة والاتصالات
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { documentsApi, statementsApi } from '../../services/api';
import PrintButton from '../../components/ui/PrintButton.vue';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import { useInfiniteList } from '../../composables/useInfiniteList';
import { BASE_SYMBOL } from '../../constants/currency';
import { csvNumber, downloadCsv } from '../../utils/csv';

const route = useRoute();

/**
 * الشاشة تخدم دفاتر العملاء والموردين وخبراء الصيانة بنظام موحد.
 */
const KINDS = {
    customer: {
        key: 'customer',
        label: 'العميل',
        icon: '👥',
        owed: 'مستحق على العميل',
        prepaid: 'رصيد لصالح العميل',
        fetch: (id, params) => statementsApi.customer(id, params),
        pdf: (id, params, paper) => documentsApi.customerStatement(id, params, paper),
    },
    supplier: {
        key: 'supplier',
        label: 'المورد',
        icon: '🏢',
        owed: 'مستحق للمورد',
        prepaid: 'رصيد لصالحنا',
        fetch: (id, params) => statementsApi.supplier(id, params),
        pdf: (id, params, paper) => documentsApi.supplierStatement(id, params, paper),
    },
    technician: {
        key: 'technician',
        label: 'خبير الصيانة',
        icon: '🔧',
        owed: 'مستحق للخبير',
        prepaid: 'مصروف زائد عن استحقاقه',
        fetch: (id, params) => statementsApi.technician(id, params),
        pdf: (id, params, paper) => documentsApi.technicianStatement(id, params, paper),
    },
};

const kind = route.path.startsWith('/customers')
    ? KINDS.customer
    : route.path.startsWith('/technicians')
        ? KINDS.technician
        : KINDS.supplier;

const owedLabel = kind.owed;
const prepaidLabel = kind.prepaid;

const REFERENCE_ROUTES = {
    sales_invoice: 'sales.edit',
    purchase_invoice: 'purchases.edit',
    payment: 'payments.edit',
};

const error = ref('');
const filters = reactive({ from: '', to: '' });
const activePreset = ref('all');
const searchQuery = ref('');
const typeFilter = ref('all');
const loadingAll = ref(false);

function formatYmd(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

const presets = [
    {
        key: 'all',
        label: 'كامل المدة',
        resolve: () => ({ from: '', to: '' }),
    },
    {
        key: 'today',
        label: 'اليوم',
        resolve: () => {
            const today = formatYmd(new Date());
            return { from: today, to: today };
        },
    },
    {
        key: 'this_week',
        label: 'هذا الأسبوع',
        resolve: () => {
            const now = new Date();
            const day = now.getDay();
            const diff = (day + 1) % 7;
            const start = new Date(now);
            start.setDate(now.getDate() - diff);
            return { from: formatYmd(start), to: formatYmd(now) };
        },
    },
    {
        key: 'this_month',
        label: 'هذا الشهر',
        resolve: () => {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            return { from: formatYmd(start), to: formatYmd(now) };
        },
    },
    {
        key: 'last_month',
        label: 'الشهر الماضي',
        resolve: () => {
            const now = new Date();
            const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const end = new Date(now.getFullYear(), now.getMonth(), 0);
            return { from: formatYmd(start), to: formatYmd(end) };
        },
    },
    {
        key: 'this_year',
        label: 'هذا العام',
        resolve: () => {
            const now = new Date();
            const start = new Date(now.getFullYear(), 0, 1);
            return { from: formatYmd(start), to: formatYmd(now) };
        },
    },
];

function selectPreset(preset) {
    activePreset.value = preset.key;
    const dates = preset.resolve();
    filters.from = dates.from;
    filters.to = dates.to;
    applyFilters();
}

const { items, meta, response, loading, loadingMore, moreError, hasMore, reload, loadMore } = useInfiniteList({
    fetch: (page, perPage) => {
        const params = { page, per_page: perPage };
        if (filters.from) params.from = filters.from;
        if (filters.to) params.to = filters.to;
        return kind.fetch(route.params.id, params);
    },
    pageSize: 100,
    maxPerPage: 200,
    select: (result) => ({ rows: result?.statement?.rows ?? [], meta: result?.meta ?? null }),
    onError: (e) => {
        error.value = e?.response?.data?.message || 'تعذّر تحميل كشف الحساب';
    },
});

const statement = computed(() => (response.value?.statement
    ? { ...response.value.statement, rows: items.value }
    : null));

const netMovement = computed(() => {
    if (!statement.value?.totals) return 0;
    return (Number(statement.value.totals.debit) || 0) - (Number(statement.value.totals.credit) || 0);
});

const printedAt = computed(() => {
    const now = new Date();
    return `${now.toLocaleDateString('ar-SY')} ${now.toLocaleTimeString('ar-SY', { hour: '2-digit', minute: '2-digit' })}`;
});

const displayedRows = computed(() => {
    if (!statement.value?.rows) return [];
    let list = statement.value.rows;

    if (typeFilter.value === 'invoices') {
        list = list.filter((r) => r.reference?.kind === 'sales_invoice' || r.reference?.kind === 'purchase_invoice' || String(r.type_label).includes('فاتورة'));
    } else if (typeFilter.value === 'payments') {
        list = list.filter((r) => r.reference?.kind === 'payment' || String(r.type_label).includes('سند') || String(r.type_label).includes('دفعة'));
    } else if (typeFilter.value === 'repairs') {
        list = list.filter((r) => r.reference?.kind === 'repair' || String(r.type_label).includes('صيانة'));
    }

    if (!searchQuery.value.trim()) return list;

    const q = searchQuery.value.trim().toLowerCase();
    return list.filter((r) => {
        return (
            (r.reference_no && String(r.reference_no).toLowerCase().includes(q)) ||
            (r.type_label && String(r.type_label).toLowerCase().includes(q)) ||
            (r.description && String(r.description).toLowerCase().includes(q)) ||
            (r.transaction_date && String(r.transaction_date).includes(q))
        );
    });
});

const numberFormat = new Intl.NumberFormat('ar-SY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function formatMoney(value) {
    return `${numberFormat.format(Number(value) || 0)}\u00a0${BASE_SYMBOL}`;
}

function formatAmount(value) {
    return Number(value) ? formatMoney(value) : '—';
}

function amountClass(value) {
    return Number(value) < 0 ? 'text-red-600' : 'text-slate-900';
}

function balanceCardTone(value) {
    const num = Number(value) || 0;
    if (Math.abs(num) < 0.005) {
        return 'border-slate-200 bg-slate-50/70 text-slate-800';
    }

    if (kind.key === 'customer') {
        return num > 0
            ? 'border-rose-200 bg-rose-50/70 text-rose-900 shadow-2xs'
            : 'border-emerald-200 bg-emerald-50/70 text-emerald-900 shadow-2xs';
    }

    // For suppliers & technicians: positive means payable to them
    return num > 0
        ? 'border-amber-200 bg-amber-50/70 text-amber-900 shadow-2xs'
        : 'border-emerald-200 bg-emerald-50/70 text-emerald-900 shadow-2xs';
}

function balanceBadgeClass(value) {
    const num = Number(value) || 0;
    if (Math.abs(num) < 0.005) {
        return 'bg-slate-100 text-slate-700';
    }
    if (kind.key === 'customer') {
        return num > 0
            ? 'bg-rose-100 text-rose-800'
            : 'bg-emerald-100 text-emerald-800';
    }
    return num > 0
        ? 'bg-amber-100 text-amber-800'
        : 'bg-emerald-100 text-emerald-800';
}

function balanceHint(value) {
    const amount = Number(value) || 0;
    if (Math.abs(amount) < 0.005) return 'الحساب مسدد بالكامل';
    return amount > 0 ? owedLabel : prepaidLabel;
}

function transactionBadgeClass(row) {
    const refKind = row?.reference?.kind;
    const label = String(row?.type_label || '');

    if (refKind === 'sales_return' || refKind === 'purchase_return' || label.includes('مرتجع')) {
        return 'border-orange-200 bg-orange-50 text-orange-700';
    }
    if (refKind === 'sales_invoice' || label.includes('مبيعات')) {
        return 'border-blue-200 bg-blue-50 text-blue-700';
    }
    if (refKind === 'purchase_invoice' || label.includes('مشتريات')) {
        return 'border-purple-200 bg-purple-50 text-purple-700';
    }
    if (refKind === 'payment' || label.includes('قبض') || label.includes('دفعة')) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }
    if (refKind === 'repair' || label.includes('صيانة')) {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }
    return 'border-slate-200 bg-slate-50 text-slate-700';
}

function referenceRoute(reference, row) {
    if (!reference) return null;

    // المرتجعات تُفتح من قائمتها مصفّاة برقمها؛ لا شاشة تحرير لها.
    if (reference.kind === 'sales_return' || reference.kind === 'purchase_return') {
        return row?.reference_no
            ? { name: reference.kind === 'sales_return' ? 'sales-returns' : 'purchase-returns', query: { search: row.reference_no } }
            : null;
    }

    if (reference.kind === 'repair') {
        return row?.reference_no
            ? { name: 'repairs', query: { search: row.reference_no } }
            : null;
    }

    if (!REFERENCE_ROUTES[reference.kind]) return null;

    return { name: REFERENCE_ROUTES[reference.kind], params: { id: reference.id } };
}

function applyFilters() {
    load();
}

function resetFilters() {
    activePreset.value = 'all';
    filters.from = '';
    filters.to = '';
    applyFilters();
}

async function loadAllRows() {
    if (loadingAll.value || !hasMore.value) return;
    loadingAll.value = true;
    try {
        while (hasMore.value) {
            await loadMore();
        }
    } finally {
        loadingAll.value = false;
    }
}

function printSheet() {
    window.print();
}

function exportCsv() {
    if (!statement.value) return;
    const partyName = statement.value.party?.name || 'statement';
    const filename = `كشف-حساب-${kind.label}-${partyName}-${filters.from || 'البداية'}-${filters.to || 'اليوم'}`;

    const columns = [
        { label: 'التاريخ', value: (row) => row.transaction_date },
        { label: 'نوع الحركة', value: (row) => row.type_label },
        { label: 'البيان والتفاصيل', value: (row) => row.description || '' },
        { label: 'المرجع', value: (row) => row.reference_no || '' },
        { label: 'مدين', value: (row) => (Number(row.debit) ? csvNumber(row.debit) : '') },
        { label: 'دائن', value: (row) => (Number(row.credit) ? csvNumber(row.credit) : '') },
        { label: 'الرصيد التراكمي', value: (row) => csvNumber(row.running_balance) },
    ];

    downloadCsv(filename, columns, statement.value.rows);
}

function loadPdf(paper) {
    const params = {};
    if (filters.from) params.from = filters.from;
    if (filters.to) params.to = filters.to;

    return kind.pdf(route.params.id, params, paper);
}

function load() {
    error.value = '';
    return reload();
}

onMounted(() => load());
</script>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm 10mm;
    }

    body {
        background: #fff !important;
        color: #000 !important;
    }

    #statement-sheet {
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    #statement-sheet table {
        font-size: 10px !important;
        border-collapse: collapse !important;
        width: 100% !important;
    }

    #statement-sheet thead {
        display: table-header-group !important;
    }

    #statement-sheet tr {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    #statement-sheet th,
    #statement-sheet td {
        border: 0.5px solid #cbd5e1 !important;
        padding: 4px 6px !important;
    }

    #statement-sheet thead th {
        background: #f1f5f9 !important;
        color: #000 !important;
    }

    #statement-sheet tfoot td {
        background: #f8fafc !important;
        font-weight: bold !important;
    }
}
</style>
