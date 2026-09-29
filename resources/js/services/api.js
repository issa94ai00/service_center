import api from './apiClient';

export const authApi = {
    async login(payload) {
        await api.get('/sanctum/csrf-cookie');
        const { data } = await api.post('/login', payload);
        return data;
    },

    async logout() {
        const { data } = await api.post('/logout');
        return data;
    },

    async me() {
        const { data } = await api.get('/user');
        return data;
    },
};

export const settingsApi = {
    async index() {
        const { data } = await api.get('/settings');
        return data.data ?? [];
    },

    async update(values) {
        const { data } = await api.put('/settings', { values });
        return data;
    },
};

export const usersApi = {
    async index(params = {}) {
        const { data } = await api.get('/users', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/users', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/users/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/users/${id}`);
        return data;
    },
};

export const rolesApi = {
    async index() {
        const { data } = await api.get('/roles');
        return data;
    },
};

export const customersApi = {
    async index(params = {}) {
        const { data } = await api.get('/customers', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/customers', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/customers/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/customers/${id}`);
        return data;
    },
};

export const suppliersApi = {
    async index(params = {}) {
        const { data } = await api.get('/suppliers', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/suppliers', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/suppliers/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/suppliers/${id}`);
        return data;
    },
};

export const techniciansApi = {
    async index(params = {}) {
        const { data } = await api.get('/technicians', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/technicians', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/technicians/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/technicians/${id}`);
        return data;
    },

    /** قائمة مختصرة للاختيار، تحمل النسبة الافتراضية لكل خبير. */
    async options(params = {}) {
        const { data } = await api.get('/technicians/options', { params });
        return data.data ?? [];
    },
};

export const serviceUnitsApi = {
    async index(params = {}) {
        const { data } = await api.get('/service-units', { params });
        return data;
    },

    async options() {
        const { data } = await api.get('/service-units/options');
        return data.data ?? data;
    },

    async store(payload) {
        const { data } = await api.post('/service-units', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/service-units/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/service-units/${id}`);
        return data;
    },
};

export const servicesApi = {
    async index(params = {}) {
        const { data } = await api.get('/services', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/services', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/services/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/services/${id}`);
        return data;
    },
};

export const materialCategoriesApi = {
    async index(params = {}) {
        const { data } = await api.get('/material-categories', { params });
        return data;
    },

    async options() {
        const { data } = await api.get('/material-categories/options');
        return data.data ?? data;
    },

    async store(payload) {
        const { data } = await api.post('/material-categories', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/material-categories/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/material-categories/${id}`);
        return data;
    },
};

export const materialsApi = {
    async index(params = {}) {
        const { data } = await api.get('/materials', { params });
        return data;
    },

    /**
     * مطابقة تامّة لرمز ممسوح. تُرجع null عند عدم العثور بدل أن ترمي، فشاشة
     * البيع تتعامل مع «رمز غير معروف» كحالة عادية لا كعطل.
     */
    async lookup(code) {
        try {
            const { data } = await api.get('/materials/lookup', { params: { code } });
            return data.data ?? null;
        } catch (e) {
            if (e?.response?.status === 404) return null;
            throw e;
        }
    },

    async store(payload) {
        const { data } = await api.post('/materials', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/materials/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/materials/${id}`);
        return data;
    },
};

export const purchasesApi = {
    async index(params = {}) {
        const { data } = await api.get('/purchases', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/purchases', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/purchases/${id}`, payload);
        return data;
    },

    async show(id) {
        const { data } = await api.get(`/purchases/${id}`);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/purchases/${id}`);
        return data;
    },

    async post(id) {
        const { data } = await api.post(`/purchases/${id}/post`);
        return data;
    },

    async cancel(id, payload) {
        const { data } = await api.post(`/purchases/${id}/cancel`, payload);
        return data;
    },

    /**
     * The supplier picker's search, guarded by `purchases.create`. Same shape
     * as the customer pickers so PartySelect can drive any of them.
     */
    async supplierOptions(params = {}) {
        const { data } = await api.get('/purchases/supplier-options', { params });
        return {
            items: data.data ?? [],
            meta: data.meta ?? { total: 0, returned: 0, truncated: false },
        };
    },

    /**
     * أفضل سعر مورد لكل مادة من فواتير الشراء المُرحَّلة، مع آخر سعر من
     * المورد المختار إن أُرسل.
     */
    async priceSuggestions({ materialIds, supplierId } = {}) {
        const { data } = await api.get('/purchases/price-suggestions', {
            params: { material_ids: materialIds, supplier_id: supplierId || undefined },
        });
        return data.data ?? [];
    },
};

export const salesApi = {
    async index(params = {}) {
        const { data } = await api.get('/sales', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/sales', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/sales/${id}`, payload);
        return data;
    },

    async show(id) {
        const { data } = await api.get(`/sales/${id}`);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/sales/${id}`);
        return data;
    },

    /**
     * الترحيل، ومعه `collect_amount` و`payment_method` حين يدفع العميل عند
     * الصندوق: سند القبض يُنشأ ويُعتمد مع الفاتورة في معاملة واحدة.
     */
    async post(id, payload = {}) {
        const { data } = await api.post(`/sales/${id}/post`, payload);
        return data;
    },

    async cancel(id, payload) {
        const { data } = await api.post(`/sales/${id}/cancel`, payload);
        return data;
    },

    /**
     * The customer picker's search, guarded by `sales.create`. Same shape as
     * the repairs picker so CustomerSelect can drive either.
     */
    async customerOptions(params = {}) {
        const { data } = await api.get('/sales/customer-options', { params });
        return {
            items: data.data ?? [],
            meta: data.meta ?? { total: 0, returned: 0, truncated: false },
        };
    },
};

export const paymentsApi = {
    async index(params = {}) {
        const { data } = await api.get('/payments', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/payments', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/payments/${id}`, payload);
        return data;
    },

    async show(id) {
        const { data } = await api.get(`/payments/${id}`);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/payments/${id}`);
        return data;
    },

    async post(id) {
        const { data } = await api.post(`/payments/${id}/post`);
        return data;
    },

    async cancel(id, payload) {
        const { data } = await api.post(`/payments/${id}/cancel`, payload);
        return data;
    },

    async openInvoices(params) {
        const { data } = await api.get('/payments/open-invoices', { params });
        return data.data ?? data;
    },
};

export const expenseCategoriesApi = {
    /** أبواب الصرف، الأكثر استعمالاً أولاً — بهذا تُرتَّب أزرار الشاشة. */
    async index(params = {}) {
        const { data } = await api.get('/expense-categories', { params });
        return data.data ?? [];
    },

    async store(payload) {
        const { data } = await api.post('/expense-categories', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/expense-categories/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/expense-categories/${id}`);
        return data;
    },
};

export const expensesApi = {
    /**
     * القائمة ومعها مجاميعها: مصروف اليوم والشهر، ومجموع ما رشّحه المستخدم
     * موزّعاً على الأبواب. تأتي من الخادم بالفلتر نفسه، فلا تختلف عمّا يُعرض.
     */
    async index(params = {}) {
        const { data } = await api.get('/expenses', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/expenses', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/expenses/${id}`, payload);
        return data;
    },

    async show(id) {
        const { data } = await api.get(`/expenses/${id}`);
        return data;
    },

    async cancel(id, payload) {
        const { data } = await api.post(`/expenses/${id}/cancel`, payload);
        return data;
    },
};

export const repairsApi = {
    async index(params = {}) {
        const { data } = await api.get('/repairs', { params });
        return data;
    },

    async store(payload) {
        const { data } = await api.post('/repairs', payload);
        return data;
    },

    async update(id, payload) {
        const { data } = await api.put(`/repairs/${id}`, payload);
        return data;
    },

    async destroy(id) {
        const { data } = await api.delete(`/repairs/${id}`);
        return data;
    },

    /**
     * The customer picker's search. Returns a capped slice plus the counts the
     * UI needs to say whether more customers exist behind the ones shown.
     */
    async customerOptions(params = {}) {
        const { data } = await api.get('/repairs/customer-options', { params });
        return {
            items: data.data ?? [],
            meta: data.meta ?? { total: 0, returned: 0, truncated: false },
        };
    },

    /**
     * Where this customer's account stands before a deposit is agreed: what
     * they already owe, and what is unpaid on the devices still on the bench.
     */
    async customerAccount(customerId) {
        const { data } = await api.get('/repairs/customer-account', {
            params: { customer_id: customerId },
        });
        return data.data ?? data;
    },

    /** The money picture behind one repair: cost, deposits, invoice, balance. */
    async settlement(id) {
        const { data } = await api.get(`/repairs/${id}/settlement`);
        return data.data ?? data;
    },

    /** Take money against a repair — a deposit, or the balance on hand-over. */
    async deposit(id, payload) {
        const { data } = await api.post(`/repairs/${id}/deposit`, payload);
        return data;
    },

    /** Invoice it, spend the deposit on it, collect the rest, hand it over. */
    async settle(id, payload) {
        const { data } = await api.post(`/repairs/${id}/settle`, payload);
        return data;
    },

    async brandOptions() {
        const { data } = await api.get('/repairs/brand-options');
        return data.data ?? data;
    },
};

export const statementsApi = {
    async customer(id, params = {}) {
        const { data } = await api.get(`/customers/${id}/statement`, { params });
        return { statement: data.data, meta: data.meta };
    },

    async supplier(id, params = {}) {
        const { data } = await api.get(`/suppliers/${id}/statement`, { params });
        return { statement: data.data, meta: data.meta };
    },

    async technician(id, params = {}) {
        const { data } = await api.get(`/technicians/${id}/statement`, { params });
        return { statement: data.data, meta: data.meta };
    },
};

/**
 * Printable documents.
 *
 * Fetched as a blob rather than opened as a plain link so a failure surfaces in
 * the SPA (a permission error would otherwise replace the tab with Laravel's
 * HTML error page) and the session's XSRF handling stays consistent.
 */
async function fetchPdf(url, params = {}) {
    const { data } = await api.get(url, { params, responseType: 'blob' });
    return URL.createObjectURL(data);
}

export const documentsApi = {
    salesInvoice: (id) => fetchPdf(`/pdf/sales/${id}`),
    purchaseInvoice: (id) => fetchPdf(`/pdf/purchases/${id}`),
    paymentReceipt: (id) => fetchPdf(`/pdf/payments/${id}`),
    repairReceipt: (id) => fetchPdf(`/pdf/repairs/${id}`),
    customerStatement: (id, params) => fetchPdf(`/pdf/customers/${id}/statement`, params),
    supplierStatement: (id, params) => fetchPdf(`/pdf/suppliers/${id}/statement`, params),
};

export const inventoryApi = {
    async index(params = {}) {
        const { data } = await api.get('/inventory', { params });
        return data;
    },

    async material(id, params = {}) {
        const { data } = await api.get(`/inventory/materials/${id}`, { params });
        return { ...data.data, meta: data.meta };
    },

    async adjustments(params = {}) {
        const { data } = await api.get('/inventory/adjustments', { params });
        return data;
    },

    async storeAdjustment(payload) {
        const { data } = await api.post('/inventory/adjustments', payload);
        return data;
    },

    async cancelAdjustment(id, reason) {
        const { data } = await api.post(`/inventory/adjustments/${id}/cancel`, { reason });
        return data;
    },
};

export const dashboardApi = {
    async index(params = {}) {
        const { data } = await api.get('/dashboard', { params });
        return data.data;
    },
};

export const reportsApi = {
    async lowStock(params = {}) {
        const { data } = await api.get('/reports/low-stock', { params });
        return data.data ?? [];
    },

    async salesSummary(params = {}) {
        const { data } = await api.get('/reports/sales-summary', { params });
        return data.data;
    },

    async purchasesSummary(params = {}) {
        const { data } = await api.get('/reports/purchases-summary', { params });
        return data.data;
    },

    async receivables(params = {}) {
        const { data } = await api.get('/reports/receivables', { params });
        return { ...data.data, meta: data.meta };
    },

    async technicianSummary(params = {}) {
        const { data } = await api.get('/reports/technician-summary', { params });
        return { ...data.data, meta: data.meta };
    },

    async technicianRepairs(params = {}) {
        const { data } = await api.get('/reports/technician-repairs', { params });
        return { ...data.data, meta: data.meta };
    },

    async payables(params = {}) {
        const { data } = await api.get('/reports/payables', { params });
        return { ...data.data, meta: data.meta };
    },

    async stockMovements(params = {}) {
        const { data } = await api.get('/reports/stock-movements', { params });
        return { ...data.data, meta: data.meta };
    },
};