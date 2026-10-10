import { useAuthStore } from '../stores/auth';
import { useFeaturesStore } from '../stores/features';

/**
 * Modules that can be switched off in Settings (feature_<module>). A permission belongs
 * to the module named by its prefix, so `repairs.settle` or `technicians.create` is
 * hidden along with its module without listing every slug here.
 */
const FEATURE_MODULES = new Set(['repairs', 'technicians', 'services', 'purchases', 'expenses', 'reports', 'inventory', 'sales']);

function featureOf(slug) {
    const module = slug.split('.')[0];
    return FEATURE_MODULES.has(module) ? module : null;
}

export function usePermissions() {
    const auth = useAuthStore();
    const features = useFeaturesStore();

    return {
        can: (slug) => {
            if (!auth.hasPermission(slug)) return false;
            if (auth.isMaster) return true;
            const feat = featureOf(slug);
            if (feat && !features.isEnabled(feat)) {
                return false;
            }
            return true;
        },
        roles: () => auth.roles,
    };
}