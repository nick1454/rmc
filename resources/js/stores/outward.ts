import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';
import type { StockOutward, StockOutwardForm, StockOutwardMeta } from '../types/outward';
import type { Party } from '../types/party';
import type { Item } from '../types/item';

export const useStockOutwardStore = defineStore('stockOutward', () => {
    // State
    const outwards = ref<StockOutward[]>([]);
    const nextEntryNo = ref<string>('OUT-0001');
    const parties = ref<Party[]>([]);
    const customers = ref<Party[]>([]);
    const agents = ref<Party[]>([]);
    const items = ref<Item[]>([]);
    const users = ref<{ id: number; name: string }[]>([]);

    const meta = ref({
        current_page: 1,
        last_page: 1,
        per_page: 5,
        total: 0,
    });

    // Filters
    const searchQuery = ref('');
    const partyFilter = ref<string | number>('all');
    const entryTypeFilter = ref<string>('all');
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
            const res = await api.get<StockOutwardMeta>('/outwards/meta');
            nextEntryNo.value = res.data.next_entry_no;
            parties.value = res.data.parties || [];
            customers.value = res.data.customers || [];
            agents.value = res.data.agents || [];
            items.value = res.data.items || [];
            users.value = res.data.users || [];
        } catch (err: any) {
            console.error('Failed to fetch stock outward meta:', err);
        }
    };

    const fetchOutwards = async (page = 1) => {
        isLoading.value = true;
        clearMessages();

        try {
            const params: Record<string, any> = { page, per_page: meta.value.per_page };

            if (searchQuery.value) params.search = searchQuery.value;
            if (partyFilter.value && partyFilter.value !== 'all') params.party_id = partyFilter.value;
            if (entryTypeFilter.value && entryTypeFilter.value !== 'all') params.entry_type = entryTypeFilter.value;
            if (fromDateFilter.value) params.from_date = fromDateFilter.value;
            if (toDateFilter.value) params.to_date = toDateFilter.value;

            const res = await api.get('/outwards', { params });

            outwards.value = res.data.data;
            meta.value = {
                current_page: res.data.current_page,
                last_page: res.data.last_page,
                per_page: res.data.per_page,
                total: res.data.total,
            };
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch stock outward dispatches.';
        } finally {
            isLoading.value = false;
        }
    };

    const createOutward = async (form: StockOutwardForm): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.post('/outwards', form);

            successMessage.value = res.data.message || 'Stock outward entry recorded successfully.';
            await fetchMeta();
            await fetchOutwards(1);
            return true;
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                validationErrors.value = Object.values(err.response.data.errors).flat() as string[];
                error.value = err.response.data.message || 'Validation failed. Please check the inputs.';
            } else {
                error.value = err.response?.data?.message || 'Failed to save stock outward entry.';
            }
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const updateOutward = async (id: number, form: StockOutwardForm): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.put(`/outwards/${id}`, form);

            successMessage.value = res.data.message || 'Stock outward entry updated successfully.';
            await fetchOutwards(meta.value.current_page);
            return true;
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                validationErrors.value = Object.values(err.response.data.errors).flat() as string[];
                error.value = err.response.data.message || 'Validation failed. Please check the inputs.';
            } else {
                error.value = err.response?.data?.message || 'Failed to update stock outward entry.';
            }
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const deleteOutward = async (id: number): Promise<boolean> => {
        isLoading.value = true;
        clearMessages();

        try {
            const res = await api.delete(`/outwards/${id}`);
            successMessage.value = res.data.message || 'Stock outward entry deleted successfully.';
            await fetchOutwards(meta.value.current_page);
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to delete stock outward entry.';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const resetFilters = () => {
        searchQuery.value = '';
        partyFilter.value = 'all';
        entryTypeFilter.value = 'all';
        fromDateFilter.value = '';
        toDateFilter.value = '';
        fetchOutwards(1);
    };

    return {
        // State
        outwards,
        nextEntryNo,
        parties,
        customers,
        agents,
        items,
        users,
        meta,
        // Filters
        searchQuery,
        partyFilter,
        entryTypeFilter,
        fromDateFilter,
        toDateFilter,
        // Status
        isLoading,
        isSaving,
        error,
        successMessage,
        validationErrors,
        // Actions
        clearMessages,
        fetchMeta,
        fetchOutwards,
        createOutward,
        updateOutward,
        deleteOutward,
        resetFilters,
    };
});
