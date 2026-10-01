import { ref } from 'vue';
import { confirm } from './useConfirm';
import { useToast } from './useToast';
import { apiMessage } from '../utils/format';

/**
 * Archive, restore and permanent delete for a catalogue list (materials,
 * services).
 *
 * Archiving is the everyday "delete": reversible, so it asks lightly and offers
 * an undo straight from the toast. Permanent deletion is rare and final, so it
 * gets the red dialog.
 *
 * @param {object} options
 * @param {{ destroy: Function, restore: Function, forceDelete: Function }} options.api
 * @param {string} options.noun      "المادة" / "الخدمة"
 * @param {Function} options.onChange called after any change, to refetch
 */
export function useCatalogArchive({ api, noun, onChange }) {
    const toast = useToast();
    const busyId = ref(null);

    async function run(row, action) {
        busyId.value = row.id;
        try {
            await action();
            onChange?.();
            return true;
        } catch (e) {
            toast.error(apiMessage(e, 'تعذّر تنفيذ العملية'));
            return false;
        } finally {
            busyId.value = null;
        }
    }

    async function archive(row, { detail = '' } = {}) {
        const ok = await confirm({
            title: `أرشفة «${row.name}»؟`,
            message: `ستختفي ${noun} من القوائم ومن الفواتير الجديدة، وتبقى ظاهرة في الفواتير السابقة والتقارير. يمكنك استعادتها من الأرشيف في أي وقت.`,
            detail,
            confirmLabel: 'أرشفة',
            tone: 'primary',
        });
        if (!ok) return;

        const done = await run(row, () => api.destroy(row.id));
        if (!done) return;

        toast.success(`أُرشفت «${row.name}»`, {
            timeout: 8000,
            action: { label: 'تراجع', onClick: () => restore(row, { quiet: true }) },
        });
    }

    async function restore(row, { quiet = false } = {}) {
        const done = await run(row, () => api.restore(row.id));
        if (done && !quiet) toast.success(`استُعيدت «${row.name}» إلى القائمة`);
        if (done && quiet) toast.info(`أُعيدت «${row.name}»`);
    }

    async function forceDelete(row) {
        const ok = await confirm({
            title: `حذف «${row.name}» نهائيًا؟`,
            message: `لم تُستخدم ${noun} في أي فاتورة أو حركة مخزنية، فيمكن حذفها من النظام تمامًا. لا يمكن التراجع عن ذلك.`,
            confirmLabel: 'حذف نهائي',
            tone: 'danger',
        });
        if (!ok) return;

        const done = await run(row, () => api.forceDelete(row.id));
        if (done) toast.success(`حُذفت «${row.name}» نهائيًا`);
    }

    return { busyId, archive, restore, forceDelete };
}
