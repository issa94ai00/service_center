<template>
    <div class="mx-auto max-w-5xl space-y-5">
        <!-- ------------------------------------------------ Identity header -->
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="relative h-24 bg-gradient-to-l from-blue-700 via-blue-800 to-cyan-900 sm:h-28">
                <div class="pointer-events-none absolute -start-16 -top-16 h-48 w-48 rounded-full bg-cyan-400/20 blur-3xl" aria-hidden="true" />
                <div class="pointer-events-none absolute -end-10 -bottom-20 h-48 w-48 rounded-full bg-blue-300/20 blur-3xl" aria-hidden="true" />
            </div>

            <div class="px-5 pb-5 sm:px-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <!-- Only the avatar rides up over the banner; the name stays on white. -->
                    <div class="flex items-end gap-4">
                        <div
                            class="-mt-10 flex h-20 w-20 shrink-0 select-none items-center justify-center rounded-2xl bg-gradient-to-tr from-blue-600 to-cyan-500 text-2xl font-bold text-white shadow-lg shadow-blue-600/30 ring-4 ring-white sm:-mt-12 sm:h-24 sm:w-24 sm:text-3xl"
                            aria-hidden="true"
                        >
                            {{ initials }}
                        </div>
                        <div class="min-w-0 pb-0.5 pt-2">
                            <h1 class="truncate text-xl font-bold text-slate-900 sm:text-2xl">{{ user?.name || '—' }}</h1>
                            <p class="truncate text-sm text-slate-500" dir="ltr">{{ user?.email }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 sm:pb-1">
                        <span
                            v-if="isMaster"
                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-300"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                            المدير العام (Master)
                        </span>
                        <span
                            v-for="role in roles"
                            v-else
                            :key="role.id"
                            class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-100"
                        >
                            {{ role.name }}
                        </span>
                        <span
                            v-if="!isMaster && !roles.length"
                            class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500"
                        >
                            بلا دور بعد
                        </span>
                    </div>
                </div>

                <!-- Quick facts -->
                <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div v-for="fact in facts" :key="fact.label" class="rounded-xl bg-slate-50 px-3.5 py-3 ring-1 ring-slate-100">
                        <dt class="flex items-center gap-1.5 text-[11px] font-medium text-slate-500">
                            <span aria-hidden="true">{{ fact.icon }}</span>
                            {{ fact.label }}
                        </dt>
                        <dd class="mt-1 truncate text-sm font-bold text-slate-900 tabular-nums" :title="fact.title || undefined">
                            {{ fact.value }}
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- ------------------------------------------------ Tabs -->
        <div class="-mx-4 overflow-x-auto px-4 lg:mx-0 lg:px-0">
            <div
                class="inline-flex min-w-full gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm sm:min-w-0"
                role="tablist"
                aria-label="أقسام الملف الشخصي"
                @keydown="onTabKeydown"
            >
                <button
                    v-for="t in tabs"
                    :id="`tab-${t.key}`"
                    :key="t.key"
                    :ref="(el) => (tabButtons[t.key] = el)"
                    type="button"
                    role="tab"
                    :aria-selected="tab === t.key"
                    :aria-controls="`panel-${t.key}`"
                    :tabindex="tab === t.key ? 0 : -1"
                    class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-300"
                    :class="tab === t.key ? 'bg-slate-900 font-semibold text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
                    @click="selectTab(t.key)"
                >
                    <span aria-hidden="true">{{ t.icon }}</span>
                    {{ t.label }}
                    <span
                        v-if="t.key === 'sessions' && sessions.length"
                        class="rounded-full px-1.5 text-[11px] font-semibold tabular-nums"
                        :class="tab === t.key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                    >{{ sessions.length }}</span>
                    <span
                        v-else-if="t.key === 'account' && dirty"
                        class="h-1.5 w-1.5 rounded-full bg-amber-500"
                        title="تغييرات غير محفوظة"
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
        </div>

        <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ loadError }}
        </div>

        <!-- ================================================ Account -->
        <section
            v-show="tab === 'account'"
            id="panel-account"
            role="tabpanel"
            aria-labelledby="tab-account"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
        >
            <SectionHeading icon="🪪" title="البيانات الشخصية" hint="الاسم الذي يظهر على المستندات التي تُنشئها، وبريد الدخول ورقم التواصل" />

            <form class="mt-5 space-y-5" novalidate @submit.prevent="saveAccount">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="pf-name" class="mb-1 block text-sm font-medium text-slate-700">
                            الاسم الكامل <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="pf-name"
                            v-model.trim="account.name"
                            type="text"
                            autocomplete="name"
                            maxlength="150"
                            :aria-invalid="Boolean(accountErrors.name)"
                            :class="[fieldClass, accountErrors.name && fieldErrorClass]"
                            @input="accountErrors.name = ''"
                        />
                        <FieldNote :error="accountErrors.name" hint="يظهر هذا الاسم في سجل التعديلات وعلى الفواتير والسندات كاسم المُنشئ" />
                    </div>

                    <div>
                        <label for="pf-email" class="mb-1 block text-sm font-medium text-slate-700">
                            البريد الإلكتروني <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="pf-email"
                            v-model.trim="account.email"
                            type="email"
                            autocomplete="email"
                            dir="ltr"
                            :aria-invalid="Boolean(accountErrors.email)"
                            :class="[fieldClass, 'text-left', accountErrors.email && fieldErrorClass]"
                            @input="accountErrors.email = ''"
                        />
                        <FieldNote :error="accountErrors.email" hint="تسجّل الدخول به — تأكد من كتابته صحيحاً قبل الحفظ" />
                    </div>

                    <div>
                        <label for="pf-phone" class="mb-1 block text-sm font-medium text-slate-700">رقم الهاتف</label>
                        <input
                            id="pf-phone"
                            v-model.trim="account.phone"
                            type="tel"
                            autocomplete="tel"
                            dir="ltr"
                            inputmode="tel"
                            placeholder="09xx xxx xxx"
                            maxlength="30"
                            :aria-invalid="Boolean(accountErrors.phone)"
                            :class="[fieldClass, 'text-left', accountErrors.phone && fieldErrorClass]"
                            @input="accountErrors.phone = ''"
                        />
                        <FieldNote :error="accountErrors.phone" hint="اختياري — للتواصل الداخلي بين فريق المحل" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <span
                            v-if="dirty"
                            class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-100"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                            تغييرات غير محفوظة
                        </span>
                        <span v-else>لا تغييرات</span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" :class="secondaryBtn" :disabled="savingAccount || !dirty" @click="resetAccount">
                            تراجع
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg px-5 py-2 text-sm font-medium text-white transition focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 disabled:opacity-40"
                            :class="accountSaved ? 'bg-emerald-600' : 'bg-blue-600 hover:bg-blue-700'"
                            :disabled="savingAccount || !dirty"
                        >
                            {{ savingAccount ? 'جارٍ الحفظ...' : accountSaved ? 'تم الحفظ ✓' : 'حفظ البيانات' }}
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- ================================================ Password -->
        <section
            v-show="tab === 'password'"
            id="panel-password"
            role="tabpanel"
            aria-labelledby="tab-password"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
        >
            <SectionHeading icon="🔐" title="كلمة المرور" hint="اختر كلمة مرور لا تستخدمها في مكانٍ آخر، ولا تشاركها مع زملائك — لكلٍّ حسابه" />

            <form class="mt-5 grid gap-6 lg:grid-cols-5" novalidate @submit.prevent="savePassword">
                <div class="space-y-4 lg:col-span-3">
                    <PasswordInput
                        id="pf-current"
                        v-model="pw.current_password"
                        label="كلمة المرور الحالية"
                        autocomplete="current-password"
                        :error="pwErrors.current_password"
                        @input="pwErrors.current_password = ''"
                    />

                    <PasswordInput
                        id="pf-new"
                        v-model="pw.password"
                        label="كلمة المرور الجديدة"
                        autocomplete="new-password"
                        :error="pwErrors.password"
                        @input="pwErrors.password = ''"
                    >
                        <!-- Strength meter -->
                        <div v-if="pw.password" class="mt-2 space-y-1" aria-live="polite">
                            <div class="flex gap-1" aria-hidden="true">
                                <span
                                    v-for="n in 4"
                                    :key="n"
                                    class="h-1.5 flex-1 rounded-full transition-colors"
                                    :class="n <= strength.score ? strength.bar : 'bg-slate-200'"
                                ></span>
                            </div>
                            <p class="text-xs" :class="strength.text">{{ strength.label }}</p>
                        </div>
                    </PasswordInput>

                    <PasswordInput
                        id="pf-confirm"
                        v-model="pw.password_confirmation"
                        label="تأكيد كلمة المرور الجديدة"
                        autocomplete="new-password"
                        :error="pwErrors.password_confirmation"
                        @input="pwErrors.password_confirmation = ''"
                    />

                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3">
                        <input v-model="pw.logout_others" type="checkbox" class="peer sr-only" />
                        <span
                            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 rounded-full bg-slate-300 transition-colors after:absolute after:start-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300 rtl:peer-checked:after:-translate-x-full"
                            aria-hidden="true"
                        ></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-800">تسجيل الخروج من الأجهزة الأخرى</span>
                            <span class="block text-xs text-slate-500">
                                تبقى جلستك هذه مفتوحة، وتُنهى كل الجلسات الأخرى فور تغيير كلمة المرور
                                <template v-if="otherSessionsCount">({{ otherSessionsCount }} حالياً)</template>
                            </span>
                        </span>
                    </label>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" :class="secondaryBtn" :disabled="savingPassword || !pwTouched" @click="resetPassword">
                            مسح الحقول
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg px-5 py-2 text-sm font-medium text-white transition focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 disabled:opacity-40"
                            :class="passwordSaved ? 'bg-emerald-600' : 'bg-blue-600 hover:bg-blue-700'"
                            :disabled="savingPassword || !pwReady"
                        >
                            {{ savingPassword ? 'جارٍ التغيير...' : passwordSaved ? 'تم التغيير ✓' : 'تغيير كلمة المرور' }}
                        </button>
                    </div>
                </div>

                <!-- Requirements checklist -->
                <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4 lg:col-span-2">
                    <p class="text-sm font-bold text-slate-800">شروط كلمة المرور</p>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="rule in pwChecks"
                            :key="rule.label"
                            class="flex items-start gap-2 text-sm transition-colors"
                            :class="rule.ok ? 'text-emerald-700' : 'text-slate-500'"
                        >
                            <span
                                class="mt-0.5 flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ring-1"
                                :class="rule.ok ? 'bg-emerald-500 text-white ring-emerald-500' : 'bg-white text-transparent ring-slate-300'"
                                aria-hidden="true"
                            >✓</span>
                            <span>
                                {{ rule.label }}
                                <span v-if="rule.optional" class="text-xs text-slate-400">(مستحسن)</span>
                            </span>
                        </li>
                    </ul>
                    <p class="mt-4 text-xs leading-relaxed text-slate-400">
                        نصيحة: جملة قصيرة من كلمات عربية أو إنجليزية مع رقم أسهل حفظاً وأصعب تخميناً من الرموز المعقدة.
                    </p>
                </aside>
            </form>
        </section>

        <!-- ================================================ Sessions -->
        <section
            v-show="tab === 'sessions'"
            id="panel-sessions"
            role="tabpanel"
            aria-labelledby="tab-sessions"
            class="space-y-5"
        >
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <SectionHeading icon="💻" title="الأجهزة والجلسات" hint="المتصفحات التي ما زالت مسجّلة الدخول بحسابك. أنهِ ما لا تعرفه." />
                    <div class="flex items-center gap-2">
                        <button type="button" :class="secondaryBtn" :disabled="loadingSessions" @click="refreshSessions">
                            {{ loadingSessions ? 'جارٍ التحديث...' : '🔄 تحديث' }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-200 disabled:opacity-40"
                            :disabled="!otherSessionsCount"
                            :title="otherSessionsCount ? '' : 'لا توجد جلسات أخرى مفتوحة'"
                            @click="endOthersOpen = true"
                        >
                            إنهاء الجلسات الأخرى
                        </button>
                    </div>
                </div>

                <div v-if="loading" class="mt-5 space-y-3">
                    <div v-for="n in 2" :key="n" class="h-16 animate-pulse rounded-xl bg-slate-100"></div>
                </div>

                <EmptyState
                    v-else-if="!sessions.length"
                    icon="🕶️"
                    title="لا تتوفر قائمة الجلسات"
                    hint="يحتفظ الخادم بالجلسات خارج قاعدة البيانات، فلا يمكن عرضها هنا. ما زال بإمكانك تغيير كلمة المرور لإخراج الأجهزة الأخرى."
                />

                <ul v-else class="mt-5 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    <li
                        v-for="s in sessions"
                        :key="s.id"
                        class="flex items-center gap-4 px-4 py-3.5"
                        :class="s.is_current ? 'bg-emerald-50/50' : ''"
                    >
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-xl ring-1"
                            :class="s.is_current ? 'bg-emerald-100 ring-emerald-200' : 'bg-slate-100 ring-slate-200'"
                            aria-hidden="true"
                        >
                            {{ deviceIcon(s.device) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900">
                                <span>{{ s.browser }} على {{ s.platform }}</span>
                                <span
                                    v-if="s.is_current"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    هذا الجهاز
                                </span>
                            </p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-500">
                                <span dir="ltr" class="tabular-nums">{{ s.ip_address || '—' }}</span>
                                <span :title="formatDateTime(s.last_activity)">
                                    آخر نشاط: {{ relativeTime(s.last_activity) }}
                                </span>
                            </p>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Recent activity -->
            <div v-if="activity.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <SectionHeading icon="🕘" title="آخر ما قمت به" hint="تغييرات الوصول المسجّلة باسمك — المستخدمون والأدوار والصلاحيات" />
                <ol class="mt-4 space-y-2">
                    <li v-for="item in activity" :key="item.id" class="flex items-center gap-3 text-sm">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-base" aria-hidden="true">
                            {{ eventMeta(item.event).icon }}
                        </span>
                        <span class="min-w-0 flex-1 truncate text-slate-700">
                            {{ eventMeta(item.event).label }}
                            <span class="text-slate-400">#{{ item.subject_id }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-slate-400" :title="formatDateTime(item.created_at)">
                            {{ relativeTime(item.created_at) }}
                        </span>
                    </li>
                </ol>
            </div>
        </section>

        <!-- ================================================ Access -->
        <section
            v-show="tab === 'access'"
            id="panel-access"
            role="tabpanel"
            aria-labelledby="tab-access"
            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"
        >
            <SectionHeading icon="🛡️" title="أدواري وصلاحياتي" hint="ما يحق لك فعله في النظام. تُعدَّل الأدوار من شاشة «المستخدمون» بيد من يملك ذلك." />

            <div v-if="isMaster" class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                حسابك هو <strong>المدير العام</strong>: يملك كل الصلاحيات الحالية والمستقبلية تلقائياً، ولا يمكن تعطيله ما دام الوحيد.
            </div>

            <EmptyState
                v-else-if="!roles.length"
                icon="🔒"
                title="لم يُسند إليك أي دور بعد"
                hint="اطلب من مدير النظام إسناد دور إلى حسابك لتظهر الشاشات المناسبة لعملك."
            />

            <div v-else class="mt-5 space-y-4">
                <article v-for="role in roles" :key="role.id" class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-slate-900">{{ role.name }}</h3>
                            <p v-if="role.description" class="text-xs text-slate-500">{{ role.description }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 tabular-nums">
                            {{ (role.permissions || []).length }} صلاحية
                        </span>
                    </div>

                    <div v-if="groupedPermissions(role).length" class="mt-3 space-y-3">
                        <div v-for="group in groupedPermissions(role)" :key="group.key">
                            <p class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ group.label }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="p in group.items"
                                    :key="p.id"
                                    class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 ring-1 ring-blue-100"
                                    :title="p.slug"
                                >
                                    {{ p.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-3 text-xs text-slate-400">هذا الدور بلا صلاحيات بعد.</p>
                </article>
            </div>
        </section>

        <!-- ------------------------------------------------ End-other-sessions dialog -->
        <BaseModal
            :open="endOthersOpen"
            title="إنهاء الجلسات الأخرى"
            subtitle="للتأكد أنك أنت، أدخل كلمة مرورك الحالية"
            max-width="max-w-md"
            @close="closeEndOthers"
        >
            <form class="space-y-4" novalidate @submit.prevent="confirmEndOthers">
                <p class="text-sm text-slate-600">
                    سيتم تسجيل الخروج من
                    <strong class="tabular-nums">{{ otherSessionsCount }}</strong>
                    {{ otherSessionsCount === 1 ? 'جلسة أخرى' : 'جلسات أخرى' }}.
                    تبقى جلستك على هذا الجهاز مفتوحة.
                </p>
                <PasswordInput
                    id="pf-end-pw"
                    v-model="endOthersPassword"
                    label="كلمة المرور"
                    autocomplete="current-password"
                    :error="endOthersError"
                    autofocus
                    @input="endOthersError = ''"
                />
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" :class="secondaryBtn" :disabled="endingOthers" @click="closeEndOthers">إلغاء</button>
                    <button
                        type="submit"
                        class="rounded-lg bg-rose-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-300 focus:ring-offset-2 disabled:opacity-40"
                        :disabled="endingOthers || !endOthersPassword"
                    >
                        {{ endingOthers ? 'جارٍ الإنهاء...' : 'إنهاء الجلسات' }}
                    </button>
                </div>
            </form>
        </BaseModal>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { profileApi } from '../../services/api';
import { useAuthStore } from '../../stores/auth';
import { useToast } from '../../composables/useToast';
import { confirm } from '../../composables/useConfirm';
import { apiFieldErrors, apiMessage, formatDate, formatDateTime, parseDateTime, relativeDays } from '../../utils/format';
import BaseModal from '../../components/ui/BaseModal.vue';
import EmptyState from '../../components/ui/EmptyState.vue';

const auth = useAuthStore();
const toast = useToast();
const route = useRoute();
const router = useRouter();

// ------------------------------------------------------------ Local pieces

const fieldClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100';
const fieldErrorClass = 'border-red-300 bg-red-50/40 focus:border-red-500 focus:ring-red-100';
const secondaryBtn =
    'rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:opacity-40';

/** Section title with an icon chip, matching the Settings screen. */
const SectionHeading = defineComponent({
    props: { icon: String, title: String, hint: String },
    setup: (props) => () =>
        h('div', { class: 'flex items-start gap-3' }, [
            h('span', { class: 'flex h-9 w-9 shrink-0 select-none items-center justify-center rounded-xl bg-blue-50 text-lg ring-1 ring-blue-100', 'aria-hidden': 'true' }, props.icon),
            h('div', [
                h('h2', { class: 'font-bold text-slate-900' }, props.title),
                props.hint ? h('p', { class: 'mt-0.5 text-xs leading-relaxed text-slate-500' }, props.hint) : null,
            ]),
        ]),
});

/** Error-or-hint line under a field. */
const FieldNote = defineComponent({
    props: { error: String, hint: String },
    setup: (props) => () =>
        props.error
            ? h('p', { class: 'mt-1 text-xs font-medium text-red-600', role: 'alert' }, props.error)
            : props.hint
              ? h('p', { class: 'mt-1 text-xs text-slate-400' }, props.hint)
              : null,
});

/** Password field with a show/hide toggle; the slot renders under the input. */
const PasswordInput = defineComponent({
    props: {
        id: { type: String, required: true },
        modelValue: { type: String, default: '' },
        label: { type: String, required: true },
        autocomplete: { type: String, default: 'off' },
        error: { type: String, default: '' },
        autofocus: Boolean,
    },
    emits: ['update:modelValue', 'input'],
    setup(props, { emit, slots }) {
        const shown = ref(false);
        return () =>
            h('div', [
                h('label', { for: props.id, class: 'mb-1 block text-sm font-medium text-slate-700' }, props.label),
                h('div', { class: 'relative', dir: 'ltr' }, [
                    h('input', {
                        id: props.id,
                        value: props.modelValue,
                        type: shown.value ? 'text' : 'password',
                        autocomplete: props.autocomplete,
                        autofocus: props.autofocus,
                        dir: 'ltr',
                        'aria-invalid': Boolean(props.error),
                        class: [fieldClass, 'pe-11 text-left', props.error && fieldErrorClass],
                        onInput: (e) => {
                            emit('update:modelValue', e.target.value);
                            emit('input', e);
                        },
                    }),
                    h(
                        'button',
                        {
                            type: 'button',
                            class: 'absolute end-1.5 top-1/2 -translate-y-1/2 rounded-md px-2 py-1 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-100',
                            'aria-label': shown.value ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور',
                            'aria-pressed': shown.value,
                            onClick: () => (shown.value = !shown.value),
                        },
                        shown.value ? 'إخفاء' : 'إظهار'
                    ),
                ]),
                props.error ? h('p', { class: 'mt-1 text-xs font-medium text-red-600', role: 'alert' }, props.error) : null,
                slots.default?.(),
            ]);
    },
});

// ------------------------------------------------------------ State

const loading = ref(true);
const loadError = ref('');
const user = ref(auth.user);
const sessions = ref([]);
const activity = ref([]);

const tabs = [
    { key: 'account', label: 'البيانات الشخصية', icon: '🪪' },
    { key: 'password', label: 'كلمة المرور', icon: '🔐' },
    { key: 'sessions', label: 'الأجهزة والجلسات', icon: '💻' },
    { key: 'access', label: 'الصلاحيات', icon: '🛡️' },
];
const tabKeys = tabs.map((t) => t.key);
const tab = ref(tabKeys.includes(route.hash.slice(1)) ? route.hash.slice(1) : 'account');
const tabButtons = reactive({});

function selectTab(key) {
    tab.value = key;
    router.replace({ hash: `#${key}` });
}

/** Arrow keys move between tabs (RTL: → goes to the previous tab visually on the right). */
function onTabKeydown(e) {
    const i = tabKeys.indexOf(tab.value);
    let next = null;
    if (e.key === 'ArrowLeft') next = tabKeys[(i + 1) % tabKeys.length];
    if (e.key === 'ArrowRight') next = tabKeys[(i - 1 + tabKeys.length) % tabKeys.length];
    if (e.key === 'Home') next = tabKeys[0];
    if (e.key === 'End') next = tabKeys[tabKeys.length - 1];
    if (!next) return;
    e.preventDefault();
    selectTab(next);
    tabButtons[next]?.focus();
}

watch(
    () => route.hash,
    (hash) => {
        const key = hash.slice(1);
        if (tabKeys.includes(key) && key !== tab.value) tab.value = key;
    }
);

// ------------------------------------------------------------ Derived

const roles = computed(() => user.value?.roles || []);
const isMaster = computed(() => roles.value.some((r) => r.slug === 'master'));

const initials = computed(() => {
    const name = user.value?.name || '';
    return name.split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('') || '؟';
});

const permissionCount = computed(() => {
    const slugs = new Set();
    roles.value.forEach((r) => (r.permissions || []).forEach((p) => slugs.add(p.slug)));
    return slugs.size;
});

const otherSessionsCount = computed(() => sessions.value.filter((s) => !s.is_current).length);

const facts = computed(() => [
    { icon: '📞', label: 'الهاتف', value: user.value?.phone || '—' },
    {
        icon: '🛡️',
        label: 'الصلاحيات',
        value: isMaster.value ? 'كاملة' : `${permissionCount.value}`,
        title: isMaster.value ? 'المدير العام يملك كل الصلاحيات' : '',
    },
    { icon: '💻', label: 'الجلسات المفتوحة', value: loading.value ? '…' : sessions.value.length || '—' },
    {
        icon: '📅',
        label: 'عضو منذ',
        value: user.value?.created_at ? relativeDays(user.value.created_at) : '—',
        title: user.value?.created_at ? formatDate(user.value.created_at) : '',
    },
]);

// ------------------------------------------------------------ Account form

const account = reactive({ name: '', email: '', phone: '' });
const accountErrors = reactive({ name: '', email: '', phone: '' });
const savingAccount = ref(false);
const accountSaved = ref(false);
let accountBaseline = JSON.stringify(account);

function fillAccount(u) {
    Object.assign(account, { name: u?.name || '', email: u?.email || '', phone: u?.phone || '' });
    accountBaseline = JSON.stringify(account);
}

const dirty = computed(() => JSON.stringify(account) !== accountBaseline);

function resetAccount() {
    fillAccount(user.value);
    Object.keys(accountErrors).forEach((k) => (accountErrors[k] = ''));
}

function validateAccount() {
    Object.keys(accountErrors).forEach((k) => (accountErrors[k] = ''));
    if (!account.name) accountErrors.name = 'أدخل اسمك';
    if (!account.email) accountErrors.email = 'أدخل البريد الإلكتروني';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(account.email)) accountErrors.email = 'صيغة البريد الإلكتروني غير صحيحة';
    return !accountErrors.name && !accountErrors.email;
}

async function saveAccount() {
    if (!validateAccount()) return;

    savingAccount.value = true;
    try {
        const { user: saved, message } = await profileApi.update({
            name: account.name,
            email: account.email,
            phone: account.phone || null,
        });
        user.value = saved;
        auth.setUser(saved);
        fillAccount(saved);
        accountSaved.value = true;
        setTimeout(() => (accountSaved.value = false), 2500);
        toast.success(message || 'تم حفظ بياناتك.');
    } catch (e) {
        Object.assign(accountErrors, apiFieldErrors(e));
        if (!Object.values(accountErrors).some(Boolean)) toast.error(apiMessage(e, 'تعذّر حفظ البيانات.'));
    } finally {
        savingAccount.value = false;
    }
}

onBeforeRouteLeave(async () => {
    if (!dirty.value) return true;
    return confirm({
        title: 'تغييرات غير محفوظة',
        message: 'لديك تعديلات على بياناتك الشخصية لم تُحفظ. مغادرة الصفحة الآن تُلغيها.',
        confirmLabel: 'مغادرة بلا حفظ',
        cancelLabel: 'البقاء',
        tone: 'danger',
    });
});

// ------------------------------------------------------------ Password form

const pw = reactive({ current_password: '', password: '', password_confirmation: '', logout_others: true });
const pwErrors = reactive({ current_password: '', password: '', password_confirmation: '' });
const savingPassword = ref(false);
const passwordSaved = ref(false);

const pwTouched = computed(() => Boolean(pw.current_password || pw.password || pw.password_confirmation));

const pwChecks = computed(() => [
    { label: '8 أحرف على الأقل', ok: pw.password.length >= 8 },
    { label: 'تختلف عن كلمة المرور الحالية', ok: Boolean(pw.password) && pw.password !== pw.current_password },
    { label: 'التأكيد يطابق كلمة المرور الجديدة', ok: Boolean(pw.password) && pw.password === pw.password_confirmation },
    { label: 'تجمع بين حروف وأرقام', ok: /\d/.test(pw.password) && /[^\d\s]/.test(pw.password), optional: true },
    { label: '12 حرفاً أو أكثر', ok: pw.password.length >= 12, optional: true },
]);

const pwReady = computed(() => Boolean(pw.current_password) && pwChecks.value.filter((c) => !c.optional).every((c) => c.ok));

const strength = computed(() => {
    const p = pw.password;
    let score = 0;
    if (p.length >= 8) score++;
    if (p.length >= 12) score++;
    if (/\d/.test(p) && /[^\d\s]/.test(p)) score++;
    if (/[A-Z]/.test(p) && /[a-z]/.test(p)) score++;
    if (/[^\p{L}\p{N}\s]/u.test(p)) score++;
    score = Math.min(4, Math.max(p.length >= 8 ? 1 : 0, score - (p.length < 8 ? 1 : 0)));
    const levels = [
        { label: 'قصيرة جداً', bar: 'bg-red-500', text: 'text-red-600' },
        { label: 'ضعيفة', bar: 'bg-orange-500', text: 'text-orange-600' },
        { label: 'مقبولة', bar: 'bg-amber-500', text: 'text-amber-600' },
        { label: 'جيدة', bar: 'bg-lime-500', text: 'text-lime-700' },
        { label: 'قوية', bar: 'bg-emerald-500', text: 'text-emerald-700' },
    ];
    return { score, ...levels[score] };
});

function resetPassword() {
    Object.assign(pw, { current_password: '', password: '', password_confirmation: '' });
    Object.keys(pwErrors).forEach((k) => (pwErrors[k] = ''));
}

async function savePassword() {
    Object.keys(pwErrors).forEach((k) => (pwErrors[k] = ''));
    if (pw.password !== pw.password_confirmation) {
        pwErrors.password_confirmation = 'تأكيد كلمة المرور لا يطابق';
        return;
    }

    savingPassword.value = true;
    try {
        const { message } = await profileApi.updatePassword({ ...pw });
        resetPassword();
        passwordSaved.value = true;
        setTimeout(() => (passwordSaved.value = false), 2500);
        toast.success(message || 'تم تغيير كلمة المرور.');
        if (pw.logout_others) refreshSessions();
    } catch (e) {
        const errors = apiFieldErrors(e);
        Object.assign(pwErrors, errors);
        if (!Object.keys(errors).length) toast.error(apiMessage(e, 'تعذّر تغيير كلمة المرور.'));
    } finally {
        savingPassword.value = false;
    }
}

// ------------------------------------------------------------ Sessions

const loadingSessions = ref(false);
const endOthersOpen = ref(false);
const endOthersPassword = ref('');
const endOthersError = ref('');
const endingOthers = ref(false);

async function refreshSessions() {
    loadingSessions.value = true;
    try {
        sessions.value = await profileApi.sessions();
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تحديث الجلسات.'));
    } finally {
        loadingSessions.value = false;
    }
}

function closeEndOthers() {
    if (endingOthers.value) return;
    endOthersOpen.value = false;
    endOthersPassword.value = '';
    endOthersError.value = '';
}

async function confirmEndOthers() {
    endingOthers.value = true;
    try {
        const { message, data } = await profileApi.endOtherSessions(endOthersPassword.value);
        if (Array.isArray(data)) sessions.value = data;
        toast.success(message || 'تم إنهاء الجلسات الأخرى.');
        endingOthers.value = false;
        closeEndOthers();
    } catch (e) {
        endOthersError.value = apiFieldErrors(e).password || apiMessage(e, 'تعذّر إنهاء الجلسات.');
    } finally {
        endingOthers.value = false;
    }
}

function deviceIcon(device) {
    return { mobile: '📱', tablet: '📟' }[device] || '🖥️';
}

/** Minutes and hours for today, then the shared day-level wording. */
function relativeTime(value) {
    const d = parseDateTime(value);
    if (!d) return '—';
    const minutes = Math.floor((Date.now() - d.getTime()) / 60000);
    if (minutes < 1) return 'الآن';
    if (minutes < 60) return minutes === 1 ? 'منذ دقيقة' : minutes === 2 ? 'منذ دقيقتين' : minutes <= 10 ? `منذ ${minutes} دقائق` : `منذ ${minutes} دقيقة`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours === 1 ? 'منذ ساعة' : hours === 2 ? 'منذ ساعتين' : hours <= 10 ? `منذ ${hours} ساعات` : `منذ ${hours} ساعة`;
    return relativeDays(value);
}

const EVENTS = {
    'role.created': { label: 'إنشاء دور', icon: '🆕' },
    'role.updated': { label: 'تعديل دور', icon: '✏️' },
    'role.deleted': { label: 'حذف دور', icon: '🗑️' },
    'role.members_changed': { label: 'تغيير مستخدمي الدور', icon: '👥' },
    'user.created': { label: 'إنشاء مستخدم', icon: '🆕' },
    'user.updated': { label: 'تعديل مستخدم', icon: '✏️' },
    'user.deleted': { label: 'حذف مستخدم', icon: '🗑️' },
};

function eventMeta(event) {
    return EVENTS[event] || { label: event, icon: '📝' };
}

// ------------------------------------------------------------ Access

const GROUPS = {
    dashboard: 'لوحة التحكم',
    sales: 'المبيعات',
    purchases: 'المشتريات',
    payments: 'السندات',
    expenses: 'المصاريف',
    repairs: 'الصيانة',
    technicians: 'الفنيون',
    customers: 'العملاء',
    suppliers: 'الموردون',
    materials: 'المواد',
    services: 'الخدمات',
    inventory: 'المخزون',
    statements: 'كشوف الحساب',
    reports: 'التقارير',
    currencies: 'العملات',
    users: 'المستخدمون',
    roles: 'الأدوار',
    settings: 'الإعدادات',
    features: 'ميزات النظام',
};

function groupedPermissions(role) {
    const map = new Map();
    for (const p of role.permissions || []) {
        const key = p.group || p.slug.split('.')[0] || 'other';
        if (!map.has(key)) map.set(key, { key, label: GROUPS[key] || key, items: [] });
        map.get(key).items.push(p);
    }
    return [...map.values()];
}

// ------------------------------------------------------------ Load

onMounted(async () => {
    fillAccount(auth.user);
    try {
        const data = await profileApi.show();
        user.value = data.user;
        sessions.value = data.sessions || [];
        activity.value = data.activity || [];
        auth.setUser(data.user);
        if (!dirty.value) fillAccount(data.user);
    } catch (e) {
        loadError.value = apiMessage(e, 'تعذّر تحميل الملف الشخصي.');
    } finally {
        loading.value = false;
    }
});
</script>
