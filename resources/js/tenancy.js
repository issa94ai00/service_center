/**
 * Which part of the platform this page is serving, injected by app.blade.php
 * from App\Tenancy\Tenancy::frontendContext().
 *
 *   single  — tenancy off (local development): the whole app on one host
 *   central — repair.damatech.sy: landing page and shop finder
 *   admin   — admin.repair.damatech.sy: tenant management
 *   tenant  — <shop>.repair.damatech.sy: one shop's system
 */
export const tenancy = Object.freeze({
    mode: 'single',
    centralDomain: '',
    tenant: null,
    ...(window.__TENANCY__ || {}),
});

export function shopUrl(slug, path = '/login') {
    return `https://${slug}.${tenancy.centralDomain}${path}`;
}
