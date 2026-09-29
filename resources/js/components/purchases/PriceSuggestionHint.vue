<template>
    <div v-if="suggestion?.best" class="mt-1 space-y-0.5 text-xs">
        <p v-if="selectedIsBest" class="text-emerald-700">
            ✓ هذا المورد هو الأرخص لهذه المادة — آخر سعر
            <b class="tabular-nums">{{ formatMoney(best.unit_cost) }}</b>
            <span class="text-emerald-600/80">({{ formatDate(best.purchase_date) }})</span>
        </p>
        <p v-else class="text-blue-700">
            💡 أفضل سعر
            <b class="tabular-nums">{{ formatMoney(best.unit_cost) }}</b>
            لدى «{{ best.supplier_name }}»
            <span class="text-blue-600/80">({{ formatDate(best.purchase_date) }})</span>
            <template v-if="mine">
                · آخر سعر من المورد الحالي
                <b class="tabular-nums">{{ formatMoney(mine.unit_cost) }}</b>
            </template>
        </p>
        <button
            v-if="mine && Number(mine.unit_cost) !== Number(currentPrice)"
            type="button"
            class="font-semibold text-blue-700 underline decoration-dotted underline-offset-2 hover:text-blue-900"
            @click="$emit('apply', mine.unit_cost)"
        >
            استخدم آخر سعر من هذا المورد ({{ formatMoney(mine.unit_cost) }})
        </button>
    </div>
</template>

<script setup>
/**
 * تلميح السعر تحت سطر فاتورة الشراء.
 *
 * يُري أرخص مورد لهذه المادة حسب آخر فاتورة مُرحَّلة من كل مورد، ويُتيح نقل
 * آخر سعر من المورد المختار إلى السطر بنقرة. لا يكتب سعر مورد آخر على السطر:
 * ذلك رقم لم يَعرضه المورد الذي نشتري منه، وإنما يصلح للمساومة لا للتسجيل.
 */
import { computed } from 'vue';
import { formatDate, formatMoney } from '../../utils/format';

const props = defineProps({
    suggestion: { type: Object, default: null },
    supplierId: { type: [Number, String], default: null },
    currentPrice: { type: [Number, String], default: null },
});

defineEmits(['apply']);

const best = computed(() => props.suggestion?.best ?? null);
const mine = computed(() => props.suggestion?.selected_supplier ?? null);
const selectedIsBest = computed(
    () => Boolean(props.supplierId) && best.value?.supplier_id === Number(props.supplierId)
);
</script>
