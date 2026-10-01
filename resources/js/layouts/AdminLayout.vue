<template>
    <div class="min-h-screen bg-slate-50">
        <aside
            class="fixed inset-y-0 right-0 z-40 flex w-64 flex-col bg-gradient-to-b from-blue-950 via-blue-950 to-slate-900 text-slate-100 transition-transform lg:translate-x-0"
            :class="open ? 'translate-x-0' : 'translate-x-full'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-blue-900 px-6">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-cyan-500 text-lg">📱</div>
                <div>
                    <p class="text-sm font-bold leading-tight">نظام الهواتف والإكسسوارات</p>
                    <p class="text-[11px] text-cyan-300/80">بيع الإكسسوارات واستلام أجهزة الصيانة</p>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="التنقل الرئيسي">
                <div
                    v-for="(section, index) in sections"
                    :key="section.label || 'overview'"
                    :class="index > 0 ? 'mt-5' : ''"
                >
                    <!-- No tracking utilities here: letter-spacing breaks the
                         joins between Arabic letters. -->
                    <h2 v-if="section.label">
                        <button
                            type="button"
                            :id="`nav-section-${index}-label`"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-[11px] font-semibold text-blue-300/60 transition hover:bg-blue-900/60 hover:text-blue-200"
                            :aria-expanded="!isCollapsed(section.label)"
                            :aria-controls="`nav-section-${index}`"
                            @click="toggleSection(section.label)"
                        >
                            <!-- inline-block: a transform does not apply to an
                                 inline box. Closed points to the inline start,
                                 which is leftwards in RTL. -->
                            <span
                                class="inline-block text-[9px] transition-transform"
                                :class="isCollapsed(section.label) ? 'rotate-90' : ''"
                                aria-hidden="true"
                            >▼</span>
                            <span>{{ section.label }}</span>
                        </button>
                    </h2>
                    <ul
                        v-show="!isCollapsed(section.label)"
                        :id="`nav-section-${index}`"
                        :aria-labelledby="section.label ? `nav-section-${index}-label` : undefined"
                        class="space-y-1"
                    >
                        <li v-for="item in section.items" :key="item.to">
                            <RouterLink
                                :to="item.to"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition hover:bg-blue-900"
                                :class="isActive(item) ? 'bg-blue-800 text-white' : ''"
                                :aria-current="isActive(item) ? 'page' : undefined"
                                @click="open = false"
                            >
                                <span class="w-5 text-center" aria-hidden="true">{{ item.icon }}</span>
                                <span>{{ item.label }}</span>
                            </RouterLink>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t border-blue-900 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-700 text-sm font-bold">
                        {{ initials }}
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="truncate text-sm font-medium">{{ auth.user?.name }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ auth.user?.email }}</p>
                    </div>
                    <button
                        type="button"
                        title="تفضيلات الطباعة لهذا الجهاز"
                        aria-label="تفضيلات الطباعة لهذا الجهاز"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-blue-900 hover:text-white"
                        @click="openPrintPreferences"
                    >
                        🖨️
                    </button>
                    <button
                        type="button"
                        title="تسجيل الخروج"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-blue-900 hover:text-white"
                        @click="logout"
                    >
                        ⏻
                    </button>
                </div>
            </div>
        </aside>

        <div class="lg:pr-64">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white px-4 lg:px-8">
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 p-2 lg:hidden"
                    @click="open = true"
                >
                    ☰
                </button>
                <h1 class="text-lg font-bold text-slate-900">{{ pageTitle }}</h1>
                <div class="ms-auto text-sm text-slate-500">{{ today }}</div>
            </header>

            <main class="p-4 lg:p-8">
                <RouterView />
            </main>
        </div>

        <ToastHost />
        <ConfirmHost />
        <PrintPreferencesHost />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import ToastHost from '../components/ui/ToastHost.vue';
import ConfirmHost from '../components/ui/ConfirmHost.vue';
import PrintPreferencesHost from '../components/print/PrintPreferencesHost.vue';
import { openPrintPreferences } from '../composables/usePrintPreferences';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const open = ref(false);

const today = new Intl.DateTimeFormat('ar-SY-u-nu-latn', {
    dateStyle: 'full',
}).format(new Date());

const pageTitle = computed(() => route.meta.title || 'لوحة التحكم');

/**
 * The sidebar, grouped the way the work is grouped: what you look at, the
 * documents you raise, the money that moves against them, the two things the
 * shop actually handles — repairs and stock — then who you deal with, and
 * last what is only ever set up once.
 *
 * A link with a `permission` is hidden from users who lack it, so nobody is
 * offered a screen whose every request would come back 403. A section whose
 * every link is hidden loses its heading too, rather than advertising a group
 * with nothing under it.
 */
const navSections = [
    {
        label: '',
        items: [
            { to: '/', label: 'لوحة التحكم', icon: '📊' },
            { to: '/reports', label: 'التقارير', icon: '📈', permission: 'reports.view' },
        ],
    },
    {
        label: 'المبيعات والمشتريات',
        items: [
            { to: '/sales', label: 'فواتير البيع', icon: '🧾', permission: 'sales.view' },
            { to: '/purchases', label: 'فواتير الشراء', icon: '📥', permission: 'purchases.view' },
        ],
    },
    {
        label: 'الصندوق',
        items: [
            { to: '/payments?type=CUSTOMER', label: 'المقبوضات', icon: '💵', permission: 'payments.create' },
            { to: '/payments?type=SUPPLIER', label: 'المدفوعات', icon: '💸', permission: 'payments.create' },
            { to: '/expenses', label: 'المصاريف', icon: '📤', permission: 'expenses.view' },
        ],
    },
    {
        label: 'الصيانة',
        items: [
            { to: '/repairs', label: 'أجهزة الصيانة', icon: '🔧', permission: 'repairs.view' },
            { to: '/technicians', label: 'خبراء الصيانة', icon: '🧰', permission: 'technicians.view' },
        ],
    },
    {
        label: 'المستودع',
        items: [
            { to: '/materials', label: 'المواد', icon: '📦', permission: 'materials.view' },
            { to: '/material-categories', label: 'أصناف المواد', icon: '🗂️', permission: 'materials.view' },
            { to: '/inventory', label: 'المخزون', icon: '⚖️', permission: 'inventory.view' },
            { to: '/services', label: 'الخدمات', icon: '🛠️', permission: 'services.view' },
            { to: '/service-units', label: 'وحدات القياس', icon: '📐', permission: 'services.view' },
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
            { to: '/settings', label: 'الإعدادات', icon: '⚙️', permission: 'settings.manage' },
        ],
    },
];

const sections = computed(() =>
    navSections
        .map((section) => ({
            label: section.label,
            items: section.items.filter((item) => !item.permission || auth.hasPermission(item.permission)),
        }))
        .filter((section) => section.items.length > 0)
);

// ------------------------------------------------------------ collapsing

/**
 * Which sections the user has folded away, by label.
 *
 * Kept per browser rather than per account: it is a view preference about one
 * person's screen, not something worth a round trip or a column.
 */
const COLLAPSED_KEY = 'nav.collapsed';

const collapsed = ref(readCollapsed());

function readCollapsed() {
    try {
        const stored = JSON.parse(localStorage.getItem(COLLAPSED_KEY));
        return Array.isArray(stored) ? stored.filter((label) => typeof label === 'string') : [];
    } catch {
        // Private windows and blocked site data: the sidebar still folds, it
        // just forgets between visits.
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

/** The headingless first group has nothing to click, so it never folds. */
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

/**
 * Landing inside a folded section — a deep link, a reload, a quick action from
 * the dashboard — opens it, so the sidebar always shows where you are.
 *
 * Folding the section you are *already* in does not trip this: the active
 * section has not changed, so the watcher does not fire and the fold stands.
 */
watch(
    activeSection,
    (label) => {
        if (!isCollapsed(label)) return;
        collapsed.value = collapsed.value.filter((name) => name !== label);
        persistCollapsed();
    },
    { immediate: true }
);

/**
 * Whether a link owns the screen on show.
 *
 * `active-class` matches on path alone, which lit المقبوضات and المدفوعات at
 * the same time — they are one path told apart by its query. Now that the two
 * sit side by side under الصندوق, the query has to decide between them.
 */
function isActive(item) {
    const [path, search] = item.to.split('?');

    // Every screen owns its subtree, so /sales stays lit on /sales/12/edit.
    // The dashboard is the exception: '/' is a prefix of everything.
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

async function logout() {
    await auth.logout();
    router.replace({ name: 'login' });
}

onMounted(() => {
    if (document.documentElement.dir !== 'rtl') {
        document.documentElement.dir = 'rtl';
        document.documentElement.lang = 'ar';
    }
});
</script>