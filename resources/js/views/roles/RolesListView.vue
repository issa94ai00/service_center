<template>
    <div class="space-y-6">
        <!-- ------------------------------------------------ Top Header Banner -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">إدارة الأدوار والصلاحيات</h1>
                    <span
                        v-if="!auth.isMaster"
                        class="inline-flex items-center gap-1.5 rounded-full bg-blue-500/10 px-3 py-0.5 text-xs font-bold text-blue-700 ring-1 ring-blue-500/30"
                        title="يمكنك إدارة الأدوار التي لا تتجاوز صلاحياتك، ومنح الصلاحيات التي تملكها فقط"
                    >
                        🔑 إدارة مفوّضة ضمن صلاحياتك
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    تحديد مسميات الأدوار، وضبط مصفوفة الصلاحيات لكل دور، وإسناد المستخدمين للأدوار المناسبة
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 hover:text-slate-900"
                    :disabled="loading"
                    title="تحديث البيانات"
                    @click="loadAll"
                >
                    <span :class="loading ? 'animate-spin' : ''">🔄</span>
                    <span>تحديث</span>
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 hover:text-slate-900"
                    @click="openLog()"
                >
                    <span>📜</span>
                    <span>سجل التغييرات</span>
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:from-blue-700 hover:to-indigo-700 focus:ring-2 focus:ring-blue-500/30"
                    @click="openCreateRoleModal"
                >
                    <span>＋</span>
                    <span>إضافة دور جديد</span>
                </button>
            </div>
        </div>

        <!-- ------------------------------------------------ Metric Cards -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">إجمالي الأدوار</span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-base">🛡️</span>
                </div>
                <p class="mt-2 text-2xl font-black text-slate-900 tabular-nums">{{ roles.length }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">أدوار مسجلة بالنظام</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">صلاحيات النظام</span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-base">🔑</span>
                </div>
                <p class="mt-2 text-2xl font-black text-slate-900 tabular-nums">{{ totalPermissionsCount }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">موزعة عبر {{ permissionGroups.length }} وحدة</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">المستخدمون النشطون</span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 text-base">👥</span>
                </div>
                <p class="mt-2 text-2xl font-black text-slate-900 tabular-nums">{{ totalUsersCount }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">مستخدم مسند لأدوار</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">الأدوار المحمية</span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 text-base">🔒</span>
                </div>
                <p class="mt-2 text-2xl font-black text-amber-700 tabular-nums">{{ protectedRolesCount }}</p>
                <p class="mt-0.5 text-[11px] text-slate-400">Master & Admin</p>
            </div>
        </div>

        <!-- ------------------------------------------------ Search & Filter Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <div class="relative w-full max-w-md">
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 text-sm">
                    🔍
                </span>
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="بحث باسم الدور، المعرف البرمجي (Slug)، أو الوصف..."
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pr-10 pl-8 text-sm text-slate-800 placeholder-slate-400 transition focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100"
                />
                <button
                    v-if="searchQuery"
                    type="button"
                    class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-slate-400 hover:text-slate-600"
                    title="مسح البحث"
                    @click="searchQuery = ''"
                >
                    ✕
                </button>
            </div>

            <span class="text-xs font-medium text-slate-500">
                عرض {{ filteredRoles.length }} من أصل {{ roles.length }} دور
            </span>
        </div>

        <!-- ------------------------------------------------ Loading / Empty State -->
        <div v-if="loading && !roles.length" class="space-y-4">
            <TableSkeleton :rows="4" :cols="4" />
        </div>

        <EmptyState
            v-else-if="filteredRoles.length === 0"
            icon="🛡️"
            :title="searchQuery ? 'لا توجد أدوار مطابقة لبحثك' : 'لا توجد أي أدوار مسجلة'"
            :hint="searchQuery ? 'جرّب البحث بكلمات أخرى أو امسح حقل البحث' : 'قم بإنشاء دور جديد بالنقر على زر إضافة دور'"
            :action-label="searchQuery ? 'مسح البحث' : 'إضافة دور جديد'"
            @action="searchQuery ? (searchQuery = '') : openCreateRoleModal()"
        />

        <!-- ------------------------------------------------ Roles Grid -->
        <div v-else class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="role in filteredRoles"
                :key="role.id"
                class="group flex flex-col justify-between rounded-2xl border bg-white p-5 shadow-xs transition duration-200 hover:shadow-md"
                :class="role.slug === 'master' ? 'border-amber-300 ring-1 ring-amber-300/40' : (role.slug === 'admin' ? 'border-blue-200 ring-1 ring-blue-200/50' : 'border-slate-200')"
            >
                <div>
                    <!-- Card Top Header -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-xl shadow-xs"
                                :class="roleBadgeClasses(role.slug)"
                            >
                                {{ roleIcon(role.slug) }}
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 group-hover:text-blue-600 transition">
                                    {{ role.name }}
                                </h3>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-mono text-slate-600 font-semibold">
                                        {{ role.slug }}
                                    </code>
                                    <span
                                        v-if="role.is_protected"
                                        class="rounded-full bg-slate-100 px-2 py-0.2 text-[10px] font-bold text-slate-600 border border-slate-200"
                                        title="دور أساسي محمي من الحذف"
                                    >
                                        محمي
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Special Role Badge -->
                        <span
                            v-if="role.slug === 'master'"
                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-black text-amber-800 ring-1 ring-amber-300"
                        >
                            👑 المدير العام
                        </span>
                        <span
                            v-else-if="role.slug === 'admin'"
                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700 ring-1 ring-blue-300"
                        >
                            🛡️ مدير النظام
                        </span>
                    </div>

                    <!-- Description -->
                    <p class="mt-3.5 text-xs leading-relaxed text-slate-500 line-clamp-2 min-h-[2rem]">
                        {{ role.description || 'لا يوجد وصف مضاف لهذا الدور.' }}
                    </p>

                    <!-- Indicators Pill Grid -->
                    <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3">
                        <!-- Permissions Indicator -->
                        <button
                            type="button"
                            class="flex flex-col items-start rounded-xl border border-slate-100 bg-slate-50/70 p-2.5 text-right transition hover:border-blue-200 hover:bg-blue-50/40"
                            :title="role.slug === 'master' ? 'المدير العام يمتلك جميع الصلاحيات' : 'انقر لتعديل الصلاحيات'"
                            @click="openPermissionsModal(role)"
                        >
                            <span class="text-[10px] font-medium text-slate-400">الصلاحيات</span>
                            <span class="mt-0.5 text-xs font-bold" :class="role.slug === 'master' ? 'text-amber-700' : 'text-slate-800'">
                                {{ role.slug === 'master' ? 'جميع الصلاحيات (كاملة)' : `${role.permissions_count || 0} مفعلة` }}
                            </span>
                        </button>

                        <!-- Users Indicator -->
                        <button
                            type="button"
                            class="flex flex-col items-start rounded-xl border border-slate-100 bg-slate-50/70 p-2.5 text-right transition hover:border-indigo-200 hover:bg-indigo-50/40 disabled:cursor-default disabled:hover:border-slate-100 disabled:hover:bg-slate-50/70"
                            :title="canAssignUsers(role) ? 'انقر لإدارة المستخدمين المسندين' : ''"
                            :disabled="!canAssignUsers(role)"
                            @click="openUsersModal(role)"
                        >
                            <span class="text-[10px] font-medium text-slate-400">المستخدمون</span>
                            <span class="mt-0.5 text-xs font-bold text-slate-800">
                                {{ role.users_count || 0 }} مستخدم
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Card Action Footer -->
                <div class="mt-4 flex flex-wrap items-center justify-between gap-1 border-t border-slate-100 pt-3">
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1.5 text-xs font-bold text-blue-700 transition hover:bg-blue-100 focus:ring-1 focus:ring-blue-400"
                            @click="openPermissionsModal(role)"
                        >
                            <span>🔑</span>
                            <span>الصلاحيات</span>
                        </button>
                        <button
                            v-if="canAssignUsers(role)"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 focus:ring-1 focus:ring-indigo-400"
                            @click="openUsersModal(role)"
                        >
                            <span>👥</span>
                            <span>المستخدمون</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                            title="سجل تغييرات الدور"
                            @click="openLog(role)"
                        >
                            📜
                        </button>
                        <span
                            v-if="!role.can_manage"
                            class="flex h-7 w-7 items-center justify-center text-slate-400"
                            :title="role.slug === 'master' ? 'دور المدير العام لا يُدار إلا من قِبل مدير عام' : 'يتضمن هذا الدور صلاحيات لا تملكها'"
                        >
                            🔒
                        </span>
                        <button
                            v-if="role.can_manage"
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                            title="تعديل بيانات الدور"
                            @click="openEditRoleModal(role)"
                        >
                            ✏️
                        </button>
                        <button
                            v-if="!role.is_protected && role.can_manage"
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 hover:text-rose-700 disabled:opacity-30 disabled:cursor-not-allowed"
                            :title="role.users_count > 0 ? 'لا يمكن حذف الدور لوجود مستخدمين مسندين' : 'حذف الدور'"
                            :disabled="role.users_count > 0"
                            @click="handleDeleteRole(role)"
                        >
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- ------------------- MODAL 1: Role Create / Edit Modal --------------- -->
        <!-- ===================================================================== -->
        <BaseModal
            :open="roleModalOpen"
            :title="roleForm.id ? 'تعديل بيانات الدور' : 'إضافة دور جديد'"
            :subtitle="roleForm.id ? `تعديل معلومات الدور (${roleForm.name})` : 'تعريف دور وظيفي جديد وإسناد صلاحياته الأساسية'"
            max-width="max-w-lg"
            @close="closeRoleModal"
        >
            <form class="space-y-4" @submit.prevent="submitRoleForm">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-700">
                        اسم الدور <span class="text-rose-500">*</span>
                    </label>
                    <input
                        v-model="roleForm.name"
                        type="text"
                        required
                        placeholder="مثال: مسؤول مبيعات، أمين مستودع، فني رئيسي..."
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        :class="roleErrors.name ? 'border-rose-400 bg-rose-50/30' : ''"
                        @input="onRoleNameInput"
                    />
                    <p v-if="roleErrors.name" class="mt-1 text-xs text-rose-600">{{ roleErrors.name }}</p>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label class="mb-1 block text-xs font-bold text-slate-700">
                            المعرف البرمجي (Slug)
                        </label>
                        <span v-if="isSlugProtected" class="text-[10px] text-amber-700 font-semibold">
                            🔒 معرف محمي لا يمكن تغييره
                        </span>
                    </div>
                    <input
                        v-model="roleForm.slug"
                        type="text"
                        dir="ltr"
                        :disabled="isSlugProtected"
                        placeholder="مثال: sales_officer, store_keeper"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100 disabled:text-slate-500"
                        :class="roleErrors.slug ? 'border-rose-400 bg-rose-50/30' : ''"
                    />
                    <p v-if="roleErrors.slug" class="mt-1 text-xs text-rose-600">{{ roleErrors.slug }}</p>
                    <p v-else class="mt-1 text-[11px] text-slate-400">
                        أحرف إنجليزية وأرقام وشرطات سفلية. يُولَّد تلقائياً إن تُرِك فارغاً.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-700">الوصف</label>
                    <textarea
                        v-model="roleForm.description"
                        rows="3"
                        placeholder="شرح موجز للمهام والمسؤوليات المنوطة بهذا الدور..."
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        @click="closeRoleModal"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-blue-700 disabled:opacity-40"
                        :disabled="savingRole"
                    >
                        <span v-if="savingRole" class="animate-spin text-xs">⏳</span>
                        <span>{{ savingRole ? 'جارٍ الحفظ...' : (roleForm.id ? 'حفظ التعديلات' : 'إنشاء الدور') }}</span>
                    </button>
                </div>
            </form>
        </BaseModal>

        <!-- ===================================================================== -->
        <!-- ------------------- MODAL 2: Permissions Matrix Modal --------------- -->
        <!-- ===================================================================== -->
        <BaseModal
            :open="permissionsModalOpen"
            :title="`صلاحيات الدور: ${currentRole?.name || ''}`"
            :subtitle="`تحديد الصلاحيات المسموح بها لهذا الدور عبر جميع أقسام النظام`"
            max-width="max-w-4xl"
            @close="closePermissionsModal"
        >
            <div class="space-y-4">
                <!-- Master Alert Banner -->
                <div
                    v-if="currentRole?.slug === 'master'"
                    class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 shadow-2xs"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-base">👑</span>
                        <h4 class="font-bold">دور المدير العام (Master) يتمتع بكافة الصلاحيات تلقائياً وبشكل دائم</h4>
                    </div>
                    <p class="mt-1 text-amber-800 leading-relaxed">
                        وفقاً لبنية النظام الأمنية، يتخطى حساب المدير العام أي قيود صلاحيات افتراضية، وتبقى جميع الصلاحيات مفعلة له دوماً حتى وإن أُلغي تحديدها هنا.
                    </p>
                </div>

                <!-- Search & Batch Actions Toolbar -->
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="relative w-full sm:w-72">
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 text-xs">
                            🔍
                        </span>
                        <input
                            v-model="permissionSearchQuery"
                            type="search"
                            placeholder="بحث في الصلاحيات..."
                            class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pr-8 pl-6 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:outline-none"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-slate-600">
                            {{ selectedPermissionIds.size }} من أصل {{ totalPermissionsCount }} صلاحية محددة
                        </span>
                        <span v-if="permissionsEditable" class="h-4 w-px bg-slate-300"></span>
                        <button
                            v-if="permissionsEditable"
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                            @click="selectAllPermissions"
                        >
                            تحديد الكل ✓
                        </button>
                        <button
                            v-if="permissionsEditable"
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                            @click="deselectAllPermissions"
                        >
                            إلغاء التحديد ✕
                        </button>
                    </div>
                </div>

                <!-- Grouped Permissions List -->
                <div class="space-y-4 sm:max-h-[60vh] sm:overflow-y-auto pr-1">
                    <div
                        v-for="group in filteredPermissionGroups"
                        :key="group.key"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs"
                    >
                        <!-- Group Header with Category Action -->
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-sm">
                                    {{ groupIcon(group.key) }}
                                </span>
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900">{{ group.title }}</h4>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ group.key }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                    {{ getGroupSelectedCount(group) }} / {{ group.permissions.length }}
                                </span>
                                <button
                                    v-if="permissionsEditable"
                                    type="button"
                                    class="text-xs text-blue-600 hover:text-blue-800 font-semibold transition"
                                    @click="toggleGroupPermissions(group)"
                                >
                                    {{ isGroupFullySelected(group) ? 'إلغاء القسم' : 'تحديد القسم' }}
                                </button>
                            </div>
                        </div>

                        <!-- Checkbox Cards Grid -->
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="perm in group.permissions"
                                :key="perm.id"
                                class="flex items-start gap-2.5 rounded-xl border p-2.5 transition select-none"
                                :class="[
                                    selectedPermissionIds.has(perm.id)
                                        ? 'border-blue-400 bg-blue-50/50 text-blue-900 shadow-2xs'
                                        : 'border-slate-200 bg-white hover:border-slate-300 text-slate-700',
                                    canTogglePermission(perm) ? 'cursor-pointer' : 'cursor-not-allowed opacity-60',
                                ]"
                                :title="canTogglePermission(perm) || !permissionsEditable ? '' : 'لا يمكنك منح صلاحية لا تملكها'"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    :checked="selectedPermissionIds.has(perm.id)"
                                    :disabled="!canTogglePermission(perm)"
                                    @change="togglePermission(perm.id)"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold leading-tight">{{ perm.name }}</p>
                                    <p class="mt-0.5 truncate text-[10px] font-mono text-slate-400" :title="perm.slug">
                                        {{ perm.slug }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-slate-100 pt-4">
                    <span class="text-xs text-slate-500">
                        {{ permissionsEditable
                            ? 'سيتم تطبيق الصلاحيات فوراً على جميع المستخدمين الذين يحملون هذا الدور.'
                            : '🔒 للعرض فقط — لا يمكنك تعديل صلاحيات هذا الدور.' }}
                    </span>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                            @click="closePermissionsModal"
                        >
                            إلغاء
                        </button>
                        <button
                            v-if="permissionsEditable"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-blue-700 disabled:opacity-40"
                            :disabled="savingPermissions"
                            @click="saveRolePermissions"
                        >
                            <span v-if="savingPermissions" class="animate-spin text-xs">⏳</span>
                            <span>{{ savingPermissions ? 'جارٍ الحفظ...' : 'حفظ مصفوفة الصلاحيات' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </BaseModal>

        <!-- ===================================================================== -->
        <!-- ------------------- MODAL 3: Assign Users Modal --------------------- -->
        <!-- ===================================================================== -->
        <BaseModal
            :open="usersModalOpen"
            :title="`المستخدمون المسندون لدور: ${currentRoleForUsers?.name || ''}`"
            :subtitle="`إسناد أو إزالة مستخدمي النظام من هذا الدور الوظيفي`"
            max-width="max-w-xl"
            @close="closeUsersModal"
        >
            <div class="space-y-4">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 text-xs">
                        🔍
                    </span>
                    <input
                        v-model="userSearchQuery"
                        type="search"
                        placeholder="بحث بالاسم أو البريد الإلكتروني..."
                        class="w-full rounded-xl border border-slate-300 py-2 pr-9 pl-7 text-xs text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                </div>

                <div v-if="loadingUsers" class="py-12 text-center text-xs text-slate-400">
                    جارٍ تحميل قائمة المستخدمين...
                </div>

                <div v-else class="space-y-2 sm:max-h-[50vh] sm:overflow-y-auto pr-1">
                    <div
                        v-if="filteredUsersList.length === 0"
                        class="py-8 text-center text-xs text-slate-400"
                    >
                        لا يوجد مستخدمون مطابقون لبحثك
                    </div>

                    <label
                        v-for="user in filteredUsersList"
                        :key="user.id"
                        class="flex items-center justify-between rounded-xl border p-3 transition select-none"
                        :class="[
                            selectedUserIds.has(user.id)
                                ? 'border-indigo-300 bg-indigo-50/50 text-indigo-950'
                                : 'border-slate-200 bg-white hover:border-slate-300 text-slate-700',
                            user.can_manage ? 'cursor-pointer' : 'cursor-not-allowed opacity-60',
                        ]"
                        :title="user.can_manage ? '' : 'لا يمكنك تعديل أدوار هذا المستخدم لأنه يملك صلاحيات أعلى من صلاحياتك'"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                :checked="selectedUserIds.has(user.id)"
                                :disabled="!user.can_manage"
                                @change="toggleUserAssignment(user.id)"
                            />
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900">{{ user.name }}</span>
                                    <span
                                        v-if="!user.is_active"
                                        class="rounded bg-rose-50 px-1.5 py-0.2 text-[10px] font-bold text-rose-600"
                                    >
                                        معطّل
                                    </span>
                                </div>
                                <p class="truncate text-xs text-slate-400 font-mono">{{ user.email }}</p>
                            </div>
                        </div>

                        <span
                            v-if="selectedUserIds.has(user.id)"
                            class="shrink-0 whitespace-nowrap rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700"
                        >
                            مسند لهذا الدور ✓
                        </span>
                    </label>
                </div>

                <!-- Users Modal Footer -->
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-slate-100 pt-4">
                    <span class="text-xs font-semibold text-slate-700">
                        تم تحديد {{ selectedUserIds.size }} مستخدم لهذا الدور
                    </span>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50"
                            @click="closeUsersModal"
                        >
                            إلغاء
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white shadow-xs transition hover:bg-indigo-700 disabled:opacity-40"
                            :disabled="savingUsers"
                            @click="saveAssignedUsers"
                        >
                            <span v-if="savingUsers" class="animate-spin text-xs">⏳</span>
                            <span>{{ savingUsers ? 'جارٍ الحفظ...' : 'حفظ المستخدمين المسندين' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </BaseModal>

        <AccessLogModal
            :open="log.open"
            subject="role"
            :subject-id="log.role?.id ?? null"
            :subject-name="log.role?.name ?? ''"
            @close="log.open = false"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { rolesApi, usersApi } from '../../services/api';
import { useToast } from '../../composables/useToast';
import { useConfirm } from '../../composables/useConfirm';
import BaseModal from '../../components/ui/BaseModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import TableSkeleton from '../../components/ui/TableSkeleton.vue';
import AccessLogModal from '../../components/access/AccessLogModal.vue';

const auth = useAuthStore();
const toast = useToast();
const { confirm } = useConfirm();

// ------------------------------------------------------------ Main State
const roles = ref([]);
const permissionGroups = ref([]);
const groupLabels = ref({});
const allUsers = ref([]);
const loading = ref(false);
const loadingUsers = ref(false);
const searchQuery = ref('');

// ------------------------------------------------------------ Metrics
const totalPermissionsCount = computed(() => {
    return permissionGroups.value.reduce((acc, g) => acc + (g.permissions?.length || 0), 0);
});

const totalUsersCount = computed(() => {
    return roles.value.reduce((acc, r) => acc + (r.users_count || 0), 0);
});

const protectedRolesCount = computed(() => {
    return roles.value.filter((r) => r.is_protected || ['master', 'admin'].includes(r.slug)).length;
});

// ------------------------------------------------------------ Filtered Roles
const filteredRoles = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return roles.value;

    return roles.value.filter((role) => {
        const name = (role.name || '').toLowerCase();
        const slug = (role.slug || '').toLowerCase();
        const desc = (role.description || '').toLowerCase();
        return name.includes(q) || slug.includes(q) || desc.includes(q);
    });
});

// ------------------------------------------------------------ Data Fetching
async function loadRoles() {
    try {
        const res = await rolesApi.index();
        roles.value = res.data || [];
    } catch (err) {
        toast.error('تعذر تحميل قائمة الأدوار: ' + (err.response?.data?.message || err.message));
    }
}

async function loadPermissions() {
    try {
        const res = await rolesApi.permissions();
        permissionGroups.value = res.grouped || [];
        groupLabels.value = res.group_labels || {};
    } catch (err) {
        toast.error('تعذر تحميل قائمة الصلاحيات: ' + (err.response?.data?.message || err.message));
    }
}

async function loadUsers() {
    if (allUsers.value.length) return;
    loadingUsers.value = true;
    try {
        const res = await usersApi.index({ per_page: 150 });
        allUsers.value = res.data || [];
    } catch (err) {
        toast.error('تعذر تحميل المستخدمين: ' + (err.response?.data?.message || err.message));
    } finally {
        loadingUsers.value = false;
    }
}

async function loadAll() {
    loading.value = true;
    try {
        await Promise.all([loadRoles(), loadPermissions()]);
    } finally {
        loading.value = false;
    }
}

// ------------------------------------------------------------ Role Create/Edit Modal
const roleModalOpen = ref(false);
const savingRole = ref(false);
const roleErrors = reactive({ name: '', slug: '' });
const roleForm = reactive({
    id: null,
    name: '',
    slug: '',
    description: '',
});

const isSlugProtected = computed(() => {
    return Boolean(roleForm.id && ['master', 'admin'].includes(roleForm.slug));
});

function openCreateRoleModal() {
    roleErrors.name = '';
    roleErrors.slug = '';
    roleForm.id = null;
    roleForm.name = '';
    roleForm.slug = '';
    roleForm.description = '';
    roleModalOpen.value = true;
}

function openEditRoleModal(role) {
    roleErrors.name = '';
    roleErrors.slug = '';
    roleForm.id = role.id;
    roleForm.name = role.name;
    roleForm.slug = role.slug;
    roleForm.description = role.description || '';
    roleModalOpen.value = true;
}

function closeRoleModal() {
    roleModalOpen.value = false;
}

function onRoleNameInput() {
    if (!roleForm.id && !roleForm.slug) {
        // Auto slug generation suggestion
    }
}

async function submitRoleForm() {
    roleErrors.name = '';
    roleErrors.slug = '';

    if (!roleForm.name.trim()) {
        roleErrors.name = 'اسم الدور مطلوب.';
        return;
    }

    savingRole.value = true;
    try {
        const payload = {
            name: roleForm.name.trim(),
            description: roleForm.description.trim() || null,
        };

        if (roleForm.slug && !isSlugProtected.value) {
            payload.slug = roleForm.slug.trim();
        }

        if (roleForm.id) {
            await rolesApi.update(roleForm.id, payload);
            toast.success('تم تحديث بيانات الدور بنجاح.');
        } else {
            await rolesApi.store(payload);
            toast.success('تم إنشاء الدور الجديد بنجاح.');
        }

        closeRoleModal();
        await loadRoles();
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) {
            roleErrors.name = data.errors.name ? data.errors.name[0] : '';
            roleErrors.slug = data.errors.slug ? data.errors.slug[0] : '';
        }
        toast.error(data?.message || 'فشلت عملية الحفظ.');
    } finally {
        savingRole.value = false;
    }
}

// ------------------------------------------------------------ Delete Role
async function handleDeleteRole(role) {
    if (role.is_protected || ['master', 'admin'].includes(role.slug)) {
        toast.error('هذا الدور محمي من قبل النظام ولا يمكن حذفه.');
        return;
    }

    if (role.users_count > 0) {
        toast.error(`لا يمكن حذف هذا الدور لوجود ${role.users_count} مستخدم مرتبط به.`);
        return;
    }

    const confirmed = await confirm({
        title: 'تأكيد حذف الدور',
        message: `هل أنت متأكد من رغبتك في حذف الدور "${role.name}" نهائياً؟`,
        detail: 'سيتم مسح تعريف هذا الدور وفصل الصلاحيات المرتبطة به.',
        confirmLabel: 'نعم، احذف الدور',
        cancelLabel: 'إلغاء',
        tone: 'danger',
    });

    if (!confirmed) return;

    try {
        await rolesApi.destroy(role.id);
        toast.success(`تم حذف الدور "${role.name}" بنجاح.`);
        await loadRoles();
    } catch (err) {
        toast.error(err.response?.data?.message || 'تعذر حذف الدور.');
    }
}

// ------------------------------------------------------------ Permissions Management Modal
const permissionsModalOpen = ref(false);
const savingPermissions = ref(false);
const currentRole = ref(null);
const selectedPermissionIds = ref(new Set());
const permissionSearchQuery = ref('');

const filteredPermissionGroups = computed(() => {
    const q = permissionSearchQuery.value.trim().toLowerCase();
    if (!q) return permissionGroups.value;

    return permissionGroups.value
        .map((group) => {
            const groupTitleMatch = group.title.toLowerCase().includes(q) || group.key.toLowerCase().includes(q);
            const matchingPerms = group.permissions.filter((p) => {
                return (
                    groupTitleMatch ||
                    p.name.toLowerCase().includes(q) ||
                    p.slug.toLowerCase().includes(q)
                );
            });
            return {
                ...group,
                permissions: matchingPerms,
            };
        })
        .filter((group) => group.permissions.length > 0);
});

// Read-only for roles the viewer may not manage (e.g. Master, or stronger than them).
const permissionsEditable = computed(() => Boolean(currentRole.value?.can_manage) && currentRole.value?.slug !== 'master');

/** A non-Master can only grant (or take away) permissions they hold themselves. */
function canTogglePermission(perm) {
    if (!permissionsEditable.value) return false;
    return auth.isMaster || auth.permissions.includes(perm.slug);
}

async function openPermissionsModal(role) {
    currentRole.value = role;
    permissionSearchQuery.value = '';
    selectedPermissionIds.value = new Set();

    try {
        // Fetch full role record to have its loaded permissions
        const res = await rolesApi.show(role.id);
        const detailedRole = res.data;
        currentRole.value = detailedRole;

        const ids = (detailedRole.permissions || []).map((p) => p.id);
        selectedPermissionIds.value = new Set(ids);
    } catch {
        // Fallback to role from list
        const ids = (role.permissions || []).map((p) => p.id);
        selectedPermissionIds.value = new Set(ids);
    }

    permissionsModalOpen.value = true;
}

function closePermissionsModal() {
    permissionsModalOpen.value = false;
    currentRole.value = null;
}

function togglePermission(id) {
    if (selectedPermissionIds.value.has(id)) {
        selectedPermissionIds.value.delete(id);
    } else {
        selectedPermissionIds.value.add(id);
    }
}

function getGroupSelectedCount(group) {
    return group.permissions.filter((p) => selectedPermissionIds.value.has(p.id)).length;
}

function isGroupFullySelected(group) {
    return group.permissions.length > 0 && group.permissions.every((p) => selectedPermissionIds.value.has(p.id));
}

function toggleGroupPermissions(group) {
    const allSelected = isGroupFullySelected(group);
    group.permissions.filter(canTogglePermission).forEach((p) => {
        if (allSelected) {
            selectedPermissionIds.value.delete(p.id);
        } else {
            selectedPermissionIds.value.add(p.id);
        }
    });
}

function selectAllPermissions() {
    permissionGroups.value.forEach((group) => {
        group.permissions.filter(canTogglePermission).forEach((p) => selectedPermissionIds.value.add(p.id));
    });
}

function deselectAllPermissions() {
    permissionGroups.value.forEach((group) => {
        group.permissions.filter(canTogglePermission).forEach((p) => selectedPermissionIds.value.delete(p.id));
    });
}

async function saveRolePermissions() {
    if (!currentRole.value) return;

    savingPermissions.value = true;
    try {
        await rolesApi.update(currentRole.value.id, {
            name: currentRole.value.name,
            description: currentRole.value.description,
            permission_ids: Array.from(selectedPermissionIds.value),
        });

        toast.success(`تم تحديث صلاحيات الدور "${currentRole.value.name}" بنجاح.`);
        closePermissionsModal();
        await loadRoles();
    } catch (err) {
        toast.error(err.response?.data?.message || 'تعذر حفظ الصلاحيات.');
    } finally {
        savingPermissions.value = false;
    }
}

// ------------------------------------------------------------ Users Assignment Modal

// Listing users needs users.manage on top of roles.manage.
function canAssignUsers(role) {
    return Boolean(role.can_manage) && auth.hasPermission('users.manage');
}
const usersModalOpen = ref(false);
const savingUsers = ref(false);
const currentRoleForUsers = ref(null);
const selectedUserIds = ref(new Set());
const userSearchQuery = ref('');

const filteredUsersList = computed(() => {
    const q = userSearchQuery.value.trim().toLowerCase();
    if (!q) return allUsers.value;

    return allUsers.value.filter((user) => {
        return (user.name || '').toLowerCase().includes(q) || (user.email || '').toLowerCase().includes(q);
    });
});

async function openUsersModal(role) {
    currentRoleForUsers.value = role;
    userSearchQuery.value = '';
    selectedUserIds.value = new Set();
    usersModalOpen.value = true;

    await loadUsers();

    try {
        const res = await rolesApi.show(role.id);
        const detailedRole = res.data;
        currentRoleForUsers.value = detailedRole;
        const ids = (detailedRole.users || []).map((u) => u.id);
        selectedUserIds.value = new Set(ids);
    } catch {
        // Fallback
        const ids = (role.users || []).map((u) => u.id);
        selectedUserIds.value = new Set(ids);
    }
}

function closeUsersModal() {
    usersModalOpen.value = false;
    currentRoleForUsers.value = null;
}

function toggleUserAssignment(userId) {
    if (selectedUserIds.value.has(userId)) {
        selectedUserIds.value.delete(userId);
    } else {
        selectedUserIds.value.add(userId);
    }
}

async function saveAssignedUsers() {
    if (!currentRoleForUsers.value) return;

    savingUsers.value = true;
    try {
        await rolesApi.assignUsers(currentRoleForUsers.value.id, Array.from(selectedUserIds.value));
        toast.success(`تم تحديث المستخدمين المسندين للدور "${currentRoleForUsers.value.name}" بنجاح.`);
        closeUsersModal();
        await loadRoles();
    } catch (err) {
        toast.error(err.response?.data?.message || 'تعذر حفظ المستخدمين.');
    } finally {
        savingUsers.value = false;
    }
}

// ------------------------------------------------------------ Change Log

const log = reactive({ open: false, role: null });

function openLog(role = null) {
    log.role = role;
    log.open = true;
}

// ------------------------------------------------------------ Helpers & UI Icons
function roleIcon(slug) {
    switch (slug) {
        case 'master':
            return '👑';
        case 'admin':
            return '🛡️';
        case 'accountant':
            return '💼';
        case 'technician':
            return '🔧';
        case 'cashier':
            return '💵';
        default:
            return '👤';
    }
}

function roleBadgeClasses(slug) {
    switch (slug) {
        case 'master':
            return 'bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-amber-500/20';
        case 'admin':
            return 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-blue-500/20';
        case 'accountant':
            return 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-emerald-500/20';
        default:
            return 'bg-gradient-to-br from-slate-100 to-slate-200 text-slate-700';
    }
}

const GROUP_ICONS = {
    access: '👤',
    dashboard: '📊',
    sales: '🧾',
    purchases: '📥',
    repairs: '🔧',
    technicians: '🧰',
    materials: '📦',
    services: '🛠️',
    inventory: '⚖️',
    payments: '💵',
    expenses: '📤',
    customers: '👥',
    suppliers: '🚚',
    statements: '📋',
    reports: '📈',
    settings: '⚙️',
};

function groupIcon(key) {
    return GROUP_ICONS[key] || '📁';
}

onMounted(() => {
    loadAll();
});
</script>
