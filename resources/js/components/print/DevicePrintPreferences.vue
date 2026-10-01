<template>
    <div class="space-y-5">
        <p class="text-xs leading-relaxed text-slate-500">
            تُحفظ هذه الاختيارات في هذا المتصفح فقط — مفيدة حين يكون على هذا الجهاز طابعة مختلفة عن باقي
            الأجهزة، كطابعة إيصالات حرارية على كاونتر الاستلام. ما لا تختاره هنا يتبع الإعداد الافتراضي للمحل.
        </p>

        <!-- How a print button behaves -->
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-800">عند الضغط على «طباعة»</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <label
                    v-for="option in modes"
                    :key="option.value"
                    class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                    :class="mode === option.value ? 'border-blue-500 bg-blue-50/60 ring-1 ring-blue-500' : 'border-slate-200 hover:border-slate-300'"
                >
                    <input
                        type="radio"
                        name="print-mode"
                        class="mt-0.5 accent-blue-600"
                        :value="option.value"
                        :checked="mode === option.value"
                        @change="setMode(option.value)"
                    />
                    <span>
                        <span class="block text-sm font-medium text-slate-800">{{ option.label }}</span>
                        <span class="block text-xs text-slate-500">{{ option.hint }}</span>
                    </span>
                </label>
            </div>
        </fieldset>

        <!-- Paper per document kind -->
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-800">حجم الورق على هذا الجهاز</legend>
            <div class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                <div
                    v-for="kind in DOCUMENT_KINDS"
                    :key="kind.key"
                    class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800">{{ kind.label }}</p>
                        <p class="text-xs text-slate-400">{{ kind.hint }}</p>
                    </div>
                    <select
                        :id="`device-paper-${kind.key}`"
                        :value="deviceOverride(kind.key) || ''"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 sm:w-56"
                        :class="deviceOverride(kind.key) ? 'font-semibold text-violet-700' : 'text-slate-600'"
                        :aria-label="`حجم ورق ${kind.label}`"
                        @change="setDevicePaper(kind.key, $event.target.value)"
                    >
                        <option value="">افتراضي المحل ({{ paperInfo(shopDefault(kind.key)).label }})</option>
                        <option v-for="paper in papersFor(kind.key)" :key="paper.value" :value="paper.value">
                            {{ paper.label }}
                        </option>
                    </select>
                </div>
            </div>
        </fieldset>

        <!-- Lining the printer up -->
        <TestPagePrinter />

        <div v-if="hasDeviceOverrides" class="flex justify-end">
            <button
                type="button"
                class="text-xs text-slate-500 underline-offset-2 hover:text-red-600 hover:underline"
                @click="resetDevice"
            >إعادة هذا الجهاز إلى إعدادات المحل</button>
        </div>
    </div>
</template>

<script setup>
import TestPagePrinter from './TestPagePrinter.vue';
import { DOCUMENT_KINDS, usePrintPreferences } from '../../composables/usePrintPreferences';

const {
    papersFor, paperInfo, shopDefault, deviceOverride, setDevicePaper, setMode, resetDevice, mode, hasDeviceOverrides,
} = usePrintPreferences();

const modes = [
    { value: 'preview', label: 'معاينة أولًا', hint: 'يفتح المستند في تبويب جديد، ثم تطبعه أو تحفظه' },
    { value: 'direct', label: 'طباعة مباشرة', hint: 'تفتح نافذة الطابعة فورًا — الأسرع على الكاونتر' },
];
</script>
