<template>
    <div class="space-y-5 px-4 pb-4">
        <!-- Headline: the total, with the two ratios that qualify it. -->
        <div class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 lg:col-span-1">
                <div class="text-sm text-slate-500">{{ isSales ? 'إجمالي المبيعات' : 'إجمالي المشتريات' }}</div>
                <div class="mt-1 break-words text-2xl font-bold tabular-nums text-slate-900 sm:text-3xl">{{ formatUsd(summary.total) }}</div>
                <p class="mt-2 text-xs text-slate-500">
                    {{ formatCount(summary.count) }} فاتورة · متوسط الفاتورة {{ formatUsd(averageInvoice) }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:col-span-2">
                <div v-for="card in cards" :key="card.label" class="rounded-2xl border border-slate-200 p-5">
                    <div class="text-sm text-slate-500">{{ card.label }}</div>
                    <div class="mt-1 text-xl font-bold tabular-nums" :class="card.tone || 'text-slate-900'">
                        {{ card.value }}
                    </div>
                    <div v-if="card.hint" class="mt-0.5 text-xs text-slate-400">{{ card.hint }}</div>
                </div>
            </div>
        </div>

        <!-- Collection: paid against total, the question every summary is really asked for. -->
        <div class="rounded-2xl border border-slate-200 p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-sm font-bold text-slate-900">{{ isSales ? 'نسبة التحصيل' : 'نسبة السداد' }}</h3>
                <p class="text-xs text-slate-500">
                    <span class="font-semibold tabular-nums text-slate-700">{{ formatUsd(summary.paid) }}</span>
                    مدفوع من
                    <span class="font-semibold tabular-nums text-slate-700">{{ formatUsd(summary.total) }}</span>
                </p>
            </div>
            <div class="mt-2 flex items-center gap-3">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${paidRatio * 100}%` }"></div>
                </div>
                <span class="w-12 text-left text-sm font-bold tabular-nums text-slate-900">{{ formatPercent(paidRatio) }}</span>
            </div>
            <p v-if="Number(summary.outstanding) > 0" class="mt-2 text-xs text-amber-700">
                متبقٍ {{ formatUsd(summary.outstanding) }} على {{ isSales ? 'العملاء' : 'الموردين' }}
            </p>
        </div>

        <!-- Trend before the table: the shape of the period reads faster than 30 rows. -->
        <div v-if="points.length > 1" class="rounded-2xl border border-slate-200 p-5">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-bold text-slate-900">الحركة اليومية</h3>
                <span class="text-xs text-slate-400" dir="ltr">{{ summary.from }} — {{ summary.to }}</span>
            </div>
            <TrendChart :points="points" :series="series" :format-value="formatUsd" :height="220" />
        </div>

        <div>
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-sm font-bold text-slate-900">التفصيل حسب التاريخ</h3>
                <p v-if="busiestDay" class="text-xs text-slate-500">
                    أعلى يوم
                    <span dir="ltr" class="font-medium text-slate-700">{{ busiestDay.date }}</span>
                    · {{ formatUsd(busiestDay.total) }}
                </p>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full min-w-[560px] text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">التاريخ</th>
                            <th scope="col" class="px-4 py-3 text-left">العدد</th>
                            <th scope="col" class="px-4 py-3 text-left">الإجمالي</th>
                            <th scope="col" class="px-4 py-3 text-left">المدفوع</th>
                            <th scope="col" class="px-4 py-3 text-left">المتبقي</th>
                            <th scope="col" class="px-4 py-3 print:hidden">الحصة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="day in days" :key="day.date" class="transition hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-700" dir="ltr">{{ day.date }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-slate-600">{{ formatCount(day.count) }}</td>
                            <td class="px-4 py-3 text-left font-semibold tabular-nums text-slate-900">{{ formatUsd(day.total) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums text-emerald-700">{{ formatUsd(day.paid) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums" :class="Number(day.outstanding) > 0 ? 'text-amber-700' : 'text-slate-400'">
                                {{ formatUsd(day.outstanding) }}
                            </td>
                            <td class="px-4 py-3 print:hidden">
                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-blue-500" :style="shareStyle(day.total)"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-semibold text-slate-900">
                        <tr>
                            <td class="px-4 py-3">الإجمالي</td>
                            <td class="px-4 py-3 text-left tabular-nums">{{ formatCount(summary.count) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums">{{ formatUsd(summary.total) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums">{{ formatUsd(summary.paid) }}</td>
                            <td class="px-4 py-3 text-left tabular-nums">{{ formatUsd(summary.outstanding) }}</td>
                            <td class="print:hidden"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import TrendChart from '../charts/TrendChart.vue';
import { formatUsd, formatPercent } from '../../utils/format';

const props = defineProps({
    summary: { type: Object, required: true },
    kind: { type: String, required: true },
});

const counter = new Intl.NumberFormat('ar-SY-u-nu-latn');

const isSales = computed(() => props.kind === 'sales');

const days = computed(() => props.summary.by_day ?? []);

const discountShare = computed(() => {
    const subtotal = Number(props.summary.subtotal) || 0;
    const discount = Number(props.summary.discount) || 0;
    if (subtotal <= 0 || discount <= 0) return '';
    return `${formatPercent(discount / subtotal)} من المجموع`;
});

const cards = computed(() => [
    { label: 'المجموع قبل الخصم', value: formatUsd(props.summary.subtotal) },
    {
        label: 'الخصم',
        value: formatUsd(props.summary.discount),
        tone: Number(props.summary.discount) > 0 ? 'text-red-600' : 'text-slate-900',
        hint: discountShare.value,
    },
    { label: 'المدفوع', value: formatUsd(props.summary.paid), tone: 'text-emerald-700' },
    {
        label: 'المتبقي',
        value: formatUsd(props.summary.outstanding),
        tone: Number(props.summary.outstanding) > 0 ? 'text-amber-700' : 'text-slate-900',
    },
]);

const averageInvoice = computed(() => {
    const count = Number(props.summary.count) || 0;
    return count ? (Number(props.summary.total) || 0) / count : 0;
});

const paidRatio = computed(() => {
    const total = Number(props.summary.total) || 0;
    if (total <= 0) return 0;
    return Math.max(0, Math.min(1, (Number(props.summary.paid) || 0) / total));
});

const busiestDay = computed(() => {
    if (!days.value.length) return null;
    return days.value.reduce((best, day) => (Number(day.total) > Number(best.total) ? day : best));
});

const maxDayTotal = computed(() =>
    days.value.reduce((max, day) => Math.max(max, Number(day.total) || 0), 0)
);

const series = computed(() => [
    { key: 'total', label: 'الإجمالي', color: '#2a78d6' },
    { key: 'paid', label: 'المدفوع', color: '#10b981' },
]);

const points = computed(() =>
    days.value.map((day) => ({
        key: day.date,
        // Day-and-month is enough to place a point once the period is labelled.
        label: day.date.slice(5),
        total: Number(day.total) || 0,
        paid: Number(day.paid) || 0,
    }))
);

function formatCount(value) {
    return counter.format(Number(value) || 0);
}

function shareStyle(total) {
    const max = maxDayTotal.value || 1;
    const ratio = Math.max(0.02, (Number(total) || 0) / max);
    return { width: `${ratio * 100}%` };
}
</script>
