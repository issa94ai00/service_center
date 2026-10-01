<template>
    <!-- The shop-wide print defaults. Bound straight to the settings form's
         values, so they save, undo and warn on leave with everything else. -->
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 select-none items-center justify-center rounded-xl bg-amber-100 text-lg" aria-hidden="true">🖨️</span>
            <div class="min-w-0 flex-1">
                <h2 class="font-bold text-slate-900">الطباعة</h2>
                <p class="text-xs text-slate-500">حجم الورق الافتراضي لكل مستند، والنصوص المطبوعة في أسفله</p>
            </div>
        </div>

        <!-- Paper per document kind -->
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-800">حجم الورق الافتراضي</legend>
            <div class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                <div
                    v-for="kind in DOCUMENT_KINDS"
                    :key="kind.key"
                    class="flex flex-col gap-2 p-3 md:flex-row md:items-center md:justify-between"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800">{{ kind.label }}</p>
                        <p class="text-xs text-slate-400">{{ kind.hint }}</p>
                        <p v-if="errors[paperKey(kind.key)]" class="mt-1 text-xs text-red-600">{{ errors[paperKey(kind.key)] }}</p>
                    </div>

                    <div class="flex flex-wrap gap-1.5" role="radiogroup" :aria-label="`حجم ورق ${kind.label}`">
                        <label
                            v-for="paper in papersFor(kind.key)"
                            :key="paper.value"
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs transition focus-within:ring-2 focus-within:ring-blue-200"
                            :class="values[paperKey(kind.key)] === paper.value
                                ? 'border-blue-500 bg-blue-50 font-semibold text-blue-800'
                                : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                        >
                            <input
                                v-model="values[paperKey(kind.key)]"
                                type="radio"
                                class="sr-only"
                                :name="paperKey(kind.key)"
                                :value="paper.value"
                            />
                            <PaperGlyph :paper="paper" :active="values[paperKey(kind.key)] === paper.value" :size="16" />
                            {{ paper.label }}
                        </label>
                    </div>
                </div>
            </div>
            <p class="mt-2 text-xs text-slate-400">
                يُطبَّق على كل الأجهزة، ويمكن لأي جهاز أن يتجاوزه لنفسه — مثل كمبيوتر الكاونتر الموصول بطابعة إيصالات.
            </p>
        </fieldset>

        <!-- Printed text -->
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div>
                <label for="print-footer-note" class="mb-1 block text-sm font-medium text-slate-700">ملاحظة أسفل المستندات</label>
                <textarea
                    id="print-footer-note"
                    v-model="values.print_footer_note"
                    rows="3"
                    :maxlength="LIMITS.print_footer_note"
                    placeholder="مثال: شكرًا لتعاملكم معنا — البضاعة المباعة لا تُرد بعد 7 أيام"
                    class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                ></textarea>
                <div class="mt-1 flex justify-between gap-2 text-xs">
                    <span :class="errors.print_footer_note ? 'text-red-600' : 'text-slate-400'">
                        {{ errors.print_footer_note || 'تُطبع في أسفل الفواتير والسندات والإيصالات' }}
                    </span>
                    <span class="shrink-0 tabular-nums" :class="counterClass('print_footer_note')">
                        {{ length('print_footer_note') }}/{{ LIMITS.print_footer_note }}
                    </span>
                </div>
            </div>

            <div>
                <label for="print-repair-terms" class="mb-1 block text-sm font-medium text-slate-700">شروط الصيانة</label>
                <textarea
                    id="print-repair-terms"
                    v-model="values.print_repair_terms"
                    rows="3"
                    :maxlength="LIMITS.print_repair_terms"
                    placeholder="مثال: لا يتحمّل المركز مسؤولية الأجهزة غير المستلمة بعد 30 يومًا"
                    class="w-full resize-y rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                ></textarea>
                <div class="mt-1 flex justify-between gap-2 text-xs">
                    <span :class="errors.print_repair_terms ? 'text-red-600' : 'text-slate-400'">
                        {{ errors.print_repair_terms || 'تُطبع على إيصال استلام الجهاز فوق توقيع العميل' }}
                    </span>
                    <span class="shrink-0 tabular-nums" :class="counterClass('print_repair_terms')">
                        {{ length('print_repair_terms') }}/{{ LIMITS.print_repair_terms }}
                    </span>
                </div>
            </div>
        </div>

        <label class="mt-4 flex cursor-pointer items-center gap-3">
            <input v-model="values.print_show_signatures" type="checkbox" class="peer sr-only" />
            <span
                class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-slate-300 transition-colors after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300 rtl:peer-checked:after:-translate-x-full"
                aria-hidden="true"
            ></span>
            <span>
                <span class="block text-sm text-slate-700">خانات التوقيع</span>
                <span class="block text-xs text-slate-400">أطفئها لإيصالات أقصر على الورق الحراري</span>
            </span>
        </label>

        <!-- Trying it out -->
        <div class="mt-5 space-y-3 border-t border-slate-100 pt-4">
            <TestPagePrinter />
            <p v-if="dirty" class="text-xs text-amber-700">
                الصفحة التجريبية تستخدم النصوص المحفوظة — احفظ أولًا لترى تعديلاتك عليها.
            </p>

            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                <span v-if="hasDeviceOverrides" class="text-violet-700">
                    هذا الجهاز يستخدم تفضيلات طباعة خاصة به تتقدّم على الإعدادات أعلاه.
                </span>
                <span v-else>هذا الجهاز يتبع الإعدادات أعلاه.</span>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 font-medium text-slate-700 transition hover:bg-slate-100"
                    @click="openPrintPreferences"
                >تفضيلات هذا الجهاز</button>
            </div>
        </div>
    </section>
</template>

<script setup>
import PaperGlyph from '../ui/PaperGlyph.vue';
import TestPagePrinter from './TestPagePrinter.vue';
import { DOCUMENT_KINDS, openPrintPreferences, usePrintPreferences } from '../../composables/usePrintPreferences';

const props = defineProps({
    /** The settings form's reactive values, edited in place. */
    values: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    dirty: { type: Boolean, default: false },
});

// Mirrors SettingUpdateRequest::MAX_LENGTH.
const LIMITS = { print_footer_note: 300, print_repair_terms: 1000 };

const { papersFor, hasDeviceOverrides } = usePrintPreferences();

const paperKey = (kind) => `print_paper_${kind}`;

function length(key) {
    return String(props.values[key] ?? '').length;
}

function counterClass(key) {
    const ratio = length(key) / LIMITS[key];
    if (ratio >= 1) return 'font-semibold text-red-600';
    if (ratio >= 0.85) return 'text-amber-600';
    return 'text-slate-300';
}
</script>
