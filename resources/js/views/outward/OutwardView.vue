<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useStockOutwardStore } from '../../stores/outward';
import type { StockOutward, StockOutwardForm, StockOutwardItemInput } from '../../types/outward';
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
    Building2,
    MapPin,
    CreditCard
} from 'lucide-vue-next';

const router = useRouter();
const outwardStore = useStockOutwardStore();

const UNIT_OPTIONS = ['cum', 'cbm', 'm3', 'ton', 'kgs', 'bags', 'pcs', 'ltr', 'nos', 'box', 'meter', 'barrel', 'gm'];
const OUTWARD_TYPES = [
    { value: 'sales', label: 'Sales / Site Delivery' },
    { value: 'transfer', label: 'Plant / Site Transfer' },
    { value: 'return', label: 'Vendor / Material Return' },
    { value: 'sample', label: 'Cube Sample / Testing' },
    { value: 'other', label: 'Other Dispatch' },
];

// Modal States
const isFormModalOpen = ref(false);
const isViewModalOpen = ref(false);
const isDeleteModalOpen = ref(false);

const editingOutward = ref<StockOutward | null>(null);
const viewingOutward = ref<StockOutward | null>(null);
const deletingOutward = ref<StockOutward | null>(null);

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

const getOutwardDiscount = (outward: StockOutward): number => {
    const invoiceDisc = Number(outward.discount_amount) || 0;
    const paymentDisc = Number((outward as any).payment_discount_amount) || 0;
    return Math.round((invoiceDisc + paymentDisc) * 100) / 100;
};

const getOutwardDue = (outward: StockOutward): number => {
    if (outward.payment_status === 'paid') return 0;
    const total = Number(outward.total_amount) || 0;
    const paid = Number(outward.paid_amount) || 0;
    const paymentDisc = Number((outward as any).payment_discount_amount) || 0;
    const due = total - paid - paymentDisc;
    return Math.max(0, Math.round(due * 100) / 100);
};

const recalculateRow = (row: StockOutwardItemInput, fieldChanged?: 'disc_pct' | 'disc_amt' | 'comm_pct' | 'comm_amt') => {
    const qty = Number(row.qty) || 0;
    const rate = Number(row.rate) || 0;
    const gross = Math.round(qty * rate * 100) / 100;
    const gstRate = Number(row.gst_rate) || 0;
    const gstAmt = Math.round(gross * (gstRate / 100) * 100) / 100;
    const pumpCost = Number(row.pump_cost) || 0;
    const overallCost = Math.round((gross + gstAmt + pumpCost) * 100) / 100;

    let discPercent = Number(row.discount_percent) || 0;
    let discAmt = Number(row.discount_amount) || 0;

    if (fieldChanged === 'disc_pct') {
        discAmt = Math.round(overallCost * (discPercent / 100) * 100) / 100;
        row.discount_amount = discAmt;
    } else if (fieldChanged === 'disc_amt') {
        if (overallCost > 0) {
            discPercent = Math.round((discAmt / overallCost) * 100 * 100) / 100;
            row.discount_percent = discPercent;
        }
    } else {
        if (discPercent > 0) {
            discAmt = Math.round(overallCost * (discPercent / 100) * 100) / 100;
            row.discount_amount = discAmt;
        }
    }

    const total = Math.max(0, Math.round((overallCost - discAmt) * 100) / 100);

    let commRate = Number(row.commission_rate) || 0;
    let commAmt = Number(row.commission_amount) || 0;

    if (fieldChanged === 'comm_pct') {
        commAmt = Math.round(gross * (commRate / 100) * 100) / 100;
        row.commission_amount = commAmt;
    } else if (fieldChanged === 'comm_amt') {
        if (gross > 0) {
            commRate = Math.round((commAmt / gross) * 100 * 100) / 100;
            row.commission_rate = commRate;
        }
    } else {
        if (commRate > 0) {
            commAmt = Math.round(gross * (commRate / 100) * 100) / 100;
            row.commission_amount = commAmt;
        }
    }

    row.taxable_amount = gross;
    row.gst_amount = gstAmt;
    row.pump_cost = pumpCost;
    row.total_amount = total;

    // Default header discounts/commissions to sum of line items if not manually detached
    if (!form.value.discount_amount || form.value.discount_amount === formTotalLineDiscount.value) {
        form.value.discount_amount = formTotalLineDiscount.value;
    }
    if (!form.value.commission_amount || form.value.commission_amount === formTotalLineCommission.value) {
        form.value.commission_amount = formTotalLineCommission.value;
    }
};

const initialItemRow = (): StockOutwardItemInput => {
    const firstItem = outwardStore.items[0];
    const factor = firstItem ? Number(firstItem.conversion_factor) || 1.0 : 1.0;
    const gstRate = firstItem ? Number((firstItem as any).gst_per || (firstItem as any).gst_rate) || 0 : 0;
    const primUnit = firstItem?.primary_unit || (firstItem?.type === 'fg' ? 'cum' : 'ton');
    const secUnit = firstItem?.secondary_unit || (firstItem?.type === 'fg' ? 'cum' : 'kgs');

    const row: StockOutwardItemInput = {
        item_id: firstItem ? firstItem.id : 0,
        qty: firstItem?.type === 'fg' ? 6 : 10,
        unit: primUnit,
        secondary_qty: factor > 0 ? (firstItem?.type === 'fg' ? 6 * factor : 10 * factor) : (firstItem?.type === 'fg' ? 6 : 10),
        secondary_unit: secUnit,
        conversion_factor: factor,
        rate: 0,
        pump_cost: 0,
        discount_percent: 0,
        discount_amount: 0,
        taxable_amount: 0,
        gst_rate: gstRate,
        gst_amount: 0,
        total_amount: 0,
        commission_agent_id: form.value?.agent_id || null,
        commission_agent_name: form.value?.agent_name || '',
        commission_rate: 0,
        commission_amount: 0,
        lotno: '',
        remarks: '',
        item: firstItem,
    };
    recalculateRow(row);
    return row;
};

const initialForm = (): StockOutwardForm => ({
    entry_no: outwardStore.nextEntryNo || 'OUT-0001',
    entry_type: 'sales',
    party_id: null,
    customer: '',
    agent_id: null,
    agent_name: '',
    invoice_date: getTodayString(),
    challan_no: '',
    challan_date: getTodayString(),
    vehicle_no: '',
    driver_name: '',
    driver_phone: '',
    destination: '',
    plant_name: 'Central Batching Plant',
    discount_amount: 0,
    commission_amount: 0,
    freight_charges: 0,
    remarks: '',
    items: [],
});

const form = ref<StockOutwardForm>(initialForm());

onMounted(async () => {
    if (!outwardStore.fromDateFilter) {
        outwardStore.fromDateFilter = getMonthFirstDateString();
    }
    if (!outwardStore.toDateFilter) {
        outwardStore.toDateFilter = getMonthLastDateString();
    }
    await outwardStore.fetchMeta();
    await outwardStore.fetchOutwards(1);
});

const handleSearch = () => {
    outwardStore.fetchOutwards(1);
};

const handleFilterChange = () => {
    outwardStore.fetchOutwards(1);
};

const resetFilters = () => {
    outwardStore.searchQuery = '';
    outwardStore.partyFilter = 'all';
    outwardStore.entryTypeFilter = 'all';
    outwardStore.fromDateFilter = getMonthFirstDateString();
    outwardStore.toDateFilter = getMonthLastDateString();
    outwardStore.fetchOutwards(1);
};

// Customer party selection handler
const onPartyChange = () => {
    const p = outwardStore.parties.find((party) => party.id === form.value.party_id);
    if (p) {
        form.value.customer = p.name;
        if (p.address && !form.value.destination) {
            form.value.destination = `${p.address}${p.city ? ', ' + p.city : ''}`;
        }
    }
};

// Agent party selection handler
const onAgentChange = () => {
    const a = outwardStore.parties.find((party) => party.id === form.value.agent_id);
    if (a) {
        form.value.agent_name = a.name;
        // Pre-fill item agents if not set
        form.value.items.forEach((row) => {
            if (!row.commission_agent_id) {
                row.commission_agent_id = a.id;
                row.commission_agent_name = a.name;
            }
        });
    }
};

// Line Item Agent Change
const onItemAgentChange = (index: number) => {
    const row = form.value.items[index];
    const a = outwardStore.parties.find((party) => party.id === row.commission_agent_id);
    if (a) {
        row.commission_agent_name = a.name;
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

// When master item selection changes in row
const onItemChange = (index: number) => {
    const row = form.value.items[index];
    const selectedItem = outwardStore.items.find((it) => it.id === row.item_id);
    if (selectedItem) {
        const factor = Number(selectedItem.conversion_factor) || 1.0;
        const gstRate = Number((selectedItem as any).gst_per || (selectedItem as any).gst_rate) || 0;
        const primUnit = selectedItem.primary_unit || (selectedItem.type === 'fg' ? 'cum' : 'ton');
        const secUnit = selectedItem.secondary_unit || (selectedItem.type === 'fg' ? 'cum' : 'kgs');

        row.item = selectedItem;
        row.unit = primUnit;
        row.secondary_unit = secUnit;
        row.conversion_factor = factor;
        row.gst_rate = gstRate;

        // Recalculate secondary qty
        const primQty = Number(row.qty) || 0;
        row.secondary_qty = factor > 0 ? Number((primQty * factor).toFixed(4)) : primQty;

        recalculateRow(row);
    }
};

// When primary qty changes in row
const onPrimaryQtyChange = (index: number) => {
    const row = form.value.items[index];
    const qty = Number(row.qty) || 0;
    const factor = Number(row.conversion_factor) || 1.0;
    row.secondary_qty = factor > 0 ? Number((qty * factor).toFixed(4)) : qty;
    recalculateRow(row);
};

// When secondary qty changes in row
const onSecondaryQtyChange = (index: number) => {
    const row = form.value.items[index];
    const secQty = Number(row.secondary_qty) || 0;
    const factor = Number(row.conversion_factor) || 1.0;
    if (factor > 0) {
        row.qty = Number((secQty / factor).toFixed(4));
    }
    recalculateRow(row);
};

// When rate or GST % changes
const onFinancialChange = (index: number) => {
    recalculateRow(form.value.items[index]);
};

// Total primary qty in form
const totalFormPrimaryQty = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.qty) || 0), 0);
});

// Form calculations
const formSubtotal = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.taxable_amount) || 0), 0);
});

const formTaxTotal = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.gst_amount) || 0), 0);
});

const formTotalLineDiscount = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.discount_amount) || 0), 0);
});

const formTotalLineCommission = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.commission_amount) || 0), 0);
});

const formTotalLinePumpCost = computed(() => {
    return form.value.items.reduce((acc, row) => acc + (Number(row.pump_cost) || 0), 0);
});

const formGrandTotal = computed(() => {
    const sub = formSubtotal.value;
    const tax = formTaxTotal.value;
    const pump = formTotalLinePumpCost.value;
    const freight = Number(form.value.freight_charges) || 0;
    const headerDisc = Number(form.value.discount_amount) || 0;
    const lineDisc = formTotalLineDiscount.value;
    const extraDisc = Math.max(0, headerDisc - lineDisc);
    return Math.max(0, Math.round((sub + tax + pump + freight - extraDisc) * 100) / 100);
});

// Modal Actions
const openCreateModal = async () => {
    outwardStore.clearMessages();
    editingOutward.value = null;
    await outwardStore.fetchMeta();

    form.value = {
        ...initialForm(),
        entry_no: outwardStore.nextEntryNo || 'OUT-0001',
        party_id: outwardStore.customers[0]?.id || null,
        customer: outwardStore.customers[0]?.name || '',
        agent_id: outwardStore.agents[0]?.id || null,
        agent_name: outwardStore.agents[0]?.name || '',
        items: [initialItemRow()],
    };
    isFormModalOpen.value = true;
};

const openEditModal = (outward: StockOutward) => {
    editingOutward.value = outward;
    outwardStore.clearMessages();

    form.value = {
        entry_no: outward.entry_no,
        entry_type: outward.entry_type || 'sales',
        party_id: outward.party_id || null,
        customer: outward.customer || '',
        agent_id: outward.agent_id || null,
        agent_name: outward.agent_name || '',
        invoice_date: outward.invoice_date || getTodayString(),
        challan_no: outward.challan_no || '',
        challan_date: outward.challan_date || getTodayString(),
        vehicle_no: outward.vehicle_no || '',
        driver_name: outward.driver_name || '',
        driver_phone: outward.driver_phone || '',
        destination: outward.destination || '',
        plant_name: outward.plant_name || 'Central Batching Plant',
        discount_amount: Number(outward.discount_amount) || 0,
        commission_amount: Number(outward.commission_amount) || 0,
        freight_charges: Number(outward.freight_charges) || 0,
        remarks: outward.remarks || '',
        items: (outward.items || []).map((item) => {
            const masterItem = outwardStore.items.find((it) => it.id === item.item_id) || item.item;
            const row: StockOutwardItemInput = {
                id: item.id,
                item_id: item.item_id,
                qty: Number(item.qty),
                unit: item.unit,
                secondary_qty: Number(item.secondary_qty),
                secondary_unit: item.secondary_unit,
                conversion_factor: Number(item.conversion_factor) || 1.0,
                rate: Number(item.rate) || 0,
                pump_cost: Number(item.pump_cost) || 0,
                discount_percent: Number(item.discount_percent) || 0,
                discount_amount: Number(item.discount_amount) || 0,
                gst_rate: Number(item.gst_rate) || 0,
                taxable_amount: Number(item.taxable_amount) || 0,
                gst_amount: Number(item.gst_amount) || 0,
                total_amount: Number(item.total_amount) || 0,
                commission_agent_id: item.commission_agent_id || outward.agent_id || null,
                commission_agent_name: item.commission_agent_name || outward.agent_name || '',
                commission_rate: Number(item.commission_rate) || 0,
                commission_amount: Number(item.commission_amount) || 0,
                lotno: item.lotno || '',
                remarks: item.remarks || '',
                item: masterItem,
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

const openViewModal = (outward: StockOutward) => {
    viewingOutward.value = outward;
    isViewModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
    editingOutward.value = null;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    viewingOutward.value = null;
};

const confirmDelete = (outward: StockOutward) => {
    outwardStore.clearMessages();
    deletingOutward.value = outward;
    isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
    if (deletingOutward.value) {
        await outwardStore.deleteOutward(deletingOutward.value.id);
        isDeleteModalOpen.value = false;
        deletingOutward.value = null;
    }
};

const closeAllModals = () => {
    if (isDeleteModalOpen.value) {
        isDeleteModalOpen.value = false;
        deletingOutward.value = null;
    } else if (isViewModalOpen.value) {
        closeViewModal();
    } else if (isFormModalOpen.value) {
        closeFormModal();
    }
};

useEscapeKey(closeAllModals);

const submitForm = async () => {
    if (editingOutward.value) {
        await outwardStore.updateOutward(editingOutward.value.id, form.value);
    } else {
        await outwardStore.createOutward(form.value);
    }
    closeFormModal();
};

const printSlip = () => {
    window.print();
};

const getTypeLabel = (type: string | undefined) => {
    const found = OUTWARD_TYPES.find((t) => t.value === type);
    return found ? found.label : (type || 'Sales');
};

const navigateToPayments = (outward?: StockOutward) => {
    if (outward && outward.party_id) {
        router.push({
            path: '/outward-payments',
            query: { party_id: outward.party_id, outward_id: outward.id, create: '1' },
        });
    } else {
        router.push('/outward-payments');
    }
};
</script>

<template>
    <AppLayout title="Stock Outward Entry" subtitle="Record Finished Goods Concrete Dispatches, Gate Passes & Material Transfers">
        <div class="space-y-6">
            <!-- Feedback Alerts -->
            <div
                v-if="outwardStore.successMessage"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="w-5 h-5 flex-shrink-0 text-emerald-600" />
                    <span class="text-xs font-semibold">{{ outwardStore.successMessage }}</span>
                </div>
                <button @click="outwardStore.clearMessages" class="text-emerald-600 hover:text-emerald-800">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div
                v-if="outwardStore.error || outwardStore.validationErrors.length > 0"
                class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 space-y-2 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 font-bold text-xs">
                        <AlertCircle class="w-5 h-5 flex-shrink-0 text-red-600" />
                        <span>{{ outwardStore.error || 'Please correct the following errors' }}</span>
                    </div>
                    <button @click="outwardStore.clearMessages" class="text-red-600 hover:text-red-800">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <ul v-if="outwardStore.validationErrors.length > 0" class="pl-8 list-disc text-[11px] space-y-1 text-red-700 font-mono">
                    <li v-for="(msg, idx) in outwardStore.validationErrors" :key="idx">
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
                            v-model="outwardStore.searchQuery"
                            type="text"
                            placeholder="Search Outward #, customer, vehicle, challan..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        />
                    </div>

                    <!-- Customer / Party Filter -->
                    <select
                        v-model="outwardStore.partyFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Customers / Consignees</option>
                        <option v-for="party in outwardStore.parties" :key="party.id" :value="party.id">
                            {{ party.name }} ({{ party.code }})
                        </option>
                    </select>

                    <!-- Outward Type Filter -->
                    <select
                        v-model="outwardStore.entryTypeFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Outward Types</option>
                        <option v-for="t in OUTWARD_TYPES" :key="t.value" :value="t.value">
                            {{ t.label }}
                        </option>
                    </select>

                    <!-- From Date Filter -->
                    <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-300">
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">From:</span>
                        <input
                            v-model="outwardStore.fromDateFilter"
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
                            v-model="outwardStore.toDateFilter"
                            type="date"
                            @change="handleFilterChange"
                            class="bg-transparent text-slate-800 text-xs font-mono focus:outline-none cursor-pointer"
                            title="To Date"
                        />
                    </div>

                    <button
                        type="button"
                        @click="outwardStore.fetchOutwards(outwardStore.meta.current_page)"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors"
                        title="Refresh List"
                    >
                        <RefreshCw :class="['w-4 h-4', outwardStore.isLoading ? 'animate-spin' : '']" />
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
                    <span>New Stock Outward</span>
                </button>
            </div>

            <!-- Outward Records (Table + Mobile Cards) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- Mobile Card List View (< md) -->
                <div class="block md:hidden divide-y divide-slate-100">
                    <!-- Loading State -->
                    <div v-if="outwardStore.isLoading && outwardStore.outwards.length === 0" class="py-12 text-center text-slate-500">
                        <div class="inline-flex items-center gap-2 text-xs">
                            <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                            <span>Loading outward shipments...</span>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="outwardStore.outwards.length === 0" class="py-12 px-4 text-center text-slate-500 space-y-2">
                        <Truck class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="font-bold text-slate-800 text-sm">No Stock Outward Dispatches Found</div>
                        <p class="text-xs text-slate-500">Record concrete deliveries and material dispatches to update inventory balance.</p>
                    </div>

                    <!-- Mobile Cards -->
                    <div
                        v-for="outward in outwardStore.outwards"
                        :key="outward.id"
                        class="p-4 space-y-3 hover:bg-slate-50 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-slate-900 text-xs">
                                    {{ outward.entry_no }}
                                </span>
                                <span class="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-800 font-bold text-[10px]">
                                    {{ outward.party?.name || outward.customer || 'Direct Customer' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-full font-bold text-[10px] uppercase border bg-slate-100 text-slate-700 border-slate-200">
                                    {{ getTypeLabel(outward.entry_type) }}
                                </span>
                                <span
                                    :class="[
                                        'px-2 py-0.5 rounded-full font-bold text-[10px] uppercase border',
                                        outward.payment_status === 'paid'
                                            ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                            : outward.payment_status === 'partially_paid'
                                                ? 'bg-amber-50 text-amber-800 border-amber-200'
                                                : 'bg-slate-100 text-slate-600 border-slate-200'
                                    ]"
                                >
                                    {{ outward.payment_status === 'partially_paid' ? 'Partial' : (outward.payment_status || 'Unpaid') }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Vehicle / Driver</span>
                                <span class="font-bold text-slate-800 font-mono block">{{ outward.vehicle_no || '—' }}</span>
                                <span class="text-[11px] text-slate-600 block">{{ outward.driver_name || '' }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Challan / Date</span>
                                <span class="font-mono font-bold text-slate-800 block">{{ outward.challan_no || '—' }}</span>
                                <span v-if="outward.invoice_date" class="text-[10px] text-slate-400 block">
                                    {{ outward.invoice_date }}
                                </span>
                            </div>
                        </div>

                        <div v-if="outward.destination" class="text-xs text-slate-600 flex items-center gap-1">
                            <MapPin class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
                            <span class="truncate">{{ outward.destination }}</span>
                        </div>

                        <!-- Goods breakdown chips -->
                        <div class="flex flex-wrap gap-1 pt-1">
                            <div
                                v-for="it in (outward.items || [])"
                                :key="it.id"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] font-mono"
                            >
                                <span class="text-slate-500">{{ it.item?.category || 'Item' }}:</span>
                                <span class="text-amber-800 font-bold">{{ Number(it.qty).toLocaleString() }} {{ it.unit }}</span>
                                <span v-if="Number(it.secondary_qty) > 0 && it.secondary_unit !== it.unit" class="text-cyan-700">
                                    ({{ Number(it.secondary_qty).toLocaleString() }} {{ it.secondary_unit }})
                                </span>
                                <span v-if="Number(it.rate) > 0" class="text-slate-400">@ ₹{{ Number(it.rate).toLocaleString() }}</span>
                            </div>
                        </div>

                        <!-- Grand Total & Payment Summary Banner -->
                        <div class="space-y-1.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase font-bold text-slate-500">Dispatch Total:</span>
                                <span class="font-mono font-bold text-slate-900">{{ formatCurrency(outward.total_amount) }}</span>
                            </div>
                            <div v-if="getOutwardDiscount(outward) > 0" class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500">Discount:</span>
                                <span class="font-mono font-semibold text-red-600">-{{ formatCurrency(getOutwardDiscount(outward)) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500">Paid: {{ formatCurrency(outward.paid_amount || 0) }}</span>
                                <span :class="getOutwardDue(outward) > 0 ? 'text-red-700 font-bold font-mono' : 'text-emerald-700 font-semibold'">
                                    Due: {{ formatCurrency(getOutwardDue(outward)) }}
                                </span>
                            </div>
                        </div>

                        <!-- Mobile Card Actions -->
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button
                                v-if="outward.payment_status !== 'paid'"
                                type="button"
                                @click="navigateToPayments(outward)"
                                class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold flex items-center gap-1"
                                title="Collect / Settle Receipt"
                            >
                                <CreditCard class="w-3.5 h-3.5" />
                                <span>Receipt</span>
                            </button>
                            <button
                                type="button"
                                @click="openViewModal(outward)"
                                class="px-3 py-1.5 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 border border-cyan-200 text-xs font-bold flex items-center gap-1"
                            >
                                <Eye class="w-3.5 h-3.5" />
                                <span>Challan</span>
                            </button>
                            <button
                                type="button"
                                @click="openEditModal(outward)"
                                class="p-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs"
                                title="Edit Outward"
                            >
                                <Edit3 class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="confirmDelete(outward)"
                                class="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs"
                                title="Delete Outward"
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
                                <th class="py-3.5 px-4">Outward No</th>
                                <th class="py-3.5 px-4">Customer / Consignee</th>
                                <th class="py-3.5 px-4">Type / Purpose</th>
                                <th class="py-3.5 px-4">Challan No & Date</th>
                                <th class="py-3.5 px-4">Vehicle & Destination</th>
                                <th class="py-3.5 px-4">Dispatched Goods</th>
                                <th class="py-3.5 px-4 text-right">Invoice & Payment</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="outwardStore.isLoading && outwardStore.outwards.length === 0">
                                <td colspan="8" class="py-12 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading outward dispatches...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="outwardStore.outwards.length === 0">
                                <td colspan="8" class="py-12 text-center text-slate-500">
                                    <div class="space-y-2">
                                        <Truck class="w-8 h-8 text-slate-400 mx-auto" />
                                        <div class="font-semibold text-slate-800">No Stock Outward Dispatches Found</div>
                                        <p class="text-[11px] text-slate-500">Record concrete deliveries and material dispatches to update inventory balance.</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="outward in outwardStore.outwards"
                                :key="outward.id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900">
                                    {{ outward.entry_no }}
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900">
                                        {{ outward.party?.name || outward.customer || 'Direct Customer' }}
                                    </div>
                                    <div v-if="outward.agent?.name || outward.agent_name" class="text-[10px] font-medium text-indigo-600">
                                        Agent: {{ outward.agent?.name || outward.agent_name }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded font-bold text-[10px] uppercase border bg-slate-100 text-slate-700 border-slate-200">
                                        {{ getTypeLabel(outward.entry_type) }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 font-mono">
                                    <div class="font-semibold text-slate-800">{{ outward.challan_no || '—' }}</div>
                                    <div class="text-[10px] text-slate-500">{{ outward.invoice_date || outward.challan_date || '—' }}</div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-mono font-bold text-slate-800">
                                        {{ outward.vehicle_no || '—' }}
                                    </div>
                                    <div v-if="outward.destination" class="text-[10px] text-slate-500 truncate max-w-[180px]" :title="outward.destination">
                                        📍 {{ outward.destination }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex flex-wrap gap-1.5 max-w-xs">
                                        <div
                                             v-for="it in (outward.items || [])"
                                             :key="it.id"
                                             class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] font-mono"
                                        >
                                            <span class="text-slate-500">{{ it.item?.category || 'Item' }}:</span>
                                            <span class="text-amber-800 font-bold">{{ Number(it.qty).toLocaleString() }} {{ it.unit }}</span>
                                            <span v-if="Number(it.secondary_qty) > 0 && it.secondary_unit !== it.unit" class="text-cyan-700">
                                                ({{ Number(it.secondary_qty).toLocaleString() }} {{ it.secondary_unit }})
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-mono font-bold text-slate-900">
                                        {{ formatCurrency(outward.total_amount) }}
                                    </div>
                                    <div v-if="getOutwardDiscount(outward) > 0" class="text-[10px] font-mono font-semibold text-red-600 mt-0.5">
                                        Disc: -{{ formatCurrency(getOutwardDiscount(outward)) }}
                                    </div>
                                    <div class="flex items-center justify-end gap-1 mt-1">
                                        <span
                                            :class="[
                                                'px-1.5 py-0.5 rounded text-[10px] font-bold uppercase border',
                                                outward.payment_status === 'paid' || getOutwardDue(outward) === 0
                                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                    : outward.payment_status === 'partially_paid'
                                                        ? 'bg-amber-50 text-amber-800 border-amber-200'
                                                        : 'bg-slate-100 text-slate-600 border-slate-200'
                                            ]"
                                        >
                                            {{ outward.payment_status === 'paid' || getOutwardDue(outward) === 0 ? 'Paid' : (outward.payment_status === 'partially_paid' ? 'Partial' : (outward.payment_status || 'Unpaid')) }}
                                        </span>
                                    </div>
                                    <div v-if="Number(outward.paid_amount) > 0" class="text-[10px] font-mono text-slate-500 mt-0.5">
                                        Paid: {{ formatCurrency(outward.paid_amount) }}
                                    </div>
                                    <div v-if="getOutwardDue(outward) > 0" class="text-[10px] font-mono font-semibold text-red-600 mt-0.5">
                                        Due: {{ formatCurrency(getOutwardDue(outward)) }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            v-if="outward.payment_status !== 'paid'"
                                            type="button"
                                            @click="navigateToPayments(outward)"
                                            class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 transition-colors border border-emerald-200"
                                            title="Collect Payment from Customer"
                                        >
                                            <CreditCard class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openViewModal(outward)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-cyan-700 transition-colors border border-slate-200"
                                            title="View / Print Challan Slip"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openEditModal(outward)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-amber-700 transition-colors border border-slate-200"
                                            title="Edit Outward Entry"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(outward)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-red-600 transition-colors border border-slate-200"
                                            title="Delete Outward"
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
                    v-if="outwardStore.meta.total > 0"
                    class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
                >
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (outwardStore.meta.current_page - 1) * outwardStore.meta.per_page + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(outwardStore.meta.current_page * outwardStore.meta.per_page, outwardStore.meta.total) }}</span>
                        of <span class="font-bold text-slate-900">{{ outwardStore.meta.total }}</span> entries
                    </div>
                    <div class="flex items-center gap-1.5" v-if="outwardStore.meta.last_page > 1">
                        <button
                            type="button"
                            :disabled="outwardStore.meta.current_page === 1"
                            @click="outwardStore.fetchOutwards(outwardStore.meta.current_page - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in outwardStore.meta.last_page"
                            :key="p"
                            @click="outwardStore.fetchOutwards(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all',
                                outwardStore.meta.current_page === p
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            {{ p }}
                        </button>
                        <button
                            type="button"
                            :disabled="outwardStore.meta.current_page === outwardStore.meta.last_page"
                            @click="outwardStore.fetchOutwards(outwardStore.meta.current_page + 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Outward Modal -->
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
                                {{ editingOutward ? `Edit Outward Entry: ${editingOutward.entry_no}` : 'New Stock Outward Entry' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Record finished goods delivery, challan commercials & debit items from inventory</p>
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
                            <span>Dispatch & Consignee Header</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- Outward No -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Outward No *</label>
                                <input
                                    v-model="form.entry_no"
                                    type="text"
                                    required
                                    placeholder="e.g. OUT-0001"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Outward Type -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Outward Type *</label>
                                <select
                                    v-model="form.entry_type"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-semibold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option v-for="t in OUTWARD_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>

                            <!-- Customer Party -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Customer / Consignee</label>
                                <select
                                    v-model.number="form.party_id"
                                    @change="onPartyChange"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-semibold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option :value="null">-- Direct / Walk-in Customer --</option>
                                    <option v-for="p in outwardStore.customers" :key="p.id" :value="p.id">
                                        {{ p.name }} ({{ p.code }})
                                    </option>
                                </select>
                            </div>

                            <!-- Customer Name (Override/Manual) -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Customer / Client Name</label>
                                <input
                                    v-model="form.customer"
                                    type="text"
                                    placeholder="e.g. L&T Construction Ltd"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Sales Agent / Broker -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Sales Agent / Broker</label>
                                <select
                                    v-model.number="form.agent_id"
                                    @change="onAgentChange"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-semibold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option :value="null">-- Direct (No Agent) --</option>
                                    <option v-for="a in outwardStore.agents" :key="a.id" :value="a.id">
                                        {{ a.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Dispatch / Invoice Date -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Dispatch / Invoice Date *</label>
                                <input
                                    v-model="form.invoice_date"
                                    type="date"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none cursor-pointer"
                                />
                            </div>

                            <!-- Challan No -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Delivery Challan No</label>
                                <input
                                    v-model="form.challan_no"
                                    type="text"
                                    placeholder="e.g. DC-9842"
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

                            <!-- Vehicle / Mixer No -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Vehicle / Mixer No</label>
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
                                    placeholder="e.g. Ramesh Kumar"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Driver Phone -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Driver Phone</label>
                                <input
                                    v-model="form.driver_phone"
                                    type="text"
                                    placeholder="e.g. +91 98220 00000"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Plant Name -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Plant / Dispatch Source</label>
                                <input
                                    v-model="form.plant_name"
                                    type="text"
                                    placeholder="Central Batching Plant"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Site Destination -->
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Site Destination / Delivery Address</label>
                                <input
                                    v-model="form.destination"
                                    type="text"
                                    placeholder="e.g. Metro Line 4 Pier #120, Powai Site"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Dispatched Line Items & Commercials Table -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                                <Layers class="w-3.5 h-3.5 text-amber-600" />
                                <span>Dispatched Finished Goods / Material Items, Rates, Discounts, Taxes & Commissions *</span>
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
                            <table class="w-full text-left text-xs min-w-[1400px]">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider border-b border-slate-200 font-bold text-[10px]">
                                        <th class="py-2.5 px-3">#</th>
                                        <th class="py-2.5 px-3 w-44">Item (FG / RM) *</th>
                                        <th class="py-2.5 px-3 w-24">Qty *</th>
                                        <th class="py-2.5 px-3 w-20">Unit</th>
                                        <th class="py-2.5 px-3 w-24">Sec. Qty</th>
                                        <th class="py-2.5 px-3 w-20">Sec. Unit</th>
                                        <th class="py-2.5 px-3 w-24">Rate (₹)</th>
                                        <th class="py-2.5 px-3 w-24">Pump Cost (₹)</th>
                                        <th class="py-2.5 px-3 w-20">Disc %</th>
                                        <th class="py-2.5 px-3 w-24">Disc Amt (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Taxable (₹)</th>
                                        <th class="py-2.5 px-3 w-20">GST %</th>
                                        <th class="py-2.5 px-3 text-right">GST (₹)</th>
                                        <th class="py-2.5 px-3 w-36">Comm Agent</th>
                                        <th class="py-2.5 px-3 w-20">Comm %</th>
                                        <th class="py-2.5 px-3 w-24">Comm Amt (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Line Total (₹)</th>
                                        <th class="py-2.5 px-3 w-24">Batch / Lot</th>
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
                                                <option v-for="it in outwardStore.items" :key="it.id" :value="it.id">
                                                    [{{ it.type.toUpperCase() }}] {{ it.category }} {{ it.size ? `(${it.size})` : '' }}
                                                </option>
                                            </select>
                                        </td>

                                        <!-- Primary Qty -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.qty"
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
                                                v-model="row.unit"
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
                                                v-model.number="row.secondary_qty"
                                                type="number"
                                                step="any"
                                                min="0"
                                                @input="onSecondaryQtyChange(idx)"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono font-bold text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none text-cyan-900"
                                            />
                                        </td>

                                        <!-- Secondary Unit Dropdown -->
                                        <td class="py-2.5 px-3">
                                            <select
                                                v-model="row.secondary_unit"
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
                                                v-model.number="row.rate"
                                                type="number"
                                                step="any"
                                                min="0"
                                                placeholder="0.00"
                                                @input="onFinancialChange(idx)"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- Pump Cost (₹) -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.pump_cost"
                                                type="number"
                                                step="any"
                                                min="0"
                                                placeholder="0.00"
                                                @input="onFinancialChange(idx)"
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none text-amber-900"
                                            />
                                        </td>

                                        <!-- Discount % -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.discount_percent"
                                                type="number"
                                                step="any"
                                                min="0"
                                                max="100"
                                                placeholder="0"
                                                @input="recalculateRow(row, 'disc_pct')"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- Discount Amount -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.discount_amount"
                                                type="number"
                                                step="any"
                                                min="0"
                                                placeholder="0.00"
                                                @input="recalculateRow(row, 'disc_amt')"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none text-red-600"
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
                                                class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- GST Amount (Calculated) -->
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-700">
                                            {{ Number(row.gst_amount).toFixed(2) }}
                                        </td>

                                        <!-- Line Item Commission Agent -->
                                        <td class="py-2.5 px-3">
                                            <select
                                                v-model.number="row.commission_agent_id"
                                                @change="onItemAgentChange(idx)"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            >
                                                <option :value="null">-- No Agent --</option>
                                                <option v-for="a in outwardStore.agents" :key="a.id" :value="a.id">
                                                    {{ a.name }}
                                                </option>
                                            </select>
                                        </td>

                                        <!-- Commission % -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.commission_rate"
                                                type="number"
                                                step="any"
                                                min="0"
                                                placeholder="0"
                                                @input="recalculateRow(row, 'comm_pct')"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                            />
                                        </td>

                                        <!-- Commission Amount -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model.number="row.commission_amount"
                                                type="number"
                                                step="any"
                                                min="0"
                                                placeholder="0.00"
                                                @input="recalculateRow(row, 'comm_amt')"
                                                class="w-full px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none text-indigo-700"
                                            />
                                        </td>

                                        <!-- Line Total (Calculated) -->
                                        <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800">
                                            {{ Number(row.total_amount).toFixed(2) }}
                                        </td>

                                        <!-- Lot / Batch -->
                                        <td class="py-2.5 px-3">
                                            <input
                                                v-model="row.lotno"
                                                type="text"
                                                placeholder="Batch #"
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
                                        <td colspan="2" class="py-2.5 px-3 text-right">Totals:</td>
                                        <td class="py-2.5 px-3 font-mono text-amber-800">
                                            {{ Number(totalFormPrimaryQty).toFixed(2) }}
                                        </td>
                                        <td colspan="4"></td>
                                        <td class="py-2.5 px-3 font-mono text-amber-900">
                                            {{ Number(formTotalLinePumpCost) > 0 ? `+${Number(formTotalLinePumpCost).toFixed(2)}` : '—' }}
                                        </td>
                                        <td colspan="2" class="py-2.5 px-3 text-right text-red-600 font-mono">
                                            -{{ Number(formTotalLineDiscount).toFixed(2) }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-900">
                                            {{ Number(formSubtotal).toFixed(2) }}
                                        </td>
                                        <td></td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-900">
                                            {{ Number(formTaxTotal).toFixed(2) }}
                                        </td>
                                        <td colspan="2"></td>
                                        <td class="py-2.5 px-3 text-right font-mono text-indigo-700">
                                            {{ Number(formTotalLineCommission).toFixed(2) }}
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

                    <!-- Additional Charges, Discounts, Commissions & Remarks -->
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Freight / Pumping Charges (₹)</label>
                            <input
                                v-model.number="form.freight_charges"
                                type="number"
                                step="any"
                                min="0"
                                placeholder="0.00"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-semibold text-slate-700">Total Discount (₹)</label>
                                <span class="text-[10px] text-slate-400 font-mono">Lines: ₹{{ formTotalLineDiscount.toFixed(2) }}</span>
                            </div>
                            <input
                                v-model.number="form.discount_amount"
                                type="number"
                                step="any"
                                min="0"
                                placeholder="0.00"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none text-red-600"
                            />
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-semibold text-slate-700">Total Commission (₹)</label>
                                <span class="text-[10px] text-slate-400 font-mono">Lines: ₹{{ formTotalLineCommission.toFixed(2) }}</span>
                            </div>
                            <input
                                v-model.number="form.commission_amount"
                                type="number"
                                step="any"
                                min="0"
                                placeholder="0.00"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none text-indigo-700"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Dispatch Remarks / Notes</label>
                            <input
                                v-model="form.remarks"
                                type="text"
                                placeholder="Slump test 140mm, pump operator instructions..."
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-500 block">Subtotal:</span>
                                <span class="text-xs font-mono font-bold text-slate-800">{{ formatCurrency(formSubtotal) }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-500 block">Total GST:</span>
                                <span class="text-xs font-mono font-bold text-slate-800">{{ formatCurrency(formTaxTotal) }}</span>
                            </div>
                            <div v-if="Number(form.discount_amount) > 0">
                                <span class="text-[10px] uppercase font-bold text-slate-500 block">Discount:</span>
                                <span class="text-xs font-mono font-bold text-red-600">-{{ formatCurrency(form.discount_amount) }}</span>
                            </div>
                            <div v-if="Number(form.commission_amount) > 0">
                                <span class="text-[10px] uppercase font-bold text-slate-500 block">Commission:</span>
                                <span class="text-xs font-mono font-bold text-indigo-700">{{ formatCurrency(form.commission_amount) }}</span>
                            </div>
                            <div class="border-l border-slate-200 pl-4">
                                <span class="text-[10px] uppercase font-bold text-slate-500 block">Grand Total:</span>
                                <span class="text-base font-mono font-extrabold text-emerald-800">{{ formatCurrency(formGrandTotal) }}</span>
                            </div>
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
                                :disabled="outwardStore.isSaving"
                                class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-md shadow-amber-500/20 transition-all active:scale-95 disabled:opacity-50"
                            >
                                <RefreshCw v-if="outwardStore.isSaving" class="w-4 h-4 animate-spin" />
                                <span>{{ editingOutward ? 'Update Outward Entry' : 'Save Stock Outward' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- View / Print Delivery Challan Slip Modal -->
        <div
            v-if="isViewModalOpen && viewingOutward"
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
                                Delivery Challan & Dispatch Slip: {{ viewingOutward.entry_no }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Material delivery verification, invoice details & inventory debit voucher</p>
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
                            <div class="text-[10px] uppercase font-bold text-slate-500">Customer / Consignee</div>
                            <div class="text-xs font-bold text-amber-800">
                                {{ viewingOutward.party?.name || viewingOutward.customer || 'Direct Customer' }}
                            </div>
                            <div v-if="viewingOutward.party?.gstin" class="text-[10px] font-mono text-slate-500">
                                GSTIN: {{ viewingOutward.party.gstin }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Delivery Challan No</div>
                            <div class="text-xs font-mono font-bold text-slate-900">{{ viewingOutward.challan_no || viewingOutward.entry_no }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Dispatch Date</div>
                            <div class="text-xs font-mono font-bold text-slate-900">{{ viewingOutward.invoice_date || viewingOutward.challan_date || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-500">Vehicle / Mixer No</div>
                            <div class="text-xs font-mono font-bold text-slate-900 uppercase">{{ viewingOutward.vehicle_no || '—' }}</div>
                        </div>
                        <div v-if="viewingOutward.driver_name">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Driver</div>
                            <div class="text-xs text-slate-800">{{ viewingOutward.driver_name }} ({{ viewingOutward.driver_phone || 'No phone' }})</div>
                        </div>
                        <div v-if="viewingOutward.destination" class="sm:col-span-2">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Delivery Site Destination</div>
                            <div class="text-xs text-slate-700 font-medium">📍 {{ viewingOutward.destination }}</div>
                        </div>
                        <div v-if="viewingOutward.agent?.name || viewingOutward.agent_name">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Agent / Broker</div>
                            <div class="text-xs text-indigo-700 font-medium">{{ viewingOutward.agent?.name || viewingOutward.agent_name }}</div>
                        </div>
                        <div v-if="viewingOutward.remarks" class="sm:col-span-4">
                            <div class="text-[10px] uppercase font-bold text-slate-500">Remarks / Quality Notes</div>
                            <div class="text-xs text-slate-700">{{ viewingOutward.remarks }}</div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="space-y-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                            <Layers class="w-3.5 h-3.5 text-amber-600" />
                            <span>Dispatched Goods Specification & Valuation</span>
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
                                        <th class="py-2.5 px-3 text-right">Pump Cost (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Discount (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Taxable (₹)</th>
                                        <th class="py-2.5 px-3 text-center">GST %</th>
                                        <th class="py-2.5 px-3 text-right">GST Amt (₹)</th>
                                        <th class="py-2.5 px-3 text-right">Total (₹)</th>
                                        <th class="py-2.5 px-3">Batch / Lot</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700 print:divide-black">
                                    <tr v-for="(it, idx) in (viewingOutward.items || [])" :key="it.id">
                                        <td class="py-2.5 px-3 font-mono text-slate-400">{{ idx + 1 }}</td>
                                        <td class="py-2.5 px-3">
                                            <div class="font-bold text-slate-900">
                                                {{ it.item?.category || 'Item' }} {{ it.item?.size ? `(${it.item.size})` : '' }}
                                            </div>
                                            <div v-if="it.commission_agent?.name || it.commission_agent_name" class="text-[10px] text-indigo-600">
                                                Agent: {{ it.commission_agent?.name || it.commission_agent_name }}
                                                <span v-if="Number(it.commission_amount) > 0"> (₹{{ Number(it.commission_amount).toLocaleString() }})</span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-emerald-800">
                                            {{ Number(it.qty).toLocaleString() }} {{ it.unit }}
                                        </td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-cyan-800">
                                            {{ Number(it.secondary_qty).toLocaleString() }} {{ it.secondary_unit }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-slate-800">
                                            {{ Number(it.rate || 0).toLocaleString() }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-amber-900">
                                            {{ Number(it.pump_cost) > 0 ? Number(it.pump_cost).toLocaleString() : '—' }}
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-mono text-red-600">
                                            {{ Number(it.discount_amount) > 0 ? `-${Number(it.discount_amount).toLocaleString()}` : '—' }}
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
                                        <td class="py-2.5 px-3 text-slate-500 font-mono">
                                            {{ it.lotno || '—' }}
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
                                <span class="font-mono font-semibold">{{ formatCurrency(viewingOutward.subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Total GST Tax:</span>
                                <span class="font-mono font-semibold">{{ formatCurrency(viewingOutward.tax_amount) }}</span>
                            </div>
                            <div v-if="Number(viewingOutward.freight_charges) > 0" class="flex justify-between text-slate-600">
                                <span>Freight / Pumping:</span>
                                <span class="font-mono font-semibold">{{ formatCurrency(viewingOutward.freight_charges) }}</span>
                            </div>
                            <div v-if="Number(viewingOutward.discount_amount) > 0" class="flex justify-between text-slate-600">
                                <span>Discount:</span>
                                <span class="font-mono font-semibold text-red-600">- {{ formatCurrency(viewingOutward.discount_amount) }}</span>
                            </div>
                            <div v-if="Number(viewingOutward.commission_amount) > 0" class="flex justify-between text-slate-600">
                                <span>Total Commission:</span>
                                <span class="font-mono font-semibold text-indigo-700">{{ formatCurrency(viewingOutward.commission_amount) }}</span>
                            </div>
                            <div class="pt-2 border-t border-slate-200 flex justify-between font-extrabold text-slate-900 text-sm print:border-black">
                                <span>Consignment Total:</span>
                                <span class="font-mono text-emerald-800">{{ formatCurrency(viewingOutward.total_amount) }}</span>
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
            v-if="isDeleteModalOpen && deletingOutward"
            @click.self="isDeleteModalOpen = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 text-red-600">
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200">
                        <AlertCircle class="w-6 h-6 text-red-600" />
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm">Delete Stock Outward Entry?</h4>
                        <p class="text-xs text-slate-500 font-mono">{{ deletingOutward.entry_no }} ({{ deletingOutward.party?.name || deletingOutward.customer }})</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Are you sure you want to delete this stock outward shipment? Deleting this voucher will credit the dispatched quantities back to your available stock balance.
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
                        :disabled="outwardStore.isLoading"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                    >
                        <RefreshCw v-if="outwardStore.isLoading" class="w-4 h-4 animate-spin" />
                        <span>Delete Outward</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
