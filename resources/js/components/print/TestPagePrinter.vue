<template>
    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-3">
        <p class="text-sm font-semibold text-slate-800">صفحة تجريبية</p>
        <p class="mb-3 text-xs text-slate-500">
            فاتورة وهمية للتأكد من عرض الورق والهوامش وظهور العربية قبل طباعة مستند حقيقي.
        </p>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="paper in papers"
                :key="paper.value"
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-blue-400 hover:text-blue-700 disabled:opacity-40"
                :disabled="generating !== null"
                @click="printSample(paper.value)"
            >
                <PaperGlyph :paper="paper" :size="14" />
                {{ generating === paper.value ? 'جارٍ التجهيز…' : paper.label }}
            </button>
        </div>
        <p v-if="pdfError" class="mt-2 text-xs text-red-600">{{ pdfError }}</p>
    </div>
</template>

<script setup>
import PaperGlyph from '../ui/PaperGlyph.vue';
import { usePdf } from '../../composables/usePdf';
import { usePrintPreferences } from '../../composables/usePrintPreferences';
import { documentsApi } from '../../services/api';

const { papers } = usePrintPreferences();
const { generating, pdfError, openPdf } = usePdf();

function printSample(paper) {
    openPdf(paper, () => documentsApi.sample(paper));
}
</script>
