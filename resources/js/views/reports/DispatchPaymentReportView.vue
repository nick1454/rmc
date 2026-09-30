<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useDispatchReportStore } from '../../stores/dispatchReport';
import type { DispatchReportRow } from '../../types/report';
import AppLayout from '../../components/layout/AppLayout.vue';
import {
    Search,
    RefreshCw,
    Printer,
    Download,
    RotateCcw,
    FileSpreadsheet,
    Building2,
    Calendar,
    Wallet,
    CreditCard,
    BadgePercent,
    AlertCircle,
    CheckCircle2,
    Clock,
    UserCheck,
    Filter,
    ArrowDownUp,
    MapPin,
    Truck
} from 'lucide-vue-next';

const reportStore = useDispatchReportStore();

// Pagination state
const currentPage = ref(1);
const perPage = ref(15);

onMounted(async () => {
    await reportStore.fetchMeta();
    await reportStore.fetchReport();
});

const handleSearch = () => {
    currentPage.value = 1;
    reportStore.fetchReport();
};

const handleFilterChange = () => {
    currentPage.value = 1;
    reportStore.fetchReport();
};

const handleSortToggle = () => {
    reportStore.sortOrder = reportStore.sortOrder === 'asc' ? 'desc' : 'asc';
    reportStore.fetchReport();
};

const resetAllFilters = () => {
    currentPage.value = 1;
    reportStore.resetFilters();
};

const formatNumber = (val: number | undefined | null, decimals = 2): string => {
    if (val === undefined || val === null || isNaN(val)) return '0.00';
    return Number(val).toLocaleString('en-IN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
};

const formatDateDisplay = (dateStr: string | undefined | null): string => {
    if (!dateStr) return '—';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dateStr;
};

// Paginated records
const totalItems = computed(() => reportStore.records.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / perPage.value)));

const paginatedItems = computed(() => {
    if (perPage.value === -1) return reportStore.records;
    const start = (currentPage.value - 1) * perPage.value;
    return reportStore.records.slice(start, start + perPage.value);
});

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const printReport = () => {
    window.print();
};

const exportCsv = () => {
    const rows = reportStore.records;
    if (!rows.length) return;

    const headers = [
        'DATE',
        'PARTY NAME',
        'LOCATION',
        'GRADE',
        'OPC',
        'QUANT (CUM)',
        'RATE',
        'ADVAN',
        'PAYMENT MODE',
        'AMOUNT',
        'RECIVE PAY',
        'PENDING',
        'RELIF',
        'COMMISSION',
        'IN ACC',
        'SELLER',
        'NOTE',
        'ENTRY NO',
        'VEHICLE NO'
    ];

    const csvData = rows.map((r) => [
        `"${r.date}"`,
        `"${r.party_name.replace(/"/g, '""')}"`,
        `"${(r.location || '').replace(/"/g, '""')}"`,
        `"${(r.grade || '').replace(/"/g, '""')}"`,
        `"${(r.opc || '').replace(/"/g, '""')}"`,
        r.quant,
        r.rate,
        r.advance,
        `"${(r.payment_mode || '').replace(/"/g, '""')}"`,
        r.amount,
        r.receive_pay,
        r.pending,
        r.relief,
        r.commission,
        `"${(r.in_acc || '').replace(/"/g, '""')}"`,
        `"${(r.seller || '').replace(/"/g, '""')}"`,
        `"${(r.note || '').replace(/"/g, '""')}"`,
        `"${r.entry_no}"`,
        `"${r.vehicle_no || ''}"`
    ]);

    // Add totals row
    if (reportStore.totals) {
        const t = reportStore.totals;
        csvData.push([
            '"TOTALS"',
            '""',
            '""',
            '""',
            '""',
            t.total_qty,
            '""',
            t.total_advance,
            '""',
            t.total_amount,
            t.total_received,
            t.total_pending,
            t.total_relief,
            t.total_commission,
            '""',
            '""',
            '""',
            '""',
            '""'
        ]);
    }

    const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...csvData.map((e) => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    const filenameParty = reportStore.selectedParty ? `_${reportStore.selectedParty.name.replace(/[^a-zA-Z0-9]/g, '_')}` : '';
    link.setAttribute('download', `Dispatch_Billing_Register${filenameParty}_${reportStore.fromDateFilter || 'all'}_to_${reportStore.toDateFilter || 'all'}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <AppLayout title="Dispatch & Customer Billing Register" subtitle="Production dispatches, customer payments, discounts, commissions & outstanding ledger">
        <div class="space-y-5">
            <!-- Filter & Action Toolbar -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-4 print:hidden">
                <!-- Top Row: Search, Presets, Actions -->
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    <!-- Search Input -->
                    <div class="relative min-w-[260px] flex-1 max-w-md">
                        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="reportStore.searchQuery"
                            type="text"
                            placeholder="Search party, location, grade, seller, note, vehicle..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white font-medium"
                        />
                    </div>

                    <!-- Date Presets Pills -->
                    <div class="flex items-center gap-1 overflow-x-auto py-1 custom-scrollbar text-xs">
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('today')"
                            class="px-2.5 py-1.5 rounded-lg font-semibold whitespace-nowrap transition-colors"
                            :class="reportStore.fromDateFilter === reportStore.toDateFilter && reportStore.fromDateFilter ? 'bg-amber-500 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('this_week')"
                            class="px-2.5 py-1.5 rounded-lg font-semibold whitespace-nowrap bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                        >
                            This Week
                        </button>
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('this_month')"
                            class="px-2.5 py-1.5 rounded-lg font-semibold whitespace-nowrap bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                        >
                            This Month
                        </button>
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('last_month')"
                            class="px-2.5 py-1.5 rounded-lg font-semibold whitespace-nowrap bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                        >
                            Last Month
                        </button>
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('all')"
                            class="px-2.5 py-1.5 rounded-lg font-semibold whitespace-nowrap transition-colors"
                            :class="!reportStore.fromDateFilter && !reportStore.toDateFilter ? 'bg-amber-500 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                        >
                            All Time
                        </button>
                    </div>

                    <!-- Actions Right -->
                    <div class="flex items-center gap-2 self-end lg:self-auto">
                        <button
                            type="button"
                            @click="handleSortToggle"
                            class="flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-colors shadow-xs"
                            :title="reportStore.sortOrder === 'asc' ? 'Sorted: Oldest to Newest (Ledger Order)' : 'Sorted: Newest First'"
                        >
                            <ArrowDownUp class="w-3.5 h-3.5 text-slate-500" />
                            <span class="hidden sm:inline">{{ reportStore.sortOrder === 'asc' ? 'Asc Date' : 'Desc Date' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="exportCsv"
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-semibold rounded-xl border border-emerald-200 transition-colors shadow-xs"
                            title="Export Register to Excel / CSV"
                        >
                            <Download class="w-3.5 h-3.5 text-emerald-600" />
                            <span>Export CSV</span>
                        </button>
                        <button
                            type="button"
                            @click="printReport"
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-colors shadow-xs"
                            title="Print Register"
                        >
                            <Printer class="w-3.5 h-3.5 text-slate-500" />
                            <span>Print</span>
                        </button>
                        <button
                            type="button"
                            @click="handleFilterChange"
                            class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors"
                            title="Refresh Data"
                        >
                            <RefreshCw :class="['w-4 h-4', reportStore.isLoading ? 'animate-spin' : '']" />
                        </button>
                        <button
                            type="button"
                            @click="resetAllFilters"
                            class="flex items-center gap-1 px-3 py-2 bg-slate-50 hover:bg-amber-50 text-slate-600 hover:text-amber-800 text-xs font-semibold rounded-xl border border-slate-200 transition-colors"
                            title="Reset all filters"
                        >
                            <RotateCcw class="w-3.5 h-3.5" />
                            <span class="hidden sm:inline">Reset</span>
                        </button>
                    </div>
                </div>

                <!-- Secondary Filters Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 pt-3 border-t border-slate-100">
                    <!-- Party / Customer Filter -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Customer / Party</label>
                        <select
                            v-model="reportStore.partyFilter"
                            @change="handleFilterChange"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-800 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        >
                            <option value="all">All Customers / Parties</option>
                            <option v-for="c in reportStore.customers" :key="c.id" :value="c.id">
                                {{ c.name }} {{ c.code ? `(${c.code})` : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- Concrete Grade Filter -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Concrete Grade</label>
                        <select
                            v-model="reportStore.gradeFilter"
                            @change="handleFilterChange"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-800 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        >
                            <option value="all">All Concrete Grades</option>
                            <option v-for="g in reportStore.grades" :key="g" :value="g">
                                {{ g }}
                            </option>
                        </select>
                    </div>

                    <!-- Seller / Agent Filter -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Seller / Agent</label>
                        <select
                            v-model="reportStore.agentFilter"
                            @change="handleFilterChange"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-800 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        >
                            <option value="all">All Salespersons / Agents</option>
                            <option v-for="a in reportStore.agents" :key="a.id" :value="a.id">
                                {{ a.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Payment Status Filter -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Payment Status</label>
                        <select
                            v-model="reportStore.paymentStatusFilter"
                            @change="handleFilterChange"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-800 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        >
                            <option value="all">All Statuses</option>
                            <option value="unpaid">Unpaid / Fully Pending</option>
                            <option value="partially_paid">Partially Paid</option>
                            <option value="paid">Fully Paid</option>
                        </select>
                    </div>

                    <!-- Date Range Input (From & To) -->
                    <div class="grid grid-cols-2 gap-1.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">From Date</label>
                            <input
                                type="date"
                                v-model="reportStore.fromDateFilter"
                                @change="handleFilterChange"
                                class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white cursor-pointer"
                            />
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">To Date</label>
                            <input
                                type="date"
                                v-model="reportStore.toDateFilter"
                                @change="handleFilterChange"
                                class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white cursor-pointer"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Banner Header (Identical to Excel Top Yellow Header Banner) -->
            <div class="bg-amber-400 border border-amber-500 text-slate-900 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3 print:border-amber-600 print:bg-amber-300">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-black/10 rounded-xl">
                        <Building2 class="w-6 h-6 text-slate-900" />
                    </div>
                    <div>
                        <div class="text-[11px] font-extrabold uppercase tracking-widest text-slate-800">
                            {{ reportStore.selectedParty ? 'Customer Dispatch & Billing Register' : 'Consolidated Dispatch & Customer Billing Register' }}
                        </div>
                        <h2 class="text-xl md:text-2xl font-black tracking-tight text-slate-950 uppercase">
                            {{ reportStore.selectedParty ? reportStore.selectedParty.name : 'ALL PARTIES REGISTER' }}
                        </h2>
                        <div v-if="reportStore.selectedParty" class="text-xs text-slate-800 font-medium flex flex-wrap items-center gap-2 mt-0.5">
                            <span v-if="reportStore.selectedParty.code">Code: <strong class="font-bold">{{ reportStore.selectedParty.code }}</strong></span>
                            <span v-if="reportStore.selectedParty.phone">• Phone: <strong class="font-bold">{{ reportStore.selectedParty.phone }}</strong></span>
                            <span v-if="reportStore.selectedParty.city">• City: <strong class="font-bold">{{ reportStore.selectedParty.city }}</strong></span>
                            <span v-if="reportStore.selectedParty.gstin">• GSTIN: <strong class="font-mono font-bold">{{ reportStore.selectedParty.gstin }}</strong></span>
                        </div>
                    </div>
                </div>

                <div class="text-right flex md:flex-col items-center md:items-end justify-between md:justify-center border-t md:border-t-0 pt-2 md:pt-0 border-amber-500/40">
                    <div class="text-xs font-black uppercase text-slate-900">
                        {{ reportStore.totals?.count || 0 }} Dispatches
                    </div>
                    <div class="text-[11px] text-slate-800 font-mono font-bold">
                        {{ reportStore.fromDateFilter ? formatDateDisplay(reportStore.fromDateFilter) : 'Start' }} to {{ reportStore.toDateFilter ? formatDateDisplay(reportStore.toDateFilter) : 'Today' }}
                    </div>
                </div>
            </div>

            <!-- KPI Metric Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 print:grid-cols-6">
                <!-- Total Quantity -->
                <div class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-xs">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Quantity</div>
                    <div class="text-lg font-black text-slate-900 font-mono mt-1">
                        {{ formatNumber(reportStore.totals?.total_qty) }} <span class="text-xs font-normal text-slate-500">m³</span>
                    </div>
                </div>

                <!-- Total Billed Amount -->
                <div class="bg-blue-50/60 border border-blue-200 rounded-2xl p-3.5 shadow-xs">
                    <div class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Total Billed</div>
                    <div class="text-lg font-black text-blue-900 font-mono mt-1">
                        ₹{{ formatNumber(reportStore.totals?.total_amount) }}
                    </div>
                </div>

                <!-- Total Received -->
                <div class="bg-emerald-50/60 border border-emerald-200 rounded-2xl p-3.5 shadow-xs">
                    <div class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Received Pay</div>
                    <div class="text-lg font-black text-emerald-900 font-mono mt-1">
                        ₹{{ formatNumber(reportStore.totals?.total_received) }}
                    </div>
                </div>

                <!-- Total Pending Balance -->
                <div class="bg-rose-50/60 border border-rose-200 rounded-2xl p-3.5 shadow-xs">
                    <div class="text-[10px] font-bold text-rose-700 uppercase tracking-wider">Pending Due</div>
                    <div class="text-lg font-black text-rose-900 font-mono mt-1">
                        ₹{{ formatNumber(reportStore.totals?.total_pending) }}
                    </div>
                </div>

                <!-- Total Relief / Discount -->
                <div class="bg-amber-50/60 border border-amber-200 rounded-2xl p-3.5 shadow-xs">
                    <div class="text-[10px] font-bold text-amber-800 uppercase tracking-wider">Relief / Discount</div>
                    <div class="text-lg font-black text-amber-900 font-mono mt-1">
                        ₹{{ formatNumber(reportStore.totals?.total_relief) }}
                    </div>
                </div>

                <!-- Total Commission -->
                <div class="bg-purple-50/60 border border-purple-200 rounded-2xl p-3.5 shadow-xs">
                    <div class="text-[10px] font-bold text-purple-700 uppercase tracking-wider">Commission</div>
                    <div class="text-lg font-black text-purple-900 font-mono mt-1">
                        ₹{{ formatNumber(reportStore.totals?.total_commission) }}
                    </div>
                </div>
            </div>

            <!-- Main Register Table Container -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- Table Header Bar -->
                <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <FileSpreadsheet class="w-4 h-4 text-amber-600" />
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Dispatch & Payment Rows</span>
                    </div>
                    <div class="flex items-center gap-3 text-[11px] text-slate-500 font-mono">
                        <span>Showing {{ paginatedItems.length }} of {{ totalItems }} records</span>
                        <div class="print:hidden flex items-center gap-1 font-sans">
                            <span>Per page:</span>
                            <select
                                v-model="perPage"
                                class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs text-slate-800"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="-1">All</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table View (Exact 17 Columns from Excel) -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse font-sans min-w-[1280px]">
                        <thead>
                            <tr class="bg-slate-100 text-slate-800 font-black uppercase text-[10px] tracking-wider border-b border-slate-300">
                                <th class="py-3 px-3 text-center w-24">DATE</th>
                                <th class="py-3 px-3">PARTY NAME</th>
                                <th class="py-3 px-3">LOCATION</th>
                                <th class="py-3 px-2 text-center w-16">GRADE</th>
                                <th class="py-3 px-2 text-center w-20">OPC</th>
                                <th class="py-3 px-2 text-right w-20">QUANT</th>
                                <th class="py-3 px-2 text-right w-20">RATE</th>
                                <th class="py-3 px-2 text-right w-20">ADVAN</th>
                                <th class="py-3 px-3 text-center">PUM / MODE</th>
                                <th class="py-3 px-3 text-right bg-blue-50/70 text-blue-900 w-28">AMOUNT</th>
                                <th class="py-3 px-3 text-right bg-emerald-50/70 text-emerald-900 w-28">RECIVE PAY</th>
                                <th class="py-3 px-3 text-right bg-rose-50/70 text-rose-900 w-28">PENDING</th>
                                <th class="py-3 px-2 text-right bg-amber-50/70 text-amber-900 w-20">RELIF</th>
                                <th class="py-3 px-2 text-right bg-purple-50/70 text-purple-900 w-20">COMMISSION</th>
                                <th class="py-3 px-2 text-center">IN ACC</th>
                                <th class="py-3 px-2">SELLER</th>
                                <th class="py-3 px-3">NOTE</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 font-medium text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="reportStore.isLoading && paginatedItems.length === 0">
                                <td colspan="17" class="py-12 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2 text-xs">
                                        <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                                        <span>Loading dispatch & billing records...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="paginatedItems.length === 0">
                                <td colspan="17" class="py-12 text-center text-slate-400">
                                    <FileSpreadsheet class="w-10 h-10 mx-auto mb-2 text-slate-300" />
                                    <div class="text-sm font-bold text-slate-700">No Dispatch Records Found</div>
                                    <p class="text-xs text-slate-400 mt-1">Try selecting another customer, grade, or expanding your date range.</p>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="(row, idx) in paginatedItems"
                                :key="`${row.id}-${row.item_id || idx}`"
                                class="hover:bg-slate-50/90 transition-colors group"
                            >
                                <!-- DATE -->
                                <td class="py-2.5 px-3 font-mono text-[11px] text-slate-900 text-center whitespace-nowrap">
                                    {{ formatDateDisplay(row.date) }}
                                </td>

                                <!-- PARTY NAME -->
                                <td class="py-2.5 px-3 font-bold text-slate-900">
                                    <div class="truncate max-w-[200px]" :title="row.party_name">
                                        {{ row.party_name }}
                                    </div>
                                    <div v-if="row.party_code" class="text-[9px] text-slate-400 font-mono">
                                        {{ row.party_code }} • {{ row.entry_no }}
                                    </div>
                                </td>

                                <!-- LOCATION -->
                                <td class="py-2.5 px-3 text-slate-600">
                                    <div class="truncate max-w-[160px]" :title="row.location">
                                        {{ row.location || '—' }}
                                    </div>
                                </td>

                                <!-- GRADE -->
                                <td class="py-2.5 px-2 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-black uppercase bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ row.grade || 'M-25' }}
                                    </span>
                                </td>

                                <!-- OPC -->
                                <td class="py-2.5 px-2 text-center font-mono text-[10px] text-slate-600">
                                    {{ row.opc || '—' }}
                                </td>

                                <!-- QUANT (CUM) -->
                                <td class="py-2.5 px-2 text-right font-mono font-bold text-slate-900">
                                    {{ formatNumber(row.quant, 2) }}
                                </td>

                                <!-- RATE -->
                                <td class="py-2.5 px-2 text-right font-mono text-slate-700">
                                    {{ formatNumber(row.rate, 2) }}
                                </td>

                                <!-- ADVAN -->
                                <td class="py-2.5 px-2 text-right font-mono text-slate-700">
                                    {{ row.advance > 0 ? formatNumber(row.advance, 2) : '—' }}
                                </td>

                                <!-- PUM / PAYMENT MODE -->
                                <td class="py-2.5 px-3 text-center text-[10px] font-semibold text-slate-600">
                                    <span
                                        v-if="row.payment_mode && row.payment_mode !== '—'"
                                        class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200"
                                    >
                                        {{ row.payment_mode }}
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>

                                <!-- AMOUNT -->
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-blue-900 bg-blue-50/40">
                                    {{ formatNumber(row.amount, 2) }}
                                </td>

                                <!-- RECIVE PAY -->
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-900 bg-emerald-50/40">
                                    {{ formatNumber(row.receive_pay, 2) }}
                                </td>

                                <!-- PENDING -->
                                <td class="py-2.5 px-3 text-right font-mono font-black bg-rose-50/40" :class="row.pending > 0 ? 'text-rose-700' : 'text-slate-400'">
                                    {{ formatNumber(row.pending, 2) }}
                                </td>

                                <!-- RELIF -->
                                <td class="py-2.5 px-2 text-right font-mono text-amber-900 bg-amber-50/40 font-bold">
                                    {{ row.relief > 0 ? formatNumber(row.relief, 2) : '—' }}
                                </td>

                                <!-- COMMISSION -->
                                <td class="py-2.5 px-2 text-right font-mono text-purple-900 bg-purple-50/40 font-semibold">
                                    {{ row.commission > 0 ? formatNumber(row.commission, 2) : '—' }}
                                </td>

                                <!-- IN ACC -->
                                <td class="py-2.5 px-2 text-center text-[10px] text-slate-600 font-medium">
                                    {{ row.in_acc || '—' }}
                                </td>

                                <!-- SELLER -->
                                <td class="py-2.5 px-2 text-slate-800 font-semibold text-[11px]">
                                    {{ row.seller || 'Open' }}
                                </td>

                                <!-- NOTE -->
                                <td class="py-2.5 px-3 text-[11px] text-slate-600">
                                    <div class="truncate max-w-[220px]" :title="row.note">
                                        {{ row.note || '—' }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Grand Totals Footer (Matching Excel summary bottom) -->
                        <tfoot v-if="reportStore.totals && reportStore.records.length > 0" class="bg-slate-200/90 font-black text-slate-900 border-t-2 border-slate-400 text-xs">
                            <tr>
                                <td colspan="5" class="py-3 px-3 uppercase tracking-wider font-extrabold text-slate-900 text-right">
                                    GRAND TOTALS ({{ reportStore.totals.count }} ROWS):
                                </td>
                                <!-- Total QUANT -->
                                <td class="py-3 px-2 text-right font-mono font-black text-slate-950">
                                    {{ formatNumber(reportStore.totals.total_qty, 2) }}
                                </td>
                                <!-- Rate (blank) -->
                                <td class="py-3 px-2 text-right">—</td>
                                <!-- Total Advance -->
                                <td class="py-3 px-2 text-right font-mono font-bold text-slate-800">
                                    {{ formatNumber(reportStore.totals.total_advance, 2) }}
                                </td>
                                <!-- Mode (blank) -->
                                <td class="py-3 px-3 text-center">—</td>
                                <!-- Total AMOUNT -->
                                <td class="py-3 px-3 text-right font-mono font-black text-blue-950 bg-blue-100/70">
                                    ₹{{ formatNumber(reportStore.totals.total_amount, 2) }}
                                </td>
                                <!-- Total RECEIVED -->
                                <td class="py-3 px-3 text-right font-mono font-black text-emerald-950 bg-emerald-100/70">
                                    ₹{{ formatNumber(reportStore.totals.total_received, 2) }}
                                </td>
                                <!-- Total PENDING -->
                                <td class="py-3 px-3 text-right font-mono font-black text-rose-950 bg-rose-100/70">
                                    ₹{{ formatNumber(reportStore.totals.total_pending, 2) }}
                                </td>
                                <!-- Total RELIEF -->
                                <td class="py-3 px-2 text-right font-mono font-black text-amber-950 bg-amber-100/70">
                                    ₹{{ formatNumber(reportStore.totals.total_relief, 2) }}
                                </td>
                                <!-- Total COMMISSION -->
                                <td class="py-3 px-2 text-right font-mono font-black text-purple-950 bg-purple-100/70">
                                    ₹{{ formatNumber(reportStore.totals.total_commission, 2) }}
                                </td>
                                <!-- IN ACC -->
                                <td colspan="3" class="py-3 px-3 text-right text-[10px] text-slate-600 font-normal">
                                    Settled: ₹{{ formatNumber(reportStore.totals.total_received + reportStore.totals.total_relief, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="totalItems > 0 && perPage !== -1"
                    class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600 print:hidden"
                >
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (currentPage - 1) * perPage + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(currentPage * perPage, totalItems) }}</span>
                        of <span class="font-bold text-slate-900">{{ totalItems }}</span> dispatches
                    </div>
                    <div class="flex items-center gap-1.5" v-if="totalPages > 1">
                        <button
                            type="button"
                            :disabled="currentPage === 1"
                            @click="goToPage(currentPage - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in totalPages"
                            :key="p"
                            @click="goToPage(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all',
                                currentPage === p
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            {{ p }}
                        </button>
                        <button
                            type="button"
                            :disabled="currentPage === totalPages"
                            @click="goToPage(currentPage + 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
