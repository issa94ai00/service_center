<template>
    <div>
        <!-- الملخّص: صف لكل خبير -->
        <table v-if="kind === 'technicians'" class="w-full text-right text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                <tr>
                    <th class="px-4 py-3">الخبير</th>
                    <th class="px-4 py-3">التخصّص</th>
                    <th class="px-4 py-3 text-left">النسبة</th>
                    <th class="px-4 py-3 text-left">بلاغات الفترة</th>
                    <th class="px-4 py-3 text-left">إيراد البلاغات</th>
                    <th class="px-4 py-3 text-left">استحقّ في الفترة</th>
                    <th class="px-4 py-3 text-left">صُرف في الفترة</th>
                    <th class="px-4 py-3 text-left">الرصيد المستحق له</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-900">
                        <RouterLink
                            v-if="canSeeStatement"
                            :to="{ name: 'technicians.statement', params: { id: row.id }, query: statementQuery }"
                            class="hover:underline"
                        >
                            {{ row.name }}
                        </RouterLink>
                        <span v-else>{{ row.name }}</span>
                        <span v-if="!row.is_active" class="ms-1 text-xs text-slate-400">(موقوف)</span>
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ row.specialty || '—' }}</td>
                    <td class="px-4 py-3 text-left tabular-nums text-slate-600">
                        {{ formatPercent(row.commission_rate / 100) }}
                    </td>
                    <td class="px-4 py-3 text-left tabular-nums text-slate-700">{{ row.repairs_count }}</td>
                    <td class="px-4 py-3 text-left tabular-nums text-slate-700">{{ formatMoney(row.revenue) }}</td>
                    <td class="px-4 py-3 text-left tabular-nums font-medium text-slate-900">
                        {{ formatMoney(row.commission) }}
                    </td>
                    <td class="px-4 py-3 text-left tabular-nums text-slate-700">{{ formatMoney(row.paid) }}</td>
                    <td
                        class="px-4 py-3 text-left font-semibold tabular-nums"
                        :class="balanceTone(row.balance)"
                    >
                        {{ formatMoney(row.balance) }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-sm font-semibold">
                <tr>
                    <td class="px-4 py-3" colspan="3">الإجمالي — {{ totals.technicians }} خبير</td>
                    <td class="px-4 py-3 text-left tabular-nums">{{ totals.repairs_count }}</td>
                    <td class="px-4 py-3 text-left tabular-nums">{{ formatMoney(totals.revenue) }}</td>
                    <td class="px-4 py-3 text-left tabular-nums">{{ formatMoney(totals.commission) }}</td>
                    <td class="px-4 py-3 text-left tabular-nums">{{ formatMoney(totals.paid) }}</td>
                    <td class="px-4 py-3 text-left tabular-nums" :class="balanceTone(totals.balance)">
                        {{ formatMoney(totals.balance) }}
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- التفصيل: صف لكل بلاغ مسوّى -->
        <table v-else class="w-full text-right text-sm">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                <tr>
                    <th class="px-4 py-3">الإيصال</th>
                    <th class="px-4 py-3">تاريخ التسوية</th>
                    <th class="px-4 py-3">الخبير</th>
                    <th class="px-4 py-3">العميل</th>
                    <th class="px-4 py-3">الجهاز</th>
                    <th class="px-4 py-3 text-left">التكلفة النهائية</th>
                    <th class="px-4 py-3 text-left">النسبة</th>
                    <th class="px-4 py-3 text-left">نصيب الخبير</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="row in rows" :key="row.id" class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-900" dir="ltr">
                        <RouterLink
                            :to="{ name: 'repairs', query: { search: row.receipt_no } }"
                            class="hover:underline"
                        >
                            {{ row.receipt_no }}
                        </RouterLink>
                    </td>
                    <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.settled_at || '—' }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ row.technician || '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ row.customer || '—' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ row.device }}</td>
                    <td class="px-4 py-3 text-left tabular-nums text-slate-700">{{ formatMoney(row.final_cost) }}</td>
                    <td class="px-4 py-3 text-left tabular-nums text-slate-500">
                        {{ formatPercent(row.commission_rate / 100) }}
                    </td>
                    <td class="px-4 py-3 text-left font-semibold tabular-nums text-slate-900">
                        {{ formatMoney(row.commission_amount) }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-sm font-semibold">
                <tr>
                    <td class="px-4 py-3" colspan="5">الإجمالي — {{ totals.repairs_count }} بلاغ</td>
                    <td class="px-4 py-3 text-left tabular-nums">{{ formatMoney(totals.revenue) }}</td>
                    <td class="px-4 py-3"></td>
                    <td class="px-4 py-3 text-left tabular-nums">{{ formatMoney(totals.commission) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { usePermissions } from '../../composables/usePermissions';
import { formatMoney, formatPercent } from '../../utils/format';

const props = defineProps({
    data: { type: Object, default: null },
    kind: { type: String, required: true },
    from: { type: String, default: '' },
    to: { type: String, default: '' },
});

const { can } = usePermissions();

const canSeeStatement = computed(() => can('statements.view'));

const rows = computed(() => props.data?.rows ?? []);
const totals = computed(() => props.data?.totals ?? {});

/** الفترة تنتقل مع الرابط، فيفتح كشف الخبير على المدى نفسه لا على الكل. */
const statementQuery = computed(() => {
    const query = {};
    if (props.from) query.from = props.from;
    if (props.to) query.to = props.to;
    return query;
});

// الموجب يعني أن المحل ما زال مديناً للخبير؛ الصفر حساب مسوّى.
function balanceTone(value) {
    const amount = Number(value) || 0;
    if (amount > 0) return 'text-amber-700';
    return amount < 0 ? 'text-red-600' : 'text-slate-400';
}
</script>
