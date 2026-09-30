<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useStockInwardStore } from '../../stores/inward';
import type { StockInward, StockInwardForm, StockInwardItemInput } from '../../types/inward';
import AppLayout from '../../components/layout/AppLayout.vue';
import { useEscapeKey } from '../../composables/useEscapeKey';
import {
    Plus,
    Search,
    Edit3,
    Trash2,
    Eye,
    Truck,
    Layers,
    AlertCircle,
    CheckCircle2,
    X,
    RefreshCw,
    RotateCcw,
    Printer,
    PlusCircle,
    Trash,
    FileText,
    CreditCard
} from 'lucide-vue-next';

const router = useRouter();
const inwardStore = useStockInwardStore();

const UNIT_OPTIONS = ['ton', 'kgs', 'bags', 'pcs', 'ltr', 'cum', 'm3', 'nos', 'box', 'meter', 'barrel', 'gm'];

// Modal States
const isFormModalOpen = ref(false);
const isViewModalOpen = ref(false);
const isDeleteModalOpen = ref(false);

const editingInward = ref<StockInward | null>(null);
const viewingInward = ref<StockInward | null>(null);
const deletingInward = ref<StockInward | null>(null);

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

const recalculateRow = (row: StockInwardItemInput) => {
    const qty = Number(row.qty_primary) || 0;
    const rate = Number(row.rate_per_unit) || 0;
    const gstRate = Number(row.gst_rate) || 0;
    const taxable = Math.round(qty * rate * 100) / 100;
    const gstAmt = Math.round(taxable * (gstRate / 100) * 100) / 100;
    const total = Math.round((taxable + gstAmt) * 100) / 100;

    row.taxable_amount = taxable;
    row.gst_amount = gstAmt;
    row.total_amount = total;
};

const initialItemRow = (): StockInwardItemInput => {
    const firstRm = inwardStore.rmItems[0];
    const factor = firstRm ? Number(firstRm.conversion_factor) || 1000 : 1000;
    const gstRate = firstRm ? Number(firstRm.gst_per) || 0 : 0;
    const row: StockInwardItemInput = {
        item_id: firstRm ? firstRm.id : 0,
        qty_primary: 10,
        unit_primary: firstRm?.primary_unit || 'ton',
        qty_secondary: 10 * factor,
        unit_secondary: firstRm?.secondary_unit || 'kgs',
        conversion_factor: factor,
        rate_per_unit: 0,
        taxable_amount: 0,
        gst_rate: gstRate,
        gst_amount: 0,
        total_amount: 0,
        remarks: '',
        item: firstRm,
    };
    recalculateRow(row);
    return row;
};

const initialForm = (): StockInwardForm => ({
    inward_no: inwardStore.nextInwardNo || 'INW-0001',
    inward_date: getTodayString(),
    supplier_party_id: null,
    supplier_name: '',
    challan_no: '',
    challan_date: getTodayString(),
    supplier_bill_no: '',
    supplier_bill_date: getTodayString(),
    vehicle_no: '',
    driver_name: '',
    driver_phone: '',
    received_by_user_id: null,
    remarks: '',
    freight_amount: 0,
    other_charges: 0,
    discount_amount: 0,
    items: [],
});

const form = ref<StockInwardForm>(initialForm());

onMounted(async () => {
    if (!inwardStore.fromDateFilter) {
        inwardStore.fromDateFilter = getMonthFirstDateString();
    }
    if (!inwardStore.toDateFilter) {
        inwardStore.toDateFilter = getMonthLastDateString();
    }
    await inwardStore.fetchMeta();
    await inwardStore.fetchInwards(1);
});

const handleSearch = () => {
    inwardStore.fetchInwards(1);
};

const handleFilterChange = () => {
    inwardStore.fetchInwards(1);
};

const resetFilters = () => {
    inwardStore.searchQuery = '';
    inwardStore.supplierFilter = 'all';
    inwardStore.paymentStatusFilter = 'all';
    inwardStore.fromDateFilter = getMonthFirstDateString();
    inwardStore.toDateFilter = getMonthLastDateString();
    inwardStore.fetchInwards(1);
};

// When Supplier is selected from dropdown, sync name
const onSupplierChange = () => {
    const s = inwardStore.suppliers.find((sup) => sup.id === form.value.supplier_party_id);
    if (s) {
        form.value.supplier_name = s.name;
    }
};

// Add item row
const addItemRow = () => {
    form.value.items.push(initialItemRow());
};

// Remove item row
const removeItemRow = (index: number) => {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
};

// When RM item changes in row, sync units, conversion factor, and GST %
const onItemChange = (index: number) => {
    const row = form.value.items[index];
    const item = inwardStore.rmItems.find((i) => i.id === row.item_id);
    if (item) {
        row.item = item;
        row.unit_primary = item.primary_unit || 'ton';
        row.unit_secondary = item.secondary_unit || 'kgs';
        row.conversion_factor = Number(item.conversion_factor) || 1000;
        row.qty_secondary = Number(((row.qty_primary || 0) * row.conversion_factor).toFixed(4));
        row.gst_rate = Number(item.gst_per) || 0;
        recalculateRow(row);
    }
};

// When Primary Qty is changed, sync Secondary Qty & recalculate financials
const onPrimaryQtyChange = (index: number) => {
    const row = form.value.items[index];
    const factor = Number(row.conversion_factor) || 1;
    row.qty_secondary = Number(((Number(row.qty_primary) || 0) * factor).toFixed(4));
    recalculateRow(row);
};

// When Rate or GST % changes, recalculate row financials
const onFinancialChange = (index: number) => {
    const row = form.value.items[index];
    recalculateRow(row);
};

// Total primary qty in form
const totalFormPrimaryQty = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.qty_primary) || 0), 0);
});

// Computed totals for line items
const formSubtotal = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.taxable_amount) || 0), 0);
});

const formTaxTotal = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.gst_amount) || 0), 0);
});

const formGrandTotal = computed(() => {
    return Math.round((formSubtotal.value + formTaxTotal.value) * 100) / 100;
});

const openCreateModal = async () => {
    inwardStore.clearMessages();
    editingInward.value = null;
    await inwardStore.fetchMeta();

    form.value = {
        ...initialForm(),
        inward_no: inwardStore.nextInwardNo || 'INW-0001',
        supplier_party_id: inwardStore.suppliers[0]?.id || null,
        supplier_name: inwardStore.suppliers[0]?.name || '',
        items: [initialItemRow()],
    };
    isFormModalOpen.value = true;
};

const openEditModal = (inw: StockInward) => {
    inwardStore.clearMessages();
    editingInward.value = inw;

    form.value = {
        inward_no: inw.inward_no,
        inward_date: inw.challan_date || inw.inward_date || getTodayString(),
        supplier_party_id: inw.supplier_party_id || null,
        supplier_name: inw.supplier_name || (inw.supplier?.name ?? ''),
        challan_no: inw.challan_no || inw.supplier_bill_no || '',
        challan_date: inw.challan_date || inw.supplier_bill_date || getTodayString(),
        supplier_bill_no: inw.supplier_bill_no || inw.challan_no || '',
        supplier_bill_date: inw.supplier_bill_date || inw.challan_date || getTodayString(),
        vehicle_no: inw.vehicle_no || '',
        driver_name: inw.driver_name || '',
        driver_phone: inw.driver_phone || '',
        received_by_user_id: null,
        remarks: inw.remarks || '',
        freight_amount: 0,
        other_charges: 0,
        discount_amount: 0,
        items: (inw.items || []).map((it) => {
            const row: StockInwardItemInput = {
                item_id: it.item_id,
                qty_primary: Number(it.qty_primary),
                unit_primary: it.unit_primary || 'ton',
                qty_secondary: Number(it.qty_secondary),
                unit_secondary: it.unit_secondary || 'kgs',
                conversion_factor: Number(it.conversion_factor) || 1,
                rate_per_unit: Number(it.rate_per_unit) || 0,
                taxable_amount: Number(it.taxable_amount) || 0,
                gst_rate: Number(it.gst_rate) || 0,
                gst_amount: Number(it.gst_amount) || 0,
                total_amount: Number(it.total_amount) || 0,
                remarks: it.remarks || '',
                item: it.item,
            };
            recalculateRow(row);
            return row;
        }),
    };

    if (form.value.items.length === 0) {
        form.value.items.push(initialItemRow());
    }

    isFormModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
    editingInward.value = null;
};

const openViewModal = (inw: StockInward) => {
    viewingInward.value = inw;
    isViewModalOpen.value = true;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    viewingInward.value = null;
};

const confirmDelete = (inw: StockInward) => {
    inwardStore.clearMessages();
    deletingInward.value = inw;
    isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
    if (deletingInward.value) {
        await inwardStore.deleteInward(deletingInward.value.id);
        isDeleteModalOpen.value = false;
        deletingInward.value = null;
    }
};

const submitForm = async () => {
    // Keep inward_date and supplier_bill_no aligned with challan fields
    form.value.inward_date = form.value.challan_date || getTodayString();
    form.value.supplier_bill_no = form.value.challan_no;
    form.value.supplier_bill_date = form.value.challan_date;

    if (editingInward.value) {
        await inwardStore.updateInward(editingInward.value.id, form.value);
    } else {
        await inwardStore.createInward(form.value);
    }

    closeFormModal();
};

const closeAllModals = () => {
    if (isDeleteModalOpen.value) {
        isDeleteModalOpen.value = false;
        deletingInward.value = null;
    } else if (isViewModalOpen.value) {
        closeViewModal();
    } else if (isFormModalOpen.value) {
        closeFormModal();
    }
};

useEscapeKey(closeAllModals);

const navigateToPayments = (inw?: StockInward) => {
    if (inw && inw.supplier_party_id) {
        router.push({
            path: '/purchase-payments',
            query: { party_id: inw.supplier_party_id, inward_id: inw.id, create: '1' },
        });
    } else {
        router.push('/purchase-payments');
    }
};

const printSlip = () => {
    window.print();
};
</script>

<template>
    <AppLayout title="Stock Inward Entry" subtitle="Record Raw Material Receipts, Purchase Rates & Gate Passes">
        <div class="space-y-6">
            <!-- Feedback Alerts -->
            <div
                v-if="inwardStore.successMessage"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="w-5 h-5 flex-shrink-0 text-emerald-600" />
                    <span class="text-xs font-semibold">{{ inwardStore.successMessage }}</span>
                </div>
                <button @click="inwardStore.clearMessages" class="text-emerald-600 hover:text-emerald-800">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div
                v-if="inwardStore.error || inwardStore.validationErrors.length > 0"
                class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 space-y-2 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 font-bold text-xs">
                        <AlertCircle class="w-5 h-5 flex-shrink-0 text-red-600" />
                        <span>{{ inwardStore.error || 'Please correct the following errors' }}</span>
                    </div>
                    <button @click="inwardStore.clearMessages" class="text-red-600 hover:text-red-800">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <ul v-if="inwardStore.validationErrors.length > 0" class="pl-8 list-disc text-[11px] space-y-1 text-red-700 font-mono">
                    <li v-for="(msg, idx) in inwardStore.validationErrors" :key="idx">
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
                            v-model="inwardStore.searchQuery"
                            type="text"
                            placeholder="Search Inward #, challan, vehicle, supplier..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        />
                    </div>

                    <!-- Supplier Filter -->
                    <select
                        v-model="inwardStore.supplierFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Suppliers / Vendors</option>
                        <option v-for="sup in inwardStore.suppliers" :key="sup.id" :value="sup.id">
                            {{ sup.name }} ({{ sup.code }})
                        </option>
                    </select>

                    <!-- Payment Status Filter -->
                    <select
                        v-model="inwardStore.paymentStatusFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Payment Status</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partially_paid">Partially Paid</option>
                        <option value="paid">Fully Paid</option>
                    </select>

                    <!-- From Date Filter -->
                    <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-300">
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">From:</span>
                        <input
                            v-model="inwardStore.fromDateFilter"
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
                            v-model="inwardStore.toDateFilter"
                            type="date"
                            @change="handleFilterChange"
                            class="bg-transparent text-slate-800 text-xs font-mono focus:outline-none cursor-pointer"
                            title="To Date"
                        />
                    </div>

                    <button
                        type="button"
                        @click="inwardStore.fetchInwards(inwardStore.meta.current_page)"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors"
                        title="Refresh List"
                    >
                        <RefreshCw :class="['w-4 h-4', inwardStore.isLoading ? 'animate-spin' : '']" />
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

                <!-- Add Button -->
                <button
                    type="button"
                    @click="openCreateModal"
                    class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 shadow-md shadow-amber-500/20 transition-all active:scale-95 whitespace-nowrap"
                >
                    <Plus class="w-4 h-4" />
                    <span>New Stock Inward</span>
                </button>
            </div>

            <!-- Inward Records (Table + Mobile Cards) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- Mobile Card List View (< md) -->
                <div class="block md:hidden divide-y divide-slate-100">
                    <!-- Loading State -->
                    <div v-if="inwardStore.isLoading && inwardStore.inwards.length === 0" class="py-12 text-center text-slate-500">
                        <div class="inline-flex items-center gap-2 text-xs">
                            <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                            <span>Loading inward shipments...</span>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="inwardStore.inwards.length === 0" class="py-12 px-4 text-center text-slate-500 space-y-2">
                        <Truck class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="font-bold text-slate-800 text-sm">No Stock Inward Shipments Found</div>
                        <p class="text-xs text-slate-500">Record raw material deliveries to increase inventory balance.</p>
                    </div>

                    <!-- Mobile Cards -->
                    <div
                        v-for="inw in inwardStore.inwards"
                        :key="inw.id"
                        class="p-4 space-y-3 hover:bg-slate-50 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-slate-900 text-xs">
                                    {{ inw.inward_no }}
                                </span>
                                <span v-if="inw.supplier" class="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-800 font-bold text-[10px]">
                                    {{ inw.supplier.name }}
                                </span>
                            </div>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full font-bold text-[10px] uppercase border',
                                    inw.payment_status === 'paid'
                                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                        : inw.payment_status === 'partially_paid'
                                            ? 'bg-amber-50 text-amber-800 border-amber-200'
                                            : 'bg-slate-100 text-slate-600 border-slate-200'
                                ]"
                            >
                                {{ inw.payment_status === 'partially_paid' ? 'Partial' : (inw.payment_status || 'Unpaid') }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Vehicle / Driver</span>
                                <span class="font-bold text-slate-800 font-mono block">{{ inw.vehicle_no || '—' }}</span>
                                <span class="text-[11px] text-slate-600 block">{{ inw.driver_name || '' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Invoice / Challan No</span>
                                <span class="font-mono font-bold text-slate-800 block">{{ inw.challan_no || inw.supplier_bill_no || '—' }}</span>
                                <span v-if="inw.challan_date" class="text-[10px] text-slate-400 block">
                                    {{ inw.challan_date }}
                                </span>
                            </div>
                        </div>

                        <!-- Materials breakdown chips -->
                        <div class="flex flex-wrap gap-1 pt-1">
                            <div
                                v-for="it in (inw.items || [])"
                                :key="it.id"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] font-mono"
                            >
                                <span class="text-slate-500">{{ it.item?.category || 'RM' }}:</span>
                                <span class="text-amber-800 font-bold">{{ Number(it.qty_primary).toLocaleString() }} {{ it.unit_primary }}</span>
                                <span v-if="Number(it.qty_secondary) > 0" class="text-cyan-700">({{ Number(it.qty_secondary).toLocaleString() }} {{ it.unit_secondary }})</span>
                                <span v-if="Number(it.rate_per_unit) > 0" class="text-slate-400">@ ₹{{ Number(it.rate_per_unit).toLocaleString() }}</span>
                            </div>
                        </div>

                        <!-- Grand Total & Payment Summary Banner -->
                        <div class="space-y-1.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase font-bold text-slate-500">Invoice Total:</span>
                                <span class="font-mono font-bold text-slate-900">{{ formatCurrency(inw.grand_total) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500">Paid: {{ formatCurrency(inw.paid_amount) }}</span>
                                <span :class="Number(inw.grand_total) - Number(inw.paid_amount || 0) > 0 ? 'text-red-700 font-bold font-mono' : 'text-emerald-700 font-semibold'">
                                    Due: {{ formatCurrency(Math.max(0, Number(inw.grand_total) - Number(inw.paid_amount || 0))) }}
                                </span>
                            </div>
                        </div>

                        <!-- Mobile Card Actions -->
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button
                                v-if="inw.payment_status !== 'paid'"
                                type="button"
                                @click="navigateToPayments"
                                class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold flex items-center gap-1"
                                title="Pay / Settle Invoice"
                            >
                                <CreditCard class="w-3.5 h-3.5" />
                                <span>Pay</span>
                            </button>
                            <button
                                type="button"
                                @click="openViewModal(inw)"
                                class="px-3 py-1.5 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 border border-cyan-200 text-xs font-bold flex items-center gap-1"
                            >
                                <Eye class="w-3.5 h-3.5" />
                                <span>Slip</span>
                            </button>
                            <button
                                type="button"
                                @click="openEditModal(inw)"
                                class="p-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs"
                                title="Edit Inward"
                            >
                                <Edit3 class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="confirmDelete(inw)"
                                class="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs"
                                title="Delete Inward"
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
                                <th class="py-3.5 px-4">Inward No</th>
                                <th class="py-3.5 px-4">Supplier / Vendor</th>
                                <th class="py-3.5 px-4">Invoice / Challan No</th>
                                <th class="py-3.5 px-4">Challan Date</th>
                                <th class="py-3.5 px-4">Vehicle & Driver</th>
                                <th class="py-3.5 px-4">Materials Received</th>
                                <th class="py-3.5 px-4 text-right">Invoice & Payment</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="inwardStore.isLoading && inwardStore.inwards.length === 0">
                                <td colspan="8" class="py-12 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading inward shipments...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="inwardStore.inwards.length === 0">
                                <td colspan="8" class="py-12 text-center text-slate-500">
                                    <div class="space-y-2">
                                        <Truck class="w-8 h-8 text-slate-400 mx-auto" />
                                        <div class="font-semibold text-slate-800">No Stock Inward Shipments Found</div>
                                        <p class="text-[11px] text-slate-500">Record raw material deliveries to increase inventory balance.</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="inw in inwardStore.inwards"
                                :key="inw.id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900">
                                    {{ inw.inward_no }}
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900">
                                        {{ inw.supplier?.name || inw.supplier_name || '—' }}
                                    </div>
                                    <div v-if="inw.supplier?.code" class="text-[10px] font-mono text-slate-400">
                                        {{ inw.supplier.code }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 font-mono font-semibold text-slate-800">
                                    {{ inw.challan_no || inw.supplier_bill_no || '—' }}
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-600">
                                    {{ inw.challan_date || inw.supplier_bill_date || '—' }}
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-mono font-bold text-slate-800">
                                        {{ inw.vehicle_no || '—' }}
                                    </div>
                                    <div v-if="inw.driver_name" class="text-[10px] text-slate-500">
                                        {{ inw.driver_name }} {{ inw.driver_phone ? `(${inw.driver_phone})` : '' }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex flex-wrap gap-1.5 max-w-xs">
                                        <div
                                            v-for="it in (inw.items || [])"
                                            :key="it.id"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] font-mono"
                                        >
                                            <span class="text-slate-500">{{ it.item?.category || 'RM' }}:</span>
                                            <span class="text-amber-800 font-bold">{{ Number(it.qty_primary).toLocaleString() }} {{ it.unit_primary }}</span>
                                            <span v-if="Number(it.qty_secondary) > 0" class="text-cyan-700">({{ Number(it.qty_secondary).toLocaleString() }} {{ it.unit_secondary }})</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-mono font-bold text-slate-900">
                                        {{ formatCurrency(inw.grand_total) }}
                                    </div>
                                    <div class="flex items-center justify-end gap-1 mt-1">
                                        <span
                                            :class="[
                                                'px-1.5 py-0.5 rounded text-[10px] font-bold uppercase border',
                                                inw.payment_status === 'paid'
                                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                    : inw.payment_status === 'partially_paid'
                                                        ? 'bg-amber-50 text-amber-800 border-amber-200'
                                                        : 'bg-slate-100 text-slate-600 border-slate-200'
                                            ]"
                                        >
                                            {{ inw.payment_status === 'partially_paid' ? 'Partial' : (inw.payment_status || 'Unpaid') }}
                                        </span>
                                    </div>
                                    <div v-if="inw.payment_status === 'partially_paid'" class="text-[10px] font-mono text-slate-500 mt-0.5">
                                        Paid: {{ formatCurrency(inw.paid_amount) }}
                                    </div>
                                    <div v-if="Number(inw.grand_total) - Number(inw.paid_amount || 0) > 0" class="text-[10px] font-mono font-semibold text-red-600 mt-0.5">
                                        Due: {{ formatCurrency(Number(inw.grand_total) - Number(inw.paid_amount || 0)) }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            v-if="inw.payment_status !== 'paid'"
                                            type="button"
                                            @click="navigateToPayments(inw)"
                                            class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 transition-colors border border-emerald-200"
                                            title="Record Payment for Vendor"
                                        >
                                            <CreditCard class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openViewModal(inw)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-cyan-700 transition-colors border border-slate-200"
                                            title="View Inward Goods Slip"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openEditModal(inw)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-amber-700 transition-colors border border-slate-200"
                                            title="Edit Inward Entry"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(inw)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-red-600 transition-colors border border-slate-200"
                                            title="Delete Inward"
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
                    v-if="inwardStore.meta.total > 0"
                    class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
                >
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (inwardStore.meta.current_page - 1) * inwardStore.meta.per_page + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(inwardStore.meta.current_page * inwardStore.meta.per_page, inwardStore.meta.total) }}</span>
                        of <span class="font-bold text-slate-900">{{ inwardStore.meta.total }}</span> entries
                    </div>
                    <div class="flex items-center gap-1.5" v-if="inwardStore.meta.last_page > 1">
                        <button
                            type="button"
                            :disabled="inwardStore.meta.current_page === 1"
                            @click="inwardStore.fetchInwards(inwardStore.meta.current_page - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in inwardStore.meta.last_page"
                            :key="p"
                            @click="inwardStore.fetchInwards(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all',
                                inwardStore.meta.current_page === p
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            {{ p }}
                        </button>
                        <button
                            type="button"
                            :disabled="inwardStore.meta.current_page === inwardStore.meta.last_page"
                            @click="inwardStore.fetchInwards(inwardStore.meta.current_page + 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Inward Modal -->
        <div
            v-if="isFormModalOpen"
            @click.self="closeFormModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-6xl shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700">
                            <Truck class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">
                                {{ editingInward ? `Edit Inward Entry: ${editingInward.inward_no}` : 'New Stock Inward Entry' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Record supplier delivery, purchase commercials & credit items to inventory</p>
                        </div>
                    </div>
                    <button @click="closeFormModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form @submit.prevent="submitForm" class="p-6 space-y-6 max-h-[82vh] overflow-y-auto custom-scrollbar">
                    <!-- General Details Grid -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                            <FileText class="w-3.5 h-3.5 text-amber-600" />
                            <span>Shipment & Supplier Header</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- Inward No -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Inward No *</label>
                                <input
                                    v-model="form.inward_no"
                                    type="text"
                                    required
                                    placeholder="e.g. INW-0001"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Supplier Party -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Supplier / Vendor *</label>
                                <select
                                    v-model.number="form.supplier_party_id"
                                    @change="onSupplierChange"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-semibold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option :value="null">-- Select Registered Supplier / Vendor --</option>
                                    <option v-for="s in inwardStore.suppliers" :key="s.id" :value="s.id">
                                        {{ s.name }} ({{ s.code }})
                                    </option>
                                </select>
                            </div>

                            <!-- Supplier Name (Override/Manual) -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Supplier Business Name</label>
                                <input
                                    v-model="form.supplier_name"
                                    type="text"
                                    placeholder="e.g. UltraTech Cement Ltd"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Invoice/challanno No -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Invoice/challanno No</label>
                                <input
                                    v-model="form.challan_no"
                                    type="text"
                                    placeholder="e.g. INV-9842"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Challan Date -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Challan Date</label>
                                <input
                                    v-model="form.challan_date"
                                    type="date"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none cursor-pointer"
                                />
                            </div>

                            <!-- Vehicle No -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Vehicle / Truck No</label>
                                <input
                                    v-model="form.vehicle_no"
                                    type="text"
                                    placeholder="e.g. MH 12 AB 1234"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono font-bold text-xs uppercase focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Driver Name -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Driver Name</label>
                                <input
                                    v-model="form.driver_name"
                                    type="text"
                                    placeholder="e.g. Suresh Patil"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Driver Phone -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Driver Phone</label>
                                <input
                                    v-model="form.driver_phone"
                                    type="text"
                                    placeholder="e.g. +91 98220 00000"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Material Line Items & Commercials Table -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                                <Layers class="w-3.5 h-3.5 text-amber-600" />
                                <span>Raw Material Line Items, Rates & Taxes *</span>
                            </div>
                            <button
                                type="button"
                                @click="addItemRow"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold transition-all shadow-sm"
                            >
                                <PlusCircle class="w-3.5 h-3.5" />
                                <span>Add Item Row</span>
                            </button>
                        </div>

                        <div class="overflow-x-auto bg-white rounded-xl border border-slate-200">
                            <table class="w-full text-left text-xs min-w-[1050px]">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider border-b border-slate-200 font-bold text-[10px]">
                                        <th class="py-2.5 px-3">#</th>
                                        <th class="py-2.5 px-3 w-52">Raw Material Item *</th>
                                        <th class="py-2.5 px-3 w-28">Primary Qty *</th>
                                        <th class="py-2.5 px-3 w-24">Unit</th>
                                        <th class="py-2.5 px-3 w-28">Secondary Qty</th>
                                        <th class="py-2.5 px-3 w-24">Sec. Unit</th>
                                        <th class="py-2.5 px-3 w-28">Rate / Unit (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Taxable (₹)</th>
                                        <th class="py-2.5 px-3 w-20">GST %</th>
                                        <th class="py-2.5 px-3 text-right">GST Amt (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Line Total (₹)</th>
                                        <th class="py-2.5 px-3">Remarks</th>
                                        <th class="py-2.5 px-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(row, idx) in form.items" :key="idx" class="hover:bg-slate-50">
                                        <td class="py-2.5 px-3 font-mono text-slate-400">{{ idx + 1 }}</td>

                                        <!-- Item Selector -->
                                        <td class="py-2.5 px-3">
                                            <select
                                                v-model.number="row.item_id"
                                                required
                                                @change="onItemChange(idx)"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-semibold text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            >
                                                <option v-for="rm in inwardStore.rmItems" :key="rm.id" :value="rm.id">
                                                    {{ rm.category }} {{ rm.size ? `(${rm.size})` : '' }}
                                                </option>
                                            </select>
                                        </td>

                                        <!-- Primary Qty -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.qty_primary"
                                                type="number"
                                                step="any"
                                                min="0.0001"
                                                required
                                                @input="onPrimaryQtyChange(idx)"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono font-bold text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- Primary Unit Dropdown -->
                                        <td class="py-2.5 px-3">
                                            <select
                                                v-model="row.unit_primary"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            >
                                                <option v-for="u in UNIT_OPTIONS" :key="u" :value="u">
                                                    {{ u }}
                                                </option>
                                            </select>
                                        </td>

                                        <!-- Secondary Qty Input -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.qty_secondary"
                                                type="number"
                                                step="any"
                                                min="0"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono font-bold text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none text-cyan-900"
                                            />
                                        </td>

                                        <!-- Secondary Unit Dropdown -->
                                        <td class="py-2.5 px-3">
                                            <select
                                                v-model="row.unit_secondary"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            >
                                                <option v-for="u in UNIT_OPTIONS" :key="u" :value="u">
                                                    {{ u }}
                                                </option>
                                            </select>
                                        </td>

                                        <!-- Rate per unit -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.rate_per_unit"
                                                type="number"
                                                step="any"
                                                min="0"
                                                placeholder="0.00"
                                                @input="onFinancialChange(idx)"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- Taxable Amount (Calculated) -->
                                        <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-800">
                                            {{ Number(row.taxable_amount).toFixed(2) }}
                                        </td>

                                        <!-- GST % -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.gst_rate"
                                                type="number"
                                                step="any"
                                                min="0"
                                                max="100"
                                                placeholder="0"
                                                @input="onFinancialChange(idx)"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- GST Amount (Calculated) -->
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-700">
                                            {{ Number(row.gst_amount).toFixed(2) }}
                                        </td>

                                        <!-- Line Total (Calculated) -->
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800">
                                            {{ Number(row.total_amount).toFixed(2) }}
                                        </td>

                                        <!-- Row Remarks -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model="row.remarks"
                                                type="text"
                                                placeholder="Silo #, lot..."
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-700 text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- Delete row button -->
                                        <td class="py-2.5 px-3 text-right">
                                            <button
                                                type="button"
                                                @click="removeItemRow(idx)"
                                                :disabled="form.items.length <= 1"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 disabled:opacity-30 disabled:hover:bg-transparent"
                                                title="Remove Item"
                                            >
                                                <Trash class="w-3.5 h-3.5" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-slate-50 border-t border-slate-200 font-bold text-xs text-slate-800">
                                        <td colspan="2" class="py-2.5 px-3 text-right">Total:</td>
                                        <td class="py-2.5 px-3 font-mono text-amber-800">
                                            {{ Number(totalFormPrimaryQty).toFixed(2) }}
                                        </td>
                                        <td colspan="4" class="py-2.5 px-3 text-right">Subtotal & Taxes:</td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-900">
                                            {{ Number(formSubtotal).toFixed(2) }}
                                        </td>
                                        <td></td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-900">
                                            {{ Number(formTaxTotal).toFixed(2) }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-emerald-800 text-sm">
                                            {{ Number(formGrandTotal).toFixed(2) }}
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Remarks & Notes -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Shipment Remarks / Gate Pass Notes</label>
                        <input
                            v-model="form.remarks"
                            type="text"
                            placeholder="Testing lab report ref, moisture deduction notes, unloader name..."
                            class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase text-slate-600">Total Amount:</span>
                            <span class="text-base font-mono font-extrabold text-emerald-800">{{ formatCurrency(formGrandTotal) }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="closeFormModal"
                                class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="inwardStore.isSaving"
                                class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-md shadow-amber-500/20 transition-all active:scale-95 disabled:opacity-50"
                            >
                                <RefreshCw v-if="inwardStore.isSaving" class="w-4 h-4 animate-spin" />
                                <span>{{ editingInward ? 'Update Inward Entry' : 'Save Stock Inward' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- View / Print Inward Goods Slip Modal -->
        <div
            v-if="isViewModalOpen && viewingInward"
            @click.self="closeViewModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden my-8 print:shadow-none print:border-none print:m-0 print:w-full">
                <!-- Slip Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50 print:bg-white print:border-b-2 print:border-black">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-cyan-50 border border-cyan-200 text-cyan-700 print:hidden">
                            <Truck class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-slate-900">
                                Stock Inward Goods & Commercial Voucher: {{ viewingInward.inward_no }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Material delivery verification, invoice details & inventory credit voucher</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 print:hidden">
                        <button
                            type="button"
                            @click="printSlip"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors border border-slate-200"
                        >
                            <Printer class="w-3.5 h-3.5" />
                            <span>Print Slip</span>
                        </button>
                        <button @click="closeViewModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Slip Content -->
                <div class="p-6 space-y-6 max-h-[80vh] overflow-y-auto custom-scrollbar print:max-h-none print:p-0">
                    <!-- Top Details Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 print:bg-transparent print:border print:border-black">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Supplier / Vendor</div>
                            <div class="text-xs font-bold text-amber-800">
                                {{ viewingInward.supplier?.name || viewingInward.supplier_name || '—' }}
                            </div>
                            <div v-if="viewingInward.supplier?.gstin" class="text-[10px] font-mono text-slate-500">
                                GSTIN: {{ viewingInward.supplier.gstin }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Invoice / Challan No</div>
                            <div class="text-xs font-mono font-bold text-slate-900">{{ viewingInward.challan_no || viewingInward.supplier_bill_no || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Challan Date</div>
                            <div class="text-xs font-mono font-bold text-slate-900">{{ viewingInward.challan_date || viewingInward.supplier_bill_date || viewingInward.inward_date || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Vehicle / Truck No</div>
                            <div class="text-xs font-mono font-bold text-slate-900 uppercase">{{ viewingInward.vehicle_no || '—' }}</div>
                        </div>
                        <div v-if="viewingInward.driver_name">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Driver</div>
                            <div class="text-xs text-slate-800">{{ viewingInward.driver_name }} ({{ viewingInward.driver_phone || 'No phone' }})</div>
                        </div>
                        <div v-if="viewingInward.remarks" class="sm:col-span-3">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Remarks</div>
                            <div class="text-xs text-slate-700">{{ viewingInward.remarks }}</div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="space-y-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                            <Layers class="w-3.5 h-3.5 text-amber-600" />
                            <span>Received Material Items & Purchase Valuation</span>
                        </div>

                        <div class="border border-slate-200 rounded-xl overflow-hidden print:border-black">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase text-[10px] border-b border-slate-200 print:bg-slate-100 print:border-black">
                                        <th class="py-2.5 px-3">#</th>
                                        <th class="py-2.5 px-3">Item Description</th>
                                        <th class="py-2.5 px-3 font-mono">Primary Qty</th>
                                        <th class="py-2.5 px-3 font-mono">Secondary Qty</th>
                                        <th class="py-2.5 px-3 text-right">Rate (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Taxable (₹)</th>
                                        <th class="py-2.5 px-3 text-center">GST %</th>
                                        <th class="py-2.5 px-3 text-right">GST Amt (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Total (₹)</th>
                                        <th class="py-2.5 px-3">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700 print:divide-black">
                                    <tr v-for="(it, idx) in (viewingInward.items || [])" :key="it.id">
                                        <td class="py-2.5 px-3 font-mono text-slate-400">{{ idx + 1 }}</td>
                                        <td class="py-2.5 px-3 font-bold text-slate-900">
                                            {{ it.item?.category || 'RM' }} {{ it.item?.size ? `(${it.item.size})` : '' }}
                                        </td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-emerald-800">
                                            {{ Number(it.qty_primary).toLocaleString() }} {{ it.unit_primary }}
                                        </td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-cyan-800">
                                            {{ Number(it.qty_secondary).toLocaleString() }} {{ it.unit_secondary }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-800">
                                            {{ Number(it.rate_per_unit || 0).toLocaleString() }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-semibold text-slate-800">
                                            {{ Number(it.taxable_amount || 0).toLocaleString() }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center font-mono text-slate-600">
                                            {{ Number(it.gst_rate || 0) }}%
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-700">
                                            {{ Number(it.gst_amount || 0).toLocaleString() }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800">
                                            {{ Number(it.total_amount || 0).toLocaleString() }}
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
                                <span>Subtotal (Taxable):</span>
                                <span class="font-mono font-semibold">{{ formatCurrency(viewingInward.subtotal_amount) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Total GST Tax:</span>
                                <span class="font-mono font-semibold">{{ formatCurrency(viewingInward.tax_amount) }}</span>
                            </div>
                            <div class="pt-2 border-t border-slate-200 flex justify-between font-extrabold text-slate-900 text-sm print:border-black">
                                <span>Total Amount:</span>
                                <span class="font-mono text-emerald-800">{{ formatCurrency(viewingInward.grand_total) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Amount Paid:</span>
                                <span class="font-mono font-semibold text-emerald-700">{{ formatCurrency(viewingInward.paid_amount) }}</span>
                            </div>
                            <div class="flex justify-between font-bold text-xs pt-1 border-t border-dashed border-slate-200">
                                <span>Balance Outstanding:</span>
                                <span :class="Number(viewingInward.grand_total) - Number(viewingInward.paid_amount || 0) > 0 ? 'text-red-700 font-mono' : 'text-emerald-700 font-mono'">
                                    {{ formatCurrency(Math.max(0, Number(viewingInward.grand_total) - Number(viewingInward.paid_amount || 0))) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slip Footer -->
                <div class="px-6 py-4 border-t border-slate-200 flex items-center justify-end bg-slate-50 print:bg-white">
                    <button
                        type="button"
                        @click="closeViewModal"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold print:hidden"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div
            v-if="isDeleteModalOpen && deletingInward"
            @click.self="isDeleteModalOpen = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 text-red-600">
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200">
                        <AlertCircle class="w-6 h-6 text-red-600" />
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm">Delete Stock Inward Entry?</h4>
                        <p class="text-xs text-slate-500 font-mono">{{ deletingInward.inward_no }} ({{ deletingInward.supplier?.name || deletingInward.supplier_name }})</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Are you sure you want to delete this stock inward shipment? Deleting this voucher will deduct the inward quantities from your available raw material stock balance.
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
                        :disabled="inwardStore.isSaving"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                    >
                        <RefreshCw v-if="inwardStore.isSaving" class="w-4 h-4 animate-spin" />
                        <span>Delete Inward</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
