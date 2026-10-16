import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        // A 401 on the "who am I" probe just means a guest is browsing (e.g. the
        // public home page); the router guard decides whether they need to sign in.
        const isSessionProbe = ['/user', '/admin/me'].includes(error.config?.url);
        if (error.response?.status === 401 && !isSessionProbe) {
            const inLogin = window.location.pathname === '/login';
            if (!inLogin) {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;