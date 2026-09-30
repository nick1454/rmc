import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';
import type {
    OutwardPayment,
    OutwardPaymentForm,
    PendingOutwardInvoice,
    PendingOutwardSale,
} from '../types/outwardPayment';
import type { Party } from '../types/party';

export const useOutwardPaymentStore = defineStore('outwardPayment', () => {
    // State
    const payments = ref<OutwardPayment[]>([]);
    const nextPaymentNo = ref<string>('REC-0001');
    const customers = ref<Party[]>([]);
    const agents = ref<Party[]>([]);
    const paymentModes = ref<{ value: string; label: string }[]>([
        { value: 'bank_transfer', label: 'Bank Transfer (NEFT/RTGS/IMPS)' },
        { value: 'cheque', label: 'Cheque / Demand Draft' },
        { value: 'upi', label: 'UPI / NetBanking' },
        { value: 'cash', label: 'Cash' },
        { value: 'other', label: 'Other / Adjustment' },
    ]);
    const pendingInvoices = ref<PendingOutwardInvoice[]>([]);
    const allPendingOutwards = ref<PendingOutwardSale[]>([]);
    const totalPendingCount = ref(0);
    const totalPendingAmount = ref(0);

    const meta = ref({
        current_page: 1,
        last_page: 1,
        per_page: 5,
        total: 0,
    });

    // Filters
    const searchQuery = ref('');
    const customerFilter = ref<string | number>('all');
    const modeFilter = ref<string>('all');
    const fromDateFilter = ref('');
    const toDateFilter = ref('');

    // Status
    const isLoading = ref(false);
    const isSaving = ref(false);
    const isLoadingInvoices = ref(false);
    const isLoadingPendingOutwards = ref(false);
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
            const res = await api.get<any>('/outward-payments/meta');
            const data = res.data?.data || res.data || {};
            nextPaymentNo.value = data.next_payment_no || res.data?.next_payment_no || 'REC-0001';
            customers.value = data.customers || res.data?.customers || [];
            agents.value = data.agents || res.data?.agents || [];
            if (data.payment_modes?.length || res.data?.payment_modes?.length) {
                paymentModes.value = data.payment_modes || res.data?.payment_modes;
            }
        } catch (err: any) {
            console.error('Failed to fetch outward payments metadata:', err);
        }
    };

    const fetchPendingInvoices = async (partyId: number, excludePaymentId: number = 0) => {
        if (!partyId) {
            pendingInvoices.value = [];
            return;
        }

        isLoadingInvoices.value = true;
        try {
            const res = await api.get<any>(
                `/outward-payments/pending-invoices/${partyId}`,
                { params: { exclude_payment_id: excludePaymentId } }
            );
            const data = res.data?.data || res.data;
            pendingInvoices.value = Array.isArray(data) ? data : [];
        } catch (err: any) {
            console.error('Failed to fetch pending outward invoices for party:', err);
            pendingInvoices.value = [];
        } finally {
            isLoadingInvoices.value = false;
        }
    };

    const fetchAllPendingOutwards = async (partyId?: number | string, search?: string) => {
        isLoadingPendingOutwards.value = true;
        try {
            const params: Record<string, any> = {};
            if (partyId && partyId !== 'all') params.party_id = partyId;
            if (search) params.search = search;

            const res = await api.get<any>('/outward-payments/all-pending-outwards', { params });
            const data = res.data?.data || [];
            allPendingOutwards.value = Array.isArray(data) ? data : [];
            totalPendingCount.value = Number(res.data?.total_pending_count) || allPendingOutwards.value.length;
            totalPendingAmount.value = Number(res.data?.total_pending_amount) || 0;
        } catch (err: any) {
            console.error('Failed to fetch all pending outwards:', err);
            allPendingOutwards.value = [];
            totalPendingCount.value = 0;
            totalPendingAmount.value = 0;
        } finally {
            isLoadingPendingOutwards.value = false;
        }
    };

    const fetchPayments = async (page = 1) => {
        isLoading.value = true;
        clearMessages();

        try {
            const params: Record<string, any> = { page, per_page: meta.value.per_page };

            if (searchQuery.value) params.search = searchQuery.value;
            if (customerFilter.value && customerFilter.value !== 'all') params.party_id = customerFilter.value;
            if (modeFilter.value && modeFilter.value !== 'all') params.payment_mode = modeFilter.value;
            if (fromDateFilter.value) params.from_date = fromDateFilter.value;
            if (toDateFilter.value) params.to_date = toDateFilter.value;

            const res = await api.get('/outward-payments', { params });

            payments.value = res.data.data;
            if (res.data.meta) {
                meta.value = {
                    current_page: res.data.meta.current_page,
                    last_page: res.data.meta.last_page,
                    per_page: res.data.meta.per_page,
                    total: res.data.meta.total,
                };
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch outward payment receipt vouchers.';
        } finally {
            isLoading.value = false;
        }
    };

    const createPayment = async (form: OutwardPaymentForm): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.post('/outward-payments', form);
            successMessage.value = res.data.message || 'Payment receipt voucher recorded successfully.';
            await fetchMeta();
            await fetchPayments(1);
            await fetchAllPendingOutwards();
            return true;
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                validationErrors.value = Object.values(err.response.data.errors).flat() as string[];
                error.value = err.response.data.message || 'Validation failed. Please verify the payment receipt inputs.';
            } else {
                error.value = err.response?.data?.message || 'Failed to record outward payment receipt.';
            }
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const updatePayment = async (id: number, form: OutwardPaymentForm): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.put(`/outward-payments/${id}`, form);
            successMessage.value = res.data.message || 'Payment receipt voucher updated successfully.';
            await fetchPayments(meta.value.current_page);
            await fetchAllPendingOutwards();
            return true;
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                validationErrors.value = Object.values(err.response.data.errors).flat() as string[];
                error.value = err.response.data.message || 'Validation failed. Please verify the payment inputs.';
            } else {
                error.value = err.response?.data?.message || 'Failed to update outward payment receipt.';
            }
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const deletePayment = async (id: number): Promise<boolean> => {
        isLoading.value = true;
        clearMessages();

        try {
            const res = await api.delete(`/outward-payments/${id}`);
            successMessage.value = res.data.message || 'Payment receipt voucher deleted successfully.';
            await fetchPayments(meta.value.current_page);
            await fetchAllPendingOutwards();
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to delete outward payment receipt voucher.';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const toggleLock = async (id: number): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.post(`/outward-payments/${id}/toggle-lock`);
            successMessage.value = res.data.message || 'Receipt lock status updated.';
            await fetchPayments(meta.value.current_page);
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to update receipt lock status.';
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const resetFilters = () => {
        searchQuery.value = '';
        customerFilter.value = 'all';
        modeFilter.value = 'all';
        fromDateFilter.value = '';
        toDateFilter.value = '';
        fetchPayments(1);
    };

    return {
        // State
        payments,
        nextPaymentNo,
        customers,
        agents,
        paymentModes,
        pendingInvoices,
        allPendingOutwards,
        totalPendingCount,
        totalPendingAmount,
        meta,
        // Filters
        searchQuery,
        customerFilter,
        modeFilter,
        fromDateFilter,
        toDateFilter,
        // Status
        isLoading,
        isSaving,
        isLoadingInvoices,
        isLoadingPendingOutwards,
        error,
        successMessage,
        validationErrors,
        // Actions
        clearMessages,
        fetchMeta,
        fetchPendingInvoices,
        fetchAllPendingOutwards,
        fetchPayments,
        createPayment,
        updatePayment,
        deletePayment,
        toggleLock,
        resetFilters,
    };
});
