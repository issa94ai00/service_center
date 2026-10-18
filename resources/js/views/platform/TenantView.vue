<template>
    <div class="space-y-6">
        <router-link :to="{ name: 'tenants' }" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 dark:text-slate-400 transition hover:text-slate-900 dark:hover:text-white">
            <AppIcon name="chevron-right" class="ltr:rotate-180" />
            كل المتاجر
        </router-link>

        <!-- Loading -->
        <div v-if="loading" class="animate-pulse space-y-4" aria-busy="true">
            <div class="h-32 rounded-2xl bg-slate-200/70 dark:bg-slate-800"></div>
            <div class="h-24 rounded-2xl bg-slate-200/50 dark:bg-slate-800/60"></div>
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="h-96 rounded-2xl bg-slate-200/50 dark:bg-slate-800/60 lg:col-span-2"></div>
                <div class="h-96 rounded-2xl bg-slate-200/50 dark:bg-slate-800/60"></div>
            </div>
        </div>

        <template v-else-if="tenant">
            <!-- Header -->
            <section class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)] sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-4">
                        <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br text-lg font-bold text-white shadow-sm" :class="avatarTone(tenant.slug)">
                            {{ initials(tenant.name) }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="truncate text-xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-2xl">{{ tenant.name }}</h1>
                                <TenantStatus :tenant="tenant" />
                            </div>
                            <div class="mt-1 flex items-center gap-1.5">
                                <a :href="tenant.url" target="_blank" rel="noopener" class="truncate font-mono text-sm text-blue-600 dark:text-blue-400 hover:underline" dir="ltr">{{ tenant.host }}</a>
                                <button
                                    type="button"
                                    class="grid h-7 w-7 shrink-0 place-items-center rounded-md text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200"
                                    :title="copied ? 'تم النسخ' : 'نسخ الرابط'"
                                    :aria-label="copied ? 'تم النسخ' : 'نسخ الرابط'"
                                    @click="copyUrl"
                                >
                                    <AppIcon :name="copied ? 'check' : 'copy'" class="text-sm" :class="copied ? 'text-emerald-500' : ''" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a
                            :href="tenant.url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            <AppIcon name="external" /> فتح المتجر
                        </a>
                        <button
                            v-if="tenant.status === 'active'"
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-xl border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 px-4 text-sm font-semibold text-amber-800 dark:text-amber-300 transition hover:bg-amber-100 dark:hover:bg-amber-950/70 disabled:opacity-50"
                            :disabled="busy"
                            @click="setStatus('suspended')"
                        >
                            <AppIcon name="pause" /> إيقاف
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                            :disabled="busy"
                            @click="setStatus('active')"
                        >
                            <AppIcon name="play" /> تفعيل المتجر
                        </button>
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-slate-100 dark:border-slate-800 pt-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-slate-400">الباقة</dt>
                        <dd class="mt-0.5 font-semibold text-slate-800 dark:text-slate-200">{{ tenant.plan || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">الاشتراك</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-bold tabular-nums" :class="subscription.tone">{{ subscription.label }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">أنشئ في</dt>
                        <dd class="mt-0.5 font-semibold text-slate-800 dark:text-slate-200">
                            {{ tenant.created_at ? formatDate(tenant.created_at) : '—' }}
                            <span v-if="tenant.source === 'signup'" class="ms-1 rounded-full bg-violet-50 dark:bg-violet-950/50 px-2 py-0.5 text-[10px] font-bold text-violet-700 dark:text-violet-300">تسجيل ذاتي</span>
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-xs text-slate-400">قاعدة البيانات</dt>
                        <dd class="mt-0.5 truncate font-mono text-xs font-semibold text-slate-600 dark:text-slate-300" dir="ltr">{{ tenant.database }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Maintenance -->
            <div
                v-if="tenant.maintenance_at"
                class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-sky-200 dark:border-sky-900/60 bg-sky-50 dark:bg-sky-950/40 px-4 py-3 text-sky-800 dark:text-sky-300"
                role="status"
            >
                <p class="flex items-center gap-2 text-sm font-semibold">
                    <AppIcon name="wrench" class="text-base" />
                    المتجر مغلق أمام مستخدميه: {{ tenant.maintenance_reason || 'صيانة' }} ({{ timeAgo(tenant.maintenance_at) }}).
                </p>
                <button type="button" class="rounded-lg bg-white/70 dark:bg-slate-900/60 px-3 py-1.5 text-xs font-bold ring-1 ring-current/20 hover:bg-white dark:hover:bg-slate-900" :disabled="busy" @click="toggleMaintenance">
                    إعادة فتح المتجر
                </button>
            </div>

            <!-- Subscription warning -->
            <div
                v-if="tenant.status === 'active' && subscription.days !== null && subscription.days <= EXPIRING_SOON_DAYS"
                class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3"
                :class="subscription.days < 0
                    ? 'border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300'
                    : 'border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300'"
                role="status"
            >
                <p class="flex items-center gap-2 text-sm font-semibold">
                    <AppIcon name="alert" class="text-base" />
                    {{ subscription.days < 0 ? 'انتهى اشتراك هذا المتجر وأُغلق أمام مستخدميه.' : `ينتهي اشتراك هذا المتجر ${subscription.days === 0 ? 'اليوم' : `خلال ${subscription.days} يوم`}.` }}
                </p>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg bg-white/70 dark:bg-slate-900/60 px-3 py-1.5 text-xs font-bold ring-1 ring-current/20 hover:bg-white dark:hover:bg-slate-900" :disabled="busy" @click="quickExtend(1)">تمديد شهر</button>
                    <button type="button" class="rounded-lg bg-white/70 dark:bg-slate-900/60 px-3 py-1.5 text-xs font-bold ring-1 ring-current/20 hover:bg-white dark:hover:bg-slate-900" :disabled="busy" @click="quickExtend(12)">تمديد سنة</button>
                </div>
            </div>

            <p v-if="tenant.database_error" class="flex items-center gap-2 rounded-2xl border border-red-200 dark:border-red-900/60 bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                <AppIcon name="alert" /> {{ tenant.database_error }}
            </p>

            <!-- Stats -->
            <section v-if="tenant.stats" class="overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)]" aria-label="إحصاءات المتجر">
                <div class="-mb-px -me-px grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
                    <div v-for="s in statCards" :key="s.label" class="border-b border-e border-slate-100 dark:border-slate-800 p-4">
                        <p class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                            <AppIcon :name="s.icon" class="text-slate-400" />
                            {{ s.label }}
                        </p>
                        <p class="mt-1.5 truncate text-lg font-bold tabular-nums text-slate-900 dark:text-white" :title="s.title || String(s.value)">{{ s.value }}</p>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
                <!-- Details -->
                <form class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)] lg:col-span-2" novalidate @submit.prevent="saveDetails">
                    <div class="space-y-6 p-5 sm:p-6">
                        <fieldset>
                            <legend class="mb-4 flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                                <AppIcon name="store" class="text-slate-400" /> المتجر والاشتراك
                            </legend>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <FormField label="اسم المتجر" required>
                                    <input v-model.trim="details.name" type="text" :class="INPUT" />
                                </FormField>
                                <FormField label="الباقة">
                                    <input v-model.trim="details.plan" type="text" :class="INPUT" placeholder="مثال: سنوية" />
                                </FormField>
                                <FormField label="ينتهي الاشتراك في" class="sm:col-span-2">
                                    <div class="flex flex-wrap gap-2">
                                        <input v-model="details.expires_at" type="date" :class="[INPUT, 'sm:max-w-56']" />
                                        <button v-for="ext in EXTENSIONS" :key="ext.months" type="button" :class="CHIP" @click="extend(ext.months)">+ {{ ext.label }}</button>
                                        <button v-if="details.expires_at" type="button" :class="CHIP" @click="details.expires_at = ''">بلا انتهاء</button>
                                    </div>
                                    <p class="mt-1.5 text-xs text-slate-400">فارغ = اشتراك مفتوح. بعد هذا التاريخ يُغلق المتجر تلقائياً.</p>
                                </FormField>
                            </div>
                        </fieldset>

                        <fieldset class="border-t border-slate-100 dark:border-slate-800 pt-6">
                            <legend class="sr-only">المالك</legend>
                            <p class="mb-4 flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                                <AppIcon name="user" class="text-slate-400" /> المالك والتواصل
                            </p>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <FormField label="اسم المالك">
                                    <input v-model.trim="details.owner_name" type="text" :class="INPUT" />
                                </FormField>
                                <FormField label="بريد المالك">
                                    <input v-model.trim="details.owner_email" type="email" dir="ltr" :class="INPUT" />
                                </FormField>
                                <FormField label="هاتف التواصل">
                                    <input v-model.trim="details.contact_phone" type="tel" dir="ltr" :class="INPUT" />
                                </FormField>
                            </div>
                        </fieldset>

                        <FormField label="ملاحظات داخلية">
                            <textarea v-model="details.notes" rows="3" :class="INPUT" placeholder="لا تظهر لصاحب المتجر" />
                        </FormField>
                    </div>

                    <div class="flex items-center justify-between gap-3 rounded-b-2xl border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 px-5 py-3 sm:px-6">
                        <p class="text-xs" :class="detailsDirty ? 'font-semibold text-amber-600 dark:text-amber-400' : 'text-slate-400'">
                            {{ detailsDirty ? 'تعديلات غير محفوظة' : 'كل التعديلات محفوظة' }}
                        </p>
                        <div class="flex gap-2">
                            <button v-if="detailsDirty" type="button" class="rounded-xl px-3 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="hydrate(tenant)">تراجع</button>
                            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-50" :disabled="busy || !detailsDirty">حفظ</button>
                        </div>
                    </div>
                </form>

                <div class="space-y-6">
                    <!-- Features -->
                    <section class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                                <AppIcon name="grid" class="text-slate-400" /> الأقسام المفعّلة
                            </h2>
                            <span v-if="tenant.features" class="text-xs font-semibold tabular-nums text-slate-400">{{ enabledCount }}/{{ Object.keys(meta.features).length }}</span>
                        </div>
                        <p v-if="!tenant.features" class="text-sm text-slate-500">غير متاح.</p>
                        <template v-else>
                            <ul class="-mx-2">
                                <li v-for="(label, key) in meta.features" :key="key">
                                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <span>{{ label }}</span>
                                        <input v-model="features[key]" type="checkbox" class="peer sr-only" />
                                        <span
                                            class="relative h-5 w-9 shrink-0 rounded-full bg-[#cbd5e1] dark:bg-[#334155] transition peer-checked:bg-[#2563eb] peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500/40 after:absolute after:top-0.5 after:start-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4"
                                            aria-hidden="true"
                                        ></span>
                                    </label>
                                </li>
                            </ul>
                            <button
                                v-if="featuresDirty"
                                type="button"
                                class="mt-3 w-full rounded-xl bg-blue-600 py-2 text-sm font-bold text-white transition hover:bg-blue-700 disabled:opacity-50"
                                :disabled="busy"
                                @click="saveFeatures"
                            >
                                حفظ الأقسام
                            </button>
                        </template>
                    </section>

                    <!-- Owner password -->
                    <form class="space-y-3 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)]" novalidate @submit.prevent="resetPassword">
                        <div>
                            <h2 class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                                <AppIcon name="lock" class="text-slate-400" /> كلمة مرور المالك
                            </h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">يعيد تفعيل الحساب ويسجّل خروجه من كل الأجهزة.</p>
                        </div>
                        <input v-model.trim="reset.email" type="email" dir="ltr" :placeholder="tenant.owner_email || 'بريد الحساب'" :class="INPUT" aria-label="بريد الحساب" />
                        <div class="flex gap-2">
                            <input v-model="reset.password" type="text" dir="ltr" placeholder="كلمة المرور الجديدة" autocomplete="off" :class="[INPUT, 'font-mono']" aria-label="كلمة المرور الجديدة" />
                            <button type="button" :class="CHIP" @click="reset.password = randomPassword()">توليد</button>
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-slate-900 dark:bg-white py-2 text-sm font-semibold text-white dark:text-slate-900 transition hover:bg-slate-800 dark:hover:bg-slate-100 disabled:opacity-50" :disabled="busy || reset.password.length < 8">
                            تعيين كلمة المرور
                        </button>
                    </form>

                    <!-- Database -->
                    <section class="space-y-4 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                                <AppIcon name="database" class="text-slate-400" /> قاعدة البيانات
                            </h2>
                            <router-link :to="{ name: 'databases', query: { database: tenant.database } }" class="text-xs font-semibold text-slate-500 hover:text-blue-600 dark:hover:text-blue-400">
                                سجل النسخ
                            </router-link>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-slate-400">آخر نسخة</dt>
                                <dd
                                    class="mt-0.5 font-semibold"
                                    :class="backupStale ? 'text-amber-600 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200'"
                                    :title="tenant.backups?.last_at ? formatDateTime(tenant.backups.last_at) : ''"
                                >
                                    {{ tenant.backups?.last_at ? timeAgo(tenant.backups.last_at) : 'لا توجد' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-400">النسخ المحفوظة</dt>
                                <dd class="mt-0.5 font-semibold text-slate-800 dark:text-slate-200">{{ tenant.backups?.count ?? 0 }}</dd>
                            </div>
                        </dl>

                        <div
                            v-if="tenant.pending_migrations?.length"
                            class="rounded-lg bg-cyan-50 dark:bg-cyan-950/40 p-2.5 text-xs text-cyan-800 dark:text-cyan-300"
                        >
                            <p class="font-bold">{{ tenant.pending_migrations.length }} تحديث بانتظار التطبيق</p>
                            <ul class="mt-1 max-h-20 space-y-0.5 overflow-auto font-mono text-[10px] opacity-80" dir="ltr">
                                <li v-for="m in tenant.pending_migrations" :key="m" class="truncate">{{ m }}</li>
                            </ul>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" :class="SECONDARY" :disabled="busy" @click="backupNow">
                                <AppIcon :name="backingUp ? 'refresh' : 'download'" :class="backingUp ? 'animate-spin' : ''" />
                                {{ backingUp ? 'جارٍ النسخ...' : 'نسخ الآن' }}
                            </button>
                            <button
                                type="button"
                                :class="SECONDARY"
                                :disabled="busy || !tenant.pending_migrations?.length"
                                :title="tenant.pending_migrations?.length ? 'تؤخذ نسخة احتياطية تلقائياً قبل التحديث' : 'قاعدة البيانات محدّثة'"
                                @click="migrate"
                            >
                                <AppIcon name="refresh" /> {{ tenant.pending_migrations?.length ? 'تحديث البنية' : 'محدّثة' }}
                            </button>
                        </div>

                        <label class="flex cursor-pointer items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800 pt-3 text-sm">
                            <span>
                                <span class="block font-semibold text-slate-800 dark:text-slate-200">وضع الصيانة</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">يغلق المتجر مؤقتاً دون تغيير حالته</span>
                            </span>
                            <input type="checkbox" class="peer sr-only" :checked="Boolean(tenant.maintenance_at)" :disabled="busy" @change="toggleMaintenance" />
                            <span
                                class="relative h-5 w-9 shrink-0 rounded-full bg-[#cbd5e1] dark:bg-[#334155] transition peer-checked:bg-[#0284c7] peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500/40 after:absolute after:top-0.5 after:start-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4"
                                aria-hidden="true"
                            ></span>
                        </label>
                    </section>

                    <!-- HTTPS -->
                    <section class="space-y-3 rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                                <AppIcon name="shield" class="text-slate-400" /> شهادة HTTPS
                            </h2>
                            <SslBadge :status="tenant.ssl_status" />
                        </div>
                        <p v-if="tenant.ssl_status !== 'issued'" class="rounded-lg bg-amber-50 dark:bg-amber-950/40 p-2.5 text-xs leading-relaxed text-amber-800 dark:text-amber-300">
                            إن تعذّر الإصدار من هنا، نفّذ على الخادم:
                            <code class="mt-1 block select-all font-mono text-[11px]" dir="ltr">php artisan tenants:certificate {{ tenant.slug }}</code>
                        </p>
                        <pre v-if="tenant.ssl_message" class="max-h-32 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 dark:bg-slate-800/60 p-2 text-[11px] text-slate-600 dark:text-slate-300" dir="ltr">{{ tenant.ssl_message }}</pre>
                        <button type="button" :class="[SECONDARY, 'w-full']" :disabled="busy" @click="issueCertificate">
                            {{ tenant.ssl_status === 'issued' ? 'تجديد / إعادة إصدار الشهادة' : 'إصدار الشهادة' }}
                        </button>
                    </section>
                </div>
            </div>

            <!-- Danger zone -->
            <section class="rounded-2xl border border-red-200 dark:border-red-900/60 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                <h2 class="flex items-center gap-2 border-b border-red-100 dark:border-red-900/40 px-5 py-3.5 text-[15px] font-bold text-red-700 dark:text-red-400 sm:px-6">
                    <AppIcon name="alert" /> منطقة الخطر
                </h2>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">أرشفة المتجر</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">يُغلق المتجر ويختفي عنوانه، وتبقى البيانات ويمكن إعادة تفعيله لاحقاً.</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50"
                            :disabled="busy || tenant.status === 'archived'"
                            @click="setStatus('archived')"
                        >
                            <AppIcon name="archive" /> {{ tenant.status === 'archived' ? 'مؤرشف' : 'أرشفة' }}
                        </button>
                    </div>
                    <div class="px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">حذف المتجر</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ tenant.status === 'archived' ? `اكتب ${tenant.slug} للتأكيد.` : 'أرشف المتجر أولاً لتتمكن من حذفه.' }}
                                </p>
                            </div>
                        </div>
                        <div v-if="tenant.status === 'archived'" class="mt-3 flex flex-wrap items-center gap-3">
                            <input v-model.trim="del.confirm_slug" type="text" dir="ltr" :placeholder="tenant.slug" :class="[INPUT, 'max-w-xs font-mono']" aria-label="اكتب النطاق للتأكيد" />
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input v-model="del.drop_database" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-red-600" />
                                حذف قاعدة البيانات نهائياً
                            </label>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-40"
                                :disabled="busy || del.confirm_slug !== tenant.slug"
                                @click="destroy"
                            >
                                <AppIcon name="trash" /> حذف المتجر
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppIcon from '../../components/ui/AppIcon.vue';
import FormField from '../../components/ui/FormField.vue';
import SslBadge from '../../components/platform/SslBadge.vue';
import TenantStatus from '../../components/platform/TenantStatus.vue';
import { avatarTone, EXPIRING_SOON_DAYS, initials, subscriptionView } from '../../components/platform/subscription';
import { timeAgo } from '../../components/platform/format';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { backupsApi, tenantsApi } from '../../services/adminApi';
import { apiMessage, formatDate, formatDateTime, isoDate } from '../../utils/format';

const props = defineProps({ id: { type: [String, Number], required: true } });

const router = useRouter();
const toast = useToast();

const INPUT = 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-white focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
const CHIP = 'shrink-0 rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 transition hover:bg-slate-50 dark:hover:bg-slate-800';
const SECONDARY = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50';

const EXTENSIONS = [
    { months: 1, label: 'شهر' },
    { months: 3, label: '3 أشهر' },
    { months: 12, label: 'سنة' },
];

const tenant = ref(null);
const meta = ref({ features: {} });
const loading = ref(true);
const busy = ref(false);

const details = reactive({});
const features = reactive({});
const reset = reactive({ email: '', password: '' });
const del = reactive({ confirm_slug: '', drop_database: false });
const copied = ref(false);
const backingUp = ref(false);

/** What the form held when last loaded or saved, to tell unsaved edits apart. */
const savedDetails = ref('');
const savedFeatures = ref('');

const detailsDirty = computed(() => JSON.stringify(details) !== savedDetails.value);
const featuresDirty = computed(() => JSON.stringify(features) !== savedFeatures.value);
const enabledCount = computed(() => Object.keys(meta.value.features || {}).filter((key) => features[key]).length);
const subscription = computed(() => subscriptionView(tenant.value?.expires_at));
// Mirrors config/backups.php stale_after_hours.
const backupStale = computed(() => !tenant.value?.backups?.last_at || Date.now() - new Date(tenant.value.backups.last_at).getTime() > 30 * 36e5);

const statCards = computed(() => {
    const s = tenant.value?.stats || {};
    return [
        { label: 'المستخدمون', icon: 'users', value: s.users ?? '—' },
        { label: 'العملاء', icon: 'user', value: s.customers ?? '—' },
        { label: 'فواتير المبيعات', icon: 'receipt', value: s.sales_invoices ?? '—' },
        { label: 'أجهزة الصيانة', icon: 'smartphone', value: s.repairs ?? '—' },
        { label: 'حجم البيانات', icon: 'database', value: s.database_mb != null ? `${s.database_mb} MB` : '—' },
        { label: 'آخر نشاط', icon: 'clock', value: s.last_activity ? timeAgo(s.last_activity) : '—', title: s.last_activity ? formatDateTime(s.last_activity) : '' },
    ];
});

function hydrate(data) {
    tenant.value = data;
    Object.assign(details, {
        name: data.name,
        plan: data.plan || '',
        expires_at: data.expires_at || '',
        contact_phone: data.contact_phone || '',
        owner_name: data.owner_name || '',
        owner_email: data.owner_email || '',
        notes: data.notes || '',
    });
    Object.assign(features, data.features || {});
    savedDetails.value = JSON.stringify(details);
    savedFeatures.value = JSON.stringify(features);
}

async function load() {
    loading.value = true;
    try {
        const [data, m] = await Promise.all([tenantsApi.show(props.id), tenantsApi.meta()]);
        meta.value = m;
        hydrate(data);
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تحميل المتجر.'));
        router.replace({ name: 'tenants' });
    } finally {
        loading.value = false;
    }
}

async function run(action, successMessage) {
    busy.value = true;
    try {
        const result = await action();
        if (successMessage) toast.success(typeof successMessage === 'function' ? successMessage(result) : successMessage);
        return result;
    } catch (e) {
        toast.error(apiMessage(e));
        return null;
    } finally {
        busy.value = false;
    }
}

function extend(months) {
    const today = new Date();
    const base = details.expires_at ? new Date(`${details.expires_at}T00:00:00`) : today;
    const from = base < today ? today : base;
    from.setMonth(from.getMonth() + months);
    // isoDate keeps the local calendar day; toISOString() would shift it back one day east of UTC.
    details.expires_at = isoDate(from);
}

/** Extend from the banner: same rule as the form buttons, saved straight away. */
async function quickExtend(months) {
    extend(months);
    await saveDetails();
}

function randomPassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    const bytes = crypto.getRandomValues(new Uint32Array(12));
    return Array.from(bytes, (b) => chars[b % chars.length]).join('');
}

async function copyUrl() {
    try {
        await navigator.clipboard.writeText(tenant.value.url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        toast.error('تعذّر النسخ.');
    }
}

async function saveDetails() {
    const data = await run(() => tenantsApi.update(props.id, { ...details, expires_at: details.expires_at || null }), 'تم الحفظ.');
    if (data) hydrate(data);
}

async function saveFeatures() {
    const data = await run(() => tenantsApi.update(props.id, { features: { ...features } }), 'تم تحديث الأقسام.');
    if (data) hydrate(data);
}

async function setStatus(status) {
    const prompts = {
        suspended: { title: 'إيقاف المتجر؟', message: 'لن يتمكن أي مستخدم في المتجر من الدخول حتى تعيد تفعيله. البيانات تبقى كما هي.', confirmLabel: 'إيقاف', tone: 'danger' },
        archived: { title: 'أرشفة المتجر؟', message: 'يُغلق المتجر ويختفي عنوانه. البيانات تبقى ويمكن إعادة تفعيله لاحقاً.', confirmLabel: 'أرشفة', tone: 'danger' },
    };
    if (prompts[status] && !(await confirm(prompts[status]))) return;

    const data = await run(() => tenantsApi.update(props.id, { status }), 'تم تحديث حالة المتجر.');
    if (data) hydrate(data);
}

async function resetPassword() {
    if (reset.password.length < 8) {
        toast.error('كلمة المرور 8 أحرف على الأقل.');
        return;
    }
    const result = await run(
        () => tenantsApi.resetPassword(props.id, { password: reset.password, email: reset.email || null }),
        (r) => r.message,
    );
    if (result) Object.assign(reset, { email: '', password: '' });
}

async function issueCertificate() {
    const data = await run(() => tenantsApi.issueCertificate(props.id));
    if (data) {
        tenant.value = { ...tenant.value, ssl_status: data.ssl_status, ssl_message: data.ssl_message };
        data.ssl_status === 'issued' ? toast.success('الشهادة مفعّلة.') : toast.error('لم تصدر الشهادة — راجع التفاصيل.');
    }
}

async function migrate() {
    const count = tenant.value.pending_migrations?.length ?? 0;
    const ok = await confirm({
        title: 'تحديث بنية قاعدة البيانات؟',
        message: `سيُطبَّق ${count} تحديث على قاعدة بيانات ${tenant.value.name}. تؤخذ نسخة احتياطية تلقائياً قبل البدء.`,
        confirmLabel: 'تحديث',
    });
    if (!ok) return;

    const result = await run(() => tenantsApi.migrate(props.id), (r) => r.message);
    if (result) await reload();
}

async function backupNow() {
    backingUp.value = true;
    const result = await run(() => backupsApi.create({ tenant_id: Number(props.id) }), (r) => r.message);
    backingUp.value = false;
    if (result) await reload();
}

async function toggleMaintenance() {
    const on = !tenant.value.maintenance_at;
    if (on && !(await confirm({
        title: 'تفعيل وضع الصيانة؟',
        message: 'لن يتمكن مستخدمو المتجر من الدخول حتى تعيد فتحه. لا تتغير حالة المتجر ولا اشتراكه.',
        confirmLabel: 'إغلاق المتجر',
        tone: 'danger',
    }))) return;

    const data = await run(() => tenantsApi.setMaintenance(props.id, on), on ? 'المتجر في وضع الصيانة.' : 'أُعيد فتح المتجر.');
    if (data) tenant.value = { ...tenant.value, ...data };
}

/** Refresh the shop without resetting unsaved edits in the details form. */
async function reload() {
    try {
        const data = await tenantsApi.show(props.id);
        tenant.value = { ...tenant.value, ...data };
    } catch {
        // The action itself succeeded; a stale card is not worth an error.
    }
}

async function destroy() {
    const ok = await confirm({
        title: 'حذف المتجر نهائياً؟',
        message: del.drop_database ? 'ستُحذف قاعدة بيانات المتجر بكل ما فيها ولا يمكن استرجاعها.' : 'يُحذف المتجر من المنصة وتبقى قاعدة بياناته على الخادم.',
        confirmLabel: 'حذف',
        tone: 'danger',
    });
    if (!ok) return;

    const result = await run(() => tenantsApi.remove(props.id, { ...del }), 'تم حذف المتجر.');
    if (result) router.replace({ name: 'tenants' });
}

onMounted(load);
</script>
