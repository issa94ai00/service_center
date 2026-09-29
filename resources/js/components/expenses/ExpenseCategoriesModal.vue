<template>
    <BaseModal :open="open" title="أبواب الصرف" subtitle="التصنيف الذي تُقرأ به المصاريف لاحقاً" @close="$emit('close')">
        <div class="space-y-4">
            <!-- الإضافة أولاً: الشاشة تُفتح غالباً لأن الباب المطلوب غير موجود. -->
            <form class="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3" @submit.prevent="create">
                <div class="min-w-[10rem] flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-600">باب جديد</label>
                    <input
                        ref="nameInput"
                        v-model.trim="newName"
                        type="text"
                        maxlength="150"
                        placeholder="اسم الباب — إيجار، كهرباء، مواصلات..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="busy || !newName"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                >
                    إضافة
                </button>
            </form>

            <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

            <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                <li v-if="!categories.length" class="px-4 py-6 text-center text-sm text-slate-400">لا توجد أبواب بعد</li>
                <li v-for="category in categories" :key="category.id" class="flex flex-wrap items-center gap-2 px-3 py-2.5">
                    <template v-if="editing === category.id">
                        <input
                            v-model.trim="editName"
                            type="text"
                            maxlength="150"
                            class="min-w-[8rem] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
                            @keyup.enter="rename(category)"
                        />
                        <button type="button" class="text-sm font-medium text-blue-600 hover:text-blue-800" :disabled="busy" @click="rename(category)">حفظ</button>
                        <button type="button" class="text-sm text-slate-500 hover:text-slate-800" @click="editing = null">تراجع</button>
                    </template>

                    <template v-else>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium" :class="category.is_active ? 'text-slate-900' : 'text-slate-400 line-through'">
                                {{ category.name }}
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ category.expenses_count ? `${category.expenses_count} مصروف` : 'لم يُصرف عليه بعد' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs font-medium transition"
                            :class="category.is_active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                            :disabled="busy"
                            :title="category.is_active ? 'تعطيله يُخفيه عن شاشة التسجيل ويُبقي سنداته' : 'إعادة تفعيله'"
                            @click="toggle(category)"
                        >
                            {{ category.is_active ? 'نشط' : 'معطَّل' }}
                        </button>
                        <button type="button" class="text-sm text-slate-500 hover:text-slate-900" @click="startEdit(category)">تعديل</button>
                        <!-- الحذف لا يُعرض إلّا لباب لم يُصرف تحته شيء: الخادم
                             يرفضه، ولا فائدة من زرّ يُعرف أنه سيُرفض. -->
                        <button
                            v-if="!category.expenses_count"
                            type="button"
                            class="text-sm text-red-500 hover:text-red-700"
                            :disabled="busy"
                            @click="remove(category)"
                        >
                            حذف
                        </button>
                    </template>
                </li>
            </ul>

            <p class="text-xs text-slate-400">
                الباب المعطَّل لا يظهر عند تسجيل مصروف جديد، وتبقى مصاريفه السابقة كما هي في القوائم والمجاميع.
            </p>
        </div>
    </BaseModal>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue';
import BaseModal from '../ui/BaseModal.vue';
import { expenseCategoriesApi } from '../../services/api';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { apiMessage } from '../../utils/format';

const props = defineProps({
    open: Boolean,
    categories: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'changed']);

const toast = useToast();
const nameInput = ref(null);
const newName = ref('');
const editing = ref(null);
const editName = ref('');
const busy = ref(false);
const error = ref('');

watch(() => props.open, async (open) => {
    if (!open) return;
    newName.value = '';
    editing.value = null;
    error.value = '';
    await nextTick();
    nameInput.value?.focus();
});

async function run(action, successMessage) {
    busy.value = true;
    error.value = '';
    try {
        await action();
        if (successMessage) toast.success(successMessage);
        emit('changed');
        return true;
    } catch (e) {
        error.value = apiMessage(e, 'تعذّر تنفيذ العملية');
        return false;
    } finally {
        busy.value = false;
    }
}

async function create() {
    const name = newName.value;
    if (!name) return;

    const done = await run(() => expenseCategoriesApi.store({ name }), 'تمت إضافة الباب');
    if (done) {
        newName.value = '';
        nameInput.value?.focus();
    }
}

function startEdit(category) {
    editing.value = category.id;
    editName.value = category.name;
}

async function rename(category) {
    if (!editName.value || editName.value === category.name) {
        editing.value = null;
        return;
    }

    const done = await run(() => expenseCategoriesApi.update(category.id, { name: editName.value }), 'تم تعديل الباب');
    if (done) editing.value = null;
}

function toggle(category) {
    return run(
        () => expenseCategoriesApi.update(category.id, { is_active: !category.is_active }),
        category.is_active ? 'تم تعطيل الباب' : 'تم تفعيل الباب'
    );
}

async function remove(category) {
    const ok = await confirm({
        title: 'حذف باب الصرف؟',
        message: `يُحذف "${category.name}" من قائمة الأبواب.`,
        detail: 'لم يُصرف تحت هذا الباب شيء بعد، فحذفه لا يمسّ أي سند.',
        confirmLabel: 'حذف',
        tone: 'danger',
    });
    if (!ok) return;

    await run(() => expenseCategoriesApi.destroy(category.id), 'تم حذف الباب');
}
</script>
