<template>
    <div class="flex h-full flex-col gap-5">
        <!-- Devices in the shop right now, split by where they are stuck. -->
        <div>
            <div class="flex items-baseline justify-between gap-3">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">أجهزة داخل الورشة الآن</p>
                <p class="text-3xl font-extrabold tabular-nums tracking-tight text-slate-900 dark:text-white">{{ active }}</p>
            </div>
            <div class="mt-3 flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                <div
                    v-for="stage in stages"
                    v-show="stage.count > 0"
                    :key="stage.key"
                    class="h-full first:rounded-s-full last:rounded-e-full transition-all duration-500"
                    :style="{ width: `${(stage.count / (active || 1)) * 100}%`, background: stage.color }"
                ></div>
            </div>
        </div>

        <ul class="-mx-2 space-y-0.5">
            <li v-for="stage in stages" :key="stage.key">
                <component
                    :is="linkable ? RouterLink : 'div'"
                    :to="linkable ? { name: 'repairs', query: stage.status ? { status: stage.status } : {} } : undefined"
                    class="group flex items-center justify-between gap-3 rounded-xl px-2 py-2 transition"
                    :class="linkable ? 'hover:bg-slate-50 dark:hover:bg-slate-800/60' : ''"
                >
                    <span class="flex min-w-0 items-center gap-2.5">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[15px]" :class="stage.chip">
                            <AppIcon :name="stage.icon" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ stage.label }}</span>
                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ stage.hint }}</span>
                        </span>
                    </span>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="text-base font-bold tabular-nums text-slate-900 dark:text-white">{{ stage.count }}</span>
                        <AppIcon v-if="linkable" name="chevron-left" class="text-sm text-slate-300 dark:text-slate-600 transition group-hover:text-slate-500 ltr:rotate-180 print:hidden" />
                    </span>
                </component>
            </li>
        </ul>

        <!-- What moved through the workshop during the selected period. -->
        <dl class="mt-auto grid grid-cols-2 gap-3 border-t border-slate-100 dark:border-slate-800 pt-4">
            <div>
                <dt class="text-xs text-slate-500 dark:text-slate-400">مستلمة في الفترة</dt>
                <dd class="mt-0.5 text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ workshop.received_count }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500 dark:text-slate-400">مسلّمة في الفترة</dt>
                <dd class="mt-0.5 text-lg font-bold tabular-nums text-slate-900 dark:text-white">
                    {{ workshop.delivered_count }}
                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ formatUsd(workshop.delivered_revenue) }}</span>
                </dd>
            </div>
        </dl>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from '../ui/AppIcon.vue';
import { formatUsd } from '../../utils/format';

const props = defineProps({
    /** The `workshop` block of the dashboard payload. */
    workshop: { type: Object, required: true },
    linkable: { type: Boolean, default: false },
});

const active = computed(() => Number(props.workshop.active_in_shop) || 0);

const stages = computed(() => {
    const ready = Number(props.workshop.ready_for_pickup) || 0;
    const waiting = Number(props.workshop.waiting_approval) || 0;

    return [
        {
            key: 'working',
            label: 'قيد الفحص والإصلاح',
            hint: 'على طاولة الخبراء',
            // Whatever is active but neither waiting on the customer nor ready.
            count: Math.max(0, active.value - ready - waiting),
            status: '',
            icon: 'wrench',
            color: '#6366f1',
            chip: 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400',
        },
        {
            key: 'waiting',
            label: 'بانتظار موافقة العميل',
            hint: 'تحتاج متابعة هاتفية',
            count: waiting,
            status: 'WAITING_APPROVAL',
            icon: 'clock',
            color: '#f59e0b',
            chip: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400',
        },
        {
            key: 'ready',
            label: 'جاهزة للتسليم',
            hint: 'أبلغ العميل بالاستلام',
            count: ready,
            status: 'READY',
            icon: 'check-circle',
            color: '#10b981',
            chip: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400',
        },
    ];
});
</script>
