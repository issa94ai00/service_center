<template>
    <!-- السند بعملة غير الدولار: ما سُلِّم فعلاً وبأي سعر، تحت رقمه بالدولار. -->
    <span
        v-if="isForeign(row)"
        class="block text-[11px] font-normal tabular-nums text-slate-500"
        :title="`سعر الصرف: 1 ${BASE_SYMBOL} = ${formatRate(row.exchange_rate)} ${currency?.symbol ?? row.currency_code}`"
    >
        {{ formatIn(row.currency_amount, currency ?? { code: row.currency_code, decimals: 2 }) }}
        <span class="text-slate-400">× {{ formatRate(row.exchange_rate) }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { BASE_SYMBOL } from '../../constants/currency';
import { formatIn, formatRate, isForeign, useCurrencies } from '../../composables/useCurrencies';

const props = defineProps({
    /** A payment or expense row: currency_code, currency_amount, exchange_rate. */
    row: { type: Object, required: true },
});

const { find } = useCurrencies();
const currency = computed(() => find(props.row.currency_code));
</script>
