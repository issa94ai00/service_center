<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">المستخدمون</h1>
                <p class="text-sm text-slate-500">حسابات الدخول إلى النظام وأدوارها</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    @click="openLog()"
                >
                    📜 سجل التغييرات
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    @click="openCreate"
                >
                    + مستخدم جديد
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 p-4">
                <input
                    v-model.trim="search"
                    type="search"
                    placeholder="بحث بالاسم أو البريد الإلكتروني..."
                    class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
                <select
                    v-model="activeFilter"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                >
                    <option value="">كل الحالات</option>
                    <option value="1">نشط</option>
                    <option value="0">معطّل</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">المستخدم</th>
                            <th class="px-4 py-3">الهاتف</th>
                            <th class="px-4 py-3">الأدوار</th>
                            <th class="px-4 py-3">الحالة</th>
                            <th class="px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="loading && !items.length" class="text-slate-400">
                            <td colspan="5" class="px-4 py-8 text-center">جارٍ التحميل...</td>
                        </tr>
                        <tr v-else-if="!items.length" class="text-slate-400">
                            <td colspan="5" class="px-4 py-8 text-center">لا توجد نتائج</td>
                        </tr>
                        <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-900">{{ row.name }}</span>
                                    <span v-if="row.id === auth.user?.id" class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-700">أنت</span>
                                </div>
                                <p class="max-w-[14rem] truncate text-xs text-slate-400 sm:max-w-none" dir="ltr">{{ row.email }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600" dir="ltr">{{ row.phone || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in row.roles"
                                        :key="role.id"
                                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                        :class="role.slug === 'master' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'"
                                    >
                                        {{ role.slug === 'master' ? '👑 ' : '' }}{{ role.name }}
                                    </span>
                                    <span v-if="!row.roles?.length" class="text-xs text-slate-400">بلا دور</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="row.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ row.is_active ? 'نشط' : 'معطّل' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="row.can_manage" class="flex flex-wrap gap-3">
                                    <button class="text-slate-500 hover:text-slate-900" @click="openEdit(row)">تعديل</button>
                                    <button
                                        v-if="row.id !== auth.user?.id"
                                        class="text-slate-500 hover:text-slate-900 disabled:opacity-40"
                                        :disabled="busyId === row.id"
                                        @click="toggleActive(row)"
                                    >
                                        {{ row.is_active ? 'تعطيل' : 'تفعيل' }}
                                    </button>
                                    <button class="text-slate-500 hover:text-slate-900" @click="openLog(row)">السجل</button>
                                    <button
                                        v-if="row.id !== auth.user?.id"
                                        class="text-red-500 hover:text-red-700 disabled:opacity-40"
                                        :disabled="busyId === row.id"
                                        @click="remove(row)"
                                    >
                                        حذف
                                    </button>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-slate-400"
                                    :title="row.is_master
                                        ? 'حساب المدير العام لا يُعدَّل إلا من قِبل مدير عام'
                                        : 'يملك هذا المستخدم صلاحيات أعلى من صلاحياتك'"
                                >
                                    🔒 محمي
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <InfiniteScrollFooter
                :shown="items.length"
                :total="meta?.total ?? 0"
                :has-more="hasMore"
                :loading-more="loadingMore"
                :busy="loading"
                :error="moreError"
                unit="مستخدم"
                @more="loadMore"
            />
        </div>

        <!-- ------------------------------------------------ Create / Edit -->
        <BaseModal
            :open="modalOpen"
            :title="form.id ? 'تعديل مستخدم' : 'مستخدم جديد'"
            :close-on-overlay="false"
            max-width="max-w-lg"
            @close="modalOpen = false"
        >
            <form class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="mb-1 block text-xs font-bold text-slate-700">الاسم <span class="text-rose-500">*</span></span>
                        <input v-model="form.name" type="text" required maxlength="150" :class="[fieldClass, errors.name && fieldErrorClass]" />
                        <span v-if="errors.name" class="mt-1 block text-xs text-rose-600">{{ errors.name }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-bold text-slate-700">البريد الإلكتروني <span class="text-rose-500">*</span></span>
                        <input v-model="form.email" type="email" required dir="ltr" :class="[fieldClass, errors.email && fieldErrorClass]" />
                        <span v-if="errors.email" class="mt-1 block text-xs text-rose-600">{{ errors.email }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-bold text-slate-700">الهاتف</span>
                        <input v-model="form.phone" type="tel" dir="ltr" maxlength="30" :class="[fieldClass, errors.phone && fieldErrorClass]" />
                        <span v-if="errors.phone" class="mt-1 block text-xs text-rose-600">{{ errors.phone }}</span>
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="mb-1 block text-xs font-bold text-slate-700">
                            كلمة المرور <span v-if="!form.id" class="text-rose-500">*</span>
                        </span>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            dir="ltr"
                            :required="!form.id"
                            minlength="8"
                            :class="[fieldClass, errors.password && fieldErrorClass]"
                        />
                        <span v-if="errors.password" class="mt-1 block text-xs text-rose-600">{{ errors.password }}</span>
                        <span v-else class="mt-1 block text-[11px] text-slate-400">
                            {{ form.id ? 'اتركها فارغة للإبقاء على كلمة المرور الحالية.' : '8 أحرف على الأقل.' }}
                        </span>
                    </label>
                </div>

                <label v-if="form.id !== auth.user?.id" class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                    الحساب نشط ويمكنه تسجيل الدخول
                </label>

                <fieldset>
                    <legend class="mb-1.5 text-xs font-bold text-slate-700">الأدوار</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="role in roleOptions"
                            :key="role.id"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
                            :class="[
                                form.role_ids.includes(role.id) ? 'border-blue-300 bg-blue-50/50' : 'border-slate-200',
                                role.grantable ? 'cursor-pointer' : 'cursor-not-allowed opacity-60',
                            ]"
                            :title="role.grantable ? '' : 'لا يمكنك إسناد أو سحب هذا الدور لأنه يتضمن صلاحيات لا تملكها'"
                        >
                            <input
                                v-model="form.role_ids"
                                type="checkbox"
                                :value="role.id"
                                :disabled="!role.grantable"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600"
                            />
                            <span class="flex-1">{{ role.slug === 'master' ? '👑 ' : '' }}{{ role.name }}</span>
                            <span v-if="!role.grantable" aria-hidden="true">🔒</span>
                        </label>
                    </div>
                    <span v-if="errors.role_ids" class="mt-1 block text-xs text-rose-600">{{ errors.role_ids }}</span>
                    <span v-if="errors.is_active" class="mt-1 block text-xs text-rose-600">{{ errors.is_active }}</span>
                </fieldset>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50" @click="modalOpen = false">
                        إلغاء
                    </button>
                    <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40" :disabled="saving">
                        {{ saving ? 'جارٍ الحفظ...' : (form.id ? 'حفظ التعديلات' : 'إنشاء المستخدم') }}
                    </button>
                </div>
            </form>
        </BaseModal>

        <AccessLogModal
            :open="log.open"
            subject="user"
            :subject-id="log.user?.id ?? null"
            :subject-name="log.user?.name ?? ''"
            @close="log.open = false"
        />
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { rolesApi, usersApi } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { useInfiniteList } from '../../composables/useInfiniteList';
import { apiFieldErrors, apiMessage } from '../../utils/format';
import BaseModal from '../../components/ui/BaseModal.vue';
import InfiniteScrollFooter from '../../components/ui/InfiniteScrollFooter.vue';
import AccessLogModal from '../../components/access/AccessLogModal.vue';

const auth = useAuthStore();
const toast = useToast();

const fieldClass = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
const fieldErrorClass = 'border-rose-400 bg-rose-50/30';

const search = ref('');
const activeFilter = ref('');
let debounceTimer = null;

const { items, meta, loading, loadingMore, moreError, hasMore, reload, loadMore } = useInfiniteList({
    fetch: (page, perPage) => usersApi.index({
        search: search.value || undefined,
        is_active: activeFilter.value === '' ? undefined : activeFilter.value,
        page,
        per_page: perPage,
    }),
    onError: (e) => toast.error(apiMessage(e, 'تعذّر تحميل المستخدمين')),
});

watch([search, activeFilter], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => reload(), 350);
});

// Roles with a `grantable` flag: the ones this user may hand out or take away.
const roleOptions = ref([]);

async function loadRoleOptions() {
    try {
        roleOptions.value = (await rolesApi.options()).data || [];
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تحميل الأدوار'));
    }
}

// ------------------------------------------------------------ Create / Edit

const modalOpen = ref(false);
const saving = ref(false);
const errors = reactive({});
const form = reactive({ id: null, name: '', email: '', phone: '', password: '', is_active: true, role_ids: [] });

function resetErrors() {
    Object.keys(errors).forEach((key) => delete errors[key]);
}

function openCreate() {
    resetErrors();
    Object.assign(form, { id: null, name: '', email: '', phone: '', password: '', is_active: true, role_ids: [] });
    modalOpen.value = true;
}

function openEdit(row) {
    resetErrors();
    Object.assign(form, {
        id: row.id,
        name: row.name,
        email: row.email,
        phone: row.phone || '',
        password: '',
        is_active: Boolean(row.is_active),
        role_ids: (row.roles || []).map((role) => role.id),
    });
    modalOpen.value = true;
}

async function save() {
    resetErrors();
    saving.value = true;

    const payload = {
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim() || null,
        role_ids: form.role_ids,
    };
    if (form.password) payload.password = form.password;
    if (form.id !== auth.user?.id) payload.is_active = form.is_active;

    try {
        if (form.id) {
            await usersApi.update(form.id, payload);
            toast.success('تم تحديث المستخدم');
        } else {
            await usersApi.store(payload);
            toast.success('تم إنشاء المستخدم');
        }
        modalOpen.value = false;
        reload({ keep: true });
        // Editing yourself can change what you may see; refresh the session's roles.
        if (form.id === auth.user?.id) auth.fetchUser();
    } catch (e) {
        Object.assign(errors, apiFieldErrors(e));
        toast.error(apiMessage(e, 'تعذّر حفظ المستخدم'));
    } finally {
        saving.value = false;
    }
}

// ------------------------------------------------------------ Row actions

const busyId = ref(null);

async function toggleActive(row) {
    if (row.is_active) {
        const ok = await confirm({
            title: 'تعطيل الحساب؟',
            message: `لن يتمكن "${row.name}" من تسجيل الدخول، وستُنهى جلسته الحالية.`,
            confirmLabel: 'تعطيل',
            tone: 'danger',
        });
        if (!ok) return;
    }

    busyId.value = row.id;
    try {
        await usersApi.update(row.id, { is_active: !row.is_active });
        toast.success(row.is_active ? 'تم تعطيل الحساب' : 'تم تفعيل الحساب');
        reload({ keep: true });
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تغيير حالة الحساب'));
    } finally {
        busyId.value = null;
    }
}

async function remove(row) {
    const ok = await confirm({
        title: 'حذف المستخدم؟',
        message: `سيُحذف حساب "${row.name}" نهائيًا.`,
        detail: 'المستخدمون الذين سجّلوا مستندات لا يمكن حذفهم — عطّل حساباتهم بدلاً من ذلك.',
        confirmLabel: 'حذف نهائي',
        tone: 'danger',
    });
    if (!ok) return;

    busyId.value = row.id;
    try {
        await usersApi.destroy(row.id);
        toast.success('تم حذف المستخدم');
        reload({ keep: true });
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر حذف المستخدم'));
    } finally {
        busyId.value = null;
    }
}

// ------------------------------------------------------------ History

const log = reactive({ open: false, user: null });

function openLog(user = null) {
    log.user = user;
    log.open = true;
}

onMounted(() => {
    reload();
    loadRoleOptions();
});
</script>

