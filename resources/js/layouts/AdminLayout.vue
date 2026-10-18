<template>
    <div class="min-h-screen min-h-dvh bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors">
        <!-- ------------------------------------------------ Mobile Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-40 bg-slate-950/70 backdrop-blur-xs lg:hidden"
                aria-hidden="true"
                @click="open = false"
            />
        </Transition>

        <!-- ------------------------------------------------ Main Sidebar Aside -->
        <aside
            class="fixed inset-y-0 right-0 z-50 flex w-72 flex-col bg-gradient-to-b from-blue-950 via-slate-950 to-blue-950 text-slate-100 overflow-hidden shadow-2xl transition-[transform,width] duration-300 ease-in-out print:hidden lg:translate-x-0"
            :class="[
                open ? 'translate-x-0' : 'translate-x-full',
                rail ? 'lg:w-16' : 'lg:w-64',
                peekOpen ? 'lg:shadow-2xl' : 'lg:shadow-none',
            ]"
            aria-label="القائمة الجانبية"
            @pointerenter="onSidebarPointerEnter"
            @pointerleave="onSidebarPointerLeave"
        >
            <!-- Sidebar Header & Branding -->
            <div
                class="flex h-16 items-center border-b border-blue-900/60 transition-[padding] duration-300 ease-in-out motion-reduce:transition-none"
                :class="rail ? 'justify-start px-3' : 'justify-between px-5'"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 via-blue-600 to-cyan-500 text-lg shadow-md shadow-blue-500/20">
                        📱
                    </div>
                    <div v-if="!rail" :class="labelFade">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm font-extrabold tracking-tight text-white">دماتك</span>
                            <span class="rounded bg-blue-500/20 px-1.5 py-0.2 text-[10px] font-semibold text-cyan-300 border border-blue-400/30">DamaTech</span>
                        </div>
                        <p class="text-[11px] font-medium text-blue-200/70">أنظمة الهواتف والصيانة</p>
                    </div>
                </div>

                <!-- Close button: drawer on mobile, collapse on desktop -->
                <button
                    v-if="!rail && !peekOpen"
                    :class="labelFade"
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white/10 hover:text-white"
                    title="إغلاق القائمة"
                    aria-label="إغلاق القائمة"
                    @click="closeSidebar"
                >
                    ✕
                </button>

                <!-- Pin button: keeps the hovered sidebar expanded -->
                <button
                    v-if="peekOpen"
                    :class="labelFade"
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white/10 hover:text-white"
                    title="تثبيت القائمة مفتوحة"
                    aria-label="تثبيت القائمة مفتوحة"
                    @click="pinSidebar"
                >
                    📌
                </button>
            </div>

            <!-- Quick Filter / Search in Nav -->
            <div v-if="!rail" class="border-b border-blue-900/40 px-3 py-2.5" :class="labelFade">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-blue-300/60" aria-hidden="true">
                        🔍
                    </span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        @focus="searchFocused = true"
                        @blur="searchFocused = false"
                        placeholder="بحث سريع في القوائم..."
                        class="w-full rounded-lg border border-blue-900/60 bg-blue-900/30 py-1.5 pr-8 pl-7 text-xs text-slate-100 placeholder-blue-300/40 transition focus:border-cyan-400 focus:bg-blue-900/50 focus:outline-none focus:ring-1 focus:ring-cyan-400"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute inset-y-0 left-0 flex items-center pl-2.5 text-xs text-slate-400 hover:text-white"
                        title="مسح البحث"
                        @click="searchQuery = ''"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- Navigation Links Scroll Area -->
            <nav
                ref="navEl"
                class="nav-scroll-fade flex-1 overflow-x-hidden overflow-y-auto overscroll-contain py-3 scrollbar-thin scrollbar-thumb-blue-900 scrollbar-track-transparent"
                :class="rail ? 'px-2' : 'px-3'"
                aria-label="التنقل الرئيسي"
                @scroll.passive="scheduleNavFades"
            >
                <!-- Single content wrapper so a ResizeObserver can track the scrollable height -->
                <div ref="navContent">
                    <!-- Empty state when search produces no links -->
                    <div v-if="filteredSections.length === 0" class="py-8 text-center text-xs text-blue-300/50">
                        لا توجد شاشات مطابقة لـ "{{ searchQuery }}"
                    </div>

                    <div
                        v-for="(section, index) in filteredSections"
                        :key="section.label || 'overview'"
                        class="transition-[margin] duration-300 ease-in-out motion-reduce:transition-none"
                        :class="index > 0 ? (rail ? 'mt-1' : 'mt-4') : ''"
                    >
                        <!-- The divider and the heading stay mounted and swap heights (grid 0fr <-> 1fr)
                             so the items below glide instead of jumping when the rail toggles. -->

                        <!-- Icon rail: a thin divider stands in for the section header.
                             The padding gives the 1px line a hoverable area for the tooltip. -->
                        <div
                            v-if="index > 0"
                            class="grid transition-[grid-template-rows] duration-300 ease-in-out motion-reduce:transition-none"
                            :class="rail ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            :aria-hidden="rail ? undefined : 'true'"
                        >
                            <div class="min-h-0 overflow-hidden">
                                <div
                                    class="mx-2 mb-1.5 cursor-default py-1.5"
                                    :role="rail ? 'separator' : undefined"
                                    :title="rail ? section.label : undefined"
                                    :aria-label="rail ? section.label : undefined"
                                >
                                    <div class="border-t border-blue-900/60"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Section Header with Accordion Toggle -->
                        <div
                            v-if="section.label"
                            class="grid transition-[grid-template-rows] duration-300 ease-in-out motion-reduce:transition-none"
                            :class="rail ? 'grid-rows-[0fr]' : 'grid-rows-[1fr]'"
                            :inert="rail || undefined"
                        >
                            <div class="min-h-0 overflow-hidden">
                                <h2 class="mb-1" :class="rail ? 'opacity-0' : labelFade">
                                    <button
                                        type="button"
                                        :id="`nav-section-${index}-label`"
                                        class="group flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-blue-300/60 transition hover:bg-blue-900/40 hover:text-blue-200"
                                        :aria-expanded="!isCollapsed(section.label)"
                                        :aria-controls="`nav-section-${index}`"
                                        @click="toggleSection(section.label)"
                                    >
                                        <span class="flex items-center gap-1.5">
                                            <span
                                                class="inline-block text-[8px] transition-transform duration-200"
                                                :class="isCollapsed(section.label) ? '-rotate-90' : 'rotate-0'"
                                                aria-hidden="true"
                                            >▼</span>
                                            <span>{{ section.label }}</span>
                                        </span>
                                        <span
                                            class="rounded-full bg-blue-900/50 px-1.5 py-0.2 text-[9px] font-medium text-blue-300/50 group-hover:text-blue-200"
                                        >
                                            {{ section.items.length }}
                                        </span>
                                    </button>
                                </h2>
                            </div>
                        </div>

                        <!-- Items List -->
                        <ul
                            v-show="rail || !isCollapsed(section.label) || Boolean(searchQuery)"
                            :id="`nav-section-${index}`"
                            :aria-labelledby="section.label ? `nav-section-${index}-label` : undefined"
                            class="space-y-1"
                        >
                            <li v-for="item in section.items" :key="item.to">
                                <RouterLink
                                    :to="item.to"
                                    class="relative flex items-center rounded-xl py-2 text-sm font-medium transition duration-150"
                                    :class="[
                                        rail ? 'justify-start px-3' : 'justify-between px-3',
                                        isActive(item)
                                            ? 'bg-gradient-to-l from-blue-700 to-blue-800 text-white shadow-sm ring-1 ring-blue-500/40 font-semibold'
                                            : 'text-slate-300 hover:bg-white/10 hover:text-white',
                                    ]"
                                    :title="rail ? railTitle(item) : undefined"
                                    :aria-current="isActive(item) ? 'page' : undefined"
                                    @click="open = false"
                                >
                                    <!-- Accent indicator bar for active item in RTL; shortens to hug the icon in the rail -->
                                    <span
                                        v-if="isActive(item)"
                                        class="absolute right-0 w-1 rounded-l-full bg-cyan-400 transition-[top,bottom] duration-300 ease-in-out motion-reduce:transition-none"
                                        :class="rail ? 'top-2.5 bottom-2.5' : 'top-1.5 bottom-1.5'"
                                        aria-hidden="true"
                                    ></span>

                                    <span class="flex items-center gap-3">
                                        <span class="flex h-6 w-6 items-center justify-center text-base" aria-hidden="true">
                                            {{ item.icon }}
                                        </span>
                                        <span :class="rail ? 'sr-only' : labelFade">{{ item.label }}</span>
                                    </span>

                                    <!-- Icon rail: badges shrink to a dot on the icon -->
                                    <span
                                        v-if="rail && isDisabled(item)"
                                        class="absolute top-1 left-1.5 h-2 w-2 rounded-full bg-amber-400 ring-2 ring-slate-950"
                                    >
                                        <span class="sr-only">معطّل</span>
                                    </span>
                                    <span
                                        v-else-if="rail && item.badge"
                                        class="absolute top-1 left-1.5 h-2 w-2 rounded-full bg-cyan-400 ring-2 ring-slate-950"
                                    >
                                        <span class="sr-only">{{ item.badge }}</span>
                                    </span>
                                    <span
                                        v-else-if="!rail && isDisabled(item)"
                                        :class="labelFade"
                                        class="rounded-full bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold text-amber-300 border border-amber-400/40"
                                    >
                                        معطّل
                                    </span>
                                    <span
                                        v-else-if="!rail && item.badge"
                                        class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                        :class="[
                                            labelFade,
                                            isActive(item) ? 'bg-white/20 text-white' : 'bg-blue-500/20 text-cyan-300 border border-blue-400/30',
                                        ]"
                                    >
                                        {{ item.badge }}
                                    </span>
                                </RouterLink>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- ------------------------------------------------ User Profile & Quick Actions Footer -->
            <div
                class="border-t border-blue-900/60 bg-slate-950/60 backdrop-blur-xs transition-[padding] duration-300 ease-in-out motion-reduce:transition-none"
                :class="rail ? 'px-3 py-3' : 'p-3.5'"
            >
                <div class="flex items-center" :class="rail ? 'w-10 flex-col gap-2' : 'gap-3'">
                    <!-- Avatar + name open the profile page (account card, password, sessions) -->
                    <RouterLink
                        to="/profile"
                        class="group/profile flex min-w-0 flex-1 items-center gap-3 rounded-xl transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400"
                        :class="[
                            rail ? '-m-1 p-1' : '-m-1.5 p-1.5',
                            onProfile ? 'bg-white/10' : 'hover:bg-white/10',
                        ]"
                        :title="rail ? 'الملف الشخصي' : 'فتح الملف الشخصي'"
                        aria-label="الملف الشخصي"
                        :aria-current="onProfile ? 'page' : undefined"
                        @click="open = false"
                    >
                        <!-- User Avatar with Initials -->
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-cyan-500 text-sm font-bold text-white shadow-sm ring-2 transition group-hover/profile:ring-cyan-400/60"
                            :class="onProfile ? 'ring-cyan-400/70' : 'ring-blue-500/20'"
                        >
                            {{ initials }}
                        </span>

                        <!-- User Name and Role -->
                        <span v-if="!rail" class="min-w-0 flex-1 overflow-hidden" :class="labelFade">
                            <span class="block truncate text-sm font-bold text-slate-100" :title="auth.user?.name">
                                {{ auth.user?.name }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                <span class="truncate text-[11px] font-medium text-cyan-300/80">
                                    {{ userRoleName }}
                                </span>
                            </span>
                        </span>
                    </RouterLink>

                    <!-- Install as App Button (hidden inside the installed app) -->
                    <button
                        v-if="!pwa.installed.value"
                        type="button"
                        title="تثبيت النظام كتطبيق على هذا الجهاز"
                        aria-label="تثبيت التطبيق"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:ring-1 focus:ring-cyan-400"
                        @click="pwa.installOrExplain"
                    >
                        📲
                    </button>

                    <!-- Print Preferences Button -->
                    <button
                        type="button"
                        title="إعدادات وتفضيلات الطباعة لهذا الجهاز"
                        aria-label="تفضيلات الطباعة لهذا الجهاز"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-white/10 hover:text-white focus:ring-1 focus:ring-cyan-400"
                        @click="openPrintPreferences"
                    >
                        🖨️
                    </button>

                    <!-- Logout Button -->
                    <button
                        type="button"
                        title="تسجيل الخروج من النظام"
                        aria-label="تسجيل الخروج"
                        class="group flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-500/20 hover:text-rose-400 focus:ring-1 focus:ring-rose-400"
                        @click="logout"
                    >
                        <svg
                            class="h-4.5 w-4.5 transition-transform duration-150 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- ------------------------------------------------ Main Application Shell -->
        <div
            class="transition-[padding] duration-300 ease-in-out print:!m-0 print:!p-0"
            :class="contentCollapsed ? 'lg:pr-16' : 'lg:pr-64'"
            :style="{ '--sidebar-w': contentCollapsed ? '4rem' : '16rem' }"
        >
            <!-- App Top Header -->
            <header
                class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-slate-200 dark:border-slate-800 bg-white/95 dark:bg-slate-900/95 px-3 backdrop-blur-xs print:hidden sm:gap-3 sm:px-4 lg:px-8"
            >
                <!-- Hamburger Menu Button: opens the drawer on mobile, collapses/expands the sidebar on desktop -->
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 shadow-2xs transition hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                    title="إظهار/إخفاء القائمة الرئيسية"
                    aria-label="إظهار/إخفاء القائمة الرئيسية"
                    :aria-expanded="desktop ? !sidebarCollapsed : open"
                    @click="toggleSidebar"
                >
                    <AppIcon name="menu" class="h-5 w-5" />
                </button>

                <!-- Section breadcrumb (where you are) + page title (what you see) -->
                <div class="min-w-0 flex-1 leading-tight">
                    <nav
                        v-if="sectionCrumb"
                        class="flex items-center gap-1 text-[11px] font-medium text-slate-400 dark:text-slate-500 overflow-x-auto whitespace-nowrap no-scrollbar py-0.5"
                        aria-label="مسار التنقل"
                    >
                        <RouterLink to="/dashboard" class="rounded transition hover:text-slate-600 dark:hover:text-slate-300">
                            الرئيسية
                        </RouterLink>
                        <AppIcon name="chevron-left" class="h-3 w-3 shrink-0 text-slate-300 dark:text-slate-600" />
                        <span class="truncate text-slate-500 dark:text-slate-400">{{ sectionCrumb }}</span>
                    </nav>

                    <h1 class="truncate text-base font-bold text-slate-900 dark:text-white sm:text-lg">{{ pageTitle }}</h1>
                </div>

                <!-- Global search: opens the command palette (Ctrl/⌘ + K) -->
                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 shadow-2xs transition hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40 md:w-52 md:justify-start md:px-3 lg:w-64 xl:w-72"
                    title="البحث في النظام (Ctrl + K)"
                    aria-label="البحث في النظام"
                    @click="paletteOpen = true"
                >
                    <AppIcon name="search" class="h-4.5 w-4.5 shrink-0" />
                    <span class="hidden min-w-0 flex-1 truncate text-start text-sm md:inline">بحث في النظام...</span>
                    <kbd
                        class="hidden shrink-0 items-center gap-1 rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-1.5 py-0.5 font-sans text-[10px] font-semibold text-slate-500 dark:text-slate-400 lg:inline-flex"
                    >
                        <span>Ctrl</span><span>K</span>
                    </kbd>
                </button>

                <!-- Appearance: light / dark / system mode and accent color -->
                <ThemeMenu
                    trigger-class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 shadow-2xs transition hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                />

                <!-- Account menu: profile / print prefs / install / logout -->
                <AccountMenu />

                <!-- Today's date -->
                <div
                    class="hidden h-10 shrink-0 items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-3 text-xs font-medium text-slate-500 dark:text-slate-400 shadow-2xs xl:flex"
                >
                    <AppIcon name="calendar" class="h-4 w-4 text-slate-400 dark:text-slate-500" />
                    <span>{{ today }}</span>
                </div>
            </header>

            <!-- Main Page View Container -->
            <main class="p-4 print:!p-0 print:!m-0 lg:p-8">
                <RouterView />
            </main>
        </div>

        <div class="print:hidden">
            <ToastHost />
            <ConfirmHost />
            <PrintPreferencesHost />
            <CommandPalette v-model:open="paletteOpen" :commands="commands" />
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useFeaturesStore } from '../stores/features';
import ToastHost from '../components/ui/ToastHost.vue';
import ConfirmHost from '../components/ui/ConfirmHost.vue';
import PrintPreferencesHost from '../components/print/PrintPreferencesHost.vue';
import { openPrintPreferences } from '../composables/usePrintPreferences';
import { usePwaInstall } from '../composables/usePwaInstall';
import { useTheme } from '../composables/useTheme';
import AppIcon from '../components/ui/AppIcon.vue';
import AccountMenu from '../components/ui/AccountMenu.vue';
import CommandPalette from '../components/ui/CommandPalette.vue';
import ThemeMenu from '../components/ui/ThemeMenu.vue';

const auth = useAuthStore();
const featuresStore = useFeaturesStore();
const pwa = usePwaInstall();
const { isDark, toggleTheme } = useTheme();
const route = useRoute();
const router = useRouter();
const open = ref(false);
const searchQuery = ref('');
const paletteOpen = ref(false);

const today = new Intl.DateTimeFormat('ar-SY-u-nu-latn', {
    dateStyle: 'full',
}).format(new Date());

const pageTitle = computed(() => route.meta.title || 'لوحة التحكم');

/** The sidebar group that owns the current page, shown as breadcrumb context. */
const sectionCrumb = computed(() => activeSection.value || '');

/**
 * The sidebar sections and links.
 */
const navSections = [
    {
        label: '',
        items: [
            { to: '/dashboard', label: 'لوحة التحكم', icon: '📊' },
            { to: '/reports', label: 'التقارير المالية', icon: '📈', badge: 'جديد', permission: 'reports.view', feature: 'reports' },
        ],
    },
    {
        label: 'المبيعات والمشتريات',
        items: [
            { to: '/sales', label: 'فواتير البيع', icon: '🧾', permission: 'sales.view', feature: 'sales' },
            { to: '/purchases', label: 'فواتير الشراء', icon: '📥', permission: 'purchases.view', feature: 'purchases' },
        ],
    },
    {
        label: 'الصندوق',
        items: [
            { to: '/payments?type=CUSTOMER', label: 'المقبوضات', icon: '💵', permission: 'payments.view' },
            { to: '/payments?type=SUPPLIER', label: 'المدفوعات', icon: '💸', permission: 'payments.view' },
            { to: '/expenses', label: 'المصاريف', icon: '📤', permission: 'expenses.view', feature: 'expenses' },
        ],
    },
    {
        label: 'الصيانة',
        items: [
            { to: '/repairs', label: 'أجهزة الصيانة', icon: '🔧', permission: 'repairs.view', feature: 'repairs' },
            { to: '/technicians', label: 'خبراء الصيانة', icon: '🧰', permission: 'technicians.view', feature: 'technicians' },
        ],
    },
    {
        label: 'المستودع',
        items: [
            { to: '/materials', label: 'المواد والقطع', icon: '📦', permission: 'materials.view' },
            { to: '/material-categories', label: 'أصناف المواد', icon: '🗂️', permission: 'materials.view' },
            { to: '/inventory', label: 'المخزون والجرد', icon: '⚖️', permission: 'inventory.view', feature: 'inventory' },
            { to: '/services', label: 'دليل الخدمات', icon: '🛠️', permission: 'services.view', feature: 'services' },
            { to: '/service-units', label: 'وحدات القياس', icon: '📐', permission: 'services.view', feature: 'services' },
        ],
    },
    {
        label: 'جهات التعامل',
        items: [
            { to: '/customers', label: 'العملاء', icon: '👥', permission: 'customers.view' },
            { to: '/suppliers', label: 'الموردون', icon: '🚚', permission: 'suppliers.view' },
        ],
    },
    {
        label: 'النظام',
        items: [
            {
                to: '/users',
                label: 'المستخدمون',
                icon: '👤',
                permission: 'users.manage',
            },
            {
                to: '/roles',
                label: 'الأدوار والصلاحيات',
                icon: '🛡️',
                permission: 'roles.manage',
            },
            {
                to: '/currencies',
                label: 'العملات وأسعار الصرف',
                icon: '💱',
                permission: 'currencies.manage',
            },
            {
                to: '/settings',
                label: 'الإعدادات',
                icon: '⚙️',
                check: () => auth.hasPermission('settings.manage') || auth.hasPermission('features.manage'),
            },
        ],
    },
];

const sections = computed(() =>
    navSections
        .map((section) => ({
            label: section.label,
            items: section.items.filter((item) => {
                if (item.check && !item.check()) return false;
                if (item.permission && !auth.hasPermission(item.permission)) return false;
                if (item.feature && !featuresStore.isEnabled(item.feature)) {
                    if (!auth.isMaster) {
                        return false;
                    }
                }
                return true;
            }),
        }))
        .filter((section) => section.items.length > 0)
);

/**
 * Filtered sections when searching in the nav.
 */
const filteredSections = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return sections.value;

    return sections.value
        .map((section) => ({
            label: section.label,
            items: section.items.filter((item) => item.label.toLowerCase().includes(q)),
        }))
        .filter((section) => section.items.length > 0);
});

// ------------------------------------------------------------ collapsing

const COLLAPSED_KEY = 'nav.collapsed';

const collapsed = ref(readCollapsed());

function readCollapsed() {
    try {
        const stored = JSON.parse(localStorage.getItem(COLLAPSED_KEY));
        return Array.isArray(stored) ? stored.filter((label) => typeof label === 'string') : [];
    } catch {
        return [];
    }
}

function persistCollapsed() {
    try {
        localStorage.setItem(COLLAPSED_KEY, JSON.stringify(collapsed.value));
    } catch {
        /* see readCollapsed */
    }
}

function isCollapsed(label) {
    return Boolean(label) && collapsed.value.includes(label);
}

function toggleSection(label) {
    collapsed.value = isCollapsed(label)
        ? collapsed.value.filter((name) => name !== label)
        : [...collapsed.value, label];

    persistCollapsed();
}

const activeSection = computed(
    () => sections.value.find((section) => section.items.some(isActive))?.label ?? ''
);

watch(
    activeSection,
    (label) => {
        if (!isCollapsed(label)) return;
        collapsed.value = collapsed.value.filter((name) => name !== label);
        persistCollapsed();
    },
    { immediate: true }
);

function isDisabled(item) {
    return Boolean(item.feature) && !featuresStore.isEnabled(item.feature);
}

function railTitle(item) {
    if (isDisabled(item)) return `${item.label} (معطّل)`;
    if (item.badge) return `${item.label} (${item.badge})`;
    return item.label;
}

function isActive(item) {
    const [path, search] = item.to.split('?');

    const onPath = path === '/'
        ? route.path === '/'
        : route.path === path || route.path.startsWith(`${path}/`);

    if (!onPath) return false;
    if (!search) return true;

    return [...new URLSearchParams(search)].every(([key, value]) => route.query[key] === value);
}

const initials = computed(() => {
    const name = auth.user?.name || '';
    return name.split(' ').slice(0, 2).map((w) => w[0]).join('');
});

const onProfile = computed(() => route.path === '/profile');

const userRoleName = computed(() => {
    const roles = auth.user?.roles || [];
    if (!roles.length) return 'مستخدم';
    return roles[0].name || roles[0].slug || 'مستخدم';
});

async function logout() {
    await auth.logout();
    router.replace({ name: 'login' });
}

// ------------------------------------------------------------ Command palette

/**
 * Everything the command palette can jump to: every visible nav link, plus the
 * handful of actions that live in the header/sidebar. Each item runs itself, so
 * the palette stays a dumb list renderer.
 */
const commands = computed(() => {
    const nav = sections.value.flatMap((section) =>
        section.items.map((item) => ({
            key: `nav:${item.to}`,
            label: item.label,
            group: section.label || 'الرئيسية',
            emoji: item.icon,
            keywords: item.to,
            run: () => router.push(item.to),
        }))
    );

    const actions = [
        {
            key: 'act:profile',
            label: 'الملف الشخصي',
            group: 'إجراءات',
            icon: 'user',
            keywords: 'profile حساب بيانات',
            run: () => router.push('/profile'),
        },
        {
            key: 'act:theme',
            label: isDark.value ? 'التبديل إلى الوضع الفاتح' : 'التبديل إلى الوضع الداكن',
            group: 'إجراءات',
            icon: isDark.value ? 'sun' : 'moon',
            keywords: 'theme dark light مظهر وضع داكن فاتح',
            run: toggleTheme,
        },
        {
            key: 'act:print',
            label: 'تفضيلات الطباعة',
            group: 'إجراءات',
            icon: 'printer',
            keywords: 'print طباعة',
            run: openPrintPreferences,
        },
    ];

    if (!pwa.installed.value) {
        actions.push({
            key: 'act:install',
            label: 'تثبيت التطبيق',
            group: 'إجراءات',
            icon: 'download',
            keywords: 'install pwa تثبيت تطبيق',
            run: () => pwa.installOrExplain(),
        });
    }

    actions.push({
        key: 'act:logout',
        label: 'تسجيل الخروج',
        group: 'إجراءات',
        icon: 'logout',
        keywords: 'logout خروج',
        danger: true,
        run: logout,
    });

    return [...nav, ...actions];
});

// ------------------------------------------------------------ Desktop icon rail

const SIDEBAR_COLLAPSED_KEY = 'nav.sidebarCollapsed';

const sidebarCollapsed = ref(readSidebarCollapsed());

function readSidebarCollapsed() {
    try {
        return localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1';
    } catch {
        return false;
    }
}

watch(sidebarCollapsed, (isCollapsedNow) => {
    try {
        localStorage.setItem(SIDEBAR_COLLAPSED_KEY, isCollapsedNow ? '1' : '0');
    } catch {
        /* see readCollapsed */
    }
});

const desktopQuery = typeof window !== 'undefined' ? window.matchMedia('(min-width: 1024px)') : null;
const desktop = ref(Boolean(desktopQuery?.matches));

function syncDesktop(e) {
    desktop.value = e.matches;
}

// Hovering the collapsed rail slides the full sidebar out over the page (the page keeps
// its rail padding). Typing in the nav search keeps it out even if the pointer leaves.
const PEEK_OPEN_DELAY = 150;
const PEEK_CLOSE_DELAY = 200;

const hovering = ref(false);
const searchFocused = ref(false);
let peekTimer = null;

const peekOpen = computed(
    () => desktop.value && sidebarCollapsed.value && (hovering.value || searchFocused.value)
);

function onSidebarPointerEnter(e) {
    // Mouse only: a tap on a touch screen fires pointerenter too.
    if (e.pointerType !== 'mouse') return;
    clearTimeout(peekTimer);
    peekTimer = setTimeout(() => (hovering.value = true), PEEK_OPEN_DELAY);
}

function onSidebarPointerLeave(e) {
    if (e.pointerType !== 'mouse') return;
    clearTimeout(peekTimer);
    peekTimer = setTimeout(() => (hovering.value = false), PEEK_CLOSE_DELAY);
}

function pinSidebar() {
    clearTimeout(peekTimer);
    hovering.value = false;
    sidebarCollapsed.value = false;
}

// The mobile drawer always shows the full menu; only the desktop sidebar shrinks to icons.
const railTarget = computed(() => desktop.value && sidebarCollapsed.value && !peekOpen.value);

// `rail` is what the template renders. It follows railTarget, except that on collapse the
// labels fade out first (LABEL_FADE_OUT ms, matching .sidebar-label-out) and only then
// does the sidebar shrink and drop them.
const LABEL_FADE_OUT = 150;

const rail = ref(railTarget.value);
const fadingOut = ref(false);
let railTimer = null;

// The page padding narrows together with the sidebar (after the fade), and stays narrow
// while a hovered rail is peeking over the page.
const contentCollapsed = ref(sidebarCollapsed.value);

watch(sidebarCollapsed, (isCollapsedNow) => {
    if (!isCollapsedNow) contentCollapsed.value = false;
});

// Labels fade in while the rail widens. Only armed once the rail has expanded, so the
// first page load and the mobile drawer don't fade.
const expandedFromRail = ref(false);
const labelFade = computed(() => {
    if (fadingOut.value) return 'sidebar-label-out';
    return expandedFromRail.value ? 'sidebar-label-in' : '';
});

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function enterRail() {
    fadingOut.value = false;
    rail.value = true;
    contentCollapsed.value = true;
    searchQuery.value = '';
}

watch(railTarget, (toRail) => {
    clearTimeout(railTimer);

    if (!toRail) {
        if (rail.value) expandedFromRail.value = true;
        fadingOut.value = false;
        rail.value = false;
        return;
    }

    if (prefersReducedMotion()) {
        enterRail();
        return;
    }

    fadingOut.value = true;
    railTimer = setTimeout(enterRail, LABEL_FADE_OUT);
});

function toggleSidebar() {
    if (desktop.value) {
        sidebarCollapsed.value = !sidebarCollapsed.value;
    } else {
        open.value = true;
    }
}

function closeSidebar() {
    if (desktop.value) {
        sidebarCollapsed.value = true;
    } else {
        open.value = false;
    }
}

// ------------------------------------------------------------ Keep the active link in view

const navEl = ref(null);
const NAV_SCROLL_MARGIN = 12;
const RAIL_TRANSITION = 300; // matches the duration-300 width/height transitions
let revealTimer = null;

// Scrolls only the nav (not the page) by the least amount that shows the active link.
function revealActiveLink({ smooth = true } = {}) {
    const nav = navEl.value;
    const link = nav?.querySelector('[aria-current="page"]');
    if (!link) return;

    const navRect = nav.getBoundingClientRect();
    const linkRect = link.getBoundingClientRect();
    let delta = 0;

    if (linkRect.top < navRect.top + NAV_SCROLL_MARGIN) {
        delta = linkRect.top - navRect.top - NAV_SCROLL_MARGIN;
    } else if (linkRect.bottom > navRect.bottom - NAV_SCROLL_MARGIN) {
        delta = linkRect.bottom - navRect.bottom + NAV_SCROLL_MARGIN;
    }

    if (delta) {
        nav.scrollBy({ top: delta, behavior: smooth && !prefersReducedMotion() ? 'smooth' : 'auto' });
    }
}

// ------------------------------------------------------------ Scroll fade hints

// Sets the mask sizes on .nav-scroll-fade: a fade only on the edge(s) with more to scroll.
const NAV_FADE = '24px';
const navContent = ref(null);
let fadeFrame = 0;
let navResizeObserver = null;

function updateNavFades() {
    fadeFrame = 0;
    const nav = navEl.value;
    if (!nav) return;

    const maxScroll = nav.scrollHeight - nav.clientHeight;
    nav.style.setProperty('--nav-fade-top', nav.scrollTop > 1 ? NAV_FADE : '0px');
    nav.style.setProperty('--nav-fade-bottom', nav.scrollTop < maxScroll - 1 ? NAV_FADE : '0px');

    // The mask would fade the scrollbar too; a solid layer over its gutter keeps it opaque.
    // Overlay scrollbars (macOS) take no gutter and come out as 0px.
    nav.style.setProperty('--nav-scrollbar-width', `${nav.offsetWidth - nav.clientWidth}px`);
    nav.style.setProperty('--nav-scrollbar-side', getComputedStyle(nav).direction === 'rtl' ? 'left' : 'right');
}

function scheduleNavFades() {
    if (!fadeFrame) fadeFrame = requestAnimationFrame(updateNavFades);
}

function revealActiveLinkAfter(delay) {
    clearTimeout(revealTimer);
    revealTimer = setTimeout(() => revealActiveLink(), delay);
}

watch(() => route.fullPath, () => nextTick(() => revealActiveLink()));

// Headings fold/unfold while the rail toggles, which shifts the items; re-check once settled.
watch(rail, () => revealActiveLinkAfter(RAIL_TRANSITION));

watch(open, (isOpen) => {
    if (isOpen) nextTick(() => revealActiveLink({ smooth: false }));
});

// ------------------------------------------------------------ Mobile drawer UX
function handleKeyDown(e) {
    // Ctrl/⌘ + K toggles the command palette from anywhere in the app.
    if (e.key.toLowerCase() === 'k' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        paletteOpen.value = !paletteOpen.value;
        return;
    }

    if (e.key === 'Escape' && paletteOpen.value) {
        paletteOpen.value = false;
        return;
    }
    if (e.key === 'Escape' && peekOpen.value) {
        clearTimeout(peekTimer);
        hovering.value = false;
        if (searchFocused.value) document.activeElement?.blur();
        return;
    }
    if (e.key === 'Escape' && open.value) {
        open.value = false;
    }
}

watch(open, (isOpen) => {
    if (typeof document !== 'undefined') {
        if (isOpen) {
            document.body.classList.add('overflow-hidden');
        } else {
            document.body.classList.remove('overflow-hidden');
        }
    }
});

onMounted(() => {
    featuresStore.fetchFeatures();
    if (document.documentElement.dir !== 'rtl') {
        document.documentElement.dir = 'rtl';
        document.documentElement.lang = 'ar';
    }
    window.addEventListener('keydown', handleKeyDown);
    desktopQuery?.addEventListener('change', syncDesktop);
    nextTick(() => revealActiveLink({ smooth: false }));

    // The nav resizes with the window; its content with section folding, search and the rail.
    navResizeObserver = new ResizeObserver(scheduleNavFades);
    if (navEl.value) navResizeObserver.observe(navEl.value);
    if (navContent.value) navResizeObserver.observe(navContent.value);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeyDown);
    desktopQuery?.removeEventListener('change', syncDesktop);
    clearTimeout(peekTimer);
    clearTimeout(railTimer);
    clearTimeout(revealTimer);
    cancelAnimationFrame(fadeFrame);
    navResizeObserver?.disconnect();
    if (typeof document !== 'undefined') {
        document.body.classList.remove('overflow-hidden');
    }
});
</script>

<style>
@keyframes sidebar-label-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* A separate keyframes name so switching from -in to -out restarts the animation. */
@keyframes sidebar-label-out {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

/* Starts after the width transition (300ms) is under way so text doesn't show cramped. */
.sidebar-label-in {
    animation: sidebar-label-in 200ms ease-out 120ms both;
}

/* Runs before the rail shrinks; keep the duration in sync with LABEL_FADE_OUT. */
.sidebar-label-out {
    animation: sidebar-label-out 150ms ease-in both;
}

/* Registered so the fade sizes can transition. */
@property --nav-fade-top {
    syntax: '<length>';
    inherits: false;
    initial-value: 0px;
}

@property --nav-fade-bottom {
    syntax: '<length>';
    inherits: false;
    initial-value: 0px;
}

/* Fade hints at the nav's scrollable edges; sizes are set from updateNavFades().
   The second mask layer covers the scrollbar gutter so the scrollbar stays at full opacity.
   Also carries the padding transition, since an unlayered `transition` here would
   override a Tailwind transition utility on the same element. */
.nav-scroll-fade {
    --nav-fade-mask: linear-gradient(
        to bottom,
        transparent,
        #000 var(--nav-fade-top),
        #000 calc(100% - var(--nav-fade-bottom)),
        transparent
    );
    -webkit-mask-image: var(--nav-fade-mask), linear-gradient(#000, #000);
    mask-image: var(--nav-fade-mask), linear-gradient(#000, #000);
    -webkit-mask-size: 100% 100%, var(--nav-scrollbar-width, 0px) 100%;
    mask-size: 100% 100%, var(--nav-scrollbar-width, 0px) 100%;
    -webkit-mask-position: 0 0, var(--nav-scrollbar-side, left) top;
    mask-position: 0 0, var(--nav-scrollbar-side, left) top;
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    transition:
        padding 300ms cubic-bezier(0.4, 0, 0.2, 1),
        --nav-fade-top 150ms ease-out,
        --nav-fade-bottom 150ms ease-out;
}

@media (prefers-reduced-motion: reduce) {
    .sidebar-label-in,
    .sidebar-label-out {
        animation: none;
    }

    .nav-scroll-fade {
        transition: none;
    }
}
</style>
