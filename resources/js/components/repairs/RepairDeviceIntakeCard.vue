<template>
    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-4">
        <!-- Header & Mode Toggle -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 pb-3">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-blue-700 text-sm">
                    🔒
                </span>
                <div>
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                        قفل الجهاز والفحص السريع (30-Sec Intake)
                    </h4>
                    <p class="text-[11px] text-slate-500">حماية حقوق المحل والعميل وتوثيق حالة الاستلام</p>
                </div>
            </div>

            <!-- Lock Mode Selector -->
            <div class="flex rounded-lg bg-slate-200/80 p-0.5 text-xs font-medium">
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-all"
                    :class="lockType === 'pattern' ? 'bg-white text-blue-700 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
                    @click="setLockType('pattern')"
                >
                    رسم النمط
                </button>
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-all"
                    :class="lockType === 'pin' ? 'bg-white text-blue-700 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
                    @click="setLockType('pin')"
                >
                    رمز PIN
                </button>
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-all"
                    :class="lockType === 'none' ? 'bg-white text-blue-700 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900'"
                    @click="setLockType('none')"
                >
                    بدون قفل
                </button>
            </div>
        </div>

        <!-- 1. Lock Capture Canvas -->
        <div v-if="lockType === 'pattern'" class="grid sm:grid-cols-2 gap-4 items-center bg-white p-3.5 rounded-xl border border-slate-200">
            <!-- 9-Dot Canvas Container -->
            <div class="flex flex-col items-center">
                <div class="relative w-48 h-48 rounded-2xl bg-slate-900 p-4 shadow-inner flex flex-col justify-between select-none touch-none">
                    <!-- SVG Connecting Lines -->
                    <svg class="absolute inset-0 w-full h-full pointer-events-none z-10">
                        <line
                            v-for="(seg, idx) in patternSegments"
                            :key="idx"
                            :x1="seg.x1"
                            :y1="seg.y1"
                            :x2="seg.x2"
                            :y2="seg.y2"
                            stroke="#06b6d4"
                            stroke-width="4"
                            stroke-linecap="round"
                            stroke-opacity="0.9"
                        />
                    </svg>

                    <!-- 3x3 Dot Grid -->
                    <div class="grid grid-cols-3 gap-6 w-full h-full relative z-20">
                        <button
                            v-for="dot in 9"
                            :key="dot"
                            type="button"
                            @click="selectDot(dot)"
                            class="m-auto flex h-9 w-9 items-center justify-center rounded-full transition-transform active:scale-90"
                            :class="patternSequence.includes(dot)
                                ? 'bg-cyan-400 text-slate-950 font-bold ring-4 ring-cyan-400/30 scale-110'
                                : 'bg-slate-700 text-slate-400 hover:bg-slate-600'"
                        >
                            <span class="text-[10px] font-mono">{{ dot }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2 mt-2">
                    <button
                        type="button"
                        v-if="patternSequence.length"
                        @click="clearPattern"
                        class="text-xs text-rose-600 hover:text-rose-700 font-medium py-1 px-2 rounded hover:bg-rose-50 transition-colors"
                    >
                        مسح النمط (إعادة)
                    </button>
                </div>
            </div>

            <!-- Pattern Explanation & Result -->
            <div class="space-y-3 text-xs">
                <p class="text-slate-600 leading-relaxed">
                    انقر على أرقام النقاط بالترتيب لرسم نمط شاشة العميل لتسهيل فحص الجهاز على الفني دون تأخير.
                </p>
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                    <span class="text-slate-400 block mb-1 font-mono text-[11px]">تسلسل النقاط المحفوظ:</span>
                    <span v-if="patternSequence.length" class="font-mono text-cyan-700 font-bold text-sm tracking-wider" dir="ltr">
                        {{ patternSequence.join(' ➔ ') }}
                    </span>
                    <span v-else class="text-slate-400 italic">لم يتم رسم النمط بعد</span>
                </div>
            </div>
        </div>

        <!-- 2. PIN Input -->
        <div v-else-if="lockType === 'pin'" class="bg-white p-3.5 rounded-xl border border-slate-200 space-y-2">
            <label class="block text-xs font-semibold text-slate-700">رمز المرور أو الـ PIN</label>
            <div class="max-w-xs">
                <input
                    v-model.trim="pinCode"
                    type="text"
                    dir="ltr"
                    maxlength="30"
                    placeholder="مثال: 1234 أو 0000"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono tracking-widest text-left focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    @input="updateSummary"
                />
            </div>
        </div>

        <!-- 3. Pre-Inspection Functional Checklist -->
        <div class="space-y-2.5">
            <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                    <span>📋</span>
                    <span>فحص الوظائف والعيوب المسبقة:</span>
                </label>
                <button
                    type="button"
                    @click="markAllOk"
                    class="text-xs font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2.5 py-1 rounded-lg transition-colors"
                >
                    ✓ الكل سليم (تسجيل سريع)
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div
                    v-for="item in checkItems"
                    :key="item.key"
                    class="p-2.5 rounded-xl border bg-white transition-all space-y-1.5"
                    :class="getItemBorderClass(item.status)"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-800">{{ item.label }}</span>
                        <span class="text-[10px] font-bold" :class="getStatusBadgeClass(item.status)">
                            {{ getStatusLabel(item.status) }}
                        </span>
                    </div>

                    <!-- Cycle Status Buttons -->
                    <div class="flex gap-1 pt-0.5">
                        <button
                            v-for="st in ['ok', 'broken', 'untested']"
                            :key="st"
                            type="button"
                            @click="setItemStatus(item, st)"
                            class="flex-1 py-1 rounded text-[10px] font-medium transition-colors border"
                            :class="item.status === st ? getSelectedStatusBtn(st) : 'bg-slate-50 text-slate-500 border-slate-200 hover:bg-slate-100'"
                        >
                            {{ st === 'ok' ? 'سليم' : st === 'broken' ? 'عاطل' : 'لم يُفحص' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue';

const props = defineProps({
    initialNotes: { type: String, default: '' },
});

const emit = defineEmits(['update']);

const lockType = ref('pattern');
const patternSequence = ref([]);
const pinCode = ref('');

// Coordinates for dots in 48x48 layout (box: 192px x 192px, padding: 16px, inner: 160px)
const dotCoords = {
    1: { x: 44, y: 44 },
    2: { x: 96, y: 44 },
    3: { x: 148, y: 44 },
    4: { x: 44, y: 96 },
    5: { x: 96, y: 96 },
    6: { x: 148, y: 96 },
    7: { x: 44, y: 148 },
    8: { x: 96, y: 148 },
    9: { x: 148, y: 148 },
};

const patternSegments = computed(() => {
    const segs = [];
    for (let i = 0; i < patternSequence.value.length - 1; i++) {
        const from = dotCoords[patternSequence.value[i]];
        const to = dotCoords[patternSequence.value[i + 1]];
        if (from && to) {
            segs.push({ x1: from.x, y1: from.y, x2: to.x, y2: to.y });
        }
    }
    return segs;
});

function selectDot(dot) {
    if (!patternSequence.value.includes(dot)) {
        patternSequence.value.push(dot);
        updateSummary();
    }
}

function clearPattern() {
    patternSequence.value = [];
    updateSummary();
}

function setLockType(type) {
    lockType.value = type;
    updateSummary();
}

// Checklist items
const checkItems = reactive([
    { key: 'screen', label: 'الشاشة واللمس', status: 'untested' },
    { key: 'camera', label: 'الكاميرات', status: 'untested' },
    { key: 'biometrics', label: 'بصمة/FaceID', status: 'untested' },
    { key: 'charging', label: 'الشحن والبطارية', status: 'untested' },
    { key: 'audio', label: 'المايك والسماعة', status: 'untested' },
    { key: 'network', label: 'الواي فاي والشبكة', status: 'untested' },
    { key: 'buttons', label: 'أزرار الصوت والباور', status: 'untested' },
    { key: 'chassis', label: 'الهيكل والكسور', status: 'untested' },
]);

function setItemStatus(item, st) {
    item.status = st;
    updateSummary();
}

function markAllOk() {
    checkItems.forEach(item => item.status = 'ok');
    updateSummary();
}

function getStatusLabel(st) {
    if (st === 'ok') return 'سليم ✓';
    if (st === 'broken') return 'عاطل ✗';
    return 'لم يُفحص';
}

function getStatusBadgeClass(st) {
    if (st === 'ok') return 'text-emerald-600';
    if (st === 'broken') return 'text-rose-600';
    return 'text-slate-400';
}

function getItemBorderClass(st) {
    if (st === 'broken') return 'border-rose-300 bg-rose-50/20';
    if (st === 'ok') return 'border-emerald-200';
    return 'border-slate-200';
}

function getSelectedStatusBtn(st) {
    if (st === 'ok') return 'bg-emerald-600 text-white border-emerald-600 font-bold';
    if (st === 'broken') return 'bg-rose-600 text-white border-rose-600 font-bold';
    return 'bg-slate-700 text-white border-slate-700 font-bold';
}

function updateSummary() {
    let summaryParts = [];

    // Lock info
    if (lockType.value === 'pattern' && patternSequence.value.length) {
        summaryParts.push(`[نمط القفل: ${patternSequence.value.join('-')}]`);
    } else if (lockType.value === 'pin' && pinCode.value) {
        summaryParts.push(`[رمز PIN: ${pinCode.value}]`);
    }

    // Check items that were inspected
    const inspected = checkItems.filter(i => i.status !== 'untested');
    if (inspected.length) {
        const defects = inspected.filter(i => i.status === 'broken').map(i => i.label);
        const goods = inspected.filter(i => i.status === 'ok').map(i => i.label);

        let checkSummary = [];
        if (defects.length) checkSummary.push(`عيوب مستلمة: ${defects.join('، ')}`);
        if (goods.length) checkSummary.push(`سليم: ${goods.join('، ')}`);
        summaryParts.push(`[فحص الاستلام: ${checkSummary.join(' | ')}]`);
    }

    emit('update', {
        lockType: lockType.value,
        pattern: patternSequence.value.join('-'),
        pin: pinCode.value,
        checklist: checkItems,
        formattedSummary: summaryParts.join(' '),
    });
}
</script>
