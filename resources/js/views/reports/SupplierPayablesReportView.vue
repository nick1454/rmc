<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useFinancialReportStore } from '../../stores/financialReport';
import type { SupplierPayableItem, SupplierInwardItem } from '../../types/financialReport';
import AppLayout from '../../components/layout/AppLayout.vue';
import { useEscapeKey } from '../../composables/useEscapeKey';
import {
    Search,
    RefreshCw,
    Printer,
    Download,
    Eye,
    RotateCcw,
    Building2,
    Filter,
    Calendar,
    ArrowUpRight,
    ArrowDownLeft,
    Clock,
    FileText,
    TrendingUp,
    AlertCircle,
    CheckCircle2,
    X,
    DollarSign,
    Truck
} from 'lucide-vue-next';

const store = useFinancialReportStore();

const selectedSupplierForInwards = ref<SupplierPayableItem | null>(null);
const isInwardModalOpen = ref(false);

onMounted(async () => {
    if (!store.meta) {
        await store.fetchMeta();
    }
    await store.fetchSupplierPayables();
});

const formatCurrency = (val: number | string | undefined | null): string => {
    if (val === undefined || val === null) return '₹0.00';
    const num = typeof val === 'string' ? parseFloat(val) : val;
    return isNaN(num) ? '₹0.00' : '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (dateStr: string | undefined | null): string => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
};

const handleSearch = () => {
    store.fetchSupplierPayables();
};

const handleFilterChange = () => {
    store.fetchSupplierPayables();
};

const resetFilters = () => {
    store.resetSupplierPayablesFilters();
};

const openInwardsModal = (supp: SupplierPayableItem) => {
    selectedSupplierForInwards.value = supp;
    isInwardModalOpen.value = true;
};

const closeInwardsModal = () => {
    isInwardModalOpen.value = false;
    selectedSupplierForInwards.value = null;
};

useEscapeKey(closeInwardsModal);

const printReport = () => {
    window.print();
};

const exportToCsv = () => {
    if (!store.supplierPayables?.suppliers || store.supplierPayables.suppliers.length === 0) return;
    
    const headers = [
        'Supplier Name',
        'Supplier Code',
        'Phone',
        'City',
        'Total Inward Amount (Rs)',
        'Total Paid (Rs)',
        'Total Discount (Rs)',
        'Total Payable Balance (Rs)',
        '0-15 Days (Rs)',
        '16-30 Days (Rs)',
        '31-60 Days (Rs)',
        '60+ Days (Rs)',
        'Pending Inwards Count'
    ];

    const rows = store.supplierPayables.suppliers.map(s => [
        `"${s.name.replace(/"/g, '""')}"`,
        `"${s.code || ''}"`,
        `"${s.phone || ''}"`,
        `"${s.city || ''}"`,
        s.total_inward_amount,
        s.total_paid,
        s.total_discount,
        s.total_payable,
        s.age_0_15,
        s.age_16_30,
        s.age_31_60,
        s.age_60_plus,
        s.inwards_count
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `supplier_payables_report_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <AppLayout>
        <div class="p-4 sm:p-6 lg:p-8 space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-teal-50 border border-teal-100 rounded-xl text-teal-600">
                        <Building2 class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Supplier Payables & Ageing</h1>
                        <p class="text-xs text-slate-500 mt-0.5">Accounts payable balances for raw material suppliers and procurement inwards</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        @click="exportToCsv"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition shadow-sm"
                    >
                        <Download class="w-4 h-4 text-slate-500" />
                        Export CSV
                    </button>
                    <button
                        @click="printReport"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition shadow-sm"
                    >
                        <Printer class="w-4 h-4 text-slate-500" />
                        Print
                    </button>
                    <button
                        @click="store.fetchSupplierPayables"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-teal-600 rounded-xl hover:bg-teal-700 transition shadow-sm"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': store.supplierPayablesLoading }" />
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-bold text-slate-800">
                        <Filter class="w-4 h-4 text-teal-600" />
                        Filters
                    </div>
                    <button
                        @click="resetFilters"
                        class="text-xs text-teal-600 hover:text-teal-800 font-semibold inline-flex items-center gap-1"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        Reset Filters
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- As of Date -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">As Of Date</label>
                        <input
                            type="date"
                            v-model="store.supplierPayablesFilters.as_of_date"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-teal-500 focus:bg-white outline-none"
                        />
                    </div>

                    <!-- Supplier Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Supplier / Vendor</label>
                        <select
                            v-model="store.supplierPayablesFilters.supplier_id"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-teal-500 focus:bg-white outline-none"
                        >
                            <option value="all">All Suppliers</option>
                            <option
                                v-for="s in store.meta?.suppliers"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }} {{ s.code ? `(${s.code})` : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Ageing Bracket Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Ageing Bracket</label>
                        <select
                            v-model="store.supplierPayablesFilters.ageing_bracket"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-teal-500 focus:bg-white outline-none"
                        >
                            <option value="all">All Overdue Days</option>
                            <option value="0_15">0 - 15 Days</option>
                            <option value="16_30">16 - 30 Days</option>
                            <option value="31_60">31 - 60 Days</option>
                            <option value="60_plus">60+ Days Overdue</option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
                        <div class="relative">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                type="text"
                                v-model="store.supplierPayablesFilters.search"
                                @input="handleSearch"
                                placeholder="Name, code, phone, city..."
                                class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-3 py-2 focus:ring-2 focus:ring-teal-500 focus:bg-white outline-none"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3" v-if="store.supplierPayables?.kpis">
                <!-- Total Inward Amount -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Purchases</span>
                        <div class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                            <Truck class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-slate-900">
                            {{ formatCurrency(store.supplierPayables.kpis.total_inward_amount) }}
                        </div>
                        <span class="text-[11px] text-slate-500">{{ store.supplierPayables.kpis.total_suppliers }} supplier(s)</span>
                    </div>
                </div>

                <!-- Total Paid -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Paid</span>
                        <div class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg">
                            <ArrowUpRight class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-emerald-600">
                            {{ formatCurrency(store.supplierPayables.kpis.total_paid) }}
                        </div>
                        <span class="text-[11px] text-slate-500">Disc: {{ formatCurrency(store.supplierPayables.kpis.total_discount) }}</span>
                    </div>
                </div>

                <!-- Total Net Payable -->
                <div class="bg-amber-50/70 p-4 rounded-2xl border border-amber-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-amber-800 uppercase tracking-wider">Net Payable</span>
                        <div class="p-1.5 bg-amber-100 text-amber-800 rounded-lg">
                            <DollarSign class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-amber-800">
                            {{ formatCurrency(store.supplierPayables.kpis.total_payable) }}
                        </div>
                        <span class="text-[11px] text-amber-700 font-medium">Outstanding to Suppliers</span>
                    </div>
                </div>

                <!-- Ageing: 0-30 Days -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">0 - 30 Days</span>
                        <div class="p-1.5 bg-teal-50 text-teal-600 rounded-lg">
                            <Clock class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-sm font-bold text-slate-800">
                            {{ formatCurrency((store.supplierPayables.kpis.total_0_15 || 0) + (store.supplierPayables.kpis.total_16_30 || 0)) }}
                        </div>
                        <span class="text-[10px] text-slate-500">0-15d: {{ formatCurrency(store.supplierPayables.kpis.total_0_15) }} | 16-30d: {{ formatCurrency(store.supplierPayables.kpis.total_16_30) }}</span>
                    </div>
                </div>

                <!-- Ageing: 31+ Days -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">31+ Days Overdue</span>
                        <div class="p-1.5 bg-rose-50 text-rose-600 rounded-lg">
                            <AlertCircle class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-sm font-bold text-rose-600">
                            {{ formatCurrency((store.supplierPayables.kpis.total_31_60 || 0) + (store.supplierPayables.kpis.total_60_plus || 0)) }}
                        </div>
                        <span class="text-[10px] text-rose-600 font-medium">60+d: {{ formatCurrency(store.supplierPayables.kpis.total_60_plus) }}</span>
                    </div>
                </div>
            </div>

            <!-- Main Supplier Payables Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider font-semibold">
                                <th class="py-3.5 px-4">Supplier Details</th>
                                <th class="py-3.5 px-3 text-right">Inward Total (₹)</th>
                                <th class="py-3.5 px-3 text-right">Paid (₹)</th>
                                <th class="py-3.5 px-3 text-right">Discount (₹)</th>
                                <th class="py-3.5 px-3 text-right font-bold text-slate-900 bg-slate-100/70">Payable Balance (₹)</th>
                                <th class="py-3.5 px-3 text-right text-emerald-700">0 - 15 d</th>
                                <th class="py-3.5 px-3 text-right text-sky-700">16 - 30 d</th>
                                <th class="py-3.5 px-3 text-right text-amber-700">31 - 60 d</th>
                                <th class="py-3.5 px-3 text-right text-rose-700">60+ d</th>
                                <th class="py-3.5 px-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="store.supplierPayablesLoading">
                                <td colspan="10" class="py-12 text-center text-slate-500">
                                    <div class="flex items-center justify-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-teal-600" />
                                        <span>Loading supplier payables data...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="!store.supplierPayables?.suppliers || store.supplierPayables.suppliers.length === 0">
                                <td colspan="10" class="py-12 text-center text-slate-400">
                                    No supplier payables records found matching your filters.
                                </td>
                            </tr>
                            <tr
                                v-for="supp in store.supplierPayables?.suppliers"
                                :key="supp.id"
                                class="hover:bg-teal-50/30 transition-colors"
                            >
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-900">{{ supp.name }}</div>
                                    <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                        <span v-if="supp.code" class="font-mono bg-slate-100 px-1 py-0.5 rounded text-[10px]">{{ supp.code }}</span>
                                        <span v-if="supp.phone">{{ supp.phone }}</span>
                                        <span v-if="supp.city">• {{ supp.city }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-slate-700">
                                    {{ formatCurrency(supp.total_inward_amount) }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-emerald-600">
                                    {{ formatCurrency(supp.total_paid) }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-slate-500">
                                    {{ formatCurrency(supp.total_discount) }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-amber-700 bg-amber-50/30">
                                    {{ formatCurrency(supp.total_payable) }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-emerald-700">
                                    {{ supp.age_0_15 > 0 ? formatCurrency(supp.age_0_15) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-sky-700">
                                    {{ supp.age_16_30 > 0 ? formatCurrency(supp.age_16_30) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-amber-700">
                                    {{ supp.age_31_60 > 0 ? formatCurrency(supp.age_31_60) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-rose-700">
                                    {{ supp.age_60_plus > 0 ? formatCurrency(supp.age_60_plus) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <button
                                        @click="openInwardsModal(supp)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-teal-700 bg-teal-50 border border-teal-100 rounded-lg hover:bg-teal-100 transition"
                                        title="View Pending Inward Bills"
                                    >
                                        <Eye class="w-3.5 h-3.5" />
                                        Inwards ({{ supp.inwards_count }})
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="store.supplierPayables?.kpis && (store.supplierPayables.suppliers?.length ?? 0) > 0">
                            <tr class="bg-slate-100/80 font-bold border-t border-slate-300 text-slate-800">
                                <td class="py-3 px-4">Total ({{ store.supplierPayables.kpis.total_suppliers }} Suppliers)</td>
                                <td class="py-3 px-3 text-right">{{ formatCurrency(store.supplierPayables.kpis.total_inward_amount) }}</td>
                                <td class="py-3 px-3 text-right text-emerald-600">{{ formatCurrency(store.supplierPayables.kpis.total_paid) }}</td>
                                <td class="py-3 px-3 text-right text-slate-500">{{ formatCurrency(store.supplierPayables.kpis.total_discount) }}</td>
                                <td class="py-3 px-3 text-right text-amber-800 bg-amber-100/50">{{ formatCurrency(store.supplierPayables.kpis.total_payable) }}</td>
                                <td class="py-3 px-3 text-right text-emerald-700">{{ formatCurrency(store.supplierPayables.kpis.total_0_15) }}</td>
                                <td class="py-3 px-3 text-right text-sky-700">{{ formatCurrency(store.supplierPayables.kpis.total_16_30) }}</td>
                                <td class="py-3 px-3 text-right text-amber-700">{{ formatCurrency(store.supplierPayables.kpis.total_31_60) }}</td>
                                <td class="py-3 px-3 text-right text-rose-700">{{ formatCurrency(store.supplierPayables.kpis.total_60_plus) }}</td>
                                <td class="py-3 px-3 text-center">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Inwards Drilldown Modal -->
            <div
                v-if="isInwardModalOpen && selectedSupplierForInwards"
                class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
            >
                <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-5xl overflow-hidden max-h-[90vh] flex flex-col">
                    <!-- Modal Header -->
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Pending Inward Bills: {{ selectedSupplierForInwards.name }}
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Total Payable: <span class="font-bold text-amber-700">{{ formatCurrency(selectedSupplierForInwards.total_payable) }}</span>
                                <span v-if="selectedSupplierForInwards.code" class="ml-2 font-mono bg-slate-200 px-1.5 py-0.5 rounded text-[10px]">Code: {{ selectedSupplierForInwards.code }}</span>
                            </p>
                        </div>
                        <button
                            @click="closeInwardsModal"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Modal Body: Inward Bills Table -->
                    <div class="p-6 overflow-y-auto flex-1">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider font-semibold">
                                    <th class="py-2.5 px-3">Inward No / Bill No</th>
                                    <th class="py-2.5 px-3">Date / Vehicle</th>
                                    <th class="py-2.5 px-3">Items Summary</th>
                                    <th class="py-2.5 px-3 text-right">Bill Total (₹)</th>
                                    <th class="py-2.5 px-3 text-right">Paid (₹)</th>
                                    <th class="py-2.5 px-3 text-right">Discount (₹)</th>
                                    <th class="py-2.5 px-3 text-right font-bold text-slate-900 bg-slate-100/70">Payable Due (₹)</th>
                                    <th class="py-2.5 px-3 text-center">Overdue Days</th>
                                    <th class="py-2.5 px-3 text-center">Ageing Bracket</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="inw in selectedSupplierForInwards.inwards"
                                    :key="inw.inward_id"
                                    class="hover:bg-slate-50 transition-colors"
                                >
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-slate-900">{{ inw.inward_no }}</div>
                                        <div class="text-[11px] text-slate-500 font-mono">Bill: {{ inw.bill_no || '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="text-slate-800">{{ formatDate(inw.date) }}</div>
                                        <div class="text-[11px] text-slate-500">{{ inw.vehicle_no || '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-700 max-w-xs truncate" :title="inw.items_summary">
                                        {{ inw.items_summary || '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-medium text-slate-700">
                                        {{ formatCurrency(inw.grand_total) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-medium text-emerald-600">
                                        {{ formatCurrency(inw.paid_amount) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-medium text-slate-500">
                                        {{ formatCurrency(inw.discount_amount) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-bold text-amber-700 bg-amber-50/40">
                                        {{ formatCurrency(inw.balance_payable) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-semibold" :class="inw.age_days > 30 ? 'text-rose-600' : 'text-slate-700'">
                                        {{ inw.age_days }} days
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span
                                            v-if="inw.age_bracket === '0_15'"
                                            class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                        >
                                            0-15 Days
                                        </span>
                                        <span
                                            v-else-if="inw.age_bracket === '16_30'"
                                            class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800"
                                        >
                                            16-30 Days
                                        </span>
                                        <span
                                            v-else-if="inw.age_bracket === '31_60'"
                                            class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800"
                                        >
                                            31-60 Days
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800"
                                        >
                                            60+ Days
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                        <button
                            @click="closeInwardsModal"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
