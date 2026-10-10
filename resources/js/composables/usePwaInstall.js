import { readonly, ref } from 'vue';
import { useToast } from './useToast';

/**
 * Installing the app to the home screen / desktop.
 *
 * Chrome, Edge and Android fire `beforeinstallprompt` once, often before the dashboard
 * mounts, so it is caught here at app start (see app.js) and replayed by the button.
 * Safari on iPhone/iPad and Firefox never fire it, and Chrome holds it back once the app
 * is installed or the prompt was recently dismissed: there the button explains the manual
 * steps instead (Firefox on a computer cannot install sites, so it points to Chrome/Edge).
 */

const deferredPrompt = ref(null);
const installed = ref(false);

function isStandalone() {
    return window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

const ua = navigator.userAgent;
const isIos = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isFirefox = /firefox|fxios/i.test(ua);
const isAndroid = /android/i.test(ua);

/** How to install by hand when the browser offers no prompt. */
function manualSteps() {
    if (isIos) return 'اضغط زر المشاركة ⎋ في Safari ثم اختر «إضافة إلى الشاشة الرئيسية».';
    if (isFirefox && isAndroid) return 'افتح قائمة Firefox ⋮ ثم اختر «تثبيت» أو «إضافة إلى الشاشة الرئيسية».';
    if (isFirefox) return 'متصفح Firefox على الكمبيوتر لا يدعم تثبيت التطبيقات. افتح النظام في Chrome أو Edge ثم اضغط «تثبيت التطبيق».';
    if (isAndroid) return 'إن كان التطبيق مثبتًا فافتحه من الشاشة الرئيسية. وإلا فافتح قائمة المتصفح ⋮ واختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية».';
    return 'إن كان التطبيق مثبتًا فافتحه من قائمة التطبيقات أو من زر «فتح في التطبيق» في شريط العنوان. وإلا فاضغط أيقونة التثبيت في شريط العنوان، أو من قائمة المتصفح ⋮ اختر «حفظ ومشاركة» ثم «تثبيت الصفحة كتطبيق».';
}

export function initPwa() {
    installed.value = isStandalone();

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt.value = event;
    });

    window.addEventListener('appinstalled', () => {
        installed.value = true;
        deferredPrompt.value = null;
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
}

/** Shows the browser's install dialog; true when the user accepted. */
async function install() {
    const event = deferredPrompt.value;
    if (!event) return false;
    deferredPrompt.value = null; // a prompt can be shown only once
    event.prompt();
    const { outcome } = await event.userChoice;
    if (outcome === 'accepted') installed.value = true;
    return outcome === 'accepted';
}

/** What the install buttons do: the browser's dialog when offered, otherwise the manual steps. */
async function installOrExplain() {
    const toast = useToast();

    if (deferredPrompt.value) {
        if (await install()) toast.success('تم تثبيت التطبيق على هذا الجهاز.');
        return;
    }
    toast.info(manualSteps(), { title: 'تثبيت التطبيق', timeout: 10000 });
}

export function usePwaInstall() {
    return {
        installed: readonly(installed),
        install,
        installOrExplain,
    };
}
