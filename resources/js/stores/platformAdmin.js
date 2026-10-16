import { defineStore } from 'pinia';
import { adminAuthApi } from '../services/adminApi';

/** The signed-in platform admin on admin.<central domain>. */
export const usePlatformAdminStore = defineStore('platformAdmin', {
    state: () => ({
        admin: null,
        initialized: false,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.admin),
    },

    actions: {
        async fetchMe() {
            try {
                const { admin } = await adminAuthApi.me();
                this.admin = admin;
            } catch {
                this.admin = null;
            } finally {
                this.initialized = true;
            }
        },

        async login(credentials) {
            const { admin } = await adminAuthApi.login(credentials);
            this.admin = admin;
            this.initialized = true;
        },

        async logout() {
            try {
                await adminAuthApi.logout();
            } finally {
                this.admin = null;
            }
        },
    },
});
