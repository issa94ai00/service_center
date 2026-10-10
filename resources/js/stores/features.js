import { defineStore } from 'pinia';
import { settingsApi } from '../services/api';

export const useFeaturesStore = defineStore('features', {
    state: () => ({
        features: {
            feature_repairs: true,
            feature_technicians: true,
            feature_purchases: true,
            feature_expenses: true,
            feature_reports: true,
            feature_services: true,
            feature_inventory: true,
            feature_sales: true,
        },
        loaded: false,
        loading: false,
    }),

    getters: {
        allFeatures: (state) => state.features,
    },

    actions: {
        async fetchFeatures() {
            if (this.loading) return;
            this.loading = true;
            try {
                const data = await settingsApi.features();
                this.features = { ...this.features, ...data };
                this.loaded = true;
            } catch {
                // Keep defaults if failed
            } finally {
                this.loading = false;
            }
        },

        setFeatures(featuresMap) {
            this.features = { ...this.features, ...featuresMap };
        },

        isEnabled(featureKey) {
            if (!featureKey) return true;
            const key = featureKey.startsWith('feature_') ? featureKey : `feature_${featureKey}`;
            return this.features[key] !== false;
        },
    },
});
