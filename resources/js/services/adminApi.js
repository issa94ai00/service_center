import api from './apiClient';

/** Platform admin API (admin.<central domain>, routes/admin.php). */
export const adminAuthApi = {
    async login(payload) {
        const { data } = await api.post('/admin/login', payload);
        return data;
    },
    async logout() {
        const { data } = await api.post('/admin/logout');
        return data;
    },
    async me() {
        const { data } = await api.get('/admin/me');
        return data;
    },
    async updatePassword(payload) {
        const { data } = await api.put('/admin/password', payload);
        return data;
    },
};

export const tenantsApi = {
    async meta() {
        const { data } = await api.get('/admin/meta');
        return data;
    },
    async list(params = {}) {
        const { data } = await api.get('/admin/tenants', { params });
        return data.data;
    },
    async show(id) {
        const { data } = await api.get(`/admin/tenants/${id}`);
        return data.data;
    },
    async create(payload) {
        const { data } = await api.post('/admin/tenants', payload, { timeout: 300000 });
        return data.data;
    },
    async update(id, payload) {
        const { data } = await api.patch(`/admin/tenants/${id}`, payload);
        return data.data;
    },
    async remove(id, payload) {
        const { data } = await api.delete(`/admin/tenants/${id}`, { data: payload });
        return data;
    },
    async resetPassword(id, payload) {
        const { data } = await api.post(`/admin/tenants/${id}/reset-password`, payload);
        return data;
    },
    async issueCertificate(id) {
        const { data } = await api.post(`/admin/tenants/${id}/certificate`, null, { timeout: 300000 });
        return data.data;
    },
    async migrate(id) {
        const { data } = await api.post(`/admin/tenants/${id}/migrate`, null, { timeout: 600000 });
        return data;
    },
    async setMaintenance(id, on) {
        const { data } = await api.post(`/admin/tenants/${id}/maintenance`, { on });
        return data.data;
    },
};

/** Database backups: dumps, restores and their health. */
export const backupsApi = {
    async overview() {
        const { data } = await api.get('/admin/backups/overview');
        return data.data;
    },
    async list(params = {}) {
        const { data } = await api.get('/admin/backups', { params });
        return data;
    },
    /** One database now: `{ tenant_id }` for a shop, `{ central: true }` for the registry. */
    async create(payload) {
        const { data } = await api.post('/admin/backups', payload, { timeout: 600000 });
        return data;
    },
    async restore(id, confirmSlug) {
        const { data } = await api.post(`/admin/backups/${id}/restore`, { confirm_slug: confirmSlug }, { timeout: 1200000 });
        return data;
    },
    async retryOffsite(id) {
        const { data } = await api.post(`/admin/backups/${id}/offsite`, null, { timeout: 600000 });
        return data;
    },
    async remove(id) {
        const { data } = await api.delete(`/admin/backups/${id}`);
        return data;
    },
    /** Same-origin URL; the admin session cookie authorises the download. */
    downloadUrl(id) {
        return `/api/admin/backups/${id}/download`;
    },
};
