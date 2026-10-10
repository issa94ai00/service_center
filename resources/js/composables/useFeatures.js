import { useFeaturesStore } from '../stores/features';

export function useFeatures() {
    const featuresStore = useFeaturesStore();

    return {
        features: featuresStore.features,
        isEnabled: (key) => featuresStore.isEnabled(key),
        fetchFeatures: () => featuresStore.fetchFeatures(),
        setFeatures: (map) => featuresStore.setFeatures(map),
    };
}
