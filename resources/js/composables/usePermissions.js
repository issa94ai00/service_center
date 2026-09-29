import { useAuthStore } from '../stores/auth';

export function usePermissions() {
    const auth = useAuthStore();

    return {
        can: (slug) => auth.hasPermission(slug),
        roles: () => auth.roles,
    };
}