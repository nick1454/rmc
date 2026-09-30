<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOutwardPaymentStore } from '../../stores/outwardPayment';
import type {
    OutwardPayment,
    OutwardPaymentForm,
    OutwardPaymentItemInput,
    PendingOutwardInvoice,
} from '../../types/outwardPayment';
import AppLayout from '../../components/layout/AppLayout.vue';
import { useEscapeKey } from '../../composables/useEscapeKey';
import {
    Plus,
    Search,
    Edit3,
    Trash2,
    Eye,
    CreditCard,
    Layers,
    AlertCircle,
    CheckCircle2,
    X,
    RefreshCw,
    RotateCcw,
    Printer,
    Building2,
    FileText,
    Receipt,
    Wallet,
    Check,
    ArrowRight,
    MapPin,
    Lock,
    Unlock
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const paymentStore = useOutwardPaymentStore();

// Modal States
const isFormModalOpen = ref(false);
const isViewModalOpen = ref(false);
const isDeleteModalOpen = ref(false);

const editingPayment = ref<OutwardPayment | null>(null);
const viewingPayment = ref<OutwardPayment | null>(null);
const deletingPayment = ref<OutwardPayment | null>(null);

const getTodayString = (): string => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const getMonthFirstDateString = (): string => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}-01`;
};

const getMonthLastDateString = (): string => {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const lastDay = new Date(year, month, 0).getDate();
    const monthStr = String(month).padStart(2, '0');
    const dayStr = String(lastDay).padStart(2, '0');
    return `${year}-${monthStr}-${dayStr}`;
};

const formatCurrency = (val: number | string | undefined | null): string => {
    const num = Number(val) || 0;
    return '₹ ' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const initialForm = (): OutwardPaymentForm => ({
    payment_no: paymentStore.nextPaymentNo || 'REC-0001',
    payment_date: getTodayString(),
    party_id: null,
    party_name: '',
    payment_mode: 'bank_transfer',
    reference_no: '',
    bank_name: '',
    amount: 0,
    discount_amount: 0,
    commission_agent_id: null,
    commission_agent_name: '',
    commission_amount: 0,
    remarks: '',
    items: [],
});

const form = ref<OutwardPaymentForm>(initialForm());

onMounted(async () => {
    if (!paymentStore.fromDateFilter) {
        paymentStore.fromDateFilter = getMonthFirstDateString();
    }
    if (!paymentStore.toDateFilter) {
        paymentStore.toDateFilter = getMonthLastDateString();
    }
    await paymentStore.fetchMeta();
    await paymentStore.fetchPayments(1);

    // Auto-open modal if navigated from Outward View with query params
    if (route.query.create === '1' || route.query.party_id) {
        const pId = route.query.party_id ? Number(route.query.party_id) : undefined;
        const outId = route.query.outward_id ? Number(route.query.outward_id) : undefined;
        await openCreateModal(pId, outId);
    }
});

const handleSearch = () => {
    paymentStore.fetchPayments(1);
};

const handleFilterChange = () => {
    paymentStore.fetchPayments(1);
};

const resetFilters = () => {
    paymentStore.searchQuery = '';
    paymentStore.customerFilter = 'all';
    paymentStore.modeFilter = 'all';
    paymentStore.fromDateFilter = getMonthFirstDateString();
    paymentStore.toDateFilter = getMonthLastDateString();
    paymentStore.fetchPayments(1);
};

const selectedOutwardToAdd = ref<number | null>(null);

const unaddedPendingInvoices = computed(() => {
    const existingIds = new Set(form.value.items.map((it) => it.stock_outward_id));
    return (paymentStore.pendingInvoices || []).filter((inv) => !existingIds.has(inv.id));
});

const formatDate = (val: string | null | undefined): string => {
    if (!val) return '—';
    return String(val).split('T')[0];
};

// Add a single pending invoice to the voucher items
const addInvoiceToVoucher = (outwardId: number, autoPayFull: boolean = true) => {
    const inv = (paymentStore.pendingInvoices || []).find((i) => i.id === outwardId);
    if (!inv) return;

    const existing = form.value.items.find((it) => it.stock_outward_id === outwardId);
    if (!existing) {
        form.value.items.push({
            stock_outward_id: inv.id,
            paid_amount: autoPayFull ? (Number(inv.balance_due) || 0) : 0,
            discount_amount: 0,
            commission_agent_id: form.value.commission_agent_id || null,
            commission_agent_name: form.value.commission_agent_name || '',
            commission_amount: 0,
            remarks: '',
            entry_no: inv.entry_no,
            challan_no: inv.challan_no || '',
            invoice_date: inv.invoice_date || '',
            total_amount: Number(inv.total_amount) || 0,
            already_paid: Number(inv.already_paid_others) || 0,
            already_discounted: Number(inv.already_discounted_others) || 0,
            balance_due: Number(inv.balance_due) || 0,
            items_summary: inv.items_summary || '',
        });
    } else if (autoPayFull) {
        const due = Number(existing.balance_due) || 0;
        const disc = Number(existing.discount_amount) || 0;
        existing.paid_amount = Math.max(0, due - disc);
    }
    syncTotalAmountFromItems();
    selectedOutwardToAdd.value = null;
};

// Remove an invoice from the voucher items
const removeInvoiceFromVoucher = (index: number) => {
    form.value.items.splice(index, 1);
    syncTotalAmountFromItems();
};

// Add ALL pending invoices for this customer to the voucher
const addAllPendingInvoices = (autoPayFull: boolean = true) => {
    for (const inv of (paymentStore.pendingInvoices || [])) {
        addInvoiceToVoucher(inv.id, autoPayFull);
    }
    if (autoPayFull) {
        syncTotalAmountFromItems();
    }
};

// Allocate full payment to all current line items
const payAllInvoices = () => {
    for (const row of form.value.items) {
        const due = Number(row.balance_due) || 0;
        const disc = Number(row.discount_amount) || 0;
        row.paid_amount = Math.max(0, due - disc);
    }
    syncTotalAmountFromItems();
};

// Clear all lines from voucher
const clearAllInvoices = () => {
    form.value.items = [];
    syncTotalAmountFromItems();
};

// When discount is changed on a line item, reduce it directly from the overall balance due
const onRowDiscountChange = (row: OutwardPaymentItemInput) => {
    const due = Number(row.balance_due) || 0;
    const disc = Number(row.discount_amount) || 0;
    // Paid amount is reduced from the overall balance due
    row.paid_amount = Math.max(0, Math.round((due - disc) * 100) / 100);
    syncTotalAmountFromItems();
};

// Computed totals allocated in modal
const totalAllocatedAmount = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.paid_amount) || 0), 0);
});

const totalAllocatedDiscount = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.discount_amount) || 0), 0);
});

const totalAllocatedCommission = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.commission_amount) || 0), 0);
});

const totalSettlementAmount = computed(() => {
    return totalAllocatedAmount.value + totalAllocatedDiscount.value;
});

// Sync form total amounts from sum of allocated line items
const syncTotalAmountFromItems = () => {
    form.value.amount = Math.round(totalAllocatedAmount.value * 100) / 100;
    form.value.discount_amount = Math.round(totalAllocatedDiscount.value * 100) / 100;
    form.value.commission_amount = Math.round(totalAllocatedCommission.value * 100) / 100;
};

// When Line Item Agent Selection Changes
const onItemAgentChange = (index: number) => {
    const row = form.value.items[index];
    if (!row) return;
    const a = (paymentStore.agents || []).find((agent) => agent.id === row.commission_agent_id);
    if (a) {
        row.commission_agent_name = a.name;
    } else {
        row.commission_agent_name = '';
    }
};

// When Customer Selection changes in Modal
const onPartyChange = async () => {
    const p = (paymentStore.customers || []).find((c) => c.id === form.value.party_id);
    if (p) {
        form.value.party_name = p.name;
    } else {
        form.value.party_name = '';
    }

    if (form.value.party_id) {
        await paymentStore.fetchPendingInvoices(
            form.value.party_id,
            editingPayment.value ? editingPayment.value.id : 0
        );
    } else {
        paymentStore.pendingInvoices = [];
    }
};

const openCreateModal = async (defaultPartyId?: number, defaultOutwardId?: number) => {
    paymentStore.clearMessages();
    editingPayment.value = null;
    await paymentStore.fetchMeta();

    const selectedPartyId = defaultPartyId || (paymentStore.customers && paymentStore.customers.length > 0 ? paymentStore.customers[0]?.id : null);
    const selectedParty = (paymentStore.customers || []).find((c) => c.id === selectedPartyId);

    form.value = {
        ...initialForm(),
        payment_no: paymentStore.nextPaymentNo || 'REC-0001',
        party_id: selectedPartyId,
        party_name: selectedParty?.name || '',
        commission_agent_id: null,
        commission_agent_name: '',
        commission_amount: 0,
        discount_amount: 0,
        items: [],
    };

    if (selectedPartyId) {
        await paymentStore.fetchPendingInvoices(selectedPartyId, 0);

        if (defaultOutwardId) {
            addInvoiceToVoucher(defaultOutwardId, true);
        }
    }

    isFormModalOpen.value = true;
};

const openEditModal = async (payment: OutwardPayment) => {
    paymentStore.clearMessages();
    editingPayment.value = payment;

    if (payment.party_id) {
        await paymentStore.fetchPendingInvoices(payment.party_id, payment.id);
    }

    form.value = {
        payment_no: payment.payment_no,
        payment_date: formatDate(payment.payment_date),
        party_id: payment.party_id || null,
        party_name: payment.party_name || payment.party?.name || '',
        payment_mode: payment.payment_mode || 'bank_transfer',
        reference_no: payment.reference_no || '',
        bank_name: payment.bank_name || '',
        amount: Number(payment.amount) || 0,
        discount_amount: Number(payment.discount_amount) || 0,
        commission_agent_id: payment.commission_agent_id || null,
        commission_agent_name: payment.commission_agent_name || payment.commission_agent?.name || '',
        commission_amount: Number(payment.commission_amount) || 0,
        remarks: payment.remarks || '',
        items: (payment.items || []).map((it) => {
            const pendingMatch = (paymentStore.pendingInvoices || []).find((pi) => pi.id === it.stock_outward_id);
            return {
                stock_outward_id: it.stock_outward_id,
                paid_amount: Number(it.paid_amount),
                discount_amount: Number(it.discount_amount) || 0,
                commission_agent_id: it.commission_agent_id || payment.commission_agent_id || null,
                commission_agent_name: it.commission_agent_name || it.commission_agent?.name || payment.commission_agent_name || '',
                commission_amount: Number(it.commission_amount) || 0,
                remarks: it.remarks || '',
                entry_no: it.outward?.entry_no || pendingMatch?.entry_no || `OUT-${it.stock_outward_id}`,
                challan_no: it.outward?.challan_no || pendingMatch?.challan_no || '',
                invoice_date: it.outward?.invoice_date || pendingMatch?.invoice_date || '',
                total_amount: Number(it.invoice_amount || it.outward?.total_amount || pendingMatch?.total_amount || 0),
                already_paid: Number(pendingMatch?.already_paid_others || 0),
                already_discounted: Number(pendingMatch?.already_discounted_others || 0),
                balance_due: Number(pendingMatch?.balance_due || 0) + Number(it.paid_amount) + Number(it.discount_amount || 0),
                items_summary: pendingMatch?.items_summary || '',
            };
        }),
    };

    isFormModalOpen.value = true;
};

const openViewModal = (payment: OutwardPayment) => {
    viewingPayment.value = payment;
    isViewModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
    editingPayment.value = null;
    selectedOutwardToAdd.value = null;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    viewingPayment.value = null;
};

const confirmDelete = (payment: OutwardPayment) => {
    paymentStore.clearMessages();
    deletingPayment.value = payment;
    isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
    if (deletingPayment.value) {
        await paymentStore.deletePayment(deletingPayment.value.id);
        isDeleteModalOpen.value = false;
        deletingPayment.value = null;
    }
};

const handleToggleLock = async (payment: OutwardPayment) => {
    await paymentStore.toggleLock(payment.id);
};

const closeAllModals = () => {
    if (isDeleteModalOpen.value) {
        isDeleteModalOpen.value = false;
        deletingPayment.value = null;
    } else if (isViewModalOpen.value) {
        closeViewModal();
    } else if (isFormModalOpen.value) {
        closeFormModal();
    }
};

useEscapeKey(closeAllModals);

const submitForm = async () => {
    syncTotalAmountFromItems();
    if (editingPayment.value) {
        const success = await paymentStore.updatePayment(editingPayment.value.id, form.value);
        if (success) {
            closeFormModal();
        }
    } else {
        const success = await paymentStore.createPayment(form.value);
        if (success) {
            closeFormModal();
        }
    }
};

const printSlip = () => {
    window.print();
};

const getModeBadgeClass = (mode: string) => {
    switch (mode) {
        case 'bank_transfer':
            return 'bg-blue-50 text-blue-800 border-blue-200';
        case 'cheque':
            return 'bg-purple-50 text-purple-800 border-purple-200';
        case 'upi':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        case 'cash':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};

const getModeLabel = (mode: string) => {
    const found = paymentStore.paymentModes.find((m) => m.value === mode);
    return found ? found.label : mode;
};
</script>

<template>
    <AppLayout title="Outward Payments & Customer Receipts" subtitle="Record Payments Received for Concrete Dispatches & Settle Accounts Receivable">
        <div class="space-y-6">
            <!-- Feedback Alerts -->
            <div
                v-if="paymentStore.successMessage"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="w-5 h-5 flex-shrink-0 text-emerald-600" />
                    <span class="text-xs font-semibold">{{ paymentStore.successMessage }}</span>
                </div>
                <button @click="paymentStore.clearMessages" class="text-emerald-600 hover:text-emerald-800 cursor-pointer">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div
                v-if="paymentStore.error || paymentStore.validationErrors.length > 0"
                class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 space-y-2 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 font-bold text-xs">
                        <AlertCircle class="w-5 h-5 flex-shrink-0 text-red-600" />
                        <span>{{ paymentStore.error || 'Please correct the following errors' }}</span>
                    </div>
                    <button @click="paymentStore.clearMessages" class="text-red-600 hover:text-red-800 cursor-pointer">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <ul v-if="paymentStore.validationErrors.length > 0" class="pl-8 list-disc text-[11px] space-y-1 text-red-700 font-mono">
                    <li v-for="(msg, idx) in paymentStore.validationErrors" :key="idx">
                        {{ msg }}
                    </li>
                </ul>
            </div>

            <!-- Filter & Action Bar -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
                <!-- Search & Filters -->
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <!-- Search Input -->
                    <div class="relative min-w-[220px] flex-1 max-w-sm">
                        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="paymentStore.searchQuery"
                            type="text"
                            placeholder="Search Receipt #, customer, reference, bank..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        />
                    </div>

                    <!-- Customer Filter -->
                    <select
                        v-model="paymentStore.customerFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Customers / Clients</option>
                        <option v-for="c in paymentStore.customers" :key="c.id" :value="c.id">
                            {{ c.name }} ({{ c.code }})
                        </option>
                    </select>

                    <!-- Payment Mode Filter -->
                    <select
                        v-model="paymentStore.modeFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Payment Modes</option>
                        <option v-for="m in paymentStore.paymentModes" :key="m.value" :value="m.value">
                            {{ m.label }}
                        </option>
                    </select>

                    <!-- From Date Filter -->
                    <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-300">
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">From:</span>
                        <input
                            v-model="paymentStore.fromDateFilter"
                            type="date"
                            @change="handleFilterChange"
                            class="bg-transparent text-slate-800 text-xs font-mono focus:outline-none cursor-pointer"
                            title="From Date"
                        />
                    </div>

                    <!-- To Date Filter -->
                    <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-300">
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">To:</span>
                        <input
                            v-model="paymentStore.toDateFilter"
                            type="date"
                            @change="handleFilterChange"
                            class="bg-transparent text-slate-800 text-xs font-mono focus:outline-none cursor-pointer"
                            title="To Date"
                        />
                    </div>

                    <button
                        type="button"
                        @click="paymentStore.fetchPayments(paymentStore.meta.current_page)"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors cursor-pointer"
                        title="Refresh List"
                    >
                        <RefreshCw :class="['w-4 h-4', paymentStore.isLoading ? 'animate-spin' : '']" />
                    </button>

                    <button
                        type="button"
                        @click="resetFilters"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-amber-600 border border-slate-200 transition-colors cursor-pointer"
                        title="Reset Filters"
                    >
                        <RotateCcw class="w-4 h-4" />
                    </button>
                </div>

                <!-- Add Button -->
                <button
                    type="button"
                    @click="() => openCreateModal()"
                    class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 shadow-md shadow-amber-500/20 transition-all active:scale-95 whitespace-nowrap cursor-pointer"
                >
                    <Plus class="w-4 h-4" />
                    <span>New Payment Receipt</span>
                </button>
            </div>

            <!-- Payments List (Table + Mobile Cards) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- Mobile Card List View (< md) -->
                <div class="block md:hidden divide-y divide-slate-100">
                    <!-- Loading State -->
                    <div v-if="paymentStore.isLoading && paymentStore.payments.length === 0" class="py-12 text-center text-slate-500">
                        <div class="inline-flex items-center gap-2 text-xs">
                            <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                            <span>Loading payment receipts...</span>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="paymentStore.payments.length === 0" class="py-12 px-4 text-center text-slate-500 space-y-2">
                        <Receipt class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="font-bold text-slate-800 text-sm">No Payment Receipt Vouchers Found</div>
                        <p class="text-xs text-slate-500">Record customer payment receipts against outward concrete deliveries.</p>
                    </div>

                    <!-- Mobile Cards -->
                    <div
                        v-for="payment in paymentStore.payments"
                        :key="payment.id"
                        class="p-4 space-y-3 hover:bg-slate-50 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-slate-900 text-xs">
                                    {{ payment.payment_no }}
                                </span>
                                <span v-if="payment.is_locked" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-red-50 border border-red-200 text-red-700 font-semibold text-[10px]" title="Receipt Locked">
                                    <Lock class="w-3 h-3" /> Locked
                                </span>
                                <span class="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-800 font-bold text-[10px]">
                                    {{ payment.party?.name || payment.party_name }}
                                </span>
                            </div>
                            <span :class="['px-2 py-0.5 rounded-full font-bold text-[10px] uppercase border', getModeBadgeClass(payment.payment_mode)]">
                                {{ payment.payment_mode.replace('_', ' ') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Date & Ref</span>
                                <span class="font-bold text-slate-800 font-mono block">{{ formatDate(payment.payment_date) }}</span>
                                <span class="text-[11px] text-slate-600 block truncate">{{ payment.reference_no || '—' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Amount Received</span>
                                <span class="font-mono font-extrabold text-emerald-800 text-sm block">
                                    {{ formatCurrency(payment.amount) }}
                                </span>
                                <div v-if="Number(payment.discount_amount) > 0" class="text-[10px] font-mono text-red-600">
                                    Disc: -{{ formatCurrency(payment.discount_amount) }}
                                </div>
                                <span v-if="payment.bank_name" class="text-[10px] text-slate-400 block truncate mt-0.5">
                                    {{ payment.bank_name }}
                                </span>
                            </div>
                        </div>

                        <!-- Settled Outward Invoices Chips -->
                        <div class="flex flex-wrap gap-1 pt-1">
                            <div
                                v-for="it in (payment.items || [])"
                                :key="it.id"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] font-mono"
                            >
                                <span class="text-slate-500">{{ it.outward?.entry_no || `OUT-${it.stock_outward_id}` }}:</span>
                                <span class="text-emerald-800 font-bold">{{ formatCurrency(it.paid_amount) }}</span>
                                <span v-if="Number(it.discount_amount) > 0" class="text-red-600">(Disc: {{ formatCurrency(it.discount_amount) }})</span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
                            <button
                                type="button"
                                @click="handleToggleLock(payment)"
                                :disabled="paymentStore.isSaving"
                                :class="[
                                    'p-2 rounded-lg border text-xs cursor-pointer transition-colors',
                                    payment.is_locked
                                        ? 'bg-red-50 hover:bg-red-100 text-red-700 border-red-200'
                                        : 'bg-slate-50 hover:bg-slate-100 text-slate-600 border-slate-200'
                                ]"
                                :title="payment.is_locked ? 'Locked - Click to Unlock' : 'Unlocked - Click to Lock'"
                            >
                                <Lock v-if="payment.is_locked" class="w-4 h-4" />
                                <Unlock v-else class="w-4 h-4" />
                            </button>
                            <button
                                type="button"
                                @click="openViewModal(payment)"
                                class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-cyan-700 transition-colors border border-slate-200 cursor-pointer"
                                title="View Receipt Voucher"
                            >
                                <Eye class="w-4 h-4" />
                            </button>
                            <button
                                type="button"
                                :disabled="payment.is_locked"
                                @click="openEditModal(payment)"
                                :class="[
                                    'p-2 rounded-lg border transition-colors',
                                    payment.is_locked
                                        ? 'bg-slate-50 text-slate-400 border-slate-200 opacity-40 cursor-not-allowed'
                                        : 'bg-slate-100 hover:bg-slate-200 text-amber-700 border-slate-200 cursor-pointer'
                                ]"
                                :title="payment.is_locked ? 'Receipt is locked' : 'Edit Receipt'"
                            >
                                <Edit3 class="w-4 h-4" />
                            </button>
                            <button
                                type="button"
                                :disabled="payment.is_locked"
                                @click="confirmDelete(payment)"
                                :class="[
                                    'p-2 rounded-lg border transition-colors',
                                    payment.is_locked
                                        ? 'bg-slate-50 text-slate-400 border-slate-200 opacity-40 cursor-not-allowed'
                                        : 'bg-slate-100 hover:bg-slate-200 text-red-600 border-slate-200 cursor-pointer'
                                ]"
                                :title="payment.is_locked ? 'Receipt is locked' : 'Delete Receipt'"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table View (>= md) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider border-b border-slate-200 font-bold text-[10px]">
                                <th class="py-3 px-4">Receipt #</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4">Receipt Date</th>
                                <th class="py-3 px-4">Mode & Reference</th>
                                <th class="py-3 px-4">Allocated Invoices & Settlement</th>
                                <th class="py-3 px-4 text-right">Amount Received</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <!-- Loading State -->
                            <tr v-if="paymentStore.isLoading && paymentStore.payments.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading payment receipts...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="paymentStore.payments.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <div class="space-y-2">
                                        <Receipt class="w-8 h-8 text-slate-400 mx-auto" />
                                        <div class="font-semibold text-slate-800">No Payment Receipt Vouchers Found</div>
                                        <p class="text-[11px] text-slate-500">Record customer payment receipts against outward concrete deliveries.</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="payment in paymentStore.payments"
                                :key="payment.id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-bold text-slate-900">{{ payment.payment_no }}</span>
                                        <span v-if="payment.is_locked" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-red-50 border border-red-200 text-red-700 font-semibold text-[10px]" title="Receipt Locked">
                                            <Lock class="w-3 h-3" /> Locked
                                        </span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900">
                                        {{ payment.party?.name || payment.party_name || '—' }}
                                    </div>
                                    <div v-if="payment.party?.code" class="text-[10px] font-mono text-slate-400">
                                        {{ payment.party.code }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-600">
                                    {{ formatDate(payment.payment_date) }}
                                </td>

                                <td class="py-3.5 px-4">
                                    <span :class="['px-2 py-0.5 rounded font-bold text-[10px] uppercase border', getModeBadgeClass(payment.payment_mode)]">
                                        {{ payment.payment_mode.replace('_', ' ') }}
                                    </span>
                                    <div v-if="payment.reference_no" class="text-[10px] font-mono text-slate-500 mt-1">
                                        Ref: {{ payment.reference_no }}
                                    </div>
                                    <div v-if="payment.bank_name" class="text-[10px] text-slate-400">
                                        {{ payment.bank_name }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex flex-wrap gap-1.5 max-w-sm">
                                        <div
                                            v-for="it in (payment.items || [])"
                                            :key="it.id"
                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] font-mono"
                                        >
                                            <span class="text-slate-600 font-semibold">{{ it.outward?.entry_no || `OUT-${it.stock_outward_id}` }}:</span>
                                            <span class="text-emerald-800 font-bold">{{ formatCurrency(it.paid_amount) }}</span>
                                            <span v-if="Number(it.discount_amount) > 0" class="text-red-600" title="Discount allowed">(-{{ formatCurrency(it.discount_amount) }})</span>
                                            <span v-if="it.commissionAgent?.name || it.commission_agent_name" class="text-indigo-600 font-sans" :title="`Agent: ${it.commissionAgent?.name || it.commission_agent_name} (₹${it.commission_amount || 0})`">
                                                [{{ it.commissionAgent?.name || it.commission_agent_name }}]
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-800 text-sm">
                                    {{ formatCurrency(payment.amount) }}
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="handleToggleLock(payment)"
                                            :disabled="paymentStore.isSaving"
                                            :class="[
                                                'p-1.5 rounded-lg transition-colors border cursor-pointer',
                                                payment.is_locked
                                                    ? 'bg-red-50 hover:bg-red-100 text-red-700 border-red-200'
                                                    : 'bg-slate-100 hover:bg-slate-200 text-slate-600 border-slate-200'
                                            ]"
                                            :title="payment.is_locked ? 'Locked - Click to Unlock' : 'Unlocked - Click to Lock'"
                                        >
                                            <Lock v-if="payment.is_locked" class="w-3.5 h-3.5" />
                                            <Unlock v-else class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openViewModal(payment)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-cyan-700 transition-colors border border-slate-200 cursor-pointer"
                                            title="View Payment Receipt Voucher"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="payment.is_locked"
                                            @click="openEditModal(payment)"
                                            :class="[
                                                'p-1.5 rounded-lg transition-colors border',
                                                payment.is_locked
                                                    ? 'bg-slate-50 text-slate-400 border-slate-200 opacity-40 cursor-not-allowed'
                                                    : 'bg-slate-100 hover:bg-slate-200 text-amber-700 border-slate-200 cursor-pointer'
                                            ]"
                                            :title="payment.is_locked ? 'Receipt is locked and cannot be edited' : 'Edit Payment Receipt'"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="payment.is_locked"
                                            @click="confirmDelete(payment)"
                                            :class="[
                                                'p-1.5 rounded-lg transition-colors border',
                                                payment.is_locked
                                                    ? 'bg-slate-50 text-slate-400 border-slate-200 opacity-40 cursor-not-allowed'
                                                    : 'bg-slate-100 hover:bg-slate-200 text-red-600 border-slate-200 cursor-pointer'
                                            ]"
                                            :title="payment.is_locked ? 'Receipt is locked and cannot be deleted' : 'Delete Payment Receipt'"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="paymentStore.meta.total > 0"
                    class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
                >
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (paymentStore.meta.current_page - 1) * paymentStore.meta.per_page + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(paymentStore.meta.current_page * paymentStore.meta.per_page, paymentStore.meta.total) }}</span>
                        of <span class="font-bold text-slate-900">{{ paymentStore.meta.total }}</span> entries
                    </div>
                    <div class="flex items-center gap-1.5" v-if="paymentStore.meta.last_page > 1">
                        <button
                            type="button"
                            :disabled="paymentStore.meta.current_page === 1"
                            @click="paymentStore.fetchPayments(paymentStore.meta.current_page - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in paymentStore.meta.last_page"
                            :key="p"
                            @click="paymentStore.fetchPayments(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all cursor-pointer',
                                paymentStore.meta.current_page === p
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            {{ p }}
                        </button>
                        <button
                            type="button"
                            :disabled="paymentStore.meta.current_page === paymentStore.meta.last_page"
                            @click="paymentStore.fetchPayments(paymentStore.meta.current_page + 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Outward Payment Receipt Modal -->
        <div
            v-if="isFormModalOpen"
            @click.self="closeFormModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700">
                            <Receipt class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">
                                {{ editingPayment ? `Edit Payment Receipt: ${editingPayment.payment_no}` : 'New Customer Payment Receipt Voucher' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Record customer payment receipt & allocate to outward dispatch invoices</p>
                        </div>
                    </div>
                    <button @click="closeFormModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form @submit.prevent="submitForm" class="p-6 space-y-6 max-h-[82vh] overflow-y-auto custom-scrollbar">
                    <!-- General Details Grid -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                            <FileText class="w-3.5 h-3.5 text-amber-600" />
                            <span>Voucher Header & Customer</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            <!-- Receipt No -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Receipt Voucher No *</label>
                                <input
                                    v-model="form.payment_no"
                                    type="text"
                                    required
                                    placeholder="e.g. REC-0001"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Customer / Client Selection -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Customer / Client *</label>
                                <select
                                    v-model.number="form.party_id"
                                    required
                                    @change="onPartyChange"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-semibold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option :value="null">-- Select Registered Customer --</option>
                                    <option v-for="c in paymentStore.customers" :key="c.id" :value="c.id">
                                        {{ c.name }} ({{ c.code }})
                                    </option>
                                </select>
                            </div>

                            <!-- Receipt Date -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Receipt Date *</label>
                                <input
                                    v-model="form.payment_date"
                                    type="date"
                                    required
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none cursor-pointer"
                                />
                            </div>

                            <!-- Payment Mode -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Payment Mode *</label>
                                <select
                                    v-model="form.payment_mode"
                                    required
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option v-for="m in paymentStore.paymentModes" :key="m.value" :value="m.value">
                                        {{ m.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Transaction / Cheque / UTR No -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Transaction Ref / Cheque #</label>
                                <input
                                    v-model="form.reference_no"
                                    type="text"
                                    placeholder="e.g. UTR-982341209 / CHQ-1002"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Bank Name -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Bank Name / Gateway</label>
                                <input
                                    v-model="form.bank_name"
                                    type="text"
                                    placeholder="e.g. HDFC Bank Ltd"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Outward Invoices Allocation Section -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                                <Layers class="w-3.5 h-3.5 text-amber-600" />
                                <span>Outward Invoices in this Voucher ({{ form.items.length }})</span>
                            </div>

                            <div v-if="form.party_id" class="flex flex-wrap items-center gap-2">
                                <!-- Selector to add pending outward invoice -->
                                <div v-if="unaddedPendingInvoices.length > 0" class="flex items-center gap-1.5">
                                    <select
                                        v-model.number="selectedOutwardToAdd"
                                        @change="selectedOutwardToAdd ? addInvoiceToVoucher(selectedOutwardToAdd, true) : null"
                                        class="px-2.5 py-1.5 bg-amber-50/70 border border-amber-300 rounded-xl text-slate-900 text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none cursor-pointer max-w-xs truncate shadow-xs"
                                    >
                                        <option :value="null">+ Add Outward Invoice ({{ unaddedPendingInvoices.length }} pending)...</option>
                                        <option v-for="inv in unaddedPendingInvoices" :key="inv.id" :value="inv.id">
                                            {{ inv.entry_no }} ({{ inv.challan_no || 'No Challan' }}) — Due: {{ formatCurrency(inv.balance_due) }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="addAllPendingInvoices(true)"
                                        class="px-2.5 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs font-bold transition-all shadow-xs cursor-pointer whitespace-nowrap"
                                        title="Add all remaining pending invoices to voucher"
                                    >
                                        + Add All ({{ unaddedPendingInvoices.length }})
                                    </button>
                                </div>

                                <template v-if="form.items.length > 0">
                                    <button
                                        type="button"
                                        @click="payAllInvoices"
                                        class="px-2.5 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold transition-colors whitespace-nowrap cursor-pointer"
                                        title="Allocate full balance due to all lines"
                                    >
                                        Pay All Full
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearAllInvoices"
                                        class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 text-xs font-semibold transition-colors whitespace-nowrap cursor-pointer"
                                        title="Clear all line items"
                                    >
                                        Clear All
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Empty Invoices Warning -->
                        <div v-if="!form.party_id" class="p-6 text-center text-slate-500 text-xs bg-white rounded-xl border border-slate-200">
                            Please select a customer above to view and allocate pending outward invoices.
                        </div>

                        <div v-else-if="form.items.length === 0" class="p-6 text-center text-slate-500 text-xs bg-white rounded-xl border border-slate-200">
                            <div v-if="paymentStore.isLoadingInvoices" class="flex items-center justify-center gap-2">
                                <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                                <span>Loading customer pending outward invoices...</span>
                            </div>
                            <div v-else-if="paymentStore.pendingInvoices.length === 0" class="space-y-1">
                                <div class="font-bold text-slate-700">No Outstanding Outward Invoices Found</div>
                                <p class="text-[11px] text-slate-400">All outward consignments for this customer are fully paid or none exist.</p>
                            </div>
                            <div v-else class="space-y-3">
                                <p class="text-slate-600 font-medium">Select a pending outward sales invoice from the dropdown below or add all:</p>
                                <div class="flex flex-wrap items-center justify-center gap-2">
                                    <select
                                        v-model.number="selectedOutwardToAdd"
                                        @change="selectedOutwardToAdd ? addInvoiceToVoucher(selectedOutwardToAdd, true) : null"
                                        class="px-3.5 py-2 bg-amber-50/60 border border-amber-300 rounded-xl text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none max-w-sm cursor-pointer shadow-xs"
                                    >
                                        <option :value="null">-- Select Outward Invoice ({{ paymentStore.pendingInvoices.length }} pending) --</option>
                                        <option v-for="inv in unaddedPendingInvoices" :key="inv.id" :value="inv.id">
                                            {{ inv.entry_no }} ({{ inv.challan_no || 'No Challan' }}) — Due: {{ formatCurrency(inv.balance_due) }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="addAllPendingInvoices(true)"
                                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs shadow-md shadow-amber-500/20 inline-flex items-center gap-1.5 cursor-pointer active:scale-95 transition-all"
                                    >
                                        <Plus class="w-4 h-4" />
                                        <span>Add All Invoices ({{ paymentStore.pendingInvoices.length }})</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Invoices Allocation Table -->
                        <div v-else class="overflow-x-auto bg-white rounded-xl border border-slate-200">
                            <table class="w-full text-left text-xs min-w-[1250px]">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider border-b border-slate-200 font-bold text-[10px]">
                                        <th class="py-2.5 px-3">#</th>
                                        <th class="py-2.5 px-3">Outward No</th>
                                        <th class="py-2.5 px-3">Challan / Date</th>
                                        <th class="py-2.5 px-3 text-right">Invoice Total</th>
                                        <th class="py-2.5 px-3 text-right">Already Paid</th>
                                        <th class="py-2.5 px-3 text-right">Pending Due</th>
                                        <th class="py-2.5 px-3 w-28 text-right">Discount (₹)</th>
                                        <th class="py-2.5 px-3 w-36 text-right">Paid Amount (₹) *</th>
                                        <th class="py-2.5 px-3 w-36">Comm Agent</th>
                                        <th class="py-2.5 px-3 w-28 text-right">Comm (₹)</th>
                                        <th class="py-2.5 px-3 text-right font-bold">Settled (₹)</th>
                                        <th class="py-2.5 px-3">Remarks</th>
                                        <th class="py-2.5 px-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(row, idx) in form.items" :key="row.stock_outward_id" class="hover:bg-slate-50">
                                        <td class="py-2.5 px-3 font-mono text-slate-400">{{ idx + 1 }}</td>

                                        <!-- Outward No -->
                                        <td class="py-2.5 px-3 font-mono font-bold text-slate-900">
                                            {{ row.entry_no }}
                                        </td>

                                        <!-- Challan / Date -->
                                        <td class="py-2.5 px-3 text-slate-600">
                                            <div>{{ row.challan_no || '—' }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono">{{ formatDate(row.invoice_date) }}</div>
                                        </td>

                                        <!-- Invoice Total -->
                                        <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-800">
                                            {{ formatCurrency(row.total_amount) }}
                                        </td>

                                        <!-- Already Paid -->
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-500">
                                            {{ formatCurrency(row.already_paid) }}
                                            <div v-if="Number(row.already_discounted) > 0" class="text-[10px] text-red-500">
                                                (Disc: {{ formatCurrency(row.already_discounted) }})
                                            </div>
                                        </td>

                                        <!-- Pending Due -->
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-red-600">
                                            {{ formatCurrency(row.balance_due) }}
                                        </td>

                                        <!-- Discount on this invoice (reduces directly from overall cost/due) -->
                                        <td class="py-2.5 px-3 text-right">
                                            <input
                                                v-model.number="row.discount_amount"
                                                type="number"
                                                step="any"
                                                min="0"
                                                :max="row.balance_due || 9999999"
                                                @input="onRowDiscountChange(row)"
                                                placeholder="0.00"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs text-right focus:ring-1 focus:ring-amber-500 focus:outline-none text-red-600"
                                                title="Discount reduces payable amount directly from overall invoice due"
                                            />
                                        </td>

                                        <!-- Amount Paid in this receipt -->
                                        <td class="py-2.5 px-3 text-right">
                                            <input
                                                v-model.number="row.paid_amount"
                                                type="number"
                                                step="any"
                                                min="0"
                                                :max="row.balance_due || 9999999"
                                                @input="syncTotalAmountFromItems"
                                                placeholder="0.00"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono font-bold text-xs text-right focus:ring-1 focus:ring-amber-500 focus:outline-none text-emerald-800"
                                            />
                                        </td>

                                        <!-- Commission Agent -->
                                        <td class="py-2.5 px-3">
                                            <select
                                                v-model.number="row.commission_agent_id"
                                                @change="onItemAgentChange(idx)"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 text-xs font-semibold focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            >
                                                <option :value="null">-- Direct --</option>
                                                <option v-for="a in paymentStore.agents" :key="a.id" :value="a.id">
                                                    {{ a.name }}
                                                </option>
                                            </select>
                                        </td>

                                        <!-- Commission (₹) -->
                                        <td class="py-2.5 px-3 text-right">
                                            <input
                                                v-model.number="row.commission_amount"
                                                type="number"
                                                step="any"
                                                min="0"
                                                @input="syncTotalAmountFromItems"
                                                placeholder="0.00"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs text-right focus:ring-1 focus:ring-amber-500 focus:outline-none text-indigo-700"
                                            />
                                        </td>

                                        <!-- Total Settled for row -->
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                            {{ formatCurrency((Number(row.paid_amount) || 0) + (Number(row.discount_amount) || 0)) }}
                                        </td>

                                        <!-- Remarks -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model="row.remarks"
                                                type="text"
                                                placeholder="Notes..."
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- Action -->
                                        <td class="py-2.5 px-3 text-right">
                                            <button
                                                type="button"
                                                @click="removeInvoiceFromVoucher(idx)"
                                                class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
                                                title="Remove Invoice"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Grand Summary Allocation Footer -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-2">
                            <div class="p-3 rounded-xl bg-white border border-slate-200">
                                <div class="text-[10px] uppercase font-bold text-slate-400">Total Invoices</div>
                                <div class="text-sm font-bold font-mono text-slate-800">{{ form.items.length }} Consignments</div>
                            </div>
                            <div class="p-3 rounded-xl bg-white border border-slate-200">
                                <div class="text-[10px] uppercase font-bold text-slate-400">Total Discount Allowed</div>
                                <div class="text-sm font-bold font-mono text-red-600">{{ formatCurrency(totalAllocatedDiscount) }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-white border border-slate-200">
                                <div class="text-[10px] uppercase font-bold text-slate-400">Total Commission</div>
                                <div class="text-sm font-bold font-mono text-indigo-700">{{ formatCurrency(totalAllocatedCommission) }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200">
                                <div class="text-[10px] uppercase font-bold text-emerald-800">Total Amount Received</div>
                                <div class="text-base font-extrabold font-mono text-emerald-900">{{ formatCurrency(totalAllocatedAmount) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Receipt Remarks / Allocation Notes</label>
                        <textarea
                            v-model="form.remarks"
                            rows="2"
                            placeholder="Optional payment notes, ledger remarks, settlement details..."
                            class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        ></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                        <button
                            type="button"
                            @click="closeFormModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="paymentStore.isSaving"
                            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider shadow-md shadow-amber-500/20 transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2 cursor-pointer"
                        >
                            <RefreshCw v-if="paymentStore.isSaving" class="w-4 h-4 animate-spin" />
                            <span>{{ editingPayment ? 'Update Payment Receipt' : 'Save Payment Receipt' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>



        <!-- View / Print Payment Receipt Voucher Slip Modal -->
        <div
            v-if="isViewModalOpen && viewingPayment"
            @click.self="closeViewModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden my-8 print:shadow-none print:border-none print:m-0 print:w-full print:max-w-none">
                <!-- Voucher Header Actions -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 print:hidden">
                    <div class="flex items-center gap-2">
                        <Receipt class="w-5 h-5 text-amber-600" />
                        <span class="font-bold text-sm text-slate-900">Customer Payment Receipt Voucher</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="printSlip"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs font-bold transition-colors cursor-pointer"
                        >
                            <Printer class="w-4 h-4" />
                            <span>Print Slip</span>
                        </button>
                        <button @click="closeViewModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Printable Receipt Slip Document -->
                <div class="p-8 space-y-6 bg-white text-slate-800 font-sans print:p-0">
                    <!-- Slip Header -->
                    <div class="flex items-start justify-between border-b pb-6 border-slate-200">
                        <div>
                            <h2 class="text-xl font-black text-slate-900 tracking-tight">RMC READY MIX CONCRETE</h2>
                            <p class="text-xs text-slate-500">Central Batching Plant • Industrial Area Phase-1</p>
                            <p class="text-xs text-slate-500">GSTIN: 27AABCR1234F1Z0 • Phone: +91 98765 43210</p>
                        </div>
                        <div class="text-right">
                            <div class="inline-flex items-center gap-1.5 mb-1">
                                <span class="px-3 py-1 bg-amber-100 border border-amber-300 rounded-lg text-amber-900 font-bold text-xs uppercase tracking-wider">
                                    PAYMENT RECEIPT
                                </span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border inline-flex items-center gap-1',
                                        viewingPayment.is_locked
                                            ? 'bg-red-50 text-red-700 border-red-200'
                                            : 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                    ]"
                                >
                                    <Lock v-if="viewingPayment.is_locked" class="w-3 h-3" />
                                    <Unlock v-else class="w-3 h-3" />
                                    <span>{{ viewingPayment.is_locked ? 'Locked' : 'Unlocked' }}</span>
                                </span>
                            </div>
                            <div class="font-mono text-sm font-bold text-slate-900">{{ viewingPayment.payment_no }}</div>
                            <div class="text-xs text-slate-500">Date: {{ formatDate(viewingPayment.payment_date) }}</div>
                        </div>
                    </div>

                    <!-- Customer & Transaction Info -->
                    <div class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs print:bg-transparent print:border print:border-black">
                        <div>
                            <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Received From (Customer)</span>
                            <div class="font-bold text-slate-900 text-sm">{{ viewingPayment.party?.name || viewingPayment.party_name }}</div>
                            <div v-if="viewingPayment.party?.phone" class="text-slate-600">Phone: {{ viewingPayment.party.phone }}</div>
                            <div v-if="viewingPayment.party?.gstin" class="text-slate-600 font-mono">GSTIN: {{ viewingPayment.party.gstin }}</div>
                            <div v-if="viewingPayment.party?.city" class="text-slate-600">City: {{ viewingPayment.party.city }}</div>
                        </div>
                        <div>
                            <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Payment & Bank Details</span>
                            <div><span class="font-semibold text-slate-600">Mode:</span> {{ getModeLabel(viewingPayment.payment_mode) }}</div>
                            <div v-if="viewingPayment.reference_no"><span class="font-semibold text-slate-600">Ref / Cheque #:</span> <span class="font-mono font-bold">{{ viewingPayment.reference_no }}</span></div>
                            <div v-if="viewingPayment.bank_name"><span class="font-semibold text-slate-600">Bank:</span> {{ viewingPayment.bank_name }}</div>
                            <div v-if="viewingPayment.commission_agent?.name || viewingPayment.commission_agent_name || Number(viewingPayment.commission_amount) > 0">
                                <span class="font-semibold text-slate-600">Commission:</span>
                                <span v-if="Number(viewingPayment.commission_amount) > 0" class="font-mono font-bold text-slate-900 ml-1">
                                    {{ formatCurrency(viewingPayment.commission_amount) }}
                                </span>
                                <span v-if="viewingPayment.commission_agent?.name || viewingPayment.commission_agent_name" class="text-slate-600 ml-1">
                                    ({{ viewingPayment.commission_agent?.name || viewingPayment.commission_agent_name }})
                                </span>
                            </div>
                            <div v-if="viewingPayment.remarks"><span class="font-semibold text-slate-600">Remarks:</span> {{ viewingPayment.remarks }}</div>
                        </div>
                    </div>

                    <!-- Invoices Settled Breakdown -->
                    <div class="space-y-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                            <Layers class="w-3.5 h-3.5 text-amber-600" />
                            <span>Settled Outward Invoices Breakdown</span>
                        </div>

                        <div class="border border-slate-200 rounded-xl overflow-hidden print:border-black">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase text-[10px] border-b border-slate-200 print:bg-slate-100 print:border-black">
                                        <th class="py-2.5 px-3">#</th>
                                        <th class="py-2.5 px-3">Outward No</th>
                                        <th class="py-2.5 px-3">Challan / Date</th>
                                        <th class="py-2.5 px-3 text-right">Invoice Amount (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Discount (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Amount Received (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Commission (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Total Settled (₹)</th>
                                        <th class="py-2.5 px-3">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700 print:divide-black">
                                    <tr v-for="(it, idx) in (viewingPayment.items || [])" :key="it.id">
                                        <td class="py-2.5 px-3 font-mono text-slate-400">{{ idx + 1 }}</td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-slate-900">
                                            {{ it.outward?.entry_no || `OUT-${it.stock_outward_id}` }}
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-600">
                                            {{ it.outward?.challan_no || '—' }} ({{ formatDate(it.outward?.invoice_date) }})
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-800">
                                            {{ Number(it.invoice_amount || it.outward?.total_amount || 0).toLocaleString() }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-red-600">
                                            {{ Number(it.discount_amount) > 0 ? Number(it.discount_amount).toLocaleString() : '—' }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800">
                                            {{ Number(it.paid_amount || 0).toLocaleString() }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-indigo-900">
                                            <span v-if="Number(it.commission_amount) > 0">
                                                {{ Number(it.commission_amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                            </span>
                                            <span v-else class="text-slate-400 font-normal">—</span>
                                            <div v-if="it.commission_agent?.name || it.commission_agent_name" class="text-[10px] text-slate-500 font-sans font-normal truncate max-w-[120px] ml-auto">
                                                {{ it.commission_agent?.name || it.commission_agent_name }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                            {{ (Number(it.paid_amount || 0) + Number(it.discount_amount || 0)).toLocaleString() }}
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-500">
                                            {{ it.remarks || '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary Breakdown -->
                    <div class="flex justify-end">
                        <div class="w-full sm:w-80 bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs print:bg-transparent print:border print:border-black">
                            <div class="flex justify-between text-slate-600">
                                <span>Total Invoices Settled:</span>
                                <span class="font-mono font-semibold">{{ (viewingPayment.items || []).length }}</span>
                            </div>
                            <div v-if="Number(viewingPayment.discount_amount) > 0" class="flex justify-between text-slate-600">
                                <span>Discount Allowed:</span>
                                <span class="font-mono font-semibold text-red-600">{{ formatCurrency(viewingPayment.discount_amount) }}</span>
                            </div>
                            <div v-if="Number(viewingPayment.commission_amount) > 0" class="flex justify-between text-slate-600">
                                <span>Commission Payable:</span>
                                <span class="font-mono font-bold text-indigo-700">{{ formatCurrency(viewingPayment.commission_amount) }}</span>
                            </div>
                            <div class="pt-2 border-t-2 border-slate-800 flex justify-between font-extrabold text-slate-900 text-sm print:border-black">
                                <span>Total Amount Received:</span>
                                <span class="font-mono text-emerald-800">{{ formatCurrency(viewingPayment.amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures Block -->
                    <div class="grid grid-cols-2 gap-8 pt-8 text-center text-xs text-slate-700">
                        <div class="border-t border-slate-300 pt-2">
                            <span class="font-medium">Accounts Officer</span>
                        </div>
                        <div class="border-t border-slate-300 pt-2">
                            <span class="font-medium">Authorized Signatory</span>
                        </div>
                    </div>
                </div>

                <!-- Voucher Footer -->
                <div class="px-6 py-4 border-t border-slate-200 flex items-center justify-end bg-slate-50 print:bg-white">
                    <button
                        type="button"
                        @click="closeViewModal"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold print:hidden cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div
            v-if="isDeleteModalOpen && deletingPayment"
            @click.self="isDeleteModalOpen = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 text-red-600">
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200">
                        <AlertCircle class="w-6 h-6 text-red-600" />
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm">Delete Payment Receipt?</h4>
                        <p class="text-xs text-slate-500 font-mono">{{ deletingPayment.payment_no }} ({{ formatCurrency(deletingPayment.amount) }})</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Are you sure you want to delete this payment receipt voucher? Deleting this voucher will restore the outstanding due balance on all associated outward invoices.
                </p>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="handleDelete"
                        :disabled="paymentStore.isSaving"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2 cursor-pointer"
                    >
                        <RefreshCw v-if="paymentStore.isSaving" class="w-4 h-4 animate-spin" />
                        <span>Delete Receipt</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
