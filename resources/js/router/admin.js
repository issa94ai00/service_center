import { createRouter, createWebHistory } from 'vue-router';
import { usePlatformAdminStore } from '../stores/platformAdmin';

/** admin.<central domain>: tenant management. */
export function createAdminRouter() {
    const router = createRouter({
        history: createWebHistory(),
        routes: [
            {
                path: '/login',
                name: 'login',
                component: () => import('../views/platform/AdminLoginView.vue'),
                meta: { guest: true },
            },
            {
                path: '/',
                component: () => import('../views/platform/PlatformShell.vue'),
                meta: { requiresAuth: true },
                children: [
                    {
                        path: '',
                        name: 'tenants',
                        component: () => import('../views/platform/TenantsView.vue'),
                    },
                    {
                        path: 'tenants/:id',
                        name: 'tenant',
                        component: () => import('../views/platform/TenantView.vue'),
                        props: true,
                    },
                    {
                        path: 'account',
                        name: 'account',
                        component: () => import('../views/platform/AccountView.vue'),
                    },
                ],
            },
            { path: '/:pathMatch(.*)*', redirect: '/' },
        ],
    });

    router.beforeEach(async (to) => {
        const store = usePlatformAdminStore();

        if (!store.initialized) {
            await store.fetchMe();
        }

        if (to.meta.requiresAuth && !store.isAuthenticated) {
            return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} };
        }

        if (to.meta.guest && store.isAuthenticated) {
            return { name: 'tenants' };
        }
    });

    return router;
}
