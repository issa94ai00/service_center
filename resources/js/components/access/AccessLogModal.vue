<template>
    <BaseModal
        :open="open"
        :title="title"
        subtitle="من غيّر ماذا ومتى في الأدوار والصلاحيات والمستخدمين"
        max-width="max-w-2xl"
        @close="emit('close')"
    >
        <div class="space-y-3">
            <div v-if="loading && !entries.length" class="py-12 text-center text-xs text-slate-400">
                جارٍ تحميل السجل...
            </div>

            <EmptyState
                v-else-if="!entries.length"
                icon="📜"
                title="لا توجد تغييرات مسجلة بعد"
                hint="تُسجَّل هنا كل التعديلات على الأدوار والصلاحيات والمستخدمين من الآن فصاعداً."
            />

            <ol v-else class="space-y-3">
                <li
                    v-for="entry in entries"
                    :key="entry.id"
                    class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-base" aria-hidden="true">{{ eventMeta(entry.event).icon }}</span>
                            <div>
                                <p class="text-sm font-bold text-slate-900">
                                    {{ eventMeta(entry.event).label }}
                                    <span v-if="entry.subject_name" class="text-slate-500">— {{ entry.subject_name }}</span>
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    بواسطة {{ entry.actor?.name || 'النظام' }}
                                </p>
                            </div>
                        </div>
                        <time class="text-[11px] tabular-nums text-slate-400" :datetime="entry.created_at">
                            {{ formatDateTime(entry.created_at) }}
                        </time>
                    </div>

                    <ul v-if="changes(entry).length" class="mt-2.5 space-y-1.5 border-t border-slate-100 pt-2.5 text-xs">
                        <li v-for="change in changes(entry)" :key="change.key" class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="font-semibold text-slate-600">{{ change.label }}:</span>

                            <template v-if="change.type === 'list'">
                                <span
                                    v-for="item in change.added"
                                    :key="`+${item}`"
                                    class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-200"
                                >+ {{ item }}</span>
                                <span
                                    v-for="item in change.removed"
                                    :key="`-${item}`"
                                    class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700 ring-1 ring-rose-200 line-through decoration-rose-300"
                                >− {{ item }}</span>
                            </template>

                            <span v-else-if="change.type === 'note'" class="text-slate-700">{{ change.text }}</span>

                            <span v-else class="text-slate-700">
                                <template v-if="change.from !== null">
                                    <span class="text-slate-400 line-through">{{ change.from }}</span>
                                    <span class="mx-1 text-slate-400" aria-hidden="true">←</span>
                                </template>
                                <span class="font-medium">{{ change.to ?? '—' }}</span>
                            </span>
                        </li>
                    </ul>
                </li>
            </ol>

            <div v-if="hasMore" class="pt-1 text-center">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                    :disabled="loading"
                    @click="load(page + 1)"
                >
                    {{ loading ? 'جارٍ التحميل...' : 'عرض المزيد' }}
                </button>
            </div>
        </div>
    </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { accessLogApi } from '../../services/api';
import { useToast } from '../../composables/useToast';
import { apiMessage, formatDateTime } from '../../utils/format';
import BaseModal from '../ui/BaseModal.vue';
import EmptyState from '../ui/EmptyState.vue';

const props = defineProps({
    open: Boolean,
    /** 'role' | 'user' — omit for everything the viewer may see. */
    subject: { type: String, default: null },
    subjectId: { type: Number, default: null },
    subjectName: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const toast = useToast();

const entries = ref([]);
const permissionLabels = ref({});
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);

const hasMore = computed(() => page.value < lastPage.value);

const title = computed(() => {
    if (props.subjectName) return `سجل التغييرات: ${props.subjectName}`;
    if (props.subject === 'role') return 'سجل تغييرات الأدوار والصلاحيات';
    if (props.subject === 'user') return 'سجل تغييرات المستخدمين';
    return 'سجل تغييرات الوصول';
});

async function load(nextPage = 1) {
    loading.value = true;
    try {
        const res = await accessLogApi.index({
            subject: props.subject || undefined,
            subject_id: props.subjectId || undefined,
            page: nextPage,
        });
        entries.value = nextPage === 1 ? res.data : [...entries.value, ...res.data];
        permissionLabels.value = res.permission_labels || {};
        page.value = res.current_page;
        lastPage.value = res.last_page;
    } catch (err) {
        toast.error(apiMessage(err, 'تعذر تحميل سجل التغييرات.'));
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            entries.value = [];
            load(1);
        }
    }
);

// ------------------------------------------------------------ Presentation

const EVENTS = {
    'role.created': { label: 'إنشاء دور', icon: '🆕' },
    'role.updated': { label: 'تعديل دور', icon: '✏️' },
    'role.deleted': { label: 'حذف دور', icon: '🗑️' },
    'role.members_changed': { label: 'تغيير مستخدمي الدور', icon: '👥' },
    'user.created': { label: 'إنشاء مستخدم', icon: '🆕' },
    'user.updated': { label: 'تعديل مستخدم', icon: '✏️' },
    'user.deleted': { label: 'حذف مستخدم', icon: '🗑️' },
};

const FIELDS = {
    name: 'الاسم',
    email: 'البريد الإلكتروني',
    phone: 'الهاتف',
    slug: 'المعرف البرمجي',
    description: 'الوصف',
    is_active: 'الحالة',
    roles: 'الأدوار',
    permissions: 'الصلاحيات',
    users: 'المستخدمون',
};

function eventMeta(event) {
    return EVENTS[event] || { label: event, icon: '📝' };
}

function display(key, value) {
    if (value === null || value === undefined || value === '') return null;
    if (key === 'is_active') return value ? 'نشط' : 'معطّل';
    return String(value);
}

/**
 * Rows describing one entry. Lists (roles, permissions, users) show what was added and
 * removed — on create everything is "added", on delete everything "removed".
 */
function changes(entry) {
    const old = entry.old_values || {};
    const now = entry.new_values || {};
    const rows = [];

    if (now.password_changed) {
        rows.push({ key: 'password', type: 'note', label: 'كلمة المرور', text: 'تم تغييرها' });
    }

    for (const key of Object.keys(FIELDS)) {
        if (!(key in old) && !(key in now)) continue;

        if (Array.isArray(old[key]) || Array.isArray(now[key])) {
            const before = old[key] || [];
            const after = now[key] || [];
            const label = (item) => (key === 'permissions' ? permissionLabels.value[item] || item : item);
            const added = after.filter((item) => !before.includes(item)).map(label);
            const removed = before.filter((item) => !after.includes(item)).map(label);
            if (added.length || removed.length) {
                rows.push({ key, type: 'list', label: FIELDS[key], added, removed });
            }
            continue;
        }

        // Created/deleted entries hold a single snapshot: show it plainly. Updates show old ← new.
        const isUpdate = entry.event.endsWith('.updated');
        const from = isUpdate ? display(key, old[key]) : null;
        const to = display(key, entry.event.endsWith('.deleted') ? old[key] : now[key]);
        // Snapshots list every field; skip the empty ones.
        if (from === null && to === null) continue;
        rows.push({ key, type: 'value', label: FIELDS[key], from, to });
    }

    return rows;
}
</script>
