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
    Percent,
    AlertCircle,
    CheckCircle2,
    Clock,
    UserCheck,
    Filter,
    ArrowDownUp,
    MapPin,
    Truck,
    ChevronLeft,
    ChevronRight,
    IndianRupee,
    Layers,
    BadgePercent,
    Receipt,
    Lock,
    Unlock,
    X
} from 'lucide-vue-next';

const reportStore = useDispatchReportStore();

// Pagination state
const currentPage = ref(1);
const perPage = ref(15);

// Modal state for viewing / printing payment receipt voucher
const isReceiptModalOpen = ref(false);
const viewingReceipt = ref<any>(null);

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

const formatCurrency = (val: number | undefined | null): string => {
    return '₹ ' + formatNumber(val, 2);
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
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / (perPage.value === -1 ? totalItems.value || 1 : perPage.value))));

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

// Summary metrics & collection percentage
const collectionRate = computed(() => {
    const billed = Number(reportStore.totals?.total_amount) || 0;
    if (billed <= 0) return 0;
    const settled = (Number(reportStore.totals?.total_received) || 0) + (Number(reportStore.totals?.total_discount ?? reportStore.totals?.total_relief) || 0);
    return Math.min(100, Math.round((settled / billed) * 100));
});

const printReport = () => {
    window.print();
};

const openReceiptSlip = (row: DispatchReportRow) => {
    const primary = row.receipts && row.receipts.length > 0 ? row.receipts[0] : null;
    const commAmount = Number(primary?.commission_amount ?? row.commission) || 0;
    const commAgent = primary?.commission_agent_name || (row.seller && row.seller !== 'Open' ? row.seller : '');

    const items = primary?.items && primary.items.length > 0
        ? primary.items.map((it: any) => ({
            ...it,
            commission_amount: Number(it.commission_amount ?? (commAmount > 0 ? commAmount : 0)),
            commission_agent_name: it.commission_agent_name || commAgent,
        }))
        : [
            {
                id: row.id,
                entry_no: row.entry_no,
                challan_no: row.challan_no || '',
                invoice_date: row.date,
                invoice_amount: row.amount,
                discount_amount: row.discount ?? row.relief ?? 0,
                paid_amount: row.receive_pay,
                commission_amount: commAmount,
                commission_agent_name: commAgent,
                remarks: row.note || '',
            }
        ];

    viewingReceipt.value = {
        payment_no: primary?.payment_no || `REC-${row.entry_no}`,
        payment_date: primary?.payment_date || row.date,
        party_name: primary?.party_name || row.party_name,
        party: primary?.party || {
            name: row.party_name,
            code: row.party_code || '',
            phone: '',
            city: row.location || '',
            gstin: '',
            address: '',
        },
        payment_mode: primary?.payment_mode || (row.payment_mode && row.payment_mode !== '—' ? row.payment_mode : 'bank_transfer'),
        reference_no: primary?.reference_no || '',
        bank_name: primary?.bank_name || (row.in_acc && row.in_acc !== '—' ? row.in_acc : ''),
        commission_agent_name: commAgent,
        commission_amount: commAmount,
        discount_amount: Number(primary?.discount_amount ?? (row.discount ?? row.relief)) || 0,
        amount: Number(primary?.amount ?? row.receive_pay) || 0,
        remarks: primary?.remarks || row.note || '',
        is_locked: primary?.is_locked || false,
        all_receipts: row.receipts || [],
        items: items,
    };

    isReceiptModalOpen.value = true;
};

const closeReceiptModal = () => {
    isReceiptModalOpen.value = false;
    viewingReceipt.value = null;
};

const printReceiptSlip = () => {
    window.print();
};

const exportCsv = () => {
    const rows = reportStore.records;
    if (!rows.length) return;

    const headers = [
        'RECEIPT #',
        'DATE',
        'ENTRY NO',
        'CHALLAN NO',
        'PARTY NAME',
        'LOCATION',
        'GRADE',
        'OPC / MIX',
        'QUANT (CUM)',
        'RATE (RS)',
        'ADVAN (RS)',
        'PAYMENT MODE',
        'GROSS AMOUNT (RS)',
        'DISCOUNT (RS)',
        'RECEIVED PAYMENT (RS)',
        'COMMISSION (RS)',
        'IN ACC / BANK',
        'SELLER / AGENT',
        'PAYMENT STATUS',
        'NOTE / REMARKS'
    ];

    const csvData = rows.map((r) => [
        `"${r.primary_receipt_no || ''}"`,
        `"${r.date}"`,
        `"${r.entry_no}"`,
        `"${r.challan_no || ''}"`,
        `"${r.party_name.replace(/"/g, '""')}"`,
        `"${(r.location || '').replace(/"/g, '""')}"`,
        `"${(r.grade || '').replace(/"/g, '""')}"`,
        `"${(r.opc || '').replace(/"/g, '""')}"`,
        r.quant,
        r.rate,
        r.advance,
        `"${(r.payment_mode || '').replace(/"/g, '""')}"`,
        r.amount,
        r.discount ?? r.relief,
        r.receive_pay,
        r.commission,
        `"${(r.in_acc || '').replace(/"/g, '""')}"`,
        `"${(r.seller || '').replace(/"/g, '""')}"`,
        `"${r.payment_status || 'paid'}"`,
        `"${(r.note || '').replace(/"/g, '""')}"`
    ]);

    // Add totals row
    if (reportStore.totals) {
        const t = reportStore.totals;
        csvData.push([
            '""',
            '"TOTALS"',
            '""',
            '""',
            '""',
            '""',
            '""',
            '""',
            t.total_qty,
            '""',
            t.total_advance,
            '""',
            t.total_amount,
            t.total_discount ?? t.total_relief,
            t.total_received,
            t.total_commission,
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
    link.setAttribute('download', `Customer_Payments${filenameParty}_${reportStore.fromDateFilter || 'all'}_to_${reportStore.toDateFilter || 'all'}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <AppLayout title="Customer Payments" subtitle="Consolidated customer payment receipts, concrete billing settlements, discounts & agent commission register">
        <div class="space-y-6">
            <!-- Filter & Action Toolbar -->
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4 print:hidden">
                <!-- Top Row: Search, Presets, Actions -->
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    <!-- Search Input -->
                    <div class="relative min-w-[280px] flex-1 max-w-md">
                        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="reportStore.searchQuery"
                            type="text"
                            placeholder="Search customer, location, grade, entry #, vehicle, agent..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-9 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white font-medium transition-all"
                        />
                        <button
                            v-if="reportStore.searchQuery"
                            @click="reportStore.searchQuery = ''; handleSearch();"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                        >
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Date Quick-Presets Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto py-1 custom-scrollbar text-xs">
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('today')"
                            class="px-3 py-1.5 rounded-xl font-bold whitespace-nowrap transition-all cursor-pointer"
                            :class="reportStore.fromDateFilter === reportStore.toDateFilter && reportStore.fromDateFilter ? 'bg-amber-500 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                        >
                            Today
                        </button>
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('this_week')"
                            class="px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all cursor-pointer"
                        >
                            This Week
                        </button>
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('this_month')"
                            class="px-3 py-1.5 rounded-xl font-bold whitespace-nowrap transition-all cursor-pointer"
                            :class="reportStore.fromDateFilter && reportStore.toDateFilter && reportStore.fromDateFilter.endsWith('-01') ? 'bg-amber-500 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'"
                        >
                            This Month
                        </button>
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('last_month')"
                            class="px-3 py-1.5 rounded-xl font-bold whitespace-nowrap bg-slate-100 hover:bg-slate-200 text-slate-700 transition-all cursor-pointer"
                        >
                            Last Month
                        </button>
                        <button
                            type="button"
                            @click="reportStore.setDatePreset('all')"
                            class="px-3 py-1.5 rounded-xl font-bold whitespace-nowrap transition-all cursor-pointer"
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
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition-colors shadow-xs cursor-pointer"
                            :title="reportStore.sortOrder === 'asc' ? 'Sorted: Oldest to Newest (Ledger Order)' : 'Sorted: Newest First'"
                        >
                            <ArrowDownUp class="w-3.5 h-3.5 text-slate-500" />
                            <span class="hidden sm:inline">{{ reportStore.sortOrder === 'asc' ? 'Oldest First' : 'Newest First' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="exportCsv"
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl border border-emerald-200 transition-colors shadow-xs cursor-pointer"
                            title="Export Register to Excel / CSV"
                        >
                            <Download class="w-3.5 h-3.5 text-emerald-600" />
                            <span>Export CSV</span>
                        </button>
                        <button
                            type="button"
                            @click="printReport"
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition-colors shadow-xs cursor-pointer"
                            title="Print Register"
                        >
                            <Printer class="w-3.5 h-3.5 text-slate-500" />
                            <span>Print</span>
                        </button>
                        <button
                            type="button"
                            @click="handleFilterChange"
                            class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors cursor-pointer"
                            title="Refresh Data"
                        >
                            <RefreshCw :class="['w-4 h-4', reportStore.isLoading ? 'animate-spin text-amber-600' : '']" />
                        </button>
                        <button
                            type="button"
                            @click="resetAllFilters"
                            class="flex items-center gap-1 px-3 py-2 bg-slate-50 hover:bg-amber-50 text-slate-600 hover:text-amber-800 text-xs font-bold rounded-xl border border-slate-200 transition-colors cursor-pointer"
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
                            <option value="all">All (Payments Made)</option>
                            <option value="paid">Fully Settled</option>
                            <option value="partially_paid">Partially Paid</option>
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

            <!-- Customer / Statement Banner -->
            <div class="bg-gradient-to-r from-amber-400 via-amber-500 to-orange-400 border border-amber-500 text-slate-950 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 print:border-amber-600 print:bg-amber-300">
                <div class="flex items-start md:items-center gap-3.5">
                    <div class="p-3 bg-black/10 rounded-2xl text-slate-950 flex-shrink-0">
                        <Building2 class="w-7 h-7" />
                    </div>
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-widest text-slate-900/80">
                            {{ reportStore.selectedParty ? 'Customer Payment Statement' : 'Consolidated Customer Payments Register' }}
                        </div>
                        <h2 class="text-xl md:text-2xl font-black tracking-tight text-slate-950 uppercase">
                            {{ reportStore.selectedParty ? reportStore.selectedParty.name : 'ALL PARTIES REGISTER' }}
                        </h2>
                        <div v-if="reportStore.selectedParty" class="text-xs text-slate-900 font-medium flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                            <span v-if="reportStore.selectedParty.code">Code: <strong class="font-bold">{{ reportStore.selectedParty.code }}</strong></span>
                            <span v-if="reportStore.selectedParty.phone">• Phone: <strong class="font-bold">{{ reportStore.selectedParty.phone }}</strong></span>
                            <span v-if="reportStore.selectedParty.city">• City: <strong class="font-bold">{{ reportStore.selectedParty.city }}</strong></span>
                            <span v-if="reportStore.selectedParty.gstin">• GSTIN: <strong class="font-mono font-bold">{{ reportStore.selectedParty.gstin }}</strong></span>
                            <span v-if="reportStore.selectedParty.address">• Addr: <span>{{ reportStore.selectedParty.address }}</span></span>
                        </div>
                    </div>
                </div>

                <div class="text-right flex md:flex-col items-center md:items-end justify-between md:justify-center border-t md:border-t-0 pt-3 md:pt-0 border-black/10">
                    <div class="text-xs font-black uppercase text-slate-950 bg-white/20 px-2.5 py-1 rounded-lg">
                        {{ reportStore.totals?.count || 0 }} Consignments
                    </div>
                    <div class="text-[11px] text-slate-900 font-mono font-bold mt-1">
                        {{ reportStore.fromDateFilter ? formatDateDisplay(reportStore.fromDateFilter) : 'Start' }} → {{ reportStore.toDateFilter ? formatDateDisplay(reportStore.toDateFilter) : 'Today' }}
                    </div>
                </div>
            </div>

            <!-- PAYMENT BLOCK / KPI METRICS & FINANCIAL SUMMARY -->
            <div class="space-y-3">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 print:grid-cols-5">
                    <!-- 1. Total Quantity -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs hover:border-slate-300 transition-all flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Dispatched Qty</span>
                            <div class="p-1.5 rounded-lg bg-slate-100 text-slate-600">
                                <Truck class="w-3.5 h-3.5" />
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="text-xl font-black text-slate-900 font-mono">
                                {{ formatNumber(reportStore.totals?.total_qty) }} <span class="text-xs font-semibold text-slate-500">m³</span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-medium">Across {{ reportStore.totals?.count || 0 }} deliveries</span>
                        </div>
                    </div>

                    <!-- 2. Total Billed Amount -->
                    <div class="bg-blue-50/50 border border-blue-200 rounded-2xl p-4 shadow-xs hover:border-blue-300 transition-all flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Gross Billed</span>
                            <div class="p-1.5 rounded-lg bg-blue-100/80 text-blue-700">
                                <FileSpreadsheet class="w-3.5 h-3.5" />
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="text-xl font-black text-blue-950 font-mono">
                                {{ formatCurrency(reportStore.totals?.total_amount) }}
                            </div>
                            <span class="text-[10px] text-blue-600 font-medium">Invoice Value</span>
                        </div>
                    </div>

                    <!-- 3. Total Received Payment -->
                    <div class="bg-emerald-50/50 border border-emerald-200 rounded-2xl p-4 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Payment Received</span>
                            <div class="p-1.5 rounded-lg bg-emerald-100/80 text-emerald-700">
                                <Wallet class="w-3.5 h-3.5" />
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="text-xl font-black text-emerald-950 font-mono">
                                {{ formatCurrency(reportStore.totals?.total_received) }}
                            </div>
                            <span class="text-[10px] text-emerald-700 font-medium">
                                Adv: {{ formatCurrency(reportStore.totals?.total_advance) }}
                            </span>
                        </div>
                    </div>

                    <!-- 4. Total Discount Allowed -->
                    <div class="bg-amber-50/50 border border-amber-200 rounded-2xl p-4 shadow-xs hover:border-amber-300 transition-all flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider">Discount Allowed</span>
                            <div class="p-1.5 rounded-lg bg-amber-100/80 text-amber-800">
                                <BadgePercent class="w-3.5 h-3.5" />
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="text-xl font-black text-amber-950 font-mono">
                                {{ formatCurrency(reportStore.totals?.total_discount ?? reportStore.totals?.total_relief) }}
                            </div>
                            <span class="text-[10px] text-amber-700 font-medium">Relief / Concession</span>
                        </div>
                    </div>

                    <!-- 5. Total Commission Amount -->
                    <div class="bg-purple-50/50 border border-purple-200 rounded-2xl p-4 shadow-xs hover:border-purple-300 transition-all flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-purple-700 uppercase tracking-wider">Total Commission</span>
                            <div class="p-1.5 rounded-lg bg-purple-100/80 text-purple-700">
                                <UserCheck class="w-3.5 h-3.5" />
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="text-xl font-black text-purple-950 font-mono">
                                {{ formatCurrency(reportStore.totals?.total_commission) }}
                            </div>
                            <span class="text-[10px] text-purple-600 font-medium">Agent Brokerage</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAIN REGISTER TABLE -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- Table Header Controls Bar -->
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <FileSpreadsheet class="w-4 h-4 text-amber-600" />
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Customer Payments Table</span>
                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 font-mono text-[10px] font-bold">
                            {{ totalItems }} Rows
                        </span>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                        <span>Showing {{ paginatedItems.length }} of {{ totalItems }} entries</span>
                        <div class="print:hidden flex items-center gap-1.5">
                            <span class="text-[11px] text-slate-400">Rows:</span>
                            <select
                                v-model="perPage"
                                class="bg-white border border-slate-300 rounded-lg px-2 py-1 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-1 focus:ring-amber-500"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                                <option :value="-1">All (No Pagination)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse font-sans min-w-[1380px]">
                        <thead>
                            <tr class="bg-slate-100 text-slate-800 font-black uppercase text-[10px] tracking-wider border-b border-slate-300">
                                <th class="py-3 px-3 text-center w-28">DATE / ENTRY #</th>
                                <th class="py-3 px-3 min-w-[180px]">CUSTOMER / LOCATION</th>
                                <th class="py-3 px-2 text-center w-20">GRADE</th>
                                <th class="py-3 px-2 text-center w-20">OPC / MIX</th>
                                <th class="py-3 px-2 text-right w-20">QUANT</th>
                                <th class="py-3 px-2 text-right w-20">RATE</th>
                                <th class="py-3 px-3 text-right bg-blue-50/70 text-blue-950 w-28">GROSS (₹)</th>
                                <th class="py-3 px-3 text-right bg-amber-50/70 text-amber-950 w-24">DISCOUNT (₹)</th>
                                <th class="py-3 px-3 text-right bg-emerald-50/70 text-emerald-950 w-28">RECEIVED (₹)</th>
                                <th class="py-3 px-3 text-right bg-purple-50/70 text-purple-950 w-28">COMMISSION (₹)</th>
                                <th class="py-3 px-2 text-center w-28">MODE & ACC</th>
                                <th class="py-3 px-3 w-40">SELLER & NOTE</th>
                                <th class="py-3 px-3 text-center print:hidden w-28">RECEIPT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 font-medium text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="reportStore.isLoading && paginatedItems.length === 0">
                                <td colspan="13" class="py-16 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2 text-xs">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading dispatch & billing records...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="paginatedItems.length === 0">
                                <td colspan="13" class="py-16 text-center text-slate-400">
                                    <FileSpreadsheet class="w-10 h-10 mx-auto mb-2 text-slate-300" />
                                    <div class="text-sm font-bold text-slate-700">No Dispatch Records Found</div>
                                    <p class="text-xs text-slate-400 mt-1">Try selecting another customer, grade, or expanding your date range filters.</p>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="(row, idx) in paginatedItems"
                                :key="`${row.id}-${row.item_id || idx}`"
                                class="hover:bg-slate-50/90 transition-colors group"
                            >
                                <!-- DATE & ENTRY # -->
                                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                    <div class="font-mono text-[11px] font-bold text-slate-900">
                                        {{ formatDateDisplay(row.date) }}
                                    </div>
                                    <div class="text-[10px] font-mono text-slate-500">
                                        {{ row.entry_no }}
                                        <span v-if="row.challan_no" class="text-slate-400">({{ row.challan_no }})</span>
                                    </div>
                                </td>

                                <!-- CUSTOMER / PARTY & LOCATION -->
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-900 truncate max-w-[210px]" :title="row.party_name">
                                        {{ row.party_name }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 flex items-center gap-1 truncate max-w-[210px]" :title="row.location">
                                        <MapPin class="w-3 h-3 text-slate-400 flex-shrink-0" />
                                        <span>{{ row.location || '—' }}</span>
                                    </div>
                                </td>

                                <!-- GRADE -->
                                <td class="py-2.5 px-2 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-black uppercase bg-slate-100 text-slate-800 border border-slate-200">
                                        {{ row.grade || 'Concrete' }}
                                    </span>
                                </td>

                                <!-- OPC / MIX -->
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

                                <!-- GROSS AMOUNT (₹) -->
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-blue-950 bg-blue-50/40">
                                    {{ formatNumber(row.amount, 2) }}
                                </td>

                                <!-- DISCOUNT (₹) -->
                                <td class="py-2.5 px-3 text-right font-mono bg-amber-50/40 font-bold">
                                    <span v-if="(Number(row.discount ?? row.relief) || 0) > 0" class="text-amber-900">
                                        {{ formatNumber(row.discount ?? row.relief, 2) }}
                                    </span>
                                    <span v-else class="text-slate-400 font-normal">—</span>
                                </td>

                                <!-- RECEIVED PAYMENT (₹) -->
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-950 bg-emerald-50/40">
                                    {{ formatNumber(row.receive_pay, 2) }}
                                    <div v-if="row.advance > 0" class="text-[9px] font-semibold text-emerald-700 font-sans">
                                        (Adv: {{ formatNumber(row.advance, 2) }})
                                    </div>
                                </td>

                                <!-- COMMISSION (₹) -->
                                <td class="py-2.5 px-3 text-right font-mono bg-purple-50/40 font-bold">
                                    <span v-if="Number(row.commission) > 0" class="text-purple-900">
                                        {{ formatNumber(row.commission, 2) }}
                                    </span>
                                    <span v-else class="text-slate-400 font-normal">—</span>
                                </td>

                                <!-- MODE & IN ACC -->
                                <td class="py-2.5 px-2 text-center text-[10px]">
                                    <div v-if="row.payment_mode && row.payment_mode !== '—'" class="font-semibold text-slate-800 uppercase">
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ row.payment_mode }}
                                        </span>
                                    </div>
                                    <div v-else class="text-slate-400">—</div>
                                    <div v-if="row.in_acc && row.in_acc !== '—'" class="text-[9px] text-slate-500 font-mono mt-0.5 truncate max-w-[100px] mx-auto" :title="row.in_acc">
                                        {{ row.in_acc }}
                                    </div>
                                </td>

                                <!-- SELLER & NOTE -->
                                <td class="py-2.5 px-3 text-[11px]">
                                    <div class="font-semibold text-slate-800 truncate max-w-[150px]" :title="`Seller / Agent: ${row.seller}`">
                                        {{ row.seller || 'Open' }}
                                    </div>
                                    <div v-if="row.note" class="text-[10px] text-slate-500 truncate max-w-[150px] mt-0.5" :title="row.note">
                                        {{ row.note }}
                                    </div>
                                </td>

                                <!-- RECEIPT PRINT LINK -->
                                <td class="py-2.5 px-3 text-center whitespace-nowrap print:hidden">
                                    <button
                                        type="button"
                                        @click="openReceiptSlip(row)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 hover:border-amber-300 text-[11px] font-bold transition-all shadow-2xs cursor-pointer group-hover:bg-amber-100/90"
                                        :title="`Print Customer Payment Receipt for ${row.entry_no}`"
                                    >
                                        <Printer class="w-3.5 h-3.5 text-amber-600" />
                                        <span class="font-mono">{{ row.primary_receipt_no || 'Receipt' }}</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>

                        <!-- Grand Totals Footer -->
                        <tfoot v-if="reportStore.totals && reportStore.records.length > 0" class="bg-slate-200/95 font-black text-slate-900 border-t-2 border-slate-400 text-xs">
                            <tr>
                                <td colspan="4" class="py-3 px-3 uppercase tracking-wider font-black text-slate-950 text-right">
                                    GRAND TOTALS ({{ reportStore.totals.count }} ROWS):
                                </td>
                                <!-- Total QUANT -->
                                <td class="py-3 px-2 text-right font-mono font-black text-slate-950">
                                    {{ formatNumber(reportStore.totals.total_qty, 2) }}
                                </td>
                                <!-- Rate (blank) -->
                                <td class="py-3 px-2 text-right font-normal text-slate-400">—</td>
                                <!-- Total GROSS AMOUNT -->
                                <td class="py-3 px-3 text-right font-mono font-black text-blue-950 bg-blue-100/80">
                                    {{ formatCurrency(reportStore.totals.total_amount) }}
                                </td>
                                <!-- Total DISCOUNT -->
                                <td class="py-3 px-3 text-right font-mono font-black text-amber-950 bg-amber-100/80">
                                    {{ formatCurrency(reportStore.totals.total_discount ?? reportStore.totals.total_relief) }}
                                </td>
                                <!-- Total RECEIVED -->
                                <td class="py-3 px-3 text-right font-mono font-black text-emerald-950 bg-emerald-100/80">
                                    {{ formatCurrency(reportStore.totals.total_received) }}
                                </td>
                                <!-- Total COMMISSION -->
                                <td class="py-3 px-3 text-right font-mono font-black text-purple-950 bg-purple-100/80">
                                    {{ formatCurrency(reportStore.totals.total_commission) }}
                                </td>
                                <!-- ACC & NOTES -->
                                <td colspan="2" class="py-3 px-3 text-right text-[10px] text-slate-700 font-semibold">
                                    Total Collected: {{ formatCurrency((reportStore.totals.total_received || 0) + (reportStore.totals.total_discount || reportStore.totals.total_relief || 0)) }}
                                </td>
                                <td class="print:hidden"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="totalItems > 0 && perPage !== -1"
                    class="p-4 bg-slate-50/90 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600 print:hidden"
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
                            class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in totalPages"
                            :key="p"
                            @click="goToPage(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all cursor-pointer',
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
                            class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <!-- View / Print Customer Payment Receipt Voucher Slip Modal -->
            <div
                v-if="isReceiptModalOpen && viewingReceipt"
                @click.self="closeReceiptModal"
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
                                @click="printReceiptSlip"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs font-bold transition-colors cursor-pointer"
                            >
                                <Printer class="w-4 h-4" />
                                <span>Print Slip</span>
                            </button>
                            <button @click="closeReceiptModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer">
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
                                        v-if="viewingReceipt.is_locked"
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border inline-flex items-center gap-1 bg-red-50 text-red-700 border-red-200"
                                    >
                                        <Lock class="w-3 h-3" />
                                        <span>Locked</span>
                                    </span>
                                </div>
                                <div class="font-mono text-sm font-bold text-slate-900">{{ viewingReceipt.payment_no }}</div>
                                <div class="text-xs text-slate-500">Date: {{ formatDateDisplay(viewingReceipt.payment_date) }}</div>
                            </div>
                        </div>

                        <!-- Customer & Transaction Info -->
                        <div class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs print:bg-transparent print:border print:border-black">
                            <div>
                                <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Received From (Customer)</span>
                                <div class="font-bold text-slate-900 text-sm">{{ viewingReceipt.party?.name || viewingReceipt.party_name }}</div>
                                <div v-if="viewingReceipt.party?.phone" class="text-slate-600">Phone: {{ viewingReceipt.party.phone }}</div>
                                <div v-if="viewingReceipt.party?.gstin" class="text-slate-600 font-mono">GSTIN: {{ viewingReceipt.party.gstin }}</div>
                                <div v-if="viewingReceipt.party?.city" class="text-slate-600">City: {{ viewingReceipt.party.city }}</div>
                            </div>
                            <div>
                                <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Payment & Bank Details</span>
                                <div><span class="font-semibold text-slate-600">Mode:</span> {{ String(viewingReceipt.payment_mode || '—').replace('_', ' ') }}</div>
                                <div v-if="viewingReceipt.reference_no"><span class="font-semibold text-slate-600">Ref / Cheque #:</span> <span class="font-mono font-bold">{{ viewingReceipt.reference_no }}</span></div>
                                <div v-if="viewingReceipt.bank_name"><span class="font-semibold text-slate-600">Bank:</span> {{ viewingReceipt.bank_name }}</div>
                                <div v-if="viewingReceipt.commission_agent_name || Number(viewingReceipt.commission_amount) > 0">
                                    <span class="font-semibold text-slate-600">Commission:</span>
                                    <span v-if="Number(viewingReceipt.commission_amount) > 0" class="font-mono font-bold text-purple-950 ml-1">
                                        {{ formatCurrency(viewingReceipt.commission_amount) }}
                                    </span>
                                    <span v-if="viewingReceipt.commission_agent_name" class="text-slate-600 ml-1">
                                        ({{ viewingReceipt.commission_agent_name }})
                                    </span>
                                </div>
                                <div v-if="viewingReceipt.remarks"><span class="font-semibold text-slate-600">Remarks:</span> {{ viewingReceipt.remarks }}</div>
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
                                        <tr v-for="(it, idx) in viewingReceipt.items" :key="idx">
                                            <td class="py-2.5 px-3 font-mono text-slate-400">{{ idx + 1 }}</td>
                                            <td class="py-2.5 px-3 font-mono font-bold text-slate-900">
                                                {{ it.entry_no || `OUT-${it.stock_outward_id}` }}
                                            </td>
                                            <td class="py-2.5 px-3 text-slate-600">
                                                {{ it.challan_no || '—' }} ({{ formatDateDisplay(it.invoice_date) }})
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono text-slate-800">
                                                {{ Number(it.invoice_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono text-red-600">
                                                {{ Number(it.discount_amount) > 0 ? Number(it.discount_amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—' }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-800">
                                                {{ Number(it.paid_amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono font-bold text-purple-950">
                                                <span v-if="Number(it.commission_amount) > 0">
                                                    {{ Number(it.commission_amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                                </span>
                                                <span v-else class="text-slate-400 font-normal">—</span>
                                                <div v-if="it.commission_agent_name" class="text-[10px] text-slate-500 font-sans font-normal truncate max-w-[120px] ml-auto">
                                                    {{ it.commission_agent_name }}
                                                </div>
                                            </td>
                                            <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                                                {{ (Number(it.paid_amount || 0) + Number(it.discount_amount || 0)).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
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
                                    <span class="font-mono font-semibold">{{ viewingReceipt.items.length }}</span>
                                </div>
                                <div v-if="Number(viewingReceipt.discount_amount) > 0" class="flex justify-between text-slate-600">
                                    <span>Discount Allowed:</span>
                                    <span class="font-mono font-semibold text-red-600">{{ formatCurrency(viewingReceipt.discount_amount) }}</span>
                                </div>
                                <div v-if="Number(viewingReceipt.commission_amount) > 0" class="flex justify-between text-slate-600">
                                    <span>Total Commission:</span>
                                    <span class="font-mono font-bold text-purple-900">{{ formatCurrency(viewingReceipt.commission_amount) }}</span>
                                </div>
                            <div class="pt-2 border-t-2 border-slate-800 flex justify-between font-extrabold text-slate-900 text-sm print:border-black">
                                <span>Total Amount Received:</span>
                                <span class="font-mono text-emerald-800">{{ formatCurrency(viewingReceipt.amount) }}</span>
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
                        @click="closeReceiptModal"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold print:hidden cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</AppLayout>
</template>
