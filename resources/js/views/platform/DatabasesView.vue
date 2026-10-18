<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">قواعد البيانات</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">النسخ الاحتياطي والاستعادة لكل متجر وللقاعدة المركزية</p>
            </div>
            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 shadow-2xs transition hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50"
                :disabled="loading"
                @click="refresh"
            >
                <AppIcon name="refresh" :class="loading ? 'animate-spin' : ''" /> تحديث
            </button>
        </div>

        <!-- Health -->
        <div
            v-if="overview && unhealthy.length"
            class="flex items-start gap-3 rounded-2xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-amber-800 dark:text-amber-300"
            role="status"
        >
            <AppIcon name="alert" class="mt-0.5 shrink-0 text-base" />
            <div class="text-sm">
                <p class="font-bold">{{ unhealthy.length }} قاعدة بيانات بلا نسخة حديثة</p>
                <p class="mt-0.5 text-xs opacity-90">
                    {{ unhealthy.map((d) => d.label).join('، ') }} — لا نسخة ناجحة خلال آخر {{ STALE_HOURS }} ساعة{{ unhealthy.some((d) => d.last_attempt_failed) ? '، وفشلت المحاولة الأخيرة لبعضها' : '' }}.
                </p>
            </div>
        </div>

        <div
            v-if="overview && !overview.summary.offsite.configured"
            class="flex items-start gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3"
        >
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400">
                <AppIcon name="globe" />
            </span>
            <div class="min-w-0 text-sm">
                <p class="font-bold text-slate-900 dark:text-white">النسخ محفوظة على هذا الخادم فقط</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    لحمايتها من فقدان الخادم نفسه، أضف بيانات تخزين S3 (AWS أو Cloudflare R2 أو Backblaze B2 أو Wasabi) إلى ملف <code class="font-mono">.env</code>:
                </p>
                <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-50 dark:bg-slate-800/60 p-2.5 font-mono text-[11px] text-slate-600 dark:text-slate-300" dir="ltr">BACKUP_S3_BUCKET=...
BACKUP_S3_KEY=...
BACKUP_S3_SECRET=...
BACKUP_S3_ENDPOINT=https://...   # R2 / B2 / Wasabi
BACKUP_S3_REGION=auto</pre>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="card in summaryCards" :key="card.label" class="rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] sm:p-5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[13px] font-medium text-slate-500 dark:text-slate-400">{{ card.label }}</span>
                    <span class="grid h-8 w-8 place-items-center rounded-lg text-[15px]" :class="card.tone">
                        <AppIcon :name="card.icon" />
                    </span>
                </div>
                <p class="mt-2 truncate text-xl font-extrabold tabular-nums text-slate-900 dark:text-white sm:text-2xl" :title="card.title">
                    <span v-if="!overview" class="inline-block h-7 w-16 animate-pulse rounded-md bg-slate-200 dark:bg-slate-800"></span>
                    <template v-else>{{ card.value }}</template>
                </p>
                <p class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500">{{ card.hint }}</p>
            </div>
        </div>

        <!-- Databases -->
        <section class="overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 px-5 py-4">
                <h2 class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                    <AppIcon name="database" class="text-slate-400" /> قواعد البيانات
                </h2>
                <span v-if="overview" class="text-xs text-slate-400">{{ overview.databases.length }} قاعدة</span>
            </header>

            <ul v-if="!overview" class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="n in 3" :key="n" class="flex animate-pulse items-center gap-3 px-5 py-4">
                    <div class="h-10 w-10 rounded-xl bg-slate-200 dark:bg-slate-800"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3.5 w-40 rounded bg-slate-200 dark:bg-slate-800"></div>
                        <div class="h-3 w-24 rounded bg-slate-100 dark:bg-slate-800/60"></div>
                    </div>
                </li>
            </ul>

            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="db in overview.databases" :key="db.database" class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
                    <div class="flex min-w-0 flex-1 basis-56 items-center gap-3">
                        <span
                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-sm font-bold"
                            :class="db.tenant ? ['bg-gradient-to-br text-white', avatarTone(db.tenant.slug)] : 'bg-slate-900 dark:bg-white text-white dark:text-slate-900'"
                        >
                            <AppIcon v-if="!db.tenant" name="database" class="text-base" />
                            <template v-else>{{ initials(db.label) }}</template>
                        </span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <router-link
                                    v-if="db.tenant"
                                    :to="{ name: 'tenant', params: { id: db.tenant.id } }"
                                    class="truncate font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400"
                                >
                                    {{ db.label }}
                                </router-link>
                                <span v-else class="truncate font-bold text-slate-900 dark:text-white">{{ db.label }}</span>
                                <span v-if="db.tenant?.maintenance" class="shrink-0 rounded-full bg-sky-50 dark:bg-sky-950/50 px-2 py-0.5 text-[10px] font-bold text-sky-700 dark:text-sky-300">صيانة</span>
                            </div>
                            <p class="truncate font-mono text-xs text-slate-400" dir="ltr">{{ db.database }} · {{ formatBytes(db.size_bytes) }}</p>
                        </div>
                    </div>

                    <div class="min-w-36 text-sm">
                        <p class="flex items-center gap-1.5 font-semibold" :class="healthTone(db).text">
                            <span class="h-2 w-2 shrink-0 rounded-full" :class="healthTone(db).dot" aria-hidden="true"></span>
                            <span :title="db.last_backup_at ? formatDateTime(db.last_backup_at) : ''">{{ db.last_backup_at ? timeAgo(db.last_backup_at) : 'لا توجد نسخة' }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ db.last_attempt_failed ? 'فشلت آخر محاولة · ' : '' }}{{ db.backups_count }} نسخة · {{ formatBytes(db.backups_bytes) }}
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <button type="button" :class="BTN" @click="filterBy(db.database)">
                            <AppIcon name="clock" /> السجل
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:opacity-60"
                            :disabled="Boolean(running[db.database])"
                            @click="backupNow(db)"
                        >
                            <AppIcon :name="running[db.database] ? 'refresh' : 'download'" :class="running[db.database] ? 'animate-spin' : ''" />
                            {{ running[db.database] ? 'جارٍ النسخ...' : 'نسخ الآن' }}
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <!-- History -->
        <section ref="historySection" class="overflow-hidden rounded-2xl border border-slate-200/70 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 px-5 py-4">
                <h2 class="flex items-center gap-2 text-[15px] font-bold text-slate-900 dark:text-white">
                    <AppIcon name="clock" class="text-slate-400" /> سجل النسخ
                    <span v-if="history.total" class="text-xs font-semibold text-slate-400">({{ history.total }})</span>
                </h2>
                <div class="flex flex-wrap gap-2">
                    <select v-model="filters.database" :class="SELECT" aria-label="قاعدة البيانات">
                        <option value="">كل القواعد</option>
                        <option v-for="db in overview?.databases ?? []" :key="db.database" :value="db.database">{{ db.label }}</option>
                    </select>
                    <select v-model="filters.trigger" :class="SELECT" aria-label="النوع">
                        <option value="">كل الأنواع</option>
                        <option v-for="(t, key) in BACKUP_TRIGGERS" :key="key" :value="key">{{ t.label }}</option>
                    </select>
                    <select v-model="filters.status" :class="SELECT" aria-label="الحالة">
                        <option value="">كل الحالات</option>
                        <option value="completed">ناجحة</option>
                        <option value="failed">فاشلة</option>
                    </select>
                </div>
            </header>

            <div v-if="history.loading && !history.rows.length" class="px-5 py-10 text-center text-sm text-slate-400">جارٍ التحميل...</div>
            <div v-else-if="!history.rows.length" class="px-6 py-12 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-xl text-slate-400">
                    <AppIcon name="archive" />
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">لا توجد نسخ مطابقة</p>
            </div>

            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="b in history.rows" :key="b.id" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5">
                    <span
                        class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-[15px]"
                        :class="b.status === 'completed'
                            ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400'
                            : b.status === 'failed'
                              ? 'bg-red-50 dark:bg-red-950/50 text-red-600 dark:text-red-400'
                              : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
                    >
                        <AppIcon :name="b.status === 'completed' ? 'check-circle' : b.status === 'failed' ? 'x-circle' : 'clock'" />
                    </span>

                    <div class="min-w-0 flex-1 basis-56">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ b.label }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="(BACKUP_TRIGGERS[b.trigger] || BACKUP_TRIGGERS.manual).tone">
                                {{ (BACKUP_TRIGGERS[b.trigger] || BACKUP_TRIGGERS.manual).label }}
                            </span>
                            <span v-if="b.restored_at" class="rounded-full bg-violet-50 dark:bg-violet-950/50 px-2 py-0.5 text-[10px] font-bold text-violet-700 dark:text-violet-300" :title="formatDateTime(b.restored_at)">
                                استُعيدت
                            </span>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            <span :title="formatDateTime(b.created_at)">{{ formatDateTime(b.created_at) }}</span>
                            <template v-if="b.status === 'completed'"> · {{ formatBytes(b.size_bytes) }} · {{ formatDuration(b.duration_seconds) }}</template>
                            <template v-if="b.created_by"> · {{ b.created_by }}</template>
                        </p>
                        <p v-if="b.error" class="mt-1 line-clamp-2 text-xs text-red-600 dark:text-red-400" dir="auto">{{ b.error }}</p>
                    </div>

                    <span
                        v-if="b.status === 'completed' && overview?.summary.offsite.configured"
                        class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="b.offsite_status === 'uploaded'
                            ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400'
                            : 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400'"
                        :title="b.offsite_error || ''"
                    >
                        <AppIcon name="globe" class="text-[11px]" />
                        {{ b.offsite_status === 'uploaded' ? 'خارجي' : 'لم تُرفع' }}
                    </span>

                    <div class="flex shrink-0 items-center gap-1">
                        <a
                            v-if="b.status === 'completed'"
                            :href="backupsApi.downloadUrl(b.id)"
                            :class="ICON_BTN"
                            title="تنزيل"
                            aria-label="تنزيل"
                        >
                            <AppIcon name="download" />
                        </a>
                        <button
                            v-if="b.status === 'completed' && overview?.summary.offsite.configured && b.offsite_status !== 'uploaded'"
                            type="button"
                            :class="ICON_BTN"
                            title="إعادة الرفع للتخزين الخارجي"
                            aria-label="إعادة الرفع للتخزين الخارجي"
                            :disabled="busyId === b.id"
                            @click="retryOffsite(b)"
                        >
                            <AppIcon name="refresh" :class="busyId === b.id ? 'animate-spin' : ''" />
                        </button>
                        <button
                            v-if="b.is_restorable"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-bold text-amber-700 dark:text-amber-400 transition hover:bg-amber-50 dark:hover:bg-amber-950/40"
                            @click="openRestore(b)"
                        >
                            <AppIcon name="corner-down-left" /> استعادة
                        </button>
                        <button
                            v-if="b.status !== 'running'"
                            type="button"
                            :class="[ICON_BTN, 'hover:!text-red-600']"
                            title="حذف"
                            aria-label="حذف"
                            :disabled="busyId === b.id"
                            @click="removeBackup(b)"
                        >
                            <AppIcon name="trash" />
                        </button>
                    </div>
                </li>
            </ul>

            <div v-if="history.page < history.lastPage" class="border-t border-slate-100 dark:border-slate-800 p-3 text-center">
                <button type="button" class="rounded-xl px-4 py-2 text-sm font-semibold text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40 disabled:opacity-50" :disabled="history.loading" @click="loadHistory(history.page + 1)">
                    {{ history.loading ? 'جارٍ التحميل...' : 'عرض المزيد' }}
                </button>
            </div>
        </section>

        <p v-if="overview" class="text-center text-xs text-slate-400">
            الاحتفاظ: آخر {{ overview.summary.retention.daily }} أيام، و{{ overview.summary.retention.weekly }} أسابيع، و{{ overview.summary.retention.monthly }} أشهر من النسخ التلقائية؛ والنسخ اليدوية {{ overview.summary.retention.adhoc_days }} يوماً.
        </p>

        <RestoreBackupModal :backup="restoreTarget" @close="restoreTarget = null" @restored="onRestored" />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '../../components/ui/AppIcon.vue';
import RestoreBackupModal from '../../components/platform/RestoreBackupModal.vue';
import { BACKUP_TRIGGERS, formatBytes, formatDuration, timeAgo, timeUntil } from '../../components/platform/format';
import { avatarTone, initials } from '../../components/platform/subscription';
import { confirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { backupsApi } from '../../services/adminApi';
import { apiMessage, formatDateTime } from '../../utils/format';

const toast = useToast();
const route = useRoute();

const BTN = 'inline-flex items-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 transition hover:bg-slate-50 dark:hover:bg-slate-800';
const ICON_BTN = 'grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 disabled:opacity-50';
const SELECT = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 py-1.5 pe-8 ps-3 text-xs font-semibold text-slate-700 dark:text-slate-200 focus:border-blue-500 focus:outline-none';

const overview = ref(null);
const loading = ref(false);
const running = reactive({});
const busyId = ref(null);
const restoreTarget = ref(null);
const historySection = ref(null);

// ?database= arrives from a shop's "backup history" link.
const filters = reactive({ database: String(route.query.database || ''), trigger: '', status: '' });
const history = reactive({ rows: [], page: 1, lastPage: 1, total: 0, loading: false });

/** Hours without a good backup before a database is flagged (config/backups.php). */
const STALE_HOURS = computed(() => overview.value?.summary.stale_after_hours ?? 30);

const unhealthy = computed(() => (overview.value?.databases ?? []).filter((d) => d.is_stale));

const summaryCards = computed(() => {
    const s = overview.value?.summary;
    const dbs = overview.value?.databases ?? [];
    const protectedCount = dbs.filter((d) => !d.is_stale).length;

    return [
        {
            label: 'محمية بنسخة حديثة',
            value: `${protectedCount}/${dbs.length}`,
            hint: `نسخة ناجحة خلال ${STALE_HOURS.value} ساعة`,
            icon: 'shield',
            tone: protectedCount === dbs.length ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400',
        },
        {
            label: 'النسخ التلقائي',
            value: s?.schedule.enabled ? timeUntil(s.schedule.next_run_at) : 'متوقف',
            title: s?.schedule.next_run_at ? formatDateTime(s.schedule.next_run_at) : '',
            hint: s?.schedule.enabled ? `يومياً ${s.schedule.time}${s.schedule.last_run_at ? ` · آخر تشغيل ${timeAgo(s.schedule.last_run_at)}` : ''}` : 'BACKUP_SCHEDULE_ENABLED',
            icon: 'calendar',
            tone: 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400',
        },
        {
            label: 'مساحة النسخ',
            value: formatBytes(s?.storage.used_bytes),
            hint: s?.storage.free_bytes != null ? `المتاح على الخادم ${formatBytes(s.storage.free_bytes)}` : '',
            icon: 'archive',
            tone: 'bg-violet-50 dark:bg-violet-950/60 text-violet-700 dark:text-violet-400',
        },
        {
            label: 'التخزين الخارجي',
            value: s?.offsite.configured ? (s.offsite.failed ? `${s.offsite.failed} لم تُرفع` : 'متصل') : 'غير مُعد',
            hint: s?.offsite.configured ? 'نسخة إضافية على S3' : 'النسخ على هذا الخادم فقط',
            icon: 'globe',
            tone: s?.offsite.configured && !s.offsite.failed ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400',
        },
    ];
});

function healthTone(db) {
    if (!db.last_backup_at || (db.is_stale && db.last_attempt_failed)) return { dot: 'bg-red-500', text: 'text-red-600 dark:text-red-400' };
    if (db.is_stale || db.last_attempt_failed) return { dot: 'bg-amber-500', text: 'text-amber-700 dark:text-amber-400' };
    return { dot: 'bg-emerald-500', text: 'text-slate-800 dark:text-slate-200' };
}

async function loadOverview() {
    try {
        overview.value = await backupsApi.overview();
    } catch (e) {
        toast.error(apiMessage(e, 'تعذّر تحميل حالة قواعد البيانات.'));
    }
}

/** Only the newest request may fill the list; a slow earlier one is dropped. */
let historySeq = 0;

async function loadHistory(page = 1) {
    const seq = ++historySeq;
    history.loading = true;
    try {
        const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v));
        const { data, meta } = await backupsApi.list({ ...params, page });
        if (seq !== historySeq) return;
        history.rows = page === 1 ? data : [...history.rows, ...data];
        history.page = meta.current_page;
        history.lastPage = meta.last_page;
        history.total = meta.total;
    } catch (e) {
        if (seq === historySeq) toast.error(apiMessage(e, 'تعذّر تحميل سجل النسخ.'));
    } finally {
        if (seq === historySeq) history.loading = false;
    }
}

async function refresh() {
    loading.value = true;
    await Promise.all([loadOverview(), loadHistory(1)]);
    loading.value = false;
}

function filterBy(database) {
    filters.database = database;
    historySection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function backupNow(db) {
    running[db.database] = true;
    try {
        const { message } = await backupsApi.create(db.tenant ? { tenant_id: db.tenant.id } : { central: true });
        toast.success(message);
    } catch (e) {
        toast.error(apiMessage(e, 'فشل النسخ الاحتياطي.'));
    } finally {
        delete running[db.database];
        refresh();
    }
}

async function retryOffsite(b) {
    busyId.value = b.id;
    try {
        toast.success((await backupsApi.retryOffsite(b.id)).message);
    } catch (e) {
        toast.error(apiMessage(e));
    } finally {
        busyId.value = null;
        refresh();
    }
}

async function removeBackup(b) {
    const ok = await confirm({
        title: 'حذف هذه النسخة؟',
        message: `نسخة ${b.label} بتاريخ ${formatDateTime(b.created_at)} ستُحذف من الخادم${b.offsite_status === 'uploaded' ? ' ومن التخزين الخارجي' : ''}.`,
        confirmLabel: 'حذف',
        tone: 'danger',
    });
    if (!ok) return;

    busyId.value = b.id;
    try {
        await backupsApi.remove(b.id);
        toast.success('تم حذف النسخة.');
    } catch (e) {
        toast.error(apiMessage(e));
    } finally {
        busyId.value = null;
        refresh();
    }
}

function openRestore(b) {
    restoreTarget.value = b;
}

function onRestored() {
    restoreTarget.value = null;
    refresh();
}

watch(filters, () => loadHistory(1));

onMounted(refresh);
</script>
