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
    BookOpen,
    Filter,
    Calendar,
    ArrowUpRight,
    ArrowDownLeft,
    FileText,
    TrendingUp,
    TrendingDown,
    Building2,
    DollarSign,
    Phone,
    MapPin,
    Hash
} from 'lucide-vue-next';

const store = useFinancialReportStore();

onMounted(async () => {
    if (!store.meta) {
        await store.fetchMeta();
    }
    // Auto-select first party if available and none selected
    if (!store.partyLedgerFilters.party_id && store.meta?.parties?.length) {
        store.partyLedgerFilters.party_id = store.meta.parties[0].id;
        await store.fetchPartyLedger();
    }
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

const handlePartyChange = () => {
    store.fetchPartyLedger();
};

const handleDateChange = () => {
    store.fetchPartyLedger();
};

const resetFilters = () => {
    store.resetPartyLedgerFilters();
};

const printReport = () => {
    window.print();
};

const exportToCsv = () => {
    if (!store.partyLedger?.entries || store.partyLedger.entries.length === 0) return;
    
    const partyName = store.partyLedger.party?.name || 'Party';
    const headers = [
        'Date',
        'Voucher Type',
        'Voucher No',
        'Reference No',
        'Particulars',
        'Debit (Dr) (Rs)',
        'Credit (Cr) (Rs)',
        'Balance (Rs)',
        'Balance Type',
        'Remarks'
    ];

    const rows = store.partyLedger.entries.map(e => [
        `"${e.date}"`,
        `"${e.voucher_type}"`,
        `"${e.voucher_no || ''}"`,
        `"${e.reference_no || ''}"`,
        `"${(e.particulars || '').replace(/"/g, '""')}"`,
        e.debit,
        e.credit,
        e.balance,
        `"${e.balance_type}"`,
        `"${(e.remarks || '').replace(/"/g, '""')}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `party_ledger_${partyName.replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().split('T')[0]}.csv`);
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
                    <div class="p-3 bg-violet-50 border border-violet-100 rounded-xl text-violet-600">
                        <BookOpen class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 tracking-tight">Party Ledger Statement</h1>
                        <p class="text-xs text-slate-500 mt-0.5">Comprehensive running account statement with opening and closing balances</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <button
                        @click="exportToCsv"
                        :disabled="!store.partyLedger"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition shadow-sm disabled:opacity-50"
                    >
                        <Download class="w-4 h-4 text-slate-500" />
                        Export CSV
                    </button>
                    <button
                        @click="printReport"
                        :disabled="!store.partyLedger"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition shadow-sm disabled:opacity-50"
                    >
                        <Printer class="w-4 h-4 text-slate-500" />
                        Print Statement
                    </button>
                    <button
                        @click="store.fetchPartyLedger"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700 transition shadow-sm"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': store.partyLedgerLoading }" />
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-bold text-slate-800">
                        <Filter class="w-4 h-4 text-violet-600" />
                        Party & Date Selection
                    </div>
                    <button
                        @click="resetFilters"
                        class="text-xs text-violet-600 hover:text-violet-800 font-semibold inline-flex items-center gap-1"
                    >
                        <RotateCcw class="w-3.5 h-3.5" />
                        Reset Dates
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <!-- Party Select -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Select Customer / Supplier *</label>
                        <select
                            v-model="store.partyLedgerFilters.party_id"
                            @change="handlePartyChange"
                            class="w-full text-xs font-semibold bg-violet-50/50 border border-violet-200 rounded-xl px-3 py-2 focus:ring-2 focus:ring-violet-500 focus:bg-white outline-none"
                        >
                            <option value="" disabled>-- Select Party --</option>
                            <option
                                v-for="p in store.meta?.parties"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.name }} [{{ p.type_label }}] {{ p.code ? `(${p.code})` : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- From Date -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">From Date</label>
                        <input
                            type="date"
                            v-model="store.partyLedgerFilters.from_date"
                            @change="handleDateChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-violet-500 focus:bg-white outline-none"
                        />
                    </div>

                    <!-- To Date -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">To Date</label>
                        <input
                            type="date"
                            v-model="store.partyLedgerFilters.to_date"
                            @change="handleDateChange"
                            class="w-full text-xs font-medium bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-violet-500 focus:bg-white outline-none"
                        />
                    </div>
                </div>
            </div>

            <!-- Party Details & Account Balance Overview Card -->
            <div
                v-if="store.partyLedger?.party"
                class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 space-y-4"
            >
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Party Profile -->
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-slate-900">{{ store.partyLedger.party.name }}</h2>
                            <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-bold bg-violet-100 text-violet-800">
                                {{ store.partyLedger.party.type_label }}
                            </span>
                            <span v-if="store.partyLedger.party.code" class="font-mono text-xs bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">
                                {{ store.partyLedger.party.code }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500 pt-1">
                            <span v-if="store.partyLedger.party.phone" class="flex items-center gap-1">
                                <Phone class="w-3.5 h-3.5 text-slate-400" />
                                {{ store.partyLedger.party.phone }}
                            </span>
                            <span v-if="store.partyLedger.party.gstin" class="flex items-center gap-1 font-mono">
                                <Hash class="w-3.5 h-3.5 text-slate-400" />
                                GSTIN: {{ store.partyLedger.party.gstin }}
                            </span>
                            <span v-if="store.partyLedger.party.city || store.partyLedger.party.address" class="flex items-center gap-1">
                                <MapPin class="w-3.5 h-3.5 text-slate-400" />
                                {{ store.partyLedger.party.city || store.partyLedger.party.address }}
                            </span>
                        </div>
                    </div>

                    <!-- Balance Summary Cards Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <!-- Opening Balance -->
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center">
                            <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Opening Balance</span>
                            <span class="text-sm font-bold text-slate-800 mt-1 block">
                                {{ formatCurrency(store.partyLedger.opening_balance.amount) }}
                                <span class="text-[10px] font-bold text-slate-500 ml-0.5">{{ store.partyLedger.opening_balance.type }}</span>
                            </span>
                        </div>

                        <!-- Total Debits (Dr) -->
                        <div class="p-3 bg-blue-50/60 border border-blue-100 rounded-xl text-center">
                            <span class="text-[10px] font-semibold text-blue-700 uppercase tracking-wider block">Total Debits (Dr)</span>
                            <span class="text-sm font-bold text-blue-800 mt-1 block">
                                {{ formatCurrency(store.partyLedger.totals.total_debit) }}
                            </span>
                        </div>

                        <!-- Total Credits (Cr) -->
                        <div class="p-3 bg-emerald-50/60 border border-emerald-100 rounded-xl text-center">
                            <span class="text-[10px] font-semibold text-emerald-700 uppercase tracking-wider block">Total Credits (Cr)</span>
                            <span class="text-sm font-bold text-emerald-800 mt-1 block">
                                {{ formatCurrency(store.partyLedger.totals.total_credit) }}
                            </span>
                        </div>

                        <!-- Closing Balance -->
                        <div class="p-3 border rounded-xl text-center"
                            :class="store.partyLedger.closing_balance.type === 'Dr' ? 'bg-amber-50/70 border-amber-200 text-amber-900' : 'bg-emerald-50/70 border-emerald-200 text-emerald-900'"
                        >
                            <span class="text-[10px] font-bold uppercase tracking-wider block">Closing Balance</span>
                            <span class="text-sm font-extrabold mt-1 block">
                                {{ formatCurrency(store.partyLedger.closing_balance.amount) }}
                                <span class="text-[10px] font-bold ml-0.5">({{ store.partyLedger.closing_balance.type }})</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Ledger Statement Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase tracking-wider font-semibold">
                                <th class="py-3.5 px-4">Date</th>
                                <th class="py-3.5 px-3">Voucher Type</th>
                                <th class="py-3.5 px-3">Voucher / Bill No</th>
                                <th class="py-3.5 px-3">Challan / Ref No</th>
                                <th class="py-3.5 px-4">Particulars</th>
                                <th class="py-3.5 px-3 text-right text-blue-800">Debit (Dr) (₹)</th>
                                <th class="py-3.5 px-3 text-right text-emerald-800">Credit (Cr) (₹)</th>
                                <th class="py-3.5 px-4 text-right font-bold text-slate-900 bg-slate-100/70">Running Balance (₹)</th>
                                <th class="py-3.5 px-3">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <!-- Opening Balance Row -->
                            <tr v-if="store.partyLedger" class="bg-slate-50/90 font-semibold text-slate-700">
                                <td class="py-2.5 px-4">{{ formatDate(store.partyLedgerFilters.from_date) }}</td>
                                <td class="py-2.5 px-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-700">
                                        Opening Balance
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">-</td>
                                <td class="py-2.5 px-3">-</td>
                                <td class="py-2.5 px-4 font-bold text-slate-800">Opening Balance Brought Forward</td>
                                <td class="py-2.5 px-3 text-right">-</td>
                                <td class="py-2.5 px-3 text-right">-</td>
                                <td class="py-2.5 px-4 text-right font-bold bg-slate-200/50">
                                    {{ formatCurrency(store.partyLedger.opening_balance.amount) }}
                                    <span class="text-[10px] text-slate-600 ml-1">{{ store.partyLedger.opening_balance.type }}</span>
                                </td>
                                <td class="py-2.5 px-3 text-slate-400">-</td>
                            </tr>

                            <tr v-if="store.partyLedgerLoading">
                                <td colspan="9" class="py-12 text-center text-slate-500">
                                    <div class="flex items-center justify-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-violet-600" />
                                        <span>Loading Party Ledger Statement...</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else-if="!store.partyLedgerFilters.party_id">
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    Please select a party from the dropdown above to view the ledger statement.
                                </td>
                            </tr>
                            <tr v-else-if="!store.partyLedger?.entries || store.partyLedger.entries.length === 0">
                                <td colspan="9" class="py-8 text-center text-slate-400">
                                    No transactions recorded for this party within the selected date range.
                                </td>
                            </tr>
                            <tr
                                v-for="(e, index) in store.partyLedger?.entries"
                                :key="index"
                                class="hover:bg-violet-50/20 transition-colors"
                            >
                                <td class="py-3 px-4 font-medium text-slate-800">
                                    {{ formatDate(e.date) }}
                                </td>
                                <td class="py-3 px-3">
                                    <span
                                        v-if="e.voucher_type === 'Dispatch'"
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800"
                                    >
                                        Dispatch
                                    </span>
                                    <span
                                        v-else-if="e.voucher_type === 'Inward'"
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-800"
                                    >
                                        Inward Purchase
                                    </span>
                                    <span
                                        v-else-if="e.voucher_type === 'Customer Receipt'"
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800"
                                    >
                                        Receipt
                                    </span>
                                    <span
                                        v-else-if="e.voucher_type === 'Supplier Payment'"
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800"
                                    >
                                        Payment
                                    </span>
                                    <span
                                        v-else-if="e.voucher_type === 'Discount Allowed'"
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800"
                                    >
                                        Discount
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800"
                                    >
                                        {{ e.voucher_type }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-mono font-medium text-slate-800">
                                    {{ e.voucher_no || '-' }}
                                </td>
                                <td class="py-3 px-3 font-mono text-slate-600">
                                    {{ e.reference_no || '-' }}
                                </td>
                                <td class="py-3 px-4 text-slate-700 font-medium">
                                    {{ e.particulars }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-blue-700">
                                    {{ e.debit > 0 ? formatCurrency(e.debit) : '-' }}
                                </td>
                                <td class="py-3 px-3 text-right font-bold text-emerald-700">
                                    {{ e.credit > 0 ? formatCurrency(e.credit) : '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-bold bg-slate-50/50">
                                    {{ formatCurrency(e.balance) }}
                                    <span
                                        class="text-[10px] font-bold ml-1 px-1 py-0.5 rounded"
                                        :class="e.balance_type === 'Dr' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                    >
                                        {{ e.balance_type }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-slate-500 text-[11px] max-w-xs truncate" :title="e.remarks || ''">
                                    {{ e.remarks || '-' }}
                                </td>
                            </tr>
                        </tbody>
                        <!-- Closing Balance Row / Footer -->
                        <tfoot v-if="store.partyLedger">
                            <tr class="bg-slate-100/90 font-bold border-t-2 border-slate-300 text-slate-900">
                                <td class="py-3 px-4" colspan="5">Period Movement & Closing Balance</td>
                                <td class="py-3 px-3 text-right text-blue-800">{{ formatCurrency(store.partyLedger.totals.total_debit) }}</td>
                                <td class="py-3 px-3 text-right text-emerald-800">{{ formatCurrency(store.partyLedger.totals.total_credit) }}</td>
                                <td class="py-3 px-4 text-right font-extrabold bg-slate-200/80">
                                    {{ formatCurrency(store.partyLedger.closing_balance.amount) }}
                                    <span class="text-[10px] font-bold ml-1">({{ store.partyLedger.closing_balance.type }})</span>
                                </td>
                                <td class="py-3 px-3">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
