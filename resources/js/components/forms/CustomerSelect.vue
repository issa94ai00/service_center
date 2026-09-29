<template>
    <PartySelect
        ref="picker"
        :model-value="modelValue"
        :options="options"
        :party="customer"
        :fetcher="fetcher ?? repairsApi.customerOptions"
        :creator="createCustomer"
        create-permission="customers.create"
        :recent-label="recentLabel"
        :invalid="invalid"
        :labels="LABELS"
        no-phone-warning="بدون رقم هاتف لن تتمكن من إبلاغ العميل عند جهوزية الجهاز."
        @update:model-value="(id) => emit('update:modelValue', id)"
        @change="(c) => emit('change', c)"
        @created="(c) => emit('created', c)"
    />
</template>

<script setup>
/**
 * The customer picker: PartySelect wearing customer wording and the customers
 * endpoint. Kept as its own component so the half-dozen call sites stay
 * declarative — `<CustomerSelect v-model="form.customer_id" />` and nothing else.
 */
import { ref } from 'vue';
import PartySelect from './PartySelect.vue';
import { customersApi, repairsApi } from '../../services/api';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    /** Optional seed so the picker can render before its first request lands. */
    options: { type: Array, default: () => [] },
    /** The already-attached customer, when the parent has the full record. */
    customer: { type: Object, default: null },
    /**
     * Where the search runs. Every counter form guards the customer book behind
     * its *own* permission — a sales clerk may hold `sales.create` and no repair
     * permission at all — so the caller supplies the endpoint it is allowed to
     * read. Defaults to the repairs picker.
     */
    fetcher: { type: Function, default: null },
    /** Heading over the pre-typing list, which differs by what "recent" means. */
    recentLabel: { type: String, default: 'آخر العملاء الذين أحضروا أجهزة' },
    /** Lets the parent paint the field red when it submits without a customer. */
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'created', 'change']);

const LABELS = {
    the: 'العميل',
    searchPlaceholder: 'ابحث بالاسم أو رقم الهاتف...',
    addNew: 'إضافة عميل جديد',
    modalTitle: 'عميل جديد',
    modalSubtitle: 'بيانات سريعة الآن — يمكن استكمال باقي التفاصيل من صفحة العملاء لاحقاً',
    namePlaceholder: 'اسم العميل كما ينادى به',
    nameRequired: 'اسم العميل مطلوب',
    noMatch: 'لا يوجد عميل مطابق لـ',
    empty: 'لا يوجد عملاء بعد',
    loading: 'جارٍ تحميل بيانات العميل...',
    save: 'حفظ العميل',
    saveError: 'تعذّر حفظ العميل',
    afterSave: 'سيتم اختيار العميل تلقائياً بعد الحفظ',
    duplicatePhone: 'يوجد عميل مسجّل بنفس رقم الهاتف:',
    duplicateSimilar: 'عملاء مشابهون بالفعل في السجل:',
    duplicateHint: 'اختر أحدهم لتفادي تكرار العميل، أو تابع الحفظ لإنشاء سجل جديد.',
};

async function createCustomer(fields) {
    // `store` already hands back the JSON body — `{ message, data }` — so the
    // created record is one unwrap away, not two. Reaching for `data.data` here
    // returned undefined and the picker reported a save failure over a row that
    // had in fact been created.
    const { data } = await customersApi.store(fields);
    return data;
}

const picker = ref(null);

defineExpose({
    focus: () => picker.value?.focus(),
    openQuickAdd: (prefill) => picker.value?.openQuickAdd(prefill),
});
</script>
