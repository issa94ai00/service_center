<template>
    <BaseModal
        :open="Boolean(backup)"
        title="استعادة نسخة احتياطية"
        :subtitle="backup ? `${backup.label} · ${formatDateTime(backup.created_at)}` : ''"
        :close-on-overlay="!restoring"
        :close-on-esc="!restoring"
        max-width="max-w-lg"
        @close="close"
    >
        <form v-if="backup" class="space-y-4" novalidate @submit.prevent="submit">
            <div class="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/40 p-3 text-sm text-amber-900 dark:text-amber-200">
                <p class="flex items-center gap-2 font-bold"><AppIcon name="alert" /> ستُستبدل بيانات المتجر الحالية بالكامل</p>
                <p class="mt-1 text-xs leading-relaxed">
                    كل ما سُجّل في المتجر بعد {{ formatDateTime(backup.created_at) }} ({{ timeAgo(backup.created_at) }}) سيختفي من المتجر.
                    لا يضيع شيء نهائياً: تؤخذ نسخة أمان من البيانات الحالية قبل البدء.
                </p>
            </div>

            <ol class="space-y-2 text-sm">
                <li v-for="(step, i) in STEPS" :key="step" class="flex items-center gap-3">
                    <span
                        class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-bold tabular-nums"
                        :class="restoring && i === activeStep
                            ? 'bg-blue-600 text-white'
                            : restoring && i < activeStep
                              ? 'bg-emerald-500 text-white'
                              : 'bg-slate-100 dark:bg-slate-800 text-slate-500'"
                    >
                        <AppIcon v-if="restoring && i < activeStep" name="check" class="text-[11px]" />
                        <AppIcon v-else-if="restoring && i === activeStep" name="refresh" class="animate-spin text-[11px]" />
                        <template v-else>{{ i + 1 }}</template>
                    </span>
                    <span :class="restoring && i === activeStep ? 'font-semibold text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-300'">{{ step }}</span>
                </li>
            </ol>

            <dl class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 p-3 text-xs">
                <div>
                    <dt class="text-slate-400">الحجم</dt>
                    <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ formatBytes(backup.size_bytes) }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-slate-400">البصمة (SHA-256)</dt>
                    <dd class="mt-0.5 truncate font-mono font-semibold text-slate-700 dark:text-slate-200" dir="ltr" :title="backup.checksum">{{ backup.checksum?.slice(0, 16) }}…</dd>
                </div>
            </dl>

            <template v-if="backup.tenant">
                <FormField :label="`اكتب ${backup.tenant.slug} للتأكيد`" required>
                    <input
                        v-model.trim="confirmSlug"
                        type="text"
                        dir="ltr"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        :placeholder="backup.tenant.slug"
                        :disabled="restoring"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 font-mono text-sm text-slate-900 dark:text-white focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-500/20"
                    />
                </FormField>
            </template>

            <p v-if="error" class="whitespace-pre-line rounded-xl bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-700 dark:text-red-300" role="alert">{{ error }}</p>

            <div class="flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800 pt-4">
                <button type="button" class="rounded-xl px-4 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50" :disabled="restoring" @click="close">
                    إلغاء
                </button>
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 disabled:opacity-40"
                    :disabled="restoring || confirmSlug !== backup.tenant?.slug"
                >
                    <AppIcon :name="restoring ? 'refresh' : 'corner-down-left'" :class="restoring ? 'animate-spin' : ''" />
                    {{ restoring ? 'جارٍ الاستعادة...' : 'استعادة النسخة' }}
                </button>
            </div>
        </form>
    </BaseModal>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '../ui/AppIcon.vue';
import BaseModal from '../ui/BaseModal.vue';
import FormField from '../ui/FormField.vue';
import { useToast } from '../../composables/useToast';
import { backupsApi } from '../../services/adminApi';
import { apiMessage, formatDateTime } from '../../utils/format';
import { formatBytes, timeAgo } from './format';

const props = defineProps({
    /** A backup row from the API, or null when closed. */
    backup: { type: Object, default: null },
});

const emit = defineEmits(['close', 'restored']);
const toast = useToast();

// What the server does, in order (App\Backups\BackupService::restore).
const STEPS = [
    'نسخة أمان من البيانات الحالية',
    'إغلاق المتجر مؤقتاً أمام مستخدميه',
    'تحميل النسخة وفحصها في قاعدة مؤقتة',
    'استبدال البيانات وتحديث بنيتها',
    'إعادة فتح المتجر',
];

const confirmSlug = ref('');
const restoring = ref(false);
const error = ref('');
const activeStep = ref(0);
let ticker = null;

watch(() => props.backup, () => {
    confirmSlug.value = '';
    error.value = '';
    activeStep.value = 0;
});

function close() {
    if (!restoring.value) emit('close');
}

/**
 * The server does all steps in one request; walk the list on a timer so the
 * admin sees it moving, and stop short of the last step until it answers.
 */
function startTicker() {
    activeStep.value = 0;
    ticker = setInterval(() => {
        if (activeStep.value < STEPS.length - 2) activeStep.value++;
    }, 1800);
}

function stopTicker() {
    clearInterval(ticker);
    ticker = null;
}

async function submit() {
    restoring.value = true;
    error.value = '';
    startTicker();
    try {
        const { message } = await backupsApi.restore(props.backup.id, confirmSlug.value);
        activeStep.value = STEPS.length;
        toast.success(message, { timeout: 10000 });
        emit('restored');
    } catch (e) {
        error.value = apiMessage(e, 'فشلت الاستعادة.');
    } finally {
        stopTicker();
        restoring.value = false;
    }
}

onBeforeUnmount(stopTicker);
</script>
