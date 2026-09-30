<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useFinancialReportStore } from '../../stores/financialReport';
import type { CustomerOutstandingItem, OutstandingInvoice } from '../../types/financialReport';
import AppLayout from '../../components/layout/AppLayout.vue';
import { useEscapeKey } from '../../composables/useEscapeKey';
import {
    Search,
    RefreshCw,
    Printer,
    Download,
    Eye,
    RotateCcw,
    Users,
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
    Building2,
    DollarSign
} from 'lucide-vue-next';

const store = useFinancialReportStore();

const selectedCustomerForInvoices = ref<CustomerOutstandingItem | null>(null);
const isInvoiceModalOpen = ref(false);

onMounted(async () => {
    if (!store.meta) {
        await store.fetchMeta();
    }
    await store.fetchCustomerOutstanding();
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
    store.fetchCustomerOutstanding();
};

const handleFilterChange = () => {
    store.fetchCustomerOutstanding();
};

const resetFilters = () => {
    store.resetCustomerOutstandingFilters();
};

const openInvoicesModal = (cust: CustomerOutstandingItem) => {
    selectedCustomerForInvoices.value = cust;
    isInvoiceModalOpen.value = true;
};

const closeInvoicesModal = () => {
    isInvoiceModalOpen.value = false;
    selectedCustomerForInvoices.value = null;
};

useEscapeKey(closeInvoicesModal);

const printReport = () => {
    window.print();
};

const exportToCsv = () => {
    if (!store.customerOutstanding?.customers || store.customerOutstanding.customers.length === 0) return;
    
    const headers = [
        'Customer Name',
        'Customer Code',
        'Phone',
        'City',
        'Agent',
        'Total Invoiced (Rs)',
        'Total Received (Rs)',
        'Total Discount (Rs)',
        'Outstanding Balance (Rs)',
        '0-15 Days (Rs)',
        '16-30 Days (Rs)',
        '31-60 Days (Rs)',
        '60+ Days (Rs)',
        'Pending Invoices Count'
    ];

    const rows = store.customerOutstanding.customers.map(c => [
        `"${c.name.replace(/"/g, '""')}"`,
        `"${c.code || ''}"`,
        `"${c.phone || ''}"`,
        `"${c.city || ''}"`,
        `"${c.agent_name || ''}"`,
        c.total_invoiced,
        c.total_received,
        c.total_discount,
        c.total_outstanding,
        c.age_0_15,
        c.age_16_30,
        c.age_31_60,
        c.age_60_plus,
        c.invoices_count
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `customer_outstanding_report_${new Date().toISOString().split('T')[0]}.csv`);
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
                    <div class="p-3 bg-indigo-50 border border-indigo-100 rounded-xl text-indigo-600">
                        <Users class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Customer Outstanding & Ageing</h1>
                        <p class="text-xs text-slate-500 mt-0.5">Accounts receivable balances and overdue ageing distribution</p>
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
                        @click="store.fetchCustomerOutstanding"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition shadow-sm"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': store.customerOutstandingLoading }" />
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-bold text-slate-800">
                        <Filter class="w-4 h-4 text-indigo-600" />
                        Filters
                    </div>
                    <button
                        @click="resetFilters"
                        class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold inline-flex items-center gap-1"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        Reset Filters
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- As of Date -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">As Of Date</label>
                        <input
                            type="date"
                            v-model="store.customerOutstandingFilters.as_of_date"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none"
                        />
                    </div>

                    <!-- Customer Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Customer</label>
                        <select
                            v-model="store.customerOutstandingFilters.customer_id"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none"
                        >
                            <option value="all">All Customers</option>
                            <option
                                v-for="c in store.meta?.customers"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }} {{ c.code ? `(${c.code})` : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Agent Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Agent / Broker</label>
                        <select
                            v-model="store.customerOutstandingFilters.agent_id"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none"
                        >
                            <option value="all">All Agents</option>
                            <option
                                v-for="ag in store.meta?.agents"
                                :key="ag.id"
                                :value="ag.id"
                            >
                                {{ ag.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Ageing Bracket Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Ageing Bracket</label>
                        <select
                            v-model="store.customerOutstandingFilters.ageing_bracket"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none"
                        >
                            <option value="all">All Overdue Days</option>
                            <option value="0_15">0 - 15 Days</option>
                            <option value="16_30">16 - 30 Days</option>
                            <option value="31_60">31 - 60 Days</option>
                            <option value="60_plus">60+ Days (High Risk)</option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
                        <div class="relative">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                type="text"
                                v-model="store.customerOutstandingFilters.search"
                                @input="handleSearch"
                                placeholder="Name, code, phone, city..."
                                class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3" v-if="store.customerOutstanding?.kpis">
                <!-- Total Invoiced -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Invoiced</span>
                        <div class="p-1.5 bg-blue-50 text-blue-600 rounded-lg">
                            <FileText class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-slate-900">
                            {{ formatCurrency(store.customerOutstanding.kpis.total_invoiced) }}
                        </div>
                        <span class="text-[11px] text-slate-500">{{ store.customerOutstanding.kpis.total_customers }} customer(s)</span>
                    </div>
                </div>

                <!-- Total Received -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Received</span>
                        <div class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg">
                            <ArrowDownLeft class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-emerald-600">
                            {{ formatCurrency(store.customerOutstanding.kpis.total_received) }}
                        </div>
                        <span class="text-[11px] text-slate-500">Disc: {{ formatCurrency(store.customerOutstanding.kpis.total_discount) }}</span>
                    </div>
                </div>

                <!-- Total Outstanding -->
                <div class="bg-rose-50/70 p-4 rounded-2xl border border-rose-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Total Outstanding</span>
                        <div class="p-1.5 bg-rose-100 text-rose-700 rounded-lg">
                            <DollarSign class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-rose-700">
                            {{ formatCurrency(store.customerOutstanding.kpis.total_outstanding) }}
                        </div>
                        <span class="text-[11px] text-rose-600 font-medium">Overdue Receivable</span>
                    </div>
                </div>

                <!-- Ageing: 0-30 Days -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">0 - 30 Days</span>
                        <div class="p-1.5 bg-amber-50 text-amber-600 rounded-lg">
                            <Clock class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-sm font-bold text-slate-800">
                            {{ formatCurrency((store.customerOutstanding.kpis.total_0_15 || 0) + (store.customerOutstanding.kpis.total_16_30 || 0)) }}
                        </div>
                        <span class="text-[10px] text-slate-500">0-15d: {{ formatCurrency(store.customerOutstanding.kpis.total_0_15) }} | 16-30d: {{ formatCurrency(store.customerOutstanding.kpis.total_16_30) }}</span>
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
                            {{ formatCurrency((store.customerOutstanding.kpis.total_31_60 || 0) + (store.customerOutstanding.kpis.total_60_plus || 0)) }}
                        </div>
                        <span class="text-[10px] text-rose-600 font-medium">60+d: {{ formatCurrency(store.customerOutstanding.kpis.total_60_plus) }}</span>
                    </div>
                </div>
            </div>

            <!-- Main Customer Outstanding Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider font-semibold">
                                <th class="py-3.5 px-4">Customer Details</th>
                                <th class="py-3.5 px-3">Agent</th>
                                <th class="py-3.5 px-3 text-right">Invoiced (₹)</th>
                                <th class="py-3.5 px-3 text-right">Received (₹)</th>
                                <th class="py-3.5 px-3 text-right">Discount (₹)</th>
                                <th class="py-3.5 px-3 text-right font-bold text-slate-900 bg-slate-100/70">Outstanding (₹)</th>
                                <th class="py-3.5 px-3 text-right text-emerald-700">0 - 15 d</th>
                                <th class="py-3.5 px-3 text-right text-sky-700">16 - 30 d</th>
                                <th class="py-3.5 px-3 text-right text-amber-700">31 - 60 d</th>
                                <th class="py-3.5 px-3 text-right text-rose-700">60+ d</th>
                                <th class="py-3.5 px-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="store.customerOutstandingLoading">
                                <td colspan="11" class="py-12 text-center text-slate-500">
                                    <div class="flex items-center justify-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-indigo-600" />
                                        <span>Loading customer outstanding data...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="!store.customerOutstanding?.customers || store.customerOutstanding.customers.length === 0">
                                <td colspan="11" class="py-12 text-center text-slate-400">
                                    No customer outstanding records found matching your filters.
                                </td>
                            </tr>
                            <tr
                                v-for="cust in store.customerOutstanding?.customers"
                                :key="cust.id"
                                class="hover:bg-indigo-50/30 transition-colors"
                            >
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-900">{{ cust.name }}</div>
                                    <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                        <span v-if="cust.code" class="font-mono bg-slate-100 px-1 py-0.5 rounded text-[10px]">{{ cust.code }}</span>
                                        <span v-if="cust.phone">{{ cust.phone }}</span>
                                        <span v-if="cust.city">• {{ cust.city }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-slate-600 font-medium">
                                    {{ cust.agent_name || '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-slate-700">
                                    {{ formatCurrency(cust.total_invoiced) }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-emerald-600">
                                    {{ formatCurrency(cust.total_received) }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-slate-500">
                                    {{ formatCurrency(cust.total_discount) }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-rose-600 bg-rose-50/30">
                                    {{ formatCurrency(cust.total_outstanding) }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-emerald-700">
                                    {{ cust.age_0_15 > 0 ? formatCurrency(cust.age_0_15) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-sky-700">
                                    {{ cust.age_16_30 > 0 ? formatCurrency(cust.age_16_30) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-amber-700">
                                    {{ cust.age_31_60 > 0 ? formatCurrency(cust.age_31_60) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-rose-700">
                                    {{ cust.age_60_plus > 0 ? formatCurrency(cust.age_60_plus) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <button
                                        @click="openInvoicesModal(cust)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 transition"
                                        title="View Pending Invoices"
                                    >
                                        <Eye class="w-3.5 h-3.5" />
                                        Invoices ({{ cust.invoices_count }})
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="store.customerOutstanding?.kpis && (store.customerOutstanding.customers?.length ?? 0) > 0">
                            <tr class="bg-slate-100/80 font-bold border-t border-slate-300 text-slate-800">
                                <td class="py-3 px-4">Total ({{ store.customerOutstanding.kpis.total_customers }} Customers)</td>
                                <td class="py-3 px-3">-</td>
                                <td class="py-3 px-3 text-right">{{ formatCurrency(store.customerOutstanding.kpis.total_invoiced) }}</td>
                                <td class="py-3 px-3 text-right text-emerald-600">{{ formatCurrency(store.customerOutstanding.kpis.total_received) }}</td>
                                <td class="py-3 px-3 text-right text-slate-500">{{ formatCurrency(store.customerOutstanding.kpis.total_discount) }}</td>
                                <td class="py-3 px-3 text-right text-rose-700 bg-rose-100/50">{{ formatCurrency(store.customerOutstanding.kpis.total_outstanding) }}</td>
                                <td class="py-3 px-3 text-right text-emerald-700">{{ formatCurrency(store.customerOutstanding.kpis.total_0_15) }}</td>
                                <td class="py-3 px-3 text-right text-sky-700">{{ formatCurrency(store.customerOutstanding.kpis.total_16_30) }}</td>
                                <td class="py-3 px-3 text-right text-amber-700">{{ formatCurrency(store.customerOutstanding.kpis.total_31_60) }}</td>
                                <td class="py-3 px-3 text-right text-rose-700">{{ formatCurrency(store.customerOutstanding.kpis.total_60_plus) }}</td>
                                <td class="py-3 px-3 text-center">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Invoices Drilldown Modal -->
            <div
                v-if="isInvoiceModalOpen && selectedCustomerForInvoices"
                class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
            >
                <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-5xl overflow-hidden max-h-[90vh] flex flex-col">
                    <!-- Modal Header -->
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Pending Invoices: {{ selectedCustomerForInvoices.name }}
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Total Outstanding: <span class="font-bold text-rose-600">{{ formatCurrency(selectedCustomerForInvoices.total_outstanding) }}</span>
                                <span v-if="selectedCustomerForInvoices.code" class="ml-2 font-mono bg-slate-200 px-1.5 py-0.5 rounded text-[10px]">Code: {{ selectedCustomerForInvoices.code }}</span>
                            </p>
                        </div>
                        <button
                            @click="closeInvoicesModal"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Modal Body: Invoices Table -->
                    <div class="p-6 overflow-y-auto flex-1">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider font-semibold">
                                    <th class="py-2.5 px-3">Challan / Date</th>
                                    <th class="py-2.5 px-3">Site / Vehicle</th>
                                    <th class="py-2.5 px-3 text-right">Invoiced (₹)</th>
                                    <th class="py-2.5 px-3 text-right">Received (₹)</th>
                                    <th class="py-2.5 px-3 text-right">Discount (₹)</th>
                                    <th class="py-2.5 px-3 text-right font-bold text-slate-900 bg-slate-100/70">Balance Due (₹)</th>
                                    <th class="py-2.5 px-3 text-center">Overdue Days</th>
                                    <th class="py-2.5 px-3 text-center">Ageing Bracket</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="inv in selectedCustomerForInvoices.invoices"
                                    :key="inv.outward_id"
                                    class="hover:bg-slate-50 transition-colors"
                                >
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-slate-900">{{ inv.challan_no }}</div>
                                        <div class="text-[11px] text-slate-500">{{ formatDate(inv.date) }}</div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="text-slate-800">{{ inv.site_location || '-' }}</div>
                                        <div class="text-[11px] text-slate-500">{{ inv.vehicle_no || '-' }}</div>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-medium text-slate-700">
                                        {{ formatCurrency(inv.grand_total) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-medium text-emerald-600">
                                        {{ formatCurrency(inv.received_amount) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-medium text-slate-500">
                                        {{ formatCurrency(inv.discount_amount) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-bold text-rose-600 bg-rose-50/40">
                                        {{ formatCurrency(inv.balance_due) }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-semibold" :class="inv.age_days > 30 ? 'text-rose-600' : 'text-slate-700'">
                                        {{ inv.age_days }} days
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span
                                            v-if="inv.age_bracket === '0_15'"
                                            class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                        >
                                            0-15 Days
                                        </span>
                                        <span
                                            v-else-if="inv.age_bracket === '16_30'"
                                            class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800"
                                        >
                                            16-30 Days
                                        </span>
                                        <span
                                            v-else-if="inv.age_bracket === '31_60'"
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
                            @click="closeInvoicesModal"
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
