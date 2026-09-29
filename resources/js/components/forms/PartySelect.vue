<template>
    <div class="relative">
        <!-- Chosen: show who is attached, not a text box that merely happens to
             contain their name. A text box left "is one selected?" ambiguous
             whenever the text was edited but nothing re-picked. -->
        <div
            v-if="selected"
            class="flex items-center gap-3 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2"
        >
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                {{ initials(selected.name) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">
                    {{ selected.name }}
                    <span v-if="selected.is_active === false" class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">غير مُفعّل</span>
                </p>
                <p class="truncate text-xs text-slate-500">
                    <span v-if="bestPhone(selected)" class="tabular-nums" dir="ltr">{{ bestPhone(selected) }}</span>
                    <span v-else>لا يوجد رقم هاتف</span>
                    <span v-if="selected.company"> · {{ selected.company }}</span>
                </p>
            </div>
            <button
                type="button"
                class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-medium text-blue-600 transition hover:bg-blue-50"
                @click="startChanging"
            >
                تغيير
            </button>
        </div>

        <!-- Searching -->
        <div v-else class="relative">
            <input
                ref="searchInput"
                v-model="query"
                type="text"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open"
                :aria-controls="listboxId"
                :aria-activedescendant="activeId"
                autocomplete="off"
                :placeholder="labels.searchPlaceholder"
                class="w-full rounded-lg border py-2 pr-9 ps-9 text-sm focus:outline-none focus:ring-2"
                :class="invalid
                    ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                    : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100'"
                @focus="onFocus"
                @input="onInput"
                @keydown.down.prevent="moveHighlight(1)"
                @keydown.up.prevent="moveHighlight(-1)"
                @keydown.enter.prevent="onEnter"
                @keydown.esc="onEscape"
                @blur="onBlur"
            />
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                <svg v-if="!loading" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="h-4 w-4 animate-spin text-blue-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                </svg>
            </span>
            <button
                v-if="canCreate"
                type="button"
                class="absolute inset-y-0 left-2 my-1 flex items-center gap-1 rounded-md px-2 text-xs font-medium text-blue-600 transition hover:bg-blue-50"
                :title="`${labels.addNew} (Alt+N)`"
                @mousedown.prevent
                @click="openQuickAdd()"
            >
                <span class="text-base leading-none">＋</span>
                <span class="hidden sm:inline">جديد</span>
            </button>
        </div>

        <p v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>

        <div
            v-if="open && !selected"
            class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
        >
            <p v-if="!query" class="border-b border-slate-100 px-3 py-1.5 text-[11px] font-medium text-slate-400">
                {{ recentLabel }}
            </p>
            <ul :id="listboxId" role="listbox" class="max-h-60 overflow-y-auto py-1">
                <li v-for="(c, i) in results" :id="`${listboxId}-${c.id}`" :key="c.id" role="option" :aria-selected="i === highlightIndex">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-right text-sm transition hover:bg-blue-50"
                        :class="i === highlightIndex ? 'bg-blue-50' : ''"
                        @mousedown.prevent
                        @click="select(c)"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-slate-800" v-html="highlight(c.name)"></span>
                            <span v-if="c.company" class="block truncate text-xs text-slate-400">{{ c.company }}</span>
                        </span>
                        <span v-if="bestPhone(c)" class="shrink-0 text-xs text-slate-400 tabular-nums" dir="ltr" v-html="highlight(bestPhone(c))"></span>
                    </button>
                </li>

                <li v-if="loading && !results.length" class="px-3 py-3 text-center text-sm text-slate-400">جارٍ البحث...</li>
                <li v-else-if="!results.length" class="px-3 py-3 text-sm text-slate-400">
                    {{ query ? `${labels.noMatch} «${query}»` : labels.empty }}
                </li>
            </ul>

            <p v-if="truncated" class="border-t border-slate-100 bg-slate-50 px-3 py-1.5 text-[11px] text-slate-400">
                تظهر أول {{ results.length }} نتيجة — تابع الكتابة لتضييق البحث
            </p>

            <!-- Always reachable, not only when the search comes back empty:
                 a walk-in whose name resembles an existing customer's still
                 needs a one-click way to be added. -->
            <div v-if="canCreate" class="border-t border-slate-200">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2.5 text-right text-sm font-medium text-blue-600 transition hover:bg-blue-50"
                    :class="highlightIndex === results.length ? 'bg-blue-50' : ''"
                    @mousedown.prevent
                    @click="openQuickAdd(query.trim())"
                >
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold">+</span>
                    <span class="truncate">
                        {{ query.trim() ? `${labels.addNew} «${query.trim()}»` : labels.addNew }}
                    </span>
                </button>
            </div>
        </div>

        <BaseModal
            :open="quickOpen"
            :title="labels.modalTitle"
            :subtitle="labels.modalSubtitle"
            max-width="max-w-lg"
            :close-on-overlay="!quickLoading"
            @close="closeQuickAdd"
        >
            <form class="space-y-4" @submit.prevent="saveQuick">
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="الاسم" required class="sm:col-span-2">
                        <input
                            ref="nameInput"
                            v-model.trim="quickForm.name"
                            type="text"
                            required
                            maxlength="150"
                            :placeholder="labels.namePlaceholder"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                            :class="fieldClass('name')"
                            @input="scheduleDuplicateCheck"
                        />
                        <p v-if="quickErrors.name" class="mt-1 text-xs text-red-600">{{ quickErrors.name }}</p>
                    </Field>

                    <Field label="الجوال">
                        <input
                            v-model="quickForm.mobile"
                            type="tel"
                            inputmode="tel"
                            dir="ltr"
                            maxlength="30"
                            placeholder="09XXXXXXXX"
                            class="w-full rounded-lg border px-3 py-2 text-left text-sm tabular-nums focus:outline-none focus:ring-2"
                            :class="fieldClass('mobile')"
                            @input="scheduleDuplicateCheck"
                            @blur="quickForm.mobile = normalisePhone(quickForm.mobile)"
                        />
                        <p v-if="quickErrors.mobile" class="mt-1 text-xs text-red-600">{{ quickErrors.mobile }}</p>
                    </Field>

                    <Field label="هاتف بديل">
                        <input
                            v-model="quickForm.phone"
                            type="tel"
                            inputmode="tel"
                            dir="ltr"
                            maxlength="30"
                            class="w-full rounded-lg border px-3 py-2 text-left text-sm tabular-nums focus:outline-none focus:ring-2"
                            :class="fieldClass('phone')"
                            @blur="quickForm.phone = normalisePhone(quickForm.phone)"
                        />
                    </Field>

                    <Field label="الشركة / الجهة" class="sm:col-span-2">
                        <input
                            v-model.trim="quickForm.company"
                            type="text"
                            maxlength="150"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2"
                            :class="fieldClass('company')"
                        />
                    </Field>
                </div>

                <!-- Called out, never blocking: someone genuinely without a
                     number must still be addable in one pass. -->
                <p v-if="noPhoneWarning && !hasAnyPhone" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    {{ noPhoneWarning }}
                </p>

                <div v-if="duplicates.length" class="rounded-lg border border-amber-300 bg-amber-50 p-3">
                    <p class="text-xs font-semibold text-amber-900">
                        {{ exactPhoneMatch ? labels.duplicatePhone : labels.duplicateSimilar }}
                    </p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="d in duplicates" :key="d.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-right text-sm transition hover:bg-blue-50"
                                @click="useExisting(d)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-slate-800">{{ d.name }}</span>
                                    <span v-if="d.company" class="block truncate text-xs text-slate-400">{{ d.company }}</span>
                                </span>
                                <span v-if="bestPhone(d)" class="shrink-0 text-xs text-slate-500 tabular-nums" dir="ltr">{{ bestPhone(d) }}</span>
                                <span class="shrink-0 text-xs font-medium text-blue-600">اختيار</span>
                            </button>
                        </li>
                    </ul>
                    <p class="mt-2 text-[11px] text-amber-800">
                        {{ labels.duplicateHint }}
                    </p>
                </div>

                <div v-if="quickError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ quickError }}
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <p class="text-xs text-slate-400">{{ labels.afterSave }}</p>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="closeQuickAdd"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="quickLoading"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
                        >
                            {{ quickLoading ? 'جارٍ الحفظ...' : saveLabel }}
                        </button>
                    </div>
                </div>
            </form>
        </BaseModal>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, useId, watch } from 'vue';
import Field from '../ui/FormField.vue';
import BaseModal from '../ui/BaseModal.vue';
import { usePermissions } from '../../composables/usePermissions';
import { useToast } from '../../composables/useToast';
import { apiFieldErrors, apiMessage, looksLikePhone, normalisePhone, phoneDigits } from '../../utils/format';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    /** Optional seed so the picker can render before its first request lands. */
    options: { type: Array, default: () => [] },
    /** The already-attached party, when the parent has the full record. */
    party: { type: Object, default: null },
    /**
     * Where the search runs. Every counter form guards the party book behind
     * its *own* permission — a sales clerk may hold `sales.create` and no repair
     * permission at all — so the caller supplies the endpoint it is allowed to
     * read. Contract: (params) => Promise<{ items, meta }>.
     */
    fetcher: { type: Function, required: true },
    /** Creates one from the quick-add form. Contract: (fields) => Promise<record>. */
    creator: { type: Function, required: true },
    /** The permission slug that gates quick-add. */
    createPermission: { type: String, required: true },
    /** Heading over the pre-typing list, which differs by what "recent" means. */
    recentLabel: { type: String, default: '' },
    /** Lets the parent paint the field red when it submits without a party. */
    invalid: { type: Boolean, default: false },
    /** Wording. A supplier is not a customer and the form should not say so. */
    labels: { type: Object, required: true },
    /**
     * Shown when the quick-add form carries no phone number at all. Optional:
     * it only earns its place where a missing number actually costs something.
     */
    noPhoneWarning: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'created', 'change']);

// Two pickers can share one page (the payments form swaps between them), so the
// listbox id must be unique or the aria wiring points at the wrong list.
const listboxId = `party-select-${useId()}`;

const { can } = usePermissions();
const toast = useToast();
const canCreate = can(props.createPermission);

const results = ref([]);
const truncated = ref(false);
const totalParties = ref(0);
const loading = ref(false);
const query = ref('');
const open = ref(false);
const highlightIndex = ref(-1);
const searchInput = ref(null);

// Everything the component has ever seen, keyed by id. `results` is replaced on
// every keystroke; without this cache a customer created seconds ago — or one
// simply scrolled past — would stop resolving and the field would render blank
// over a perfectly valid customer_id.
const known = reactive(new Map());

const quickOpen = ref(false);
const quickLoading = ref(false);
const quickError = ref('');
const quickErrors = ref({});
const duplicates = ref([]);
const nameInput = ref(null);
const quickForm = reactive({ name: '', mobile: '', phone: '', company: '' });

const lookup = (params) => props.fetcher(params);

let searchTimer = null;
let duplicateTimer = null;
let requestSeq = 0;

const selected = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return null;
    return known.get(String(props.modelValue)) ?? null;
});

const hint = computed(() => {
    if (selected.value || open.value) return '';
    if (!props.modelValue) return '';
    // A customer_id we could not resolve: say so rather than showing an empty box.
    return props.labels.loading;
});

const hasAnyPhone = computed(() => Boolean(phoneDigits(quickForm.mobile) || phoneDigits(quickForm.phone)));

const exactPhoneMatch = computed(() => {
    const digits = [phoneDigits(quickForm.mobile), phoneDigits(quickForm.phone)].filter((d) => d.length >= 6);
    if (!digits.length) return null;
    return (
        duplicates.value.find((d) =>
            digits.includes(phoneDigits(d.mobile)) || digits.includes(phoneDigits(d.phone))
        ) ?? null
    );
});

// Saving over an exact phone match is allowed — two people do share a shop
// line — but it takes a second, deliberate click.
const duplicateAcknowledged = ref(false);

const saveLabel = computed(() =>
    exactPhoneMatch.value && !duplicateAcknowledged.value ? 'حفظ رغم التشابه' : props.labels.save
);

const activeId = computed(() => {
    const item = results.value[highlightIndex.value];
    return item ? `${listboxId}-${item.id}` : undefined;
});

function remember(list) {
    for (const c of list ?? []) {
        if (c?.id != null) known.set(String(c.id), c);
    }
}

function bestPhone(c) {
    return c?.mobile || c?.phone || '';
}

function initials(name) {
    return String(name || '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0])
        .join('');
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
}

/** Bolds the matched run so the reason a row is in the list is obvious. */
function highlight(text) {
    const safe = escapeHtml(text);
    const q = query.value.trim();
    if (!q) return safe;
    const needle = escapeHtml(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return safe.replace(new RegExp(needle, 'gi'), (m) => `<mark class="bg-transparent font-bold text-blue-700">${m}</mark>`);
}

async function search(term = '') {
    const seq = ++requestSeq;
    loading.value = true;
    try {
        const { items, meta } = await lookup({
            search: term || undefined,
            limit: 20,
            include: props.modelValue || undefined,
        });
        // A slower earlier request must not overwrite a newer one's results.
        if (seq !== requestSeq) return;
        results.value = items;
        truncated.value = Boolean(meta.truncated);
        totalParties.value = meta.total ?? 0;
        remember(items);
    } catch (e) {
        if (seq === requestSeq) results.value = [];
    } finally {
        if (seq === requestSeq) loading.value = false;
    }
}

function onFocus() {
    open.value = true;
    if (!results.value.length) search(query.value.trim());
}

function onInput() {
    open.value = true;
    highlightIndex.value = -1;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => search(query.value.trim()), 250);
}

function onBlur() {
    // Long enough for a click on a result to land first.
    setTimeout(() => {
        if (!quickOpen.value) open.value = false;
    }, 150);
}

function onEscape() {
    if (open.value) {
        open.value = false;
        return;
    }
    query.value = '';
}

function moveHighlight(delta) {
    const total = results.value.length + (canCreate ? 1 : 0);
    if (!total) return;
    open.value = true;
    highlightIndex.value = (highlightIndex.value + delta + total) % total;
}

function onEnter() {
    if (!open.value) {
        open.value = true;
        return;
    }
    if (highlightIndex.value === results.value.length && canCreate) {
        openQuickAdd(query.value.trim());
        return;
    }
    const index = highlightIndex.value >= 0 ? highlightIndex.value : 0;
    const item = results.value[index];
    if (item) {
        select(item);
    } else if (canCreate && query.value.trim()) {
        openQuickAdd(query.value.trim());
    }
}

function select(party) {
    remember([party]);
    emit('update:modelValue', party.id);
    emit('change', party);
    query.value = '';
    open.value = false;
    highlightIndex.value = -1;
}

function startChanging() {
    emit('update:modelValue', null);
    emit('change', null);
    query.value = '';
    highlightIndex.value = -1;
    nextTick(() => {
        searchInput.value?.focus();
        open.value = true;
        search('');
    });
}

function openQuickAdd(prefill = '') {
    const text = (prefill || query.value).trim();
    // Someone who typed a phone number into the search box meant it as a phone
    // number; the old form dropped it into the *name* field.
    const isPhone = looksLikePhone(text);

    quickForm.name = isPhone ? '' : text;
    quickForm.mobile = isPhone ? normalisePhone(text) : '';
    quickForm.phone = '';
    quickForm.company = '';
    quickError.value = '';
    quickErrors.value = {};
    duplicates.value = [];
    duplicateAcknowledged.value = false;
    quickOpen.value = true;
    open.value = false;

    nextTick(() => nameInput.value?.focus());
    if (text) scheduleDuplicateCheck();
}

function closeQuickAdd() {
    if (quickLoading.value) return;
    quickOpen.value = false;
    clearTimeout(duplicateTimer);
    nextTick(() => searchInput.value?.focus());
}

function fieldClass(field) {
    return quickErrors.value[field]
        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300 focus:border-blue-500 focus:ring-blue-100';
}

/**
 * Duplicate customers are the quiet data-quality failure of a shop system: the
 * same person ends up with three records and their repair history splits three
 * ways. Check as they type rather than after the record exists.
 */
function scheduleDuplicateCheck() {
    duplicateAcknowledged.value = false;
    clearTimeout(duplicateTimer);
    duplicateTimer = setTimeout(runDuplicateCheck, 350);
}

async function runDuplicateCheck() {
    const digits = phoneDigits(quickForm.mobile);
    const term = digits.length >= 6 ? digits : quickForm.name.trim();
    if (term.length < 3) {
        duplicates.value = [];
        return;
    }

    try {
        const { items } = await lookup({ search: term, limit: 5 });
        duplicates.value = items;
        remember(items);
    } catch {
        duplicates.value = [];
    }
}

function useExisting(party) {
    select(party);
    quickOpen.value = false;
    toast.info(`تم اختيار ${props.labels.the} «${party.name}» بدل إنشاء سجل جديد.`);
}

function validateQuick() {
    const errors = {};
    if (!quickForm.name.trim()) errors.name = props.labels.nameRequired;
    const mobile = phoneDigits(quickForm.mobile);
    if (quickForm.mobile && mobile.length < 6) errors.mobile = 'رقم الجوال غير مكتمل';
    quickErrors.value = errors;
    return Object.keys(errors).length === 0;
}

async function saveQuick() {
    if (quickLoading.value) return;
    if (!validateQuick()) return;

    if (exactPhoneMatch.value && !duplicateAcknowledged.value) {
        duplicateAcknowledged.value = true;
        quickError.value = `${props.labels.the} «${exactPhoneMatch.value.name}» مسجّل بنفس الرقم. اضغط الحفظ مرة أخرى لإنشاء سجل منفصل.`;
        return;
    }

    quickLoading.value = true;
    quickError.value = '';
    try {
        const party = await props.creator({
            name: quickForm.name,
            phone: normalisePhone(quickForm.phone) || null,
            mobile: normalisePhone(quickForm.mobile) || null,
            company: quickForm.company || null,
        });

        if (!party?.id) throw new Error('unexpected response');

        remember([party]);
        emit('created', party);
        select(party);
        quickOpen.value = false;
        toast.success(`تم إنشاء ${props.labels.the} «${party.name}» واختياره.`);
    } catch (e) {
        quickErrors.value = apiFieldErrors(e);
        quickError.value = apiMessage(e, props.labels.saveError);
    } finally {
        quickLoading.value = false;
    }
}

watch(
    () => props.options,
    (list) => remember(list),
    { deep: true, immediate: true }
);

watch(
    () => props.party,
    (party) => remember(party ? [party] : []),
    { immediate: true }
);

// An id arriving without its record — editing a repair whose customer is not in
// the default slice, or has since been deactivated — is fetched on demand.
watch(
    () => props.modelValue,
    async (id) => {
        if (!id || known.has(String(id))) return;
        try {
            const { items } = await lookup({ include: id, limit: 1 });
            remember(items);
        } catch {
            /* the picker simply keeps showing its loading hint */
        }
    },
    { immediate: true }
);

// Alt rather than Ctrl: Ctrl+N is a reserved browser shortcut and never
// reaches the page.
function onGlobalKeydown(event) {
    if (!canCreate || quickOpen.value) return;
    if (event.altKey && !event.ctrlKey && !event.metaKey && (event.key === 'n' || event.key === 'N' || event.code === 'KeyN')) {
        event.preventDefault();
        openQuickAdd();
    }
}

/** So a parent can send the cursor here when it submits with no customer. */
function focus() {
    searchInput.value?.focus();
}

defineExpose({ focus, openQuickAdd });

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(duplicateTimer);
    window.removeEventListener('keydown', onGlobalKeydown);
});
</script>
