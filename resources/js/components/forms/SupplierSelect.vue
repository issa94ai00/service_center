<template>
    <PartySelect
        ref="picker"
        :model-value="modelValue"
        :options="options"
        :party="supplier"
        :fetcher="fetcher ?? purchasesApi.supplierOptions"
        :creator="createSupplier"
        create-permission="suppliers.create"
        :recent-label="recentLabel"
        :invalid="invalid"
        :labels="LABELS"
        no-phone-warning="بدون رقم هاتف لن تتمكن من مراجعة المورد حول فاتورة أو توريد ناقص."
        @update:model-value="(id) => emit('update:modelValue', id)"
        @change="(s) => emit('change', s)"
        @created="(s) => emit('created', s)"
    />
</template>

<script setup>
/**
 * The supplier picker: PartySelect wearing supplier wording and the purchases
 * endpoint.
 *
 * Quick-add matters more here than it looks. A delivery arrives from a new
 * wholesaler and the invoice has to be entered *now*, against the goods on the
 * counter; sending the storekeeper to the suppliers page mid-invoice is how
 * purchases end up recorded a day late, or against the wrong supplier because
 * one that was already there happened to be close enough.
 */
import { ref } from 'vue';
import PartySelect from './PartySelect.vue';
import { suppliersApi, purchasesApi } from '../../services/api';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    /** Optional seed so the picker can render before its first request lands. */
    options: { type: Array, default: () => [] },
    /** The already-attached supplier, when the parent has the full record. */
    supplier: { type: Object, default: null },
    /** Overrides the endpoint when a caller holds a different permission. */
    fetcher: { type: Function, default: null },
    /** Heading over the pre-typing list, which differs by what "recent" means. */
    recentLabel: { type: String, default: 'آخر الموردين الذين اشتُري منهم' },
    /** Lets the parent paint the field red when it submits without a supplier. */
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'created', 'change']);

const LABELS = {
    the: 'المورد',
    searchPlaceholder: 'ابحث بالاسم أو الشركة أو رقم الهاتف...',
    addNew: 'إضافة مورد جديد',
    modalTitle: 'مورد جديد',
    modalSubtitle: 'بيانات سريعة الآن — العنوان والرقم الضريبي والرصيد الافتتاحي من صفحة الموردين لاحقاً',
    namePlaceholder: 'اسم المورد أو المحل',
    nameRequired: 'اسم المورد مطلوب',
    noMatch: 'لا يوجد مورد مطابق لـ',
    empty: 'لا يوجد موردون بعد',
    loading: 'جارٍ تحميل بيانات المورد...',
    save: 'حفظ المورد',
    saveError: 'تعذّر حفظ المورد',
    afterSave: 'سيتم اختيار المورد تلقائياً بعد الحفظ',
    duplicatePhone: 'يوجد مورد مسجّل بنفس رقم الهاتف:',
    duplicateSimilar: 'موردون مشابهون بالفعل في السجل:',
    duplicateHint: 'اختر أحدهم لتفادي تكرار المورد، أو تابع الحفظ لإنشاء سجل جديد.',
};

async function createSupplier(fields) {
    // `store` already hands back the JSON body — `{ message, data }` — so the
    // created record is one unwrap away, not two. Reaching for `data.data` here
    // returned undefined and the picker reported a save failure over a row that
    // had in fact been created.
    const { data } = await suppliersApi.store(fields);
    return data;
}

const picker = ref(null);

defineExpose({
    focus: () => picker.value?.focus(),
    openQuickAdd: (prefill) => picker.value?.openQuickAdd(prefill),
});
</script>
