<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-[120] overflow-y-auto p-4 pt-12 sm:pt-24">
                <div
                    class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm"
                    aria-hidden="true"
                    @click="close"
                />

                <div
                    class="relative mx-auto w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    aria-label="لوحة الأوامر والبحث"
                >
                    <!-- Search field -->
                    <div class="flex items-center gap-3 border-b border-slate-100 px-4">
                        <AppIcon name="search" class="h-5 w-5 shrink-0 text-slate-400" />
                        <input
                            ref="inputEl"
                            v-model="query"
                            type="text"
                            class="min-w-0 flex-1 bg-transparent py-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none"
                            placeholder="ابحث عن شاشة أو إجراء..."
                            autocomplete="off"
                            spellcheck="false"
                            @keydown="onKeydown"
                        />
                        <kbd
                            class="shrink-0 rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-sans text-[10px] font-semibold text-slate-500"
                        >
                            Esc
                        </kbd>
                    </div>

                    <!-- Results -->
                    <ul ref="listEl" class="max-h-[55vh] overflow-y-auto p-2" role="listbox">
                        <template v-for="group in grouped" :key="group.name">
                            <li
                                class="px-3 pb-1 pt-2 text-[11px] font-bold text-slate-400"
                                role="presentation"
                            >
                                {{ group.name }}
                            </li>
                            <li v-for="cmd in group.items" :key="cmd.key" role="option" :aria-selected="cmd === activeCommand">
                                <button
                                    type="button"
                                    :data-idx="indexOf(cmd)"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-start text-sm transition"
                                    :class="
                                        cmd === activeCommand
                                            ? 'bg-blue-50 text-blue-900'
                                            : cmd.danger
                                              ? 'text-rose-700 hover:bg-rose-50'
                                              : 'text-slate-700 hover:bg-slate-100'
                                    "
                                    @mousemove="activeIndex = indexOf(cmd)"
                                    @click="run(cmd)"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center text-base" aria-hidden="true">
                                        <span v-if="cmd.emoji">{{ cmd.emoji }}</span>
                                        <AppIcon v-else :name="cmd.icon" class="h-4.5 w-4.5" />
                                    </span>

                                    <span class="min-w-0 flex-1 truncate">{{ cmd.label }}</span>

                                    <span v-if="cmd === activeCommand" class="shrink-0 text-slate-400">
                                        <AppIcon name="corner-down-left" class="h-4 w-4" />
                                    </span>
                                    <span v-else class="shrink-0 text-[11px] text-slate-400">{{ cmd.group }}</span>
                                </button>
                            </li>
                        </template>

                        <li v-if="grouped.length === 0" class="px-3 py-10 text-center text-sm text-slate-400">
                            لا توجد نتائج مطابقة لـ "{{ query }}"
                        </li>
                    </ul>

                    <!-- Hints -->
                    <div class="flex items-center gap-4 border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400">
                        <span class="flex items-center gap-1">
                            <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 font-sans text-[10px]">↑</kbd>
                            <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 font-sans text-[10px]">↓</kbd>
                            للتنقل
                        </span>
                        <span class="flex items-center gap-1">
                            <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 font-sans text-[10px]">↵</kbd>
                            للفتح
                        </span>
                        <span class="ms-auto flex items-center gap-1">
                            <kbd class="rounded border border-slate-200 bg-slate-50 px-1.5 font-sans text-[10px]">Esc</kbd>
                            للإغلاق
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    commands: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open']);

const query = ref('');
const activeIndex = ref(0);
const inputEl = ref(null);
const listEl = ref(null);
let restoreFocus = null;

const flat = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.commands;

    return props.commands.filter((cmd) =>
        `${cmd.label} ${cmd.keywords || ''} ${cmd.group || ''}`.toLowerCase().includes(q)
    );
});

const grouped = computed(() => {
    const groups = [];
    for (const cmd of flat.value) {
        let group = groups.find((g) => g.name === cmd.group);
        if (!group) {
            group = { name: cmd.group, items: [] };
            groups.push(group);
        }
        group.items.push(cmd);
    }
    return groups;
});

const activeCommand = computed(() => flat.value[activeIndex.value]);

function indexOf(cmd) {
    return flat.value.indexOf(cmd);
}

function close() {
    emit('update:open', false);
}

function run(cmd) {
    if (!cmd) return;
    close();
    cmd.run?.();
}

function move(delta) {
    const list = flat.value;
    if (!list.length) return;
    activeIndex.value = (activeIndex.value + delta + list.length) % list.length;

    nextTick(() => {
        listEl.value?.querySelector(`[data-idx="${activeIndex.value}"]`)?.scrollIntoView({ block: 'nearest' });
    });
}

function onKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        move(1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        move(-1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        run(activeCommand.value);
    } else if (e.key === 'Escape') {
        e.preventDefault();
        close();
    } else if (e.key === 'Tab') {
        e.preventDefault();
    }
}

watch(query, () => {
    activeIndex.value = 0;
});

watch(
    () => props.open,
    (nowOpen) => {
        if (typeof document === 'undefined') return;

        if (nowOpen) {
            restoreFocus = document.activeElement;
            query.value = '';
            activeIndex.value = 0;
            document.body.classList.add('overflow-hidden');
            nextTick(() => inputEl.value?.focus());
        } else {
            document.body.classList.remove('overflow-hidden');
            restoreFocus?.focus?.();
            restoreFocus = null;
        }
    }
);

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') document.body.classList.remove('overflow-hidden');
});
</script>
