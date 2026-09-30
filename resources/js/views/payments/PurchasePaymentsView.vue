<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePurchasePaymentStore } from '../../stores/payment';
import type {
    PurchasePayment,
    PurchasePaymentForm,
    PurchasePaymentItemInput,
    PendingInwardInvoice,
} from '../../types/payment';
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
    ArrowRight
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const paymentStore = usePurchasePaymentStore();

// Modal States
const isFormModalOpen = ref(false);
const isViewModalOpen = ref(false);
const isDeleteModalOpen = ref(false);

const editingPayment = ref<PurchasePayment | null>(null);
const viewingPayment = ref<PurchasePayment | null>(null);
const deletingPayment = ref<PurchasePayment | null>(null);

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

const initialForm = (): PurchasePaymentForm => ({
    payment_no: paymentStore.nextPaymentNo || 'PAY-0001',
    payment_date: getTodayString(),
    party_id: null,
    party_name: '',
    payment_mode: 'bank_transfer',
    reference_no: '',
    bank_name: '',
    amount: 0,
    remarks: '',
    items: [],
});

const form = ref<PurchasePaymentForm>(initialForm());

onMounted(async () => {
    if (!paymentStore.fromDateFilter) {
        paymentStore.fromDateFilter = getMonthFirstDateString();
    }
    if (!paymentStore.toDateFilter) {
        paymentStore.toDateFilter = getMonthLastDateString();
    }
    await paymentStore.fetchMeta();
    await paymentStore.fetchPayments(1);

    // Auto-open modal if navigated from Inward View with query params
    if (route.query.create === '1' || route.query.party_id) {
        const pId = route.query.party_id ? Number(route.query.party_id) : undefined;
        const inwId = route.query.inward_id ? Number(route.query.inward_id) : undefined;
        await openCreateModal(pId, inwId);
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
    paymentStore.supplierFilter = 'all';
    paymentStore.modeFilter = 'all';
    paymentStore.fromDateFilter = getMonthFirstDateString();
    paymentStore.toDateFilter = getMonthLastDateString();
    paymentStore.fetchPayments(1);
};

const selectedInwardToAdd = ref<number | null>(null);

const unaddedPendingInvoices = computed(() => {
    const existingIds = new Set(form.value.items.map((it) => it.stock_inward_id));
    return paymentStore.pendingInvoices.filter((inv) => !existingIds.has(inv.id));
});

const formatDate = (val: string | null | undefined): string => {
    if (!val) return '—';
    return String(val).split('T')[0];
};

// Add a single pending invoice to the voucher items
const addInvoiceToVoucher = (inwardId: number, autoPayFull: boolean = true) => {
    const inv = paymentStore.pendingInvoices.find((i) => i.id === inwardId);
    if (!inv) return;

    const existing = form.value.items.find((it) => it.stock_inward_id === inwardId);
    if (!existing) {
        form.value.items.push({
            stock_inward_id: inv.id,
            paid_amount: autoPayFull ? inv.balance_due : 0,
            remarks: '',
            inward_no: inv.inward_no,
            challan_no: inv.challan_no || '',
            challan_date: inv.challan_date || '',
            grand_total: inv.grand_total,
            already_paid: inv.already_paid_others,
            balance_due: inv.balance_due,
            materials_summary: inv.materials_summary,
        });
    } else if (autoPayFull) {
        existing.paid_amount = existing.balance_due || 0;
    }
    syncTotalAmountFromItems();
    selectedInwardToAdd.value = null;
};

// Remove an invoice from the voucher items
const removeInvoiceFromVoucher = (index: number) => {
    form.value.items.splice(index, 1);
    syncTotalAmountFromItems();
};

// Add ALL pending invoices for this supplier to the voucher
const addAllPendingInvoices = (autoPayFull: boolean = false) => {
    for (const inv of paymentStore.pendingInvoices) {
        addInvoiceToVoucher(inv.id, autoPayFull);
    }
    if (autoPayFull) {
        syncTotalAmountFromItems();
    }
};

// When vendor changes in modal form, fetch their pending invoices
const onPartyChange = async (targetInwardId?: number) => {
    if (!form.value.party_id) {
        form.value.party_name = '';
        form.value.items = [];
        return;
    }

    const sup = paymentStore.suppliers.find((s) => s.id === form.value.party_id);
    if (sup) {
        form.value.party_name = sup.name;
    }

    const excludeId = editingPayment.value ? editingPayment.value.id : 0;
    await paymentStore.fetchPendingInvoices(form.value.party_id, excludeId);

    // If a specific inward was selected (e.g. from Inward list Pay button), add ONLY that one!
    if (targetInwardId) {
        form.value.items = [];
        addInvoiceToVoucher(targetInwardId, true);
    } else if (!editingPayment.value) {
        // If supplier selected manually from dropdown:
        // Populate all pending invoices with amount 0 (or balanced if single invoice)
        form.value.items = paymentStore.pendingInvoices.map((inv) => ({
            stock_inward_id: inv.id,
            paid_amount: paymentStore.pendingInvoices.length === 1 ? inv.balance_due : 0,
            remarks: '',
            inward_no: inv.inward_no,
            challan_no: inv.challan_no || '',
            challan_date: inv.challan_date || '',
            grand_total: inv.grand_total,
            already_paid: inv.already_paid_others,
            balance_due: inv.balance_due,
            materials_summary: inv.materials_summary,
        }));
        syncTotalAmountFromItems();
    }
};

// Auto allocate total payment amount across pending invoices in FIFO order
const autoAllocateTotal = () => {
    let remaining = Number(form.value.amount) || 0;

    for (const item of form.value.items) {
        const due = Number(item.balance_due) || 0;
        if (remaining <= 0) {
            item.paid_amount = 0;
        } else if (remaining >= due) {
            item.paid_amount = due;
            remaining -= due;
        } else {
            item.paid_amount = Math.round(remaining * 100) / 100;
            remaining = 0;
        }
    }
};

// Set full balance on a single invoice
const payFullInvoice = (item: PurchasePaymentItemInput) => {
    item.paid_amount = item.balance_due || 0;
    syncTotalAmountFromItems();
};

// Clear payment on a single invoice
const clearInvoice = (item: PurchasePaymentItemInput) => {
    item.paid_amount = 0;
    syncTotalAmountFromItems();
};

// Settle all invoices with full balance due
const payAllInvoices = () => {
    for (const item of form.value.items) {
        item.paid_amount = item.balance_due || 0;
    }
    syncTotalAmountFromItems();
};

// Clear all allocations to 0
const clearAllInvoices = () => {
    for (const item of form.value.items) {
        item.paid_amount = 0;
    }
    syncTotalAmountFromItems();
};

const syncTotalAmountFromItems = () => {
    const sum = form.value.items.reduce((acc, it) => acc + (Number(it.paid_amount) || 0), 0);
    form.value.amount = Math.round(sum * 100) / 100;
};

// Total allocated across items in form
const totalAllocatedAmount = computed(() => {
    return form.value.items.reduce((acc, it) => acc + (Number(it.paid_amount) || 0), 0);
});

const isAllocationValid = computed(() => {
    const total = Number(form.value.amount) || 0;
    const allocated = totalAllocatedAmount.value;
    return total > 0 && Math.abs(total - allocated) < 0.01;
});

const openCreateModal = async (preselectedPartyId?: number, preselectedInwardId?: number) => {
    paymentStore.clearMessages();
    editingPayment.value = null;
    await paymentStore.fetchMeta();

    form.value = {
        ...initialForm(),
        payment_no: paymentStore.nextPaymentNo || 'PAY-0001',
        party_id: preselectedPartyId || null,
    };

    if (form.value.party_id) {
        await onPartyChange(preselectedInwardId);
    }

    isFormModalOpen.value = true;
};

const openEditModal = async (pay: PurchasePayment) => {
    paymentStore.clearMessages();
    editingPayment.value = pay;
    await paymentStore.fetchMeta();

    form.value = {
        payment_no: pay.payment_no,
        payment_date: pay.payment_date,
        party_id: pay.party_id || null,
        party_name: pay.party_name || pay.party?.name || '',
        payment_mode: pay.payment_mode || 'bank_transfer',
        reference_no: pay.reference_no || '',
        bank_name: pay.bank_name || '',
        amount: Number(pay.amount) || 0,
        remarks: pay.remarks || '',
        items: [],
    };

    if (pay.party_id) {
        await paymentStore.fetchPendingInvoices(pay.party_id, pay.id);

        form.value.items = paymentStore.pendingInvoices.map((inv) => {
            const existing = (pay.items || []).find((pi) => pi.stock_inward_id === inv.id);
            return {
                stock_inward_id: inv.id,
                paid_amount: existing ? Number(existing.paid_amount) : (inv.current_allocated || 0),
                remarks: existing?.remarks || '',
                inward_no: inv.inward_no,
                challan_no: inv.challan_no || '',
                challan_date: inv.challan_date || '',
                grand_total: inv.grand_total,
                already_paid: inv.already_paid_others,
                balance_due: inv.balance_due,
                materials_summary: inv.materials_summary,
            };
        });
    }

    isFormModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
    editingPayment.value = null;
};

const openViewModal = (pay: PurchasePayment) => {
    viewingPayment.value = pay;
    isViewModalOpen.value = true;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    viewingPayment.value = null;
};

const confirmDelete = (pay: PurchasePayment) => {
    paymentStore.clearMessages();
    deletingPayment.value = pay;
    isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
    if (deletingPayment.value) {
        await paymentStore.deletePayment(deletingPayment.value.id);
        isDeleteModalOpen.value = false;
        deletingPayment.value = null;
    }
};

const submitForm = async () => {
    // Filter out items with 0 paid amount
    const activeItems = form.value.items.filter((it) => Number(it.paid_amount) > 0);

    const payload: PurchasePaymentForm = {
        ...form.value,
        items: activeItems,
    };

    if (editingPayment.value) {
        await paymentStore.updatePayment(editingPayment.value.id, payload);
    } else {
        await paymentStore.createPayment(payload);
    }

    closeFormModal();
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

const printVoucher = () => {
    window.print();
};

const getModeLabel = (mode: string): string => {
    switch (mode) {
        case 'bank_transfer':
            return 'Bank Transfer (NEFT/RTGS)';
        case 'cheque':
            return 'Cheque / DD';
        case 'upi':
            return 'UPI / NetBanking';
        case 'cash':
            return 'Cash';
        default:
            return mode || 'Other';
    }
};

defineExpose({
    openCreateModal,
});
</script>

<template>
    <AppLayout title="Purchase & Vendor Payments" subtitle="Record supplier payments, settle partial/multi-invoices & maintain ledger vouchers">
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
                <button @click="paymentStore.clearMessages" class="text-emerald-600 hover:text-emerald-800">
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
                        <span>{{ paymentStore.error || 'Please correct the following payment errors' }}</span>
                    </div>
                    <button @click="paymentStore.clearMessages" class="text-red-600 hover:text-red-800">
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
                            placeholder="Search Voucher #, supplier, UTR/Cheque..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        />
                    </div>

                    <!-- Supplier Filter -->
                    <select
                        v-model="paymentStore.supplierFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Suppliers / Vendors</option>
                        <option v-for="sup in paymentStore.suppliers" :key="sup.id" :value="sup.id">
                            {{ sup.name }} ({{ sup.code }})
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
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors"
                        title="Refresh List"
                    >
                        <RefreshCw :class="['w-4 h-4', paymentStore.isLoading ? 'animate-spin' : '']" />
                    </button>

                    <button
                        type="button"
                        @click="resetFilters"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-amber-600 border border-slate-200 transition-colors"
                        title="Reset Filters"
                    >
                        <RotateCcw class="w-4 h-4" />
                    </button>
                </div>

                <!-- Add Payment Button -->
                <button
                    type="button"
                    @click="openCreateModal()"
                    class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 shadow-md shadow-amber-500/20 transition-all active:scale-95 whitespace-nowrap"
                >
                    <Plus class="w-4 h-4" />
                    <span>Record Payment</span>
                </button>
            </div>

            <!-- Payment Records (Table + Mobile Cards) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- Mobile Card List View (< md) -->
                <div class="block md:hidden divide-y divide-slate-100">
                    <div v-if="paymentStore.isLoading && paymentStore.payments.length === 0" class="py-12 text-center text-slate-500">
                        <div class="inline-flex items-center gap-2 text-xs">
                            <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                            <span>Loading purchase payments...</span>
                        </div>
                    </div>

                    <div v-else-if="paymentStore.payments.length === 0" class="py-12 px-4 text-center text-slate-500 space-y-2">
                        <Receipt class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="font-bold text-slate-800 text-sm">No Purchase Payments Found</div>
                        <p class="text-xs text-slate-500">Record payments to suppliers against inward purchase shipments.</p>
                    </div>

                    <!-- Mobile Cards -->
                    <div
                        v-for="pay in paymentStore.payments"
                        :key="pay.id"
                        class="p-4 space-y-3 hover:bg-slate-50 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-slate-900 text-xs">
                                    {{ pay.payment_no }}
                                </span>
                                <span class="text-[11px] font-mono text-slate-500">{{ pay.payment_date }}</span>
                            </div>
                            <span class="px-2 py-0.5 rounded-full bg-cyan-50 border border-cyan-200 text-cyan-800 font-bold text-[10px] uppercase">
                                {{ pay.payment_mode }}
                            </span>
                        </div>

                        <div class="text-xs font-bold text-slate-800">
                            {{ pay.party?.name || pay.party_name || '—' }}
                        </div>

                        <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Ref / UTR / Cheque</span>
                                <span class="font-mono font-bold text-slate-800 block">{{ pay.reference_no || '—' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Bank / Account</span>
                                <span class="text-slate-700 block">{{ pay.bank_name || '—' }}</span>
                            </div>
                        </div>

                        <!-- Settled Invoices Chips -->
                        <div class="space-y-1">
                            <span class="text-[10px] uppercase text-slate-400 font-bold block">Invoices Paid:</span>
                            <div class="flex flex-wrap gap-1">
                                <div
                                    v-for="it in (pay.items || [])"
                                    :key="it.id"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200 text-[10px] font-mono"
                                >
                                    <span class="font-bold text-slate-800">{{ it.inward?.inward_no || `INW #${it.stock_inward_id}` }}:</span>
                                    <span class="text-emerald-800 font-bold">{{ formatCurrency(it.paid_amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Amount Banner -->
                        <div class="flex items-center justify-between bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200">
                            <span class="text-[10px] uppercase font-bold text-emerald-800">Voucher Total:</span>
                            <span class="font-mono font-bold text-emerald-900 text-sm">{{ formatCurrency(pay.amount) }}</span>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                @click="openViewModal(pay)"
                                class="px-3 py-1.5 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 border border-cyan-200 text-xs font-bold flex items-center gap-1"
                            >
                                <Eye class="w-3.5 h-3.5" />
                                <span>Voucher</span>
                            </button>
                            <button
                                type="button"
                                @click="openEditModal(pay)"
                                class="p-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs"
                                title="Edit Voucher"
                            >
                                <Edit3 class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="confirmDelete(pay)"
                                class="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs"
                                title="Delete Voucher"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table View (>= md) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider border-b border-slate-200 font-bold text-[11px]">
                                <th class="py-3.5 px-4">Voucher No</th>
                                <th class="py-3.5 px-4">Payment Date</th>
                                <th class="py-3.5 px-4">Supplier / Vendor</th>
                                <th class="py-3.5 px-4">Mode & Reference</th>
                                <th class="py-3.5 px-4">Invoices Settled</th>
                                <th class="py-3.5 px-4 text-right">Amount Paid</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-if="paymentStore.isLoading && paymentStore.payments.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading purchase payments...</span>
                                    </div>
                                </td>
                            </tr>

                            <tr v-else-if="paymentStore.payments.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <div class="space-y-2">
                                        <Receipt class="w-8 h-8 text-slate-400 mx-auto" />
                                        <div class="font-semibold text-slate-800">No Purchase Payments Found</div>
                                        <p class="text-[11px] text-slate-500">Record supplier payments against purchase invoices.</p>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-for="pay in paymentStore.payments"
                                :key="pay.id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900">
                                    {{ pay.payment_no }}
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-700">
                                    {{ pay.payment_date }}
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900">
                                        {{ pay.party?.name || pay.party_name || '—' }}
                                    </div>
                                    <div v-if="pay.party?.code" class="text-[10px] font-mono text-slate-400">
                                        {{ pay.party.code }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="inline-flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-semibold text-[10px]">
                                            {{ getModeLabel(pay.payment_mode) }}
                                        </span>
                                    </div>
                                    <div v-if="pay.reference_no" class="text-[10px] font-mono text-slate-500 mt-0.5">
                                        Ref: {{ pay.reference_no }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-sm">
                                        <div
                                            v-for="it in (pay.items || [])"
                                            :key="it.id"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200 text-[10px] font-mono"
                                        >
                                            <span class="font-bold text-slate-800">{{ it.inward?.inward_no || `INW #${it.stock_inward_id}` }}:</span>
                                            <span class="text-emerald-800 font-bold">{{ formatCurrency(it.paid_amount) }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-800 text-sm">
                                    {{ formatCurrency(pay.amount) }}
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openViewModal(pay)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-cyan-700 transition-colors border border-slate-200"
                                            title="View Payment Voucher"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openEditModal(pay)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-amber-700 transition-colors border border-slate-200"
                                            title="Edit Payment Voucher"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(pay)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-red-600 transition-colors border border-slate-200"
                                            title="Delete Payment Voucher"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer (5 Items Default) -->
                <div
                    v-if="paymentStore.meta.total > 0"
                    class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
                >
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (paymentStore.meta.current_page - 1) * paymentStore.meta.per_page + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(paymentStore.meta.current_page * paymentStore.meta.per_page, paymentStore.meta.total) }}</span>
                        of <span class="font-bold text-slate-900">{{ paymentStore.meta.total }}</span> payment vouchers
                    </div>
                    <div class="flex items-center gap-1.5" v-if="paymentStore.meta.last_page > 1">
                        <button
                            type="button"
                            :disabled="paymentStore.meta.current_page === 1"
                            @click="paymentStore.fetchPayments(paymentStore.meta.current_page - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in paymentStore.meta.last_page"
                            :key="p"
                            @click="paymentStore.fetchPayments(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all',
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
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Payment Voucher Modal -->
        <div
            v-if="isFormModalOpen"
            @click.self="closeFormModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden my-8">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700">
                            <CreditCard class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">
                                {{ editingPayment ? `Edit Payment Voucher: ${editingPayment.payment_no}` : 'Record Purchase / Vendor Payment' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Settle single or multi-invoices with partial/full payment allocation</p>
                        </div>
                    </div>
                    <button @click="closeFormModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Body -->
                <form @submit.prevent="submitForm" class="p-6 space-y-6 max-h-[82vh] overflow-y-auto custom-scrollbar">
                    <!-- General Details Grid -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                            <Wallet class="w-3.5 h-3.5 text-amber-600" />
                            <span>Voucher & Vendor Details</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Voucher No *</label>
                                <input
                                    v-model="form.payment_no"
                                    type="text"
                                    required
                                    placeholder="e.g. PAY-0001"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Payment Date *</label>
                                <input
                                    v-model="form.payment_date"
                                    type="date"
                                    required
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none cursor-pointer"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Supplier / Vendor *</label>
                                <select
                                    v-model.number="form.party_id"
                                    @change="onPartyChange"
                                    required
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-semibold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option :value="null">-- Select Supplier / Vendor --</option>
                                    <option v-for="s in paymentStore.suppliers" :key="s.id" :value="s.id">
                                        {{ s.name }} ({{ s.code }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-200">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Payment Mode *</label>
                                <select
                                    v-model="form.payment_mode"
                                    required
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-semibold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option v-for="m in paymentStore.paymentModes" :key="m.value" :value="m.value">
                                        {{ m.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Reference / UTR / Cheque No</label>
                                <input
                                    v-model="form.reference_no"
                                    type="text"
                                    placeholder="e.g. UTR-20260925-998811"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Bank Name</label>
                                <input
                                    v-model="form.bank_name"
                                    type="text"
                                    placeholder="e.g. HDFC Bank Ltd"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Invoice Allocation Section -->
                    <div class="space-y-3">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                            <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                                <FileText class="w-3.5 h-3.5 text-amber-600" />
                                <span>Invoices in this Voucher ({{ form.items.length }})</span>
                            </div>

                            <div v-if="form.party_id" class="flex flex-wrap items-center gap-2">
                                <!-- Selector to add another invoice if available -->
                                <div v-if="unaddedPendingInvoices.length > 0" class="flex items-center gap-1.5">
                                    <select
                                        v-model="selectedInwardToAdd"
                                        @change="selectedInwardToAdd && addInvoiceToVoucher(selectedInwardToAdd, true)"
                                        class="px-2.5 py-1.5 bg-amber-50/60 border border-amber-300 rounded-xl text-slate-900 text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                    >
                                        <option :value="null">+ Add Inward Invoice ({{ unaddedPendingInvoices.length }} pending)...</option>
                                        <option v-for="inv in unaddedPendingInvoices" :key="inv.id" :value="inv.id">
                                            {{ inv.inward_no }} ({{ inv.challan_no || 'No Challan' }}) — Due: ₹{{ Number(inv.balance_due).toLocaleString('en-IN') }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="addAllPendingInvoices(false)"
                                        class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-xs font-semibold transition-colors"
                                        title="Add all remaining pending invoices to voucher"
                                    >
                                        + Add All
                                    </button>
                                </div>

                                <template v-if="form.items.length > 0">
                                    <button
                                        type="button"
                                        @click="payAllInvoices"
                                        class="px-2.5 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold transition-colors whitespace-nowrap"
                                        title="Allocate full balance due to all invoices"
                                    >
                                        Pay All Full
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearAllInvoices"
                                        class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 text-xs font-semibold transition-colors whitespace-nowrap"
                                        title="Reset all line amounts to 0"
                                    >
                                        Clear All
                                    </button>
                                    <div class="flex items-center gap-1 bg-slate-100 px-3 py-1 rounded-xl border border-slate-200 text-xs">
                                        <span class="text-slate-500 font-semibold">Total Amount:</span>
                                        <input
                                            v-model.number="form.amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            placeholder="0.00"
                                            class="w-28 px-2 py-0.5 bg-white border border-slate-300 rounded-lg text-emerald-800 font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none text-right"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        @click="autoAllocateTotal"
                                        class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-xs transition-colors whitespace-nowrap"
                                        title="Distribute total payment across invoices in FIFO order"
                                    >
                                        Auto-Allocate
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- State 1: No Supplier Selected Yet -->
                        <div v-if="!form.party_id" class="p-8 text-center bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                            <Building2 class="w-8 h-8 text-slate-400 mx-auto" />
                            <div class="text-xs font-bold text-slate-800">Please Select a Supplier / Vendor</div>
                            <p class="text-[11px] text-slate-500 max-w-md mx-auto">
                                Choose a supplier from the dropdown above to load and attach their inward invoices to this voucher.
                            </p>
                        </div>

                        <!-- State 2: Loading Invoices -->
                        <div v-else-if="paymentStore.isLoadingInvoices" class="py-8 text-center text-slate-500 bg-slate-50 border border-slate-200 rounded-xl">
                            <div class="inline-flex items-center gap-2 text-xs">
                                <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                                <span>Loading pending supplier invoices...</span>
                            </div>
                        </div>

                        <!-- State 3: Supplier has no pending invoices and none added -->
                        <div v-else-if="form.items.length === 0 && paymentStore.pendingInvoices.length === 0" class="p-8 text-center bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                            <CheckCircle2 class="w-6 h-6 text-emerald-500 mx-auto" />
                            <div class="text-xs font-bold text-slate-700">No Pending Invoices Found</div>
                            <p class="text-[11px] text-slate-400">This supplier has no outstanding unpaid inward shipments (All invoices settled).</p>
                        </div>

                        <!-- State 4: Invoices Table -->
                        <div v-else class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase text-[10px] border-b border-slate-200 font-bold">
                                        <th class="py-2.5 px-3">Inward #</th>
                                        <th class="py-2.5 px-3">Date</th>
                                        <th class="py-2.5 px-3">Materials</th>
                                        <th class="py-2.5 px-3 text-right">Invoice Total</th>
                                        <th class="py-2.5 px-3 text-right">Already Paid</th>
                                        <th class="py-2.5 px-3 text-right">Balance Due</th>
                                        <th class="py-2.5 px-3 text-right w-36">Amount to Pay (₹)</th>
                                        <th class="py-2.5 px-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr
                                        v-for="(it, idx) in form.items"
                                        :key="it.stock_inward_id"
                                        :class="[Number(it.paid_amount) > 0 ? 'bg-amber-50/40' : '']"
                                    >
                                        <td class="py-2.5 px-3 font-mono font-bold text-slate-900">
                                            {{ it.inward_no }}
                                            <div v-if="it.challan_no" class="text-[10px] font-normal text-slate-400">
                                                Challan: {{ it.challan_no }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-slate-600">{{ formatDate(it.challan_date) }}</td>
                                        <td class="py-2.5 px-3 text-slate-600 max-w-xs truncate" :title="it.materials_summary">
                                            {{ it.materials_summary || '—' }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                            {{ formatCurrency(it.grand_total) }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-500">
                                            {{ formatCurrency(it.already_paid) }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-red-700">
                                            {{ formatCurrency(it.balance_due) }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right">
                                            <input
                                                v-model.number="it.paid_amount"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                :max="it.balance_due"
                                                @input="syncTotalAmountFromItems"
                                                placeholder="0.00"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-right font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <div class="inline-flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    @click="payFullInvoice(it)"
                                                    class="px-2 py-1 rounded bg-slate-100 hover:bg-emerald-100 text-slate-700 hover:text-emerald-800 text-[10px] font-bold transition-colors border border-slate-200"
                                                    title="Allocate full balance due"
                                                >
                                                    Pay Full
                                                </button>
                                                <button
                                                    v-if="Number(it.paid_amount) > 0"
                                                    type="button"
                                                    @click="clearInvoice(it)"
                                                    class="px-1.5 py-1 rounded bg-slate-100 hover:bg-amber-100 text-slate-500 hover:text-amber-700 text-[10px] transition-colors border border-slate-200"
                                                    title="Reset amount to 0"
                                                >
                                                    Clear
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="removeInvoiceFromVoucher(idx)"
                                                    class="p-1 rounded bg-slate-100 hover:bg-red-100 text-slate-400 hover:text-red-600 text-[10px] transition-colors border border-slate-200"
                                                    title="Remove invoice from this payment voucher"
                                                >
                                                    <Trash2 class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Allocation Summary & Remarks -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Voucher Remarks / Notes</label>
                            <textarea
                                v-model="form.remarks"
                                rows="3"
                                placeholder="e.g. Settle cement bills via RTGS, verified with vendor ledger..."
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none resize-none"
                            ></textarea>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                            <div class="flex justify-between text-slate-600">
                                <span>Voucher Payment Total:</span>
                                <span class="font-mono font-bold text-slate-900">{{ formatCurrency(form.amount) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Total Allocated to Invoices:</span>
                                <span class="font-mono font-bold text-emerald-800">{{ formatCurrency(totalAllocatedAmount) }}</span>
                            </div>
                            <div class="pt-2 border-t border-slate-200 flex justify-between font-bold">
                                <span>Allocation Status:</span>
                                <span v-if="isAllocationValid" class="text-emerald-700 flex items-center gap-1 font-semibold">
                                    <Check class="w-4 h-4" /> 100% Balanced
                                </span>
                                <span v-else class="text-red-600 font-semibold">
                                    Mismatch: Difference of {{ formatCurrency(Math.abs((form.amount || 0) - totalAllocatedAmount)) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeFormModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="paymentStore.isSaving || totalAllocatedAmount <= 0"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-md shadow-amber-500/20 disabled:opacity-50"
                        >
                            <RefreshCw v-if="paymentStore.isSaving" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ paymentStore.isSaving ? 'Saving...' : (editingPayment ? 'Update Voucher' : 'Record Payment') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- View Payment Voucher Slip Modal -->
        <div
            v-if="isViewModalOpen && viewingPayment"
            @click.self="closeViewModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden my-8 print:border-none print:shadow-none print:w-full print:m-0">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 print:bg-white">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 print:hidden">
                            <Receipt class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-slate-900">
                                Purchase Payment Voucher: {{ viewingPayment.payment_no }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Vendor disbursement voucher & invoice settlement summary</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 print:hidden">
                        <button
                            type="button"
                            @click="printVoucher"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors border border-slate-200"
                        >
                            <Printer class="w-3.5 h-3.5" />
                            <span>Print Voucher</span>
                        </button>
                        <button @click="closeViewModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Voucher Body -->
                <div class="p-6 space-y-6 max-h-[80vh] overflow-y-auto custom-scrollbar print:max-h-none print:p-0">
                    <!-- Top Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 print:bg-transparent print:border-black">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Supplier / Payee</div>
                            <div class="text-xs font-bold text-amber-800">
                                {{ viewingPayment.party?.name || viewingPayment.party_name || '—' }}
                            </div>
                            <div v-if="viewingPayment.party?.gstin" class="text-[10px] font-mono text-slate-500">
                                GSTIN: {{ viewingPayment.party.gstin }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Payment Date</div>
                            <div class="text-xs font-mono font-bold text-slate-900">{{ viewingPayment.payment_date }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Payment Mode</div>
                            <div class="text-xs font-bold text-slate-900 uppercase">{{ getModeLabel(viewingPayment.payment_mode) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Reference / UTR #</div>
                            <div class="text-xs font-mono font-bold text-slate-900">{{ viewingPayment.reference_no || '—' }}</div>
                        </div>
                        <div v-if="viewingPayment.bank_name">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Bank Name</div>
                            <div class="text-xs text-slate-800">{{ viewingPayment.bank_name }}</div>
                        </div>
                        <div v-if="viewingPayment.remarks" class="sm:col-span-3">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Remarks</div>
                            <div class="text-xs text-slate-700">{{ viewingPayment.remarks }}</div>
                        </div>
                    </div>

                    <!-- Settled Invoices Table -->
                    <div class="space-y-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                            <Layers class="w-3.5 h-3.5 text-amber-600" />
                            <span>Invoices Covered in this Payment Voucher</span>
                        </div>

                        <div class="border border-slate-200 rounded-xl overflow-hidden print:border-black">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase text-[10px] border-b border-slate-200 print:bg-slate-100 print:border-black">
                                        <th class="py-2.5 px-3">#</th>
                                        <th class="py-2.5 px-3">Inward / Invoice #</th>
                                        <th class="py-2.5 px-3">Invoice Date</th>
                                        <th class="py-2.5 px-3 text-right">Invoice Amount (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Payment Allocated (₹)</th>
                                        <th class="py-2.5 px-3">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700 print:divide-black">
                                    <tr v-for="(it, idx) in (viewingPayment.items || [])" :key="it.id">
                                        <td class="py-2.5 px-3 font-mono text-slate-400">{{ idx + 1 }}</td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-slate-900">
                                            {{ it.inward?.inward_no || `INW #${it.stock_inward_id}` }}
                                            <span v-if="it.inward?.challan_no" class="text-slate-400 font-normal">({{ it.inward.challan_no }})</span>
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-slate-600">
                                            {{ it.inward?.challan_date || it.inward?.inward_date || '—' }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-800">
                                            {{ formatCurrency(it.invoice_amount || it.inward?.grand_total) }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800">
                                            {{ formatCurrency(it.paid_amount) }}
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-500">{{ it.remarks || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Total Voucher Amount Banner -->
                    <div class="flex justify-end">
                        <div class="w-full sm:w-80 bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs print:bg-transparent print:border-black">
                            <div class="flex justify-between font-extrabold text-slate-900 text-sm">
                                <span>Total Paid:</span>
                                <span class="font-mono text-emerald-800">{{ formatCurrency(viewingPayment.amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures for Print -->
                    <div class="hidden print:grid grid-cols-3 gap-8 pt-12 border-t border-black text-center text-xs">
                        <div>
                            <div class="border-t border-black pt-1 font-semibold">Prepared By</div>
                        </div>
                        <div>
                            <div class="border-t border-black pt-1 font-semibold">Checked / Verified By</div>
                        </div>
                        <div>
                            <div class="border-t border-black pt-1 font-semibold">Authorized Signatory</div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-200 flex items-center justify-end bg-slate-50 print:hidden">
                    <button
                        type="button"
                        @click="closeViewModal"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
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
                        <h4 class="font-bold text-slate-900 text-sm">Delete Payment Voucher?</h4>
                        <p class="text-xs text-slate-500 font-mono">{{ deletingPayment.payment_no }} ({{ formatCurrency(deletingPayment.amount) }})</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Are you sure you want to delete this payment voucher? Deleting this voucher will revert the payment status and restore the unpaid balance on all linked purchase invoices.
                </p>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="handleDelete"
                        :disabled="paymentStore.isSaving"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                    >
                        <RefreshCw v-if="paymentStore.isSaving" class="w-4 h-4 animate-spin" />
                        <span>Delete Voucher</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
