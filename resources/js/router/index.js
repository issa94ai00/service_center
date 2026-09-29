import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
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
                path: '',
                name: 'dashboard',
                component: () => import('../views/dashboard/DashboardView.vue'),
            },
            {
                path: 'customers',
                name: 'customers',
                component: () => import('../views/customers/CustomersListView.vue'),
            },
            {
                path: 'suppliers',
                name: 'suppliers',
                component: () => import('../views/suppliers/SuppliersListView.vue'),
            },
            {
                path: 'repairs',
                name: 'repairs',
                component: () => import('../views/repairs/RepairsListView.vue'),
            },
            {
                path: 'technicians',
                name: 'technicians',
                component: () => import('../views/technicians/TechniciansListView.vue'),
            },
            {
                path: 'services',
                name: 'services',
                component: () => import('../views/services/ServicesListView.vue'),
            },
            {
                path: 'service-units',
                name: 'service-units',
                component: () => import('../views/services/ServiceUnitsListView.vue'),
            },
            {
                path: 'materials',
                name: 'materials',
                component: () => import('../views/materials/MaterialsListView.vue'),
            },
            {
                path: 'material-categories',
                name: 'material-categories',
                component: () => import('../views/materials/MaterialCategoriesListView.vue'),
            },
            {
                path: 'inventory',
                name: 'inventory',
                component: () => import('../views/inventory/InventoryView.vue'),
            },
            {
                path: 'purchases',
                name: 'purchases',
                component: () => import('../views/purchases/PurchasesListView.vue'),
            },
            {
                path: 'purchases/create',
                name: 'purchases.create',
                component: () => import('../views/purchases/PurchaseFormView.vue'),
            },
            {
                path: 'purchases/:id/edit',
                name: 'purchases.edit',
                component: () => import('../views/purchases/PurchaseFormView.vue'),
            },
            {
                path: 'sales',
                name: 'sales',
                component: () => import('../views/sales/SalesListView.vue'),
            },
            {
                path: 'sales/create',
                name: 'sales.create',
                component: () => import('../views/sales/SalesFormView.vue'),
            },
            {
                path: 'sales/:id/edit',
                name: 'sales.edit',
                component: () => import('../views/sales/SalesFormView.vue'),
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
                meta: { permission: 'expenses.view' },
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
            },
            {
                path: 'settings',
                name: 'settings',
                component: () => import('../views/settings/SettingsView.vue'),
                meta: { permission: 'settings.manage' },
            },
            {
                path: 'reports',
                name: 'reports',
                component: () => import('../views/reports/ReportsListView.vue'),
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('../views/errors/NotFoundView.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.initialized) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    // Routes may declare a required permission; without this a user lands on a
    // screen whose every request comes back 403.
    if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
        return { name: 'dashboard' };
    }
});

export default router;