import api from './apiClient';

/** Public-site API (bare central domain, routes/public.php). */
export const signupApi = {
    async checkSlug(slug) {
        const { data } = await api.get('/public/slug-check', { params: { slug } });
        return data;
    },
    async register(payload) {
        // Provisioning a shop (database, schema, certificate) can take a minute.
        const { data } = await api.post('/public/register', payload, { timeout: 300000 });
        return data.data;
    },
};
