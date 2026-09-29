import { ref } from 'vue';

/**
 * Opens a generated PDF in a new tab.
 *
 * The blob URL is revoked on a timer rather than immediately: revoking it in the
 * same tick can race the new tab's load and leave it blank.
 */
export function usePdf() {
    const generating = ref(null);
    const pdfError = ref('');

    async function openPdf(key, loader) {
        generating.value = key;
        pdfError.value = '';

        // Opened before the await: a popup blocker only trusts a window opened
        // synchronously from the click.
        const tab = window.open('', '_blank');

        try {
            const url = await loader();

            if (tab) {
                tab.location = url;
            } else {
                window.location.assign(url);
            }

            setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch (e) {
            tab?.close();
            pdfError.value = e?.response?.status === 403
                ? 'لا تملك صلاحية طباعة هذا المستند'
                : 'تعذّر إنشاء ملف PDF';
        } finally {
            generating.value = null;
        }
    }

    return { generating, pdfError, openPdf };
}
