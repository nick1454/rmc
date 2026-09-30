<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useFinancialReportStore } from '../../stores/financialReport';
import AppLayout from '../../components/layout/AppLayout.vue';
import {
    Search,
    RefreshCw,
    Printer,
    Download,
    RotateCcw,
    Landmark,
    Filter,
    Calendar,
    ArrowUpRight,
    ArrowDownLeft,
    FileText,
    TrendingUp,
    TrendingDown,
    Lock,
    Unlock,
    CreditCard,
    DollarSign,
    Wallet
} from 'lucide-vue-next';

const store = useFinancialReportStore();

onMounted(async () => {
    if (!store.meta) {
        await store.fetchMeta();
    }
    await store.fetchCashBankBook();
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
    store.fetchCashBankBook();
};

const handleFilterChange = () => {
    store.fetchCashBankBook();
};

const resetFilters = () => {
    store.resetCashBankBookFilters();
};

const printReport = () => {
    window.print();
};

const exportToCsv = () => {
    if (!store.cashBankBook?.transactions || store.cashBankBook.transactions.length === 0) return;
    
    const headers = [
        'Date',
        'Voucher No',
        'Type',
        'Party Name',
        'Party Type',
        'Payment Mode',
        'Bank Name',
        'Instrument Ref',
        'Inflow Amount (Rs)',
        'Outflow Amount (Rs)',
        'Discount (Rs)',
        'Running Balance (Rs)',
        'Remarks'
    ];

    const rows = store.cashBankBook.transactions.map(t => [
        `"${t.date}"`,
        `"${t.voucher_no || ''}"`,
        `"${t.type_label}"`,
        `"${(t.party_name || '').replace(/"/g, '""')}"`,
        `"${t.party_type || ''}"`,
        `"${t.payment_mode || ''}"`,
        `"${t.bank_name || ''}"`,
        `"${t.reference_number || ''}"`,
        t.inflow,
        t.outflow,
        t.discount,
        t.running_balance,
        `"${(t.remarks || '').replace(/"/g, '""')}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `cash_bank_book_${new Date().toISOString().split('T')[0]}.csv`);
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
                    <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-xl text-emerald-600">
                        <Landmark class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Cash & Bank Book Register</h1>
                        <p class="text-xs text-slate-500 mt-0.5">Chronological record of inflows (customer receipts) and outflows (vendor disbursements)</p>
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
                        @click="store.fetchCashBankBook"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-xl hover:bg-emerald-700 transition shadow-sm"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': store.cashBankBookLoading }" />
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-bold text-slate-800">
                        <Filter class="w-4 h-4 text-emerald-600" />
                        Filters
                    </div>
                    <button
                        @click="resetFilters"
                        class="text-xs text-emerald-600 hover:text-emerald-800 font-semibold inline-flex items-center gap-1"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        Reset Filters
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                    <!-- From Date -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">From Date</label>
                        <input
                            type="date"
                            v-model="store.cashBankBookFilters.from_date"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none"
                        />
                    </div>

                    <!-- To Date -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">To Date</label>
                        <input
                            type="date"
                            v-model="store.cashBankBookFilters.to_date"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none"
                        />
                    </div>

                    <!-- Type Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Transaction Type</label>
                        <select
                            v-model="store.cashBankBookFilters.type"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none"
                        >
                            <option value="all">All Transactions</option>
                            <option value="receipt">Inflow (Customer Receipts)</option>
                            <option value="payment">Outflow (Supplier Payments)</option>
                        </select>
                    </div>

                    <!-- Payment Mode Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment Mode</label>
                        <select
                            v-model="store.cashBankBookFilters.payment_mode"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none"
                        >
                            <option value="all">All Modes</option>
                            <option
                                v-for="mode in store.meta?.payment_modes"
                                :key="mode"
                                :value="mode"
                            >
                                {{ mode }}
                            </option>
                        </select>
                    </div>

                    <!-- Bank Name Filter -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Bank Account</label>
                        <select
                            v-model="store.cashBankBookFilters.bank_name"
                            @change="handleFilterChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none"
                        >
                            <option value="all">All Banks</option>
                            <option
                                v-for="b in store.meta?.banks"
                                :key="b"
                                :value="b"
                            >
                                {{ b }}
                            </option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
                        <div class="relative">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                type="text"
                                v-model="store.cashBankBookFilters.search"
                                @input="handleSearch"
                                placeholder="Party, voucher, ref..."
                                class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:bg-white outline-none"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3" v-if="store.cashBankBook?.kpis">
                <!-- Total Inflow -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Inflow</span>
                        <div class="p-1.5 bg-emerald-50 text-emerald-600 rounded-lg">
                            <ArrowDownLeft class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-emerald-600">
                            {{ formatCurrency(store.cashBankBook.kpis.total_inflow) }}
                        </div>
                        <span class="text-[11px] text-slate-500">Customer Collections</span>
                    </div>
                </div>

                <!-- Total Outflow -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Outflow</span>
                        <div class="p-1.5 bg-rose-50 text-rose-600 rounded-lg">
                            <ArrowUpRight class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-rose-600">
                            {{ formatCurrency(store.cashBankBook.kpis.total_outflow) }}
                        </div>
                        <span class="text-[11px] text-slate-500">Supplier Disbursements</span>
                    </div>
                </div>

                <!-- Net Cash Flow -->
                <div class="p-4 rounded-2xl border shadow-sm flex flex-col justify-between"
                    :class="(store.cashBankBook.kpis.net_cashflow >= 0) ? 'bg-emerald-50/70 border-emerald-200' : 'bg-rose-50/70 border-rose-200'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider" :class="(store.cashBankBook.kpis.net_cashflow >= 0) ? 'text-emerald-800' : 'text-rose-800'">
                            Net Cash Flow
                        </span>
                        <div class="p-1.5 rounded-lg" :class="(store.cashBankBook.kpis.net_cashflow >= 0) ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'">
                            <TrendingUp v-if="store.cashBankBook.kpis.net_cashflow >= 0" class="w-4 h-4" />
                            <TrendingDown v-else class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold" :class="(store.cashBankBook.kpis.net_cashflow >= 0) ? 'text-emerald-800' : 'text-rose-800'">
                            {{ formatCurrency(store.cashBankBook.kpis.net_cashflow) }}
                        </div>
                        <span class="text-[11px] font-medium" :class="(store.cashBankBook.kpis.net_cashflow >= 0) ? 'text-emerald-700' : 'text-rose-700'">
                            {{ store.cashBankBook.kpis.net_cashflow >= 0 ? 'Net Surplus' : 'Net Deficit' }}
                        </span>
                    </div>
                </div>

                <!-- Total Transactions -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Transactions</span>
                        <div class="p-1.5 bg-indigo-50 text-indigo-600 rounded-lg">
                            <FileText class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-base sm:text-lg font-bold text-slate-900">
                            {{ store.cashBankBook.kpis.transactions_count }}
                        </div>
                        <span class="text-[11px] text-slate-500">Entries in Period</span>
                    </div>
                </div>
            </div>

            <!-- Mode & Bank Summary Mini Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" v-if="store.cashBankBook">
                <!-- By Mode -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <Wallet class="w-4 h-4 text-indigo-600" />
                        Summary by Payment Mode
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <div
                            v-for="m in store.cashBankBook.summary_by_mode"
                            :key="m.mode"
                            class="p-2.5 bg-slate-50 rounded-xl border border-slate-200"
                        >
                            <div class="text-xs font-bold text-slate-800">{{ m.mode }}</div>
                            <div class="text-[11px] text-emerald-600 font-medium mt-1">In: {{ formatCurrency(m.inflow) }}</div>
                            <div class="text-[11px] text-rose-600 font-medium">Out: {{ formatCurrency(m.outflow) }}</div>
                            <div class="text-[11px] font-bold mt-1" :class="m.net >= 0 ? 'text-slate-900' : 'text-rose-700'">
                                Net: {{ formatCurrency(m.net) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- By Bank -->
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm" v-if="store.cashBankBook.summary_by_bank?.length">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <CreditCard class="w-4 h-4 text-teal-600" />
                        Summary by Bank Account
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <div
                            v-for="b in store.cashBankBook.summary_by_bank"
                            :key="b.bank"
                            class="p-2.5 bg-slate-50 rounded-xl border border-slate-200"
                        >
                            <div class="text-xs font-bold text-slate-800 truncate" :title="b.bank">{{ b.bank }}</div>
                            <div class="text-[11px] text-emerald-600 font-medium mt-1">In: {{ formatCurrency(b.inflow) }}</div>
                            <div class="text-[11px] text-rose-600 font-medium">Out: {{ formatCurrency(b.outflow) }}</div>
                            <div class="text-[11px] font-bold mt-1" :class="b.net >= 0 ? 'text-slate-900' : 'text-rose-700'">
                                Net: {{ formatCurrency(b.net) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chronological Transactions Register Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider font-semibold">
                                <th class="py-3.5 px-4">Date / Voucher</th>
                                <th class="py-3.5 px-3">Type</th>
                                <th class="py-3.5 px-3">Party Name</th>
                                <th class="py-3.5 px-3">Mode / Bank / Ref</th>
                                <th class="py-3.5 px-3 text-right text-emerald-700">Inflow (+)</th>
                                <th class="py-3.5 px-3 text-right text-rose-700">Outflow (-)</th>
                                <th class="py-3.5 px-3 text-right text-slate-500">Discount</th>
                                <th class="py-3.5 px-3 text-right font-bold text-slate-900 bg-slate-100/70">Running Net (₹)</th>
                                <th class="py-3.5 px-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="store.cashBankBookLoading">
                                <td colspan="9" class="py-12 text-center text-slate-500">
                                    <div class="flex items-center justify-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-emerald-600" />
                                        <span>Loading Cash & Bank transactions...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="!store.cashBankBook?.transactions || store.cashBankBook.transactions.length === 0">
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    No cash / bank transactions found for the selected period.
                                </td>
                            </tr>
                            <tr
                                v-for="t in store.cashBankBook?.transactions"
                                :key="t.voucher_no"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ formatDate(t.date) }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ t.voucher_no }}</div>
                                </td>
                                <td class="py-3 px-3">
                                    <span
                                        v-if="t.type === 'receipt'"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                    >
                                        <ArrowDownLeft class="w-3 h-3" />
                                        Receipt
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800"
                                    >
                                        <ArrowUpRight class="w-3 h-3" />
                                        Payment
                                    </span>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-semibold text-slate-900">{{ t.party_name }}</div>
                                    <div class="text-[10px] text-slate-500 capitalize">{{ t.party_type }}</div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-medium text-slate-800">{{ t.payment_mode }}</div>
                                    <div class="text-[11px] text-slate-500 truncate max-w-xs" v-if="t.bank_name || t.reference_number">
                                        {{ t.bank_name }} <span v-if="t.reference_number">#{{ t.reference_number }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-emerald-600">
                                    {{ t.inflow > 0 ? formatCurrency(t.inflow) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-rose-600">
                                    {{ t.outflow > 0 ? formatCurrency(t.outflow) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-medium text-slate-500">
                                    {{ t.discount > 0 ? formatCurrency(t.discount) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold bg-slate-50/70" :class="t.running_balance >= 0 ? 'text-slate-900' : 'text-rose-600'">
                                    {{ formatCurrency(t.running_balance) }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span
                                        v-if="t.is_locked"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                                        title="Transaction Locked"
                                    >
                                        <Lock class="w-3 h-3" />
                                        Locked
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600"
                                    >
                                        <Unlock class="w-3 h-3" />
                                        Open
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="store.cashBankBook?.kpis && (store.cashBankBook.transactions?.length ?? 0) > 0">
                            <tr class="bg-slate-100/80 font-bold border-t border-slate-300 text-slate-800">
                                <td class="py-3 px-4" colspan="4">Total ({{ store.cashBankBook.kpis.transactions_count }} Transactions)</td>
                                <td class="py-3 px-3 text-right text-emerald-700">{{ formatCurrency(store.cashBankBook.kpis.total_inflow) }}</td>
                                <td class="py-3 px-3 text-right text-rose-700">{{ formatCurrency(store.cashBankBook.kpis.total_outflow) }}</td>
                                <td class="py-3 px-3 text-right text-slate-500">-</td>
                                <td class="py-3 px-3 text-right bg-slate-200/60" :class="store.cashBankBook.kpis.net_cashflow >= 0 ? 'text-slate-900' : 'text-rose-700'">
                                    {{ formatCurrency(store.cashBankBook.kpis.net_cashflow) }}
                                </td>
                                <td class="py-3 px-3 text-center">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
