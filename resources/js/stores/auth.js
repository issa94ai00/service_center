import { defineStore } from 'pinia';
import { authApi } from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loading: false,
        initialized: false,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.user),
        permissions: (state) => {
            if (!state.user) return [];
            return state.user.roles.flatMap((role) => role.permissions.map((p) => p.slug));
        },
        roles: (state) => (state.user ? state.user.roles.map((r) => r.slug) : []),
        isMaster: (state) => (state.user ? state.user.roles.some((r) => r.slug === 'master') : false),
    },

    actions: {
        async fetchUser() {
            try {
                const { user } = await authApi.me();
                this.user = user;
            } catch {
                this.user = null;
            } finally {
                this.initialized = true;
            }
        },

        async login(credentials) {
            this.loading = true;
            try {
                const { user } = await authApi.login(credentials);
                this.user = user;
                this.initialized = true;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            try {
                await authApi.logout();
            } finally {
                this.user = null;
                this.initialized = true;
            }
        },

        /** Replace the cached user after a profile save, without a round trip. */
        setUser(user) {
            if (user) this.user = user;
        },

        hasRole(slug) {
            return this.roles.includes(slug);
        },

        hasPermission(slug) {
            if (this.roles.includes('master')) return true;
            return this.permissions.includes(slug);
        },
    },
});