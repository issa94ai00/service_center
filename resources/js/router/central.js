import { createRouter, createWebHistory } from 'vue-router';

/** repair.damatech.sy: the public site. No shop data and no sign-in here. */
export function createCentralRouter() {
    return createRouter({
        history: createWebHistory(),
        routes: [
            {
                path: '/',
                name: 'landing',
                component: () => import('../views/landing/LandingView.vue'),
            },
            {
                path: '/login',
                name: 'login',
                component: () => import('../views/central/ShopFinderView.vue'),
            },
            { path: '/:pathMatch(.*)*', redirect: '/' },
        ],
    });
}
