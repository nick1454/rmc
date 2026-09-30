import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';
import type {
    PurchasePayment,
    PurchasePaymentForm,
    PendingInwardInvoice,
    PurchasePaymentMeta,
} from '../types/payment';
import type { Party } from '../types/party';

export const usePurchasePaymentStore = defineStore('purchasePayment', () => {
    // State
    const payments = ref<PurchasePayment[]>([]);
    const nextPaymentNo = ref<string>('PAY-0001');
    const suppliers = ref<Party[]>([]);
    const paymentModes = ref<{ value: string; label: string }[]>([
        { value: 'bank_transfer', label: 'Bank Transfer (NEFT/RTGS/IMPS)' },
        { value: 'cheque', label: 'Cheque / Demand Draft' },
        { value: 'upi', label: 'UPI / NetBanking' },
        { value: 'cash', label: 'Cash' },
        { value: 'other', label: 'Other / Adjustment' },
    ]);
    const pendingInvoices = ref<PendingInwardInvoice[]>([]);

    const meta = ref({
        current_page: 1,
        last_page: 1,
        per_page: 5,
        total: 0,
    });

    // Filters
    const searchQuery = ref('');
    const supplierFilter = ref<string | number>('all');
    const modeFilter = ref<string>('all');
    const fromDateFilter = ref('');
    const toDateFilter = ref('');

    // Status
    const isLoading = ref(false);
    const isSaving = ref(false);
    const isLoadingInvoices = ref(false);
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
            const res = await api.get<any>('/purchase-payments/meta');
            const data = res.data?.data || res.data || {};
            nextPaymentNo.value = data.next_payment_no || res.data?.next_payment_no || 'PAY-0001';
            suppliers.value = data.suppliers || res.data?.suppliers || [];
            if (data.payment_modes?.length || res.data?.payment_modes?.length) {
                paymentModes.value = data.payment_modes || res.data?.payment_modes;
            }
        } catch (err: any) {
            console.error('Failed to fetch purchase payments metadata:', err);
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
                `/purchase-payments/pending-invoices/${partyId}`,
                { params: { exclude_payment_id: excludePaymentId } }
            );
            const data = res.data?.data || res.data;
            pendingInvoices.value = Array.isArray(data) ? data : [];
        } catch (err: any) {
            console.error('Failed to fetch pending inward invoices for party:', err);
            pendingInvoices.value = [];
        } finally {
            isLoadingInvoices.value = false;
        }
    };

    const fetchPayments = async (page = 1) => {
        isLoading.value = true;
        clearMessages();

        try {
            const params: Record<string, any> = { page, per_page: meta.value.per_page };

            if (searchQuery.value) params.search = searchQuery.value;
            if (supplierFilter.value && supplierFilter.value !== 'all') params.party_id = supplierFilter.value;
            if (modeFilter.value && modeFilter.value !== 'all') params.payment_mode = modeFilter.value;
            if (fromDateFilter.value) params.from_date = fromDateFilter.value;
            if (toDateFilter.value) params.to_date = toDateFilter.value;

            const res = await api.get('/purchase-payments', { params });

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
            error.value = err.response?.data?.message || 'Failed to fetch purchase payment vouchers.';
        } finally {
            isLoading.value = false;
        }
    };

    const createPayment = async (form: PurchasePaymentForm): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.post('/purchase-payments', form);
            successMessage.value = res.data.message || 'Payment voucher recorded successfully.';
            await fetchMeta();
            await fetchPayments(1);
            return true;
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                validationErrors.value = Object.values(err.response.data.errors).flat() as string[];
                error.value = err.response.data.message || 'Validation failed. Please verify the payment inputs.';
            } else {
                error.value = err.response?.data?.message || 'Failed to record purchase payment.';
            }
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const updatePayment = async (id: number, form: PurchasePaymentForm): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.put(`/purchase-payments/${id}`, form);
            successMessage.value = res.data.message || 'Payment voucher updated successfully.';
            await fetchPayments(meta.value.current_page);
            return true;
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                validationErrors.value = Object.values(err.response.data.errors).flat() as string[];
                error.value = err.response.data.message || 'Validation failed. Please verify the payment inputs.';
            } else {
                error.value = err.response?.data?.message || 'Failed to update purchase payment.';
            }
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const deletePayment = async (id: number): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.delete(`/purchase-payments/${id}`);
            successMessage.value = res.data.message || 'Payment voucher deleted successfully.';
            await fetchPayments(meta.value.current_page);
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to delete purchase payment.';
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    const toggleLock = async (id: number): Promise<boolean> => {
        isSaving.value = true;
        clearMessages();

        try {
            const res = await api.post(`/purchase-payments/${id}/toggle-lock`);
            successMessage.value = res.data.message || 'Payment lock status updated.';
            await fetchPayments(meta.value.current_page);
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to update payment lock status.';
            return false;
        } finally {
            isSaving.value = false;
        }
    };

    return {
        payments,
        nextPaymentNo,
        suppliers,
        paymentModes,
        pendingInvoices,
        meta,
        searchQuery,
        supplierFilter,
        modeFilter,
        fromDateFilter,
        toDateFilter,
        isLoading,
        isSaving,
        isLoadingInvoices,
        error,
        successMessage,
        validationErrors,
        clearMessages,
        fetchMeta,
        fetchPendingInvoices,
        fetchPayments,
        createPayment,
        updatePayment,
        deletePayment,
        toggleLock,
    };
});
