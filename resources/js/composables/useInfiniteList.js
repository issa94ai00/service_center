import { computed, ref, shallowRef } from 'vue';
import { apiMessage } from '../utils/format';

/**
 * A paginated API list that grows as the user scrolls, in place of a pager.
 * Pair it with <InfiniteScrollFooter>, which asks for the next page when it
 * nears the viewport.
 *
 * `fetch(page, perPage)` returns the raw API response; `select` pulls the rows
 * and meta out of it. The first page's full response stays in `response`, for
 * whatever else the endpoint sends alongside (totals, counts...).
 *
 * @param {object} options
 * @param {(page: number, perPage: number) => Promise<any>} options.fetch
 * @param {number} [options.pageSize]
 * @param {number} [options.maxPerPage] the endpoint's per_page ceiling, if any
 * @param {(response: any) => { rows: any[], meta: any }} [options.select]
 * @param {(error: any) => void} [options.onError] a failed fresh load
 */
export function useInfiniteList({
    fetch,
    pageSize = 30,
    maxPerPage = Infinity,
    select = (response) => ({ rows: response?.data ?? [], meta: response?.meta ?? null }),
    onError,
}) {
    const items = ref([]);
    const meta = ref(null);
    const response = shallowRef(null);
    const loading = ref(false);
    const loadingMore = ref(false);
    const moreError = ref('');

    /** Pages of pageSize rows on screen. */
    let loadedPages = 0;
    /** Rows dropped locally since the last load: they shift the server's page offsets. */
    let shifted = 0;
    /** Bumped on every fresh load so a late response can't land on a newer list. */
    let requestId = 0;

    const hasMore = computed(() => Boolean(meta.value && items.value.length < meta.value.total));

    /**
     * Loads the list from the top. With `keep`, it reloads every page already
     * scrolled into view, so saving or deleting a row deep in the list doesn't
     * throw the user back to the first page.
     */
    function reload({ keep = false } = {}) {
        return fetchPages(keep ? Math.max(1, loadedPages) : 1, keep);
    }

    /** Loads pages 1..pages from scratch, replacing the rows on screen. */
    async function fetchPages(pages, keep) {
        const id = ++requestId;
        loading.value = true;
        loadingMore.value = false;
        moreError.value = '';

        try {
            let first;
            let rows;

            if (pages * pageSize <= maxPerPage) {
                first = await fetch(1, pages * pageSize);
                rows = select(first).rows ?? [];
            } else {
                // Past the endpoint's ceiling: walk the pages one by one instead.
                rows = [];
                for (let page = 1; page <= pages; page += 1) {
                    const result = await fetch(page, pageSize);
                    if (id !== requestId) return;
                    first ??= result;
                    rows.push(...(select(result).rows ?? []));
                    if (page >= (select(result).meta?.last_page ?? 1)) break;
                }
            }

            if (id !== requestId) return;
            response.value = first;
            items.value = rows;
            meta.value = select(first).meta ?? null;
            loadedPages = pages;
            shifted = 0;
        } catch (e) {
            if (id !== requestId) return;
            if (!keep) items.value = [];
            onError?.(e);
        } finally {
            if (id === requestId) loading.value = false;
        }
    }

    /** Appends the next page. */
    async function loadMore() {
        if (!hasMore.value || loading.value || loadingMore.value) return;

        // Rows removed locally shifted the server's offsets; a plain next page
        // would skip as many rows, so reload everything up to and including it.
        if (shifted) {
            await fetchPages(loadedPages + 1, true);
            return;
        }

        const id = requestId;
        const next = loadedPages + 1;
        loadingMore.value = true;
        moreError.value = '';

        try {
            const result = select(await fetch(next, pageSize));
            if (id !== requestId) return;
            // A row added meanwhile pushes the rest down a slot; don't list one twice.
            const seen = new Set(items.value.map((row) => row.id));
            items.value = [...items.value, ...(result.rows ?? []).filter((row) => row.id == null || !seen.has(row.id))];
            meta.value = result.meta ?? meta.value;
            loadedPages = next;
        } catch (e) {
            if (id !== requestId) return;
            moreError.value = apiMessage(e, 'تعذّر تحميل المزيد');
        } finally {
            if (id === requestId) loadingMore.value = false;
        }
    }

    /**
     * Drops rows from the screen without a round trip — say, one that no longer
     * matches the active filter after an inline edit.
     */
    function removeLocal(predicate) {
        const before = items.value.length;
        items.value = items.value.filter((row) => !predicate(row));
        const removed = before - items.value.length;
        if (!removed) return;

        shifted += removed;
        if (meta.value?.total) meta.value = { ...meta.value, total: Math.max(0, meta.value.total - removed) };
    }

    return { items, meta, response, loading, loadingMore, moreError, hasMore, reload, loadMore, removeLocal };
}
