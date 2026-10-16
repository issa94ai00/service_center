import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useFeaturesStore } from '../stores/features';
import { tenancy } from '../tenancy';
import { createAdminRouter } from './admin';
import { createCentralRouter } from './central';

// The landing page belongs to the public site; a shop's own host opens on its
// dashboard (or sign-in). Without tenancy (local development) both live here.
const entryRoutes = tenancy.mode === 'tenant'
    ? [{ path: '/', redirect: { name: 'dashboard' } }]
    : [
        {
            path: '/',
            name: 'landing',
            component: () => import('../views/landing/LandingView.vue'),
            meta: { guest: true },
        },
        { path: '/landing', redirect: '/' },
    ];

const routes = [
    ...entryRoutes,
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: () => import('../layouts/AdminLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('../views/dashboard/DashboardView.vue'),
            },
            {
                path: 'customers',
                name: 'customers',
                component: () => import('../views/customers/CustomersListView.vue'),
                meta: { permission: 'customers.view' },
            },
            {
                path: 'suppliers',
                name: 'suppliers',
                component: () => import('../views/suppliers/SuppliersListView.vue'),
                meta: { permission: 'suppliers.view' },
            },
            {
                path: 'repairs',
                name: 'repairs',
                component: () => import('../views/repairs/RepairsListView.vue'),
                meta: { feature: 'repairs', permission: 'repairs.view' },
            },
            {
                path: 'technicians',
                name: 'technicians',
                component: () => import('../views/technicians/TechniciansListView.vue'),
                meta: { feature: 'technicians', permission: 'technicians.view' },
            },
            {
                path: 'services',
                name: 'services',
                component: () => import('../views/services/ServicesListView.vue'),
                meta: { feature: 'services', permission: 'services.view' },
            },
            {
                path: 'service-units',
                name: 'service-units',
                component: () => import('../views/services/ServiceUnitsListView.vue'),
                meta: { feature: 'services', permission: 'services.view' },
            },
            {
                path: 'materials',
                name: 'materials',
                component: () => import('../views/materials/MaterialsListView.vue'),
                meta: { permission: 'materials.view' },
            },
            {
                path: 'material-categories',
                name: 'material-categories',
                component: () => import('../views/materials/MaterialCategoriesListView.vue'),
                meta: { permission: 'materials.view' },
            },
            {
                path: 'inventory',
                name: 'inventory',
                component: () => import('../views/inventory/InventoryView.vue'),
                meta: { feature: 'inventory', permission: 'inventory.view' },
            },
            {
                path: 'purchases',
                name: 'purchases',
                component: () => import('../views/purchases/PurchasesListView.vue'),
                meta: { feature: 'purchases', permission: 'purchases.view' },
            },
            {
                path: 'purchases/create',
                name: 'purchases.create',
                component: () => import('../views/purchases/PurchaseFormView.vue'),
                meta: { feature: 'purchases', permission: 'purchases.create' },
            },
            {
                path: 'purchases/:id/edit',
                name: 'purchases.edit',
                component: () => import('../views/purchases/PurchaseFormView.vue'),
                meta: { feature: 'purchases', permission: 'purchases.create' },
            },
            {
                path: 'sales',
                name: 'sales',
                component: () => import('../views/sales/SalesListView.vue'),
                meta: { feature: 'sales', permission: 'sales.view' },
            },
            {
                path: 'sales/create',
                name: 'sales.create',
                component: () => import('../views/sales/SalesFormView.vue'),
                meta: { feature: 'sales', permission: 'sales.create' },
            },
            {
                path: 'sales/:id/edit',
                name: 'sales.edit',
                component: () => import('../views/sales/SalesFormView.vue'),
                meta: { feature: 'sales', permission: 'sales.create' },
            },
            {
                path: 'payments',
                name: 'payments',
                component: () => import('../views/payments/PaymentsListView.vue'),
            },
            {
                path: 'payments/create',
                name: 'payments.create',
                component: () => import('../views/payments/PaymentFormView.vue'),
            },
            {
                path: 'payments/:id/edit',
                name: 'payments.edit',
                component: () => import('../views/payments/PaymentFormView.vue'),
            },
            {
                path: 'expenses',
                name: 'expenses',
                component: () => import('../views/expenses/ExpensesListView.vue'),
                meta: { feature: 'expenses', permission: 'expenses.view' },
            },
            {
                path: 'customers/:id/statement',
                name: 'customers.statement',
                component: () => import('../views/statements/LedgerStatementView.vue'),
            },
            {
                path: 'suppliers/:id/statement',
                name: 'suppliers.statement',
                component: () => import('../views/statements/LedgerStatementView.vue'),
            },
            {
                path: 'technicians/:id/statement',
                name: 'technicians.statement',
                component: () => import('../views/statements/LedgerStatementView.vue'),
                meta: { feature: 'technicians' },
            },
            {
                path: 'profile',
                name: 'profile',
                component: () => import('../views/profile/ProfileView.vue'),
                meta: { title: 'الملف الشخصي' },
            },
            {
                path: 'roles',
                name: 'roles',
                component: () => import('../views/roles/RolesListView.vue'),
                meta: {
                    title: 'إدارة الأدوار والصلاحيات',
                    permission: 'roles.manage',
                },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('../views/users/UsersListView.vue'),
                meta: {
                    title: 'المستخدمون',
                    permission: 'users.manage',
                },
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('../views/settings/SettingsView.vue'),
                meta: {
                    check: (auth) => auth.hasPermission('settings.manage') || auth.hasPermission('features.manage'),
                },
            },
            {
                path: 'currencies',
                name: 'currencies',
                component: () => import('../views/settings/CurrenciesView.vue'),
                meta: {
                    title: 'العملات وأسعار الصرف',
                    permission: 'currencies.manage',
                },
            },
            {
                path: 'reports',
                name: 'reports',
                component: () => import('../views/reports/ReportsListView.vue'),
                meta: { feature: 'reports', permission: 'reports.view' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../views/errors/NotFoundView.vue'),
    },
];

function createShopRouter() {
    const router = createRouter({
        history: createWebHistory(),
        routes,
    });

    router.beforeEach(async (to) => {
        const auth = useAuthStore();
        const features = useFeaturesStore();

        if (!auth.initialized) {
            await auth.fetchUser();
        }

        if (to.meta.requiresAuth && !auth.isAuthenticated) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        if (to.meta.guest && auth.isAuthenticated) {
            return { name: 'dashboard' };
        }

        if (!features.loaded && auth.isAuthenticated) {
            await features.fetchFeatures();
        }

        // Feature toggle guard (master user bypasses)
        if (to.meta.feature && !auth.isMaster && !features.isEnabled(to.meta.feature)) {
            return { name: 'dashboard' };
        }

        if (to.meta.check && !to.meta.check(auth)) {
            return { name: 'dashboard' };
        }

        // Routes may declare a required permission; without this a user lands on a
        // screen whose every request comes back 403.
        if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
            return { name: 'dashboard' };
        }
    });

    return router;
}

const router = tenancy.mode === 'admin'
    ? createAdminRouter()
    : tenancy.mode === 'central'
        ? createCentralRouter()
        : createShopRouter();

export default router;
