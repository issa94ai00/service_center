<template>
    <div>
        <div class="flex gap-1.5">
            <div class="relative min-w-0 flex-1">
                <input
                    ref="input"
                    :value="foreign ? currencyAmount : amount"
                    type="number"
                    :step="step"
                    :min="step"
                    inputmode="decimal"
                    :placeholder="foreign && selected?.decimals === 0 ? '0' : '0.00'"
                    class="w-full rounded-lg border px-3 py-2 pe-10 text-lg font-bold tabular-nums focus:outline-none focus:ring-2"
                    :class="invalid ? INVALID_FIELD : VALID_FIELD"
                    :aria-label="foreign ? `المبلغ بـ${selected?.name ?? currencyCode}` : 'المبلغ بالدولار'"
                    @input="onInput($event.target.value)"
                />
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-400">
                    {{ selected?.symbol ?? BASE_SYMBOL }}
                </span>
            </div>

            <!-- العملة تظهر فقط حين يوجد ما يُختار: محلّ يعمل بالدولار وحده
                 لا يرى قائمة بخيار واحد. -->
            <select
                v-if="showPicker"
                :value="currencyCode"
                class="w-20 shrink-0 rounded-lg border border-slate-300 px-2 py-2 text-sm font-semibold focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                aria-label="العملة"
                @change="switchCurrency($event.target.value)"
            >
                <option v-for="c in options" :key="c.code" :value="c.code">{{ c.code }}</option>
            </select>
        </div>

        <!-- بعملة غير الدولار: ما يدخل الدفاتر وسعر الصرف المُعتمد، مكتوبان
             تحت الخانة قبل الحفظ لا بعده. -->
        <p v-if="foreign" class="mt-1 text-xs text-slate-500">
            يعادل <b class="tabular-nums text-slate-800">{{ formatMoney(amount || 0) }} {{ BASE_SYMBOL }}</b>
            <span class="text-slate-400">· 1 {{ BASE_SYMBOL }} = {{ formatRate(exchangeRate) }} {{ selected?.symbol }}</span>
            <button
                v-if="staleRate"
                type="button"
                class="ms-1 font-medium text-blue-600 underline-offset-2 hover:underline"
                @click="applyCurrentRate"
            >
                سعر اليوم {{ formatRate(selected.exchange_rate) }}
            </button>
        </p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { BASE_CURRENCY, BASE_SYMBOL } from '../../constants/currency';
import { formatRate, fromBase, toBase, useCurrencies } from '../../composables/useCurrencies';
import { formatMoney, toLatinDigits } from '../../utils/format';

/**
 * Amount input with an optional currency picker.
 *
 * `amount` is always USD — it is what the parent allocates, totals and sends.
 * In another currency the user types `currencyAmount` and `amount` follows it
 * at `exchangeRate`; when the parent sets `amount` itself (e.g. from invoice
 * allocations) `currencyAmount` follows the other way.
 */
const amount = defineModel('amount', { type: [String, Number], default: '' });
const currencyCode = defineModel('currencyCode', { type: String, default: 'USD' });
const currencyAmount = defineModel('currencyAmount', { type: [String, Number], default: '' });
const exchangeRate = defineModel('exchangeRate', { type: [String, Number], default: '1' });

defineProps({
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['typed']);

const VALID_FIELD = 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
const INVALID_FIELD = 'border-red-300 focus:border-red-500 focus:ring-red-100';

const { currencies, find } = useCurrencies();
const input = ref(null);

const foreign = computed(() => currencyCode.value && currencyCode.value !== BASE_CURRENCY.code);
const selected = computed(() => find(currencyCode.value));

/** Active currencies, plus the record's own if it has since been switched off. */
const options = computed(() => {
    const list = currencies.value;
    if (!currencyCode.value || list.some((c) => c.code === currencyCode.value)) return list;
    return [...list, { code: currencyCode.value }];
});

const showPicker = computed(() => options.value.length > 1);

const step = computed(() => {
    const decimals = foreign.value ? (selected.value?.decimals ?? 2) : 2;
    return decimals === 0 ? '1' : (1 / 10 ** decimals).toFixed(decimals);
});

/** An edited record keeps the rate it was converted at; today's may differ. */
const staleRate = computed(() =>
    foreign.value
    && selected.value?.exchange_rate
    && Number(selected.value.exchange_rate) !== Number(exchangeRate.value)
);

function syncBase() {
    amount.value = currencyAmount.value === '' ? '' : toBase(currencyAmount.value, exchangeRate.value);
}

function onInput(value) {
    const typed = toLatinDigits(value);
    if (foreign.value) {
        currencyAmount.value = typed;
        syncBase();
    } else {
        amount.value = typed;
    }
    emit('typed', Boolean(typed));
}

function switchCurrency(code) {
    const next = find(code);
    currencyCode.value = code;

    if (!next || next.is_base) {
        exchangeRate.value = '1';
        currencyAmount.value = '';
        return;
    }

    // The USD figure already on the form carries over at the new rate.
    exchangeRate.value = next.exchange_rate;
    currencyAmount.value = amount.value === '' ? '' : fromBase(amount.value, next);
    syncBase();
}

function applyCurrentRate() {
    exchangeRate.value = selected.value.exchange_rate;
    syncBase();
}

// The parent moved the USD amount (allocations, "settle everything"): carry it
// into the currency. Typing goes the other way and is already consistent, so
// this does nothing then.
watch(amount, (usd) => {
    if (!foreign.value) return;
    if (usd === '' || usd === null) {
        if (currencyAmount.value !== '') currencyAmount.value = '';
        return;
    }
    if (currencyAmount.value !== '' && toBase(currencyAmount.value, exchangeRate.value) === Number(usd).toFixed(2)) return;

    currencyAmount.value = fromBase(usd, { ...selected.value, exchange_rate: exchangeRate.value });
    syncBase();
});

defineExpose({ focus: () => input.value?.focus() });
</script>
