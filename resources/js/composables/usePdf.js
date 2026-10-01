import { ref } from 'vue';
import { usePrintPreferences } from './usePrintPreferences';

// One hidden frame, reused: a frame per print would pile up PDFs in memory
// while the print dialog sits open.
let printFrame = null;

/**
 * Sends a PDF straight to the browser's print dialog through a hidden frame.
 * Resolves false when the browser will not print a PDF from a frame (some
 * mobile browsers), so the caller can fall back to opening it.
 */
function printInFrame(url) {
    return new Promise((resolve) => {
        printFrame?.remove();

        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        printFrame = frame;

        const timeout = setTimeout(() => resolve(false), 15000);

        frame.onload = () => {
            clearTimeout(timeout);
            // The PDF viewer inside the frame needs a beat after load before
            // it will accept print(); without it Chrome prints a blank page.
            setTimeout(() => {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                    resolve(true);
                } catch {
                    resolve(false);
                }
            }, 250);
        };

        frame.src = url;
        document.body.appendChild(frame);
    });
}

/** The server's message out of an error whose body was requested as a blob. */
async function errorMessage(e) {
    const status = e?.response?.status;

    if (status === 403) return 'لا تملك صلاحية طباعة هذا المستند';
    if (status === 429) return 'طلبات طباعة كثيرة متتالية — انتظر دقيقة ثم أعد المحاولة';

    try {
        const body = JSON.parse(await e.response.data.text());
        const first = body?.errors && Object.values(body.errors)[0]?.[0];
        if (first || body?.message) return first || body.message;
    } catch {
        // Not JSON: fall through to the generic message.
    }

    return 'تعذّر إنشاء ملف PDF';
}

/**
 * Opens a generated PDF — in a new tab to preview, or straight into the print
 * dialog, per this device's print preference.
 *
 * The blob URL is revoked on a timer rather than immediately: revoking it in the
 * same tick can race the new tab's load and leave it blank.
 */
export function usePdf() {
    const generating = ref(null);
    const pdfError = ref('');
    const prefs = usePrintPreferences();

    async function openPdf(key, loader, { mode } = {}) {
        generating.value = key;
        pdfError.value = '';

        const direct = (mode || prefs.mode.value) === 'direct';

        // Opened before the await: a popup blocker only trusts a window opened
        // synchronously from the click.
        const tab = direct ? null : window.open('', '_blank');

        try {
            const url = await loader();

            if (direct) {
                if (!(await printInFrame(url))) {
                    // Cannot print from a frame here; show it instead.
                    if (!window.open(url, '_blank')) {
                        pdfError.value = 'تعذّرت الطباعة المباشرة — اسمح بالنوافذ المنبثقة أو اختر المعاينة';
                    }
                }
            } else if (tab) {
                tab.location = url;
            } else {
                window.location.assign(url);
            }

            setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch (e) {
            tab?.close();
            pdfError.value = await errorMessage(e);
        } finally {
            generating.value = null;
        }
    }

    return { generating, pdfError, openPdf };
}
