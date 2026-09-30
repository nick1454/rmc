import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';
import type { StockInward, StockInwardForm, StockInwardMeta } from '../types/inward';
import type { Party } from '../types/party';
import type { Item } from '../types/item';

export const useStockInwardStore = defineStore('stockInward', () => {
    // State
    const inwards = ref<StockInward[]>([]);
    const nextInwardNo = ref<string>('INW-0001');
    const suppliers = ref<Party[]>([]);
    const rmItems = ref<Item[]>([]);
    const users = ref<{ id: number; name: string }[]>([]);

    const meta = ref({
        current_page: 1,
        last_page: 1,
        per_page: 5,
        total: 0,
    });

    // Filters
    const searchQuery = ref('');
    const supplierFilter = ref<string | number>('all');
    const paymentStatusFilter = ref<string>('all');
    const fromDateFilter = ref('');
    const toDateFilter = ref('');

    // Status
    const isLoading = ref(false);
    const isSaving = ref(false);
    const error = ref<string | null>(null);
    const successMessage = ref<string | null>(null);
    const validationErrors = ref<string[]>([]);

    const clearMessages = () => {
        error.value = null;
        successMessage.value = null;
        validationErrors.value = [];
    };

    const fetchMeta = async () => {
        try {
            const res = await api.get<StockInwardMeta>('/inwards/meta');
            nextInwardNo.value = res.data.next_inward_no;
            suppliers.value = res.data.suppliers || [];
            rmItems.value = res.data.rm_items || [];
            users.value = res.data.users || [];
        } catch (err: any) {
            console.error('Failed to fetch stock inward meta:', err);
        }
    };

    const fetchInwards = async (page = 1) => {
        isLoading.value = true;
        clearMessages();

        try {
            const params: Record<string, any> = { page, per_page: meta.value.per_page };

            if (searchQuery.value) params.search = searchQuery.value;
            if (supplierFilter.value && supplierFilter.value !== 'all') params.supplier_id = supplierFilter.value;
            if (paymentStatusFilter.value && paymentStatusFilter.value !== 'all') params.payment_status = paymentStatusFilter.value;
            if (fromDateFilter.value) params.from_date = fromDateFilter.value;
            if (toDateFilter.value) params.to_date = toDateFilter.value;

            const res = await api.get('/inwards', { params });

            inwards.value = res.data.data;
            meta.value = {
                current_page: res.data.current_page,
                last_page: res.data.last_page,
                per_page: res.data.per_page,
                total: res.data.total,
            };
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch stock inward shipments.';
        } finally {
            isLoading.value = false;
        }
    };

    const createInward = async (form: StockInwardForm): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.post('/inwards', form);

            successMessage.value = res.data.message || 'Stock inward entry recorded successfully.';
            await fetchMeta();
            await fetchInwards(1);
            return true;
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                validationErrors.value = Object.values(err.response.data.errors).flat() as string[];
                error.value = err.response.data.message || 'Validation failed. Please check the inputs.';
            } else {
                error.value = err.response?.data?.message || 'Failed to save stock inward entry.';
            }
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const updateInward = async (id: number, form: StockInwardForm): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.put(`/inwards/${id}`, form);

            successMessage.value = res.data.message || 'Stock inward entry updated successfully.';
            await fetchInwards(meta.value.current_page);
            return true;
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                validationErrors.value = Object.values(err.response.data.errors).flat() as string[];
                error.value = err.response.data.message || 'Validation failed. Please check the inputs.';
            } else {
                error.value = err.response?.data?.message || 'Failed to update stock inward entry.';
            }
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const deleteInward = async (id: number): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.delete(`/inwards/${id}`);

            successMessage.value = res.data.message || 'Stock inward entry deleted successfully.';
            await fetchInwards(meta.value.current_page);
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to delete stock inward entry.';
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    return {
        inwards,
        nextInwardNo,
        suppliers,
        rmItems,
        users,
        meta,
        searchQuery,
        supplierFilter,
        paymentStatusFilter,
        fromDateFilter,
        toDateFilter,
        isLoading,
        isSaving,
        error,
        successMessage,
        validationErrors,
        clearMessages,
        fetchMeta,
        fetchInwards,
        createInward,
        updateInward,
        deleteInward,
    };
});
