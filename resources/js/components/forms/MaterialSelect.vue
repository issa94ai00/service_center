<template>
    <div class="relative">
        <!-- المختارة تُعرض كبطاقة لا كنصّ في مربّع: مربّع النص يترك «هل اختير
             شيء؟» سؤالاً مفتوحاً كلّما عُدِّل نصّه دون إعادة اختيار. -->
        <div
            v-if="material"
            class="flex items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1.5"
        >
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900">
                    {{ material.name }}
                    <span v-if="material.is_active === false" class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">
                        غير مفعّلة
                    </span>
                </p>
                <p class="truncate text-[11px] text-slate-400" dir="ltr">
                    {{ material.barcode || material.sku }}
                </p>
            </div>
            <button
                type="button"
                class="shrink-0 rounded-md px-2 py-1 text-xs font-medium text-blue-600 transition hover:bg-blue-50"
                @click="startChanging"
            >
                تغيير
            </button>
        </div>

        <div v-else>
            <input
                ref="searchInput"
                v-model="query"
                type="text"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open"
                :aria-controls="listboxId"
                autocomplete="off"
                placeholder="ابحث بالاسم أو الرمز..."
                class="w-full rounded-lg border px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2"
                :class="invalid
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                    : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100'"
                @focus="onFocus"
                @input="onInput"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="onEnter"
                @keydown.esc="open = false"
                @blur="onBlur"
            />

            <div
                v-if="open"
                class="absolute z-30 mt-1 w-full min-w-64 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
            >
                <ul :id="listboxId" role="listbox" class="max-h-60 overflow-y-auto py-1">
                    <li
                        v-for="(m, i) in results"
                        :key="m.id"
                        role="option"
                        :aria-selected="i === highlight"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 px-3 py-2 text-right text-sm transition hover:bg-blue-50"
                            :class="i === highlight ? 'bg-blue-50' : ''"
                            @mousedown.prevent
                            @click="select(m)"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-slate-800">{{ m.name }}</span>
                                <span class="block truncate text-[11px] text-slate-400" dir="ltr">{{ m.barcode || m.sku }}</span>
                            </span>
                            <span class="shrink-0 text-xs tabular-nums text-slate-500">{{ formatMoney(m.cost_price) }}</span>
                        </button>
                    </li>

                    <li v-if="loading && !results.length" class="px-3 py-3 text-center text-sm text-slate-400">
                        جارٍ البحث...
                    </li>
                    <li v-else-if="!results.length" class="px-3 py-3 text-sm text-slate-400">
                        {{ query.trim().length >= 2 ? `لا مادة تطابق «${query.trim()}»` : 'اكتب حرفين على الأقل' }}
                    </li>
                </ul>

                <!-- متاح دائماً لا عند خلوّ النتائج فقط: مادة تشبه اسم أخرى
                     تظلّ بحاجة إلى طريق واحد لإضافتها. -->
                <div v-if="canCreate" class="border-t border-slate-200">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2.5 text-right text-sm font-medium text-blue-600 transition hover:bg-blue-50"
                        :class="highlight === results.length ? 'bg-blue-50' : ''"
                        @mousedown.prevent
                        @click="requestCreate"
                    >
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold">+</span>
                        <span class="truncate">
                            {{ query.trim() ? `مادة جديدة «${query.trim()}»` : 'مادة جديدة' }}
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, useId } from 'vue';
import { materialsApi } from '../../services/api';
import { usePermissions } from '../../composables/usePermissions';
import { formatMoney } from '../../utils/format';

const props = defineProps({
    /** المادة المختارة كاملة، لا رقمها فقط: السطر يعرض اسمها ورمزها وتكلفتها. */
    material: { type: Object, default: null },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'create']);

const listboxId = `material-select-${useId()}`;
const { can } = usePermissions();
const canCreate = can('materials.create');

const searchInput = ref(null);
const query = ref('');
const results = ref([]);
const loading = ref(false);
const open = ref(false);
const highlight = ref(0);

let timer = null;
let sequence = 0;

function onFocus() {
    if (query.value.trim().length >= 2 || canCreate) open.value = true;
}

function onBlur() {
    // مهلة قصيرة: النقر على نتيجة يسبقه blur، وإغلاق القائمة فوراً يُفقده هدفه.
    setTimeout(() => { open.value = false; }, 120);
}

function onInput() {
    open.value = true;
    highlight.value = 0;
    clearTimeout(timer);

    if (query.value.trim().length < 2) {
        results.value = [];
        loading.value = false;
        return;
    }

    loading.value = true;
    timer = setTimeout(search, 250);
}

async function search() {
    const token = ++sequence;
    const term = query.value.trim();

    try {
        const { data } = await materialsApi.index({ search: term, per_page: 8 });
        // استجابة متأخّرة لبحث قديم يجب ألّا تكتب فوق نتيجة أحدث منها.
        if (token !== sequence) return;
        results.value = data ?? [];
    } catch {
        if (token === sequence) results.value = [];
    } finally {
        if (token === sequence) loading.value = false;
    }
}

function move(step) {
    open.value = true;
    const last = results.value.length + (canCreate ? 1 : 0);
    if (!last) return;
    highlight.value = (highlight.value + step + last) % last;
}

function onEnter() {
    if (highlight.value === results.value.length && canCreate) {
        requestCreate();
        return;
    }

    const row = results.value[highlight.value];
    if (row) select(row);
}

function select(m) {
    query.value = '';
    results.value = [];
    open.value = false;
    emit('select', m);
}

function requestCreate() {
    open.value = false;
    emit('create', query.value.trim());
}

function startChanging() {
    emit('select', null);
    query.value = '';
    results.value = [];
    // مربّع البحث لا يوجد قبل أن يُستبدل عرض البطاقة به.
    nextTick(() => searchInput.value?.focus());
}

function focus() {
    searchInput.value?.focus();
}

defineExpose({ focus });

onBeforeUnmount(() => clearTimeout(timer));
</script>
