<template>
    <div class="overflow-x-auto pb-4">
        <!-- Kanban Columns Container -->
        <div class="flex gap-4 min-w-[1280px] items-start p-1">
            <div
                v-for="col in kanbanColumns"
                :key="col.value"
                class="flex-1 min-w-[270px] max-w-[320px] rounded-2xl bg-slate-100/80 border border-slate-200/90 flex flex-col max-h-[calc(100vh-280px)]"
            >
                <!-- Column Header -->
                <div class="p-3.5 border-b border-slate-200/80 flex items-center justify-between bg-white rounded-t-2xl">
                    <div class="flex items-center gap-2">
                        <span class="text-base">{{ col.icon }}</span>
                        <h3 class="text-xs font-bold text-slate-800 tracking-wide">{{ col.label }}</h3>
                    </div>
                    <span
                        class="px-2 py-0.5 rounded-full text-xs font-bold tabular-nums"
                        :class="colCards(col.value).length ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-400'"
                    >
                        {{ colCards(col.value).length }}
                    </span>
                </div>

                <!-- Column Cards List -->
                <div class="p-2 space-y-2.5 overflow-y-auto flex-1 min-h-[140px]">
                    <div
                        v-if="!colCards(col.value).length"
                        class="h-28 flex flex-col items-center justify-center text-center p-3 text-slate-400 text-xs border-2 border-dashed border-slate-200 rounded-xl"
                    >
                        <span>لا توجد أجهزة في هذه المرحلة</span>
                    </div>

                    <article
                        v-for="item in colCards(col.value)"
                        :key="item.id"
                        class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm hover:shadow-md hover:border-blue-400 transition-all space-y-2.5 relative group"
                    >
                        <!-- Top Bar: Receipt & Date -->
                        <div class="flex items-center justify-between text-xs">
                            <button
                                type="button"
                                @click="$emit('expand', item.id)"
                                class="font-mono font-bold text-blue-700 hover:underline flex items-center gap-1"
                                dir="ltr"
                            >
                                <span>{{ item.receipt_no }}</span>
                            </button>
                            <span class="text-[11px] text-slate-400">
                                {{ relativeDays(item.received_at) }}
                            </span>
                        </div>

                        <!-- Device Info -->
                        <div>
                            <h4 class="text-sm font-bold text-slate-900 leading-tight">
                                {{ [item.brand, item.model].filter(Boolean).join(' ') || item.device_type }}
                            </h4>
                            <p v-if="item.color" class="text-[11px] text-slate-500 mt-0.5">اللون: {{ item.color }}</p>
                            <p class="text-xs text-slate-700 mt-1 line-clamp-2 bg-slate-50 p-1.5 rounded-lg border border-slate-100">
                                {{ item.reported_issue }}
                            </p>
                        </div>

                        <!-- Customer & Technician -->
                        <div class="pt-1 border-t border-slate-100 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1 text-slate-700 truncate max-w-[150px]">
                                <span class="text-slate-400">👤</span>
                                <span class="font-medium truncate">{{ item.customer?.name || 'عميل نقدي' }}</span>
                            </div>
                            <span
                                v-if="item.technician"
                                class="px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 text-[10px] font-semibold border border-purple-100 truncate max-w-[100px]"
                                :title="item.technician.name"
                            >
                                🔧 {{ item.technician.name }}
                            </span>
                            <span v-else class="text-[10px] text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                غير مسند
                            </span>
                        </div>

                        <!-- Balance & Due -->
                        <div class="flex items-center justify-between text-xs pt-1">
                            <span class="font-mono font-bold text-slate-900 tabular-nums">
                                {{ item.chargeable_cost ? formatMoney(item.chargeable_cost) : '—' }}
                            </span>
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                :class="dueBadgeClass(item)"
                            >
                                {{ dueLabel(item) }}
                            </span>
                        </div>

                        <!-- Card Action Toolbar -->
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-1">
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    class="p-1 rounded text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition-colors"
                                    title="تفاصيل"
                                    @click="$emit('expand', item.id)"
                                >
                                    🔍
                                </button>
                                <button
                                    v-if="canEdit"
                                    type="button"
                                    class="p-1 rounded text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition-colors"
                                    title="تعديل"
                                    @click="$emit('edit', item)"
                                >
                                    ✏️
                                </button>
                                <button
                                    v-if="canSettle"
                                    type="button"
                                    class="p-1 rounded text-emerald-600 hover:bg-emerald-50 transition-colors text-xs font-semibold"
                                    title="تسوية / قبض"
                                    @click="$emit('settle', item)"
                                >
                                    💵
                                </button>
                            </div>

                            <!-- Next Status Advance Button -->
                            <button
                                v-if="canEdit && getNextStatus(item.status)"
                                type="button"
                                :disabled="statusBusy === item.id"
                                @click="$emit('change-status', item, getNextStatus(item.status).value)"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all flex items-center gap-1 shadow-sm"
                                :class="getNextStatusTone(item.status)"
                            >
                                <span>{{ getNextStatus(item.status).short }}</span>
                                <span class="rtl:rotate-180">→</span>
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { REPAIR_STATUSES, nextStatus } from '../../constants/repairStatus';
import { formatMoney, relativeDays } from '../../utils/format';

const props = defineProps({
    items: { type: Array, required: true },
    statusBusy: { type: [Number, String], default: null },
    canEdit: { type: Boolean, default: false },
    canSettle: { type: Boolean, default: false },
});

defineEmits(['expand', 'edit', 'settle', 'change-status']);

// The 6 operational stages for workshop boards
const kanbanColumns = [
    { value: 'RECEIVED', label: 'مستلَم', icon: '📥' },
    { value: 'DIAGNOSING', label: 'قيد الفحص', icon: '🔎' },
    { value: 'WAITING_APPROVAL', label: 'بانتظار الموافقة', icon: '⏳' },
    { value: 'UNDER_REPAIR', label: 'قيد الإصلاح', icon: '🛠️' },
    { value: 'READY', label: 'جاهز للتسليم', icon: '✅' },
    { value: 'DELIVERED', label: 'تم التسليم', icon: '📦' },
];

function colCards(statusValue) {
    return props.items.filter((item) => item.status === statusValue);
}

function getNextStatus(status) {
    return nextStatus(status);
}

function getNextStatusTone(status) {
    if (status === 'READY') {
        return 'bg-emerald-600 hover:bg-emerald-700 text-white';
    }
    return 'bg-blue-600 hover:bg-blue-700 text-white';
}

function dueLabel(item) {
    const due = Number(item.due ?? 0);
    const paid = Number(item.paid ?? 0);
    if (Math.abs(due) < 0.005) return 'مدفوع بالكامل';
    if (due < 0) return `رصيد للعميل ${formatMoney(Math.abs(due))}`;
    return `متبقٍ ${formatMoney(due)}`;
}

function dueBadgeClass(item) {
    const due = Number(item.due ?? 0);
    if (Math.abs(due) < 0.005) return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    return due < 0 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-amber-50 text-amber-700 border border-amber-200';
}
</script>
