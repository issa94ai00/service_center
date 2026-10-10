<template>
    <div>
        <div class="grid gap-3 px-4 pb-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">عدد الحسابات</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ formatCount(data.totals.parties) }}</div>
            </div>
            <div class="rounded-xl border p-4" :class="isReceivables ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                <div class="text-xs" :class="isReceivables ? 'text-emerald-800' : 'text-amber-800'">
                    {{ isReceivables ? 'إجمالي المستحق لنا' : 'إجمالي المستحق علينا' }}
                </div>
                <div class="mt-1 text-2xl font-bold tabular-nums" :class="isReceivables ? 'text-emerald-900' : 'text-amber-900'">
                    {{ formatUsd(data.totals.balance) }}
                </div>
                <div v-if="asOf" class="mt-0.5 text-[11px] opacity-70" dir="ltr">{{ asOf }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="text-xs text-slate-500">متوسط الرصيد</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ formatUsd(averageBalance) }}</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-right text-sm">
                <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3">الاسم</th>
                        <th scope="col" class="px-4 py-3">الشركة</th>
                        <th scope="col" class="px-4 py-3">الهاتف</th>
                        <th scope="col" class="px-4 py-3 text-left">الرصيد الافتتاحي</th>
                        <th scope="col" class="px-4 py-3 text-left">الرصيد</th>
                        <th scope="col" class="px-4 py-3 print:hidden">الحصة</th>
                        <th scope="col" class="px-4 py-3 print:hidden"><span class="sr-only">إجراءات</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in rows" :key="row.id" class="transition hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ row.name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ row.company || '—' }}</td>
                        <td class="px-4 py-3 text-slate-600" dir="ltr">
                            <!-- On a phone this row is one tap from a collection call. -->
                            <a v-if="row.phone" :href="`tel:${row.phone}`" class="hover:text-slate-900 hover:underline">{{ row.phone }}</a>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-left tabular-nums text-slate-500">{{ formatUsd(row.opening_balance) }}</td>
                        <td class="px-4 py-3 text-left font-semibold tabular-nums text-slate-900">{{ formatUsd(row.balance) }}</td>
                        <td class="px-4 py-3 print:hidden">
                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full" :style="shareStyle(row.balance)"></div>
                            </div>
                        </td>
                        <td class="px-4 py-3 print:hidden">
                            <RouterLink
                                :to="statementRoute(row.id)"
                                class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                            >
                                كشف الحساب
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { formatUsd } from '../../utils/format';

const props = defineProps({
    data: { type: Object, required: true },
    kind: { type: String, required: true },
    asOf: { type: String, default: '' },
});

const counter = new Intl.NumberFormat('ar-SY-u-nu-latn');

const isReceivables = computed(() => props.kind === 'receivables');

const rows = computed(() => props.data.rows ?? []);

const averageBalance = computed(() => {
    const parties = Number(props.data.totals?.parties) || 0;
    return parties ? (Number(props.data.totals?.balance) || 0) / parties : 0;
});

// Shares compare against the largest balance on this page — the list is already
// sorted by balance, so that is the row sitting at the top of it.
const maxBalance = computed(() =>
    rows.value.reduce((max, row) => Math.max(max, Number(row.balance) || 0), 0)
);

function formatCount(value) {
    return counter.format(Number(value) || 0);
}

function shareStyle(balance) {
    const max = maxBalance.value || 1;
    const ratio = Math.max(0.02, (Number(balance) || 0) / max);
    return {
        width: `${ratio * 100}%`,
        background: isReceivables.value ? '#10b981' : '#f59e0b',
    };
}

function statementRoute(id) {
    return isReceivables.value
        ? { name: 'customers.statement', params: { id } }
        : { name: 'suppliers.statement', params: { id } };
}
</script>
