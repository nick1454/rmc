<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useStockReportStore } from '../../stores/stockReport';
import type { StockReportItem } from '../../types/report';
import AppLayout from '../../components/layout/AppLayout.vue';
import { useEscapeKey } from '../../composables/useEscapeKey';
import {
    Search,
    RefreshCw,
    Printer,
    Download,
    Eye,
    RotateCcw,
    Boxes,
    Filter,
    CheckCircle2,
    AlertCircle,
    X,
    FileSpreadsheet,
    ArrowDownLeft,
    ArrowUpRight,
    Clock,
    FileText
} from 'lucide-vue-next';

const reportStore = useStockReportStore();

// Pagination state (5 items per page default)
const currentPage = ref(1);
const perPage = ref(5);

// Stock Status Filter state
const statusFilter = ref<'all' | 'in_stock' | 'nil'>('all');

// Ledger Modal state
const isLedgerModalOpen = ref(false);
const activeLedgerItem = ref<StockReportItem | null>(null);

onMounted(async () => {
    await reportStore.fetchMeta();
    await reportStore.fetchSummary();
});

const handleSearch = () => {
    currentPage.value = 1;
    reportStore.fetchSummary();
};

const handleFilterChange = () => {
    currentPage.value = 1;
    reportStore.fetchSummary();
};

const resetAllFilters = () => {
    statusFilter.value = 'all';
    currentPage.value = 1;
    reportStore.resetFilters();
    reportStore.fetchSummary();
};

// Filtered items based on search, type, category, and status
const filteredItems = computed(() => {
    let list = reportStore.filteredSummaryItems;

    if (statusFilter.value === 'in_stock') {
        list = list.filter((it) => it.closing_primary > 0);
    } else if (statusFilter.value === 'nil') {
        list = list.filter((it) => it.closing_primary <= 0);
    }

    return list;
});

// Paginated items (5 per page)
const totalItems = computed(() => filteredItems.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / perPage.value)));

const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredItems.value.slice(start, start + perPage.value);
});

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// Ledger Modal Handlers
const openLedgerModal = async (item: StockReportItem) => {
    activeLedgerItem.value = item;
    reportStore.selectedItemId = item.item_id;
    await reportStore.fetchLedger();
    isLedgerModalOpen.value = true;
};

const closeLedgerModal = () => {
    isLedgerModalOpen.value = false;
    activeLedgerItem.value = null;
};

useEscapeKey(() => {
    if (isLedgerModalOpen.value) {
        closeLedgerModal();
    }
});

const formatNumber = (val: number | undefined | null, decimals = 2): string => {
    if (val === undefined || val === null || isNaN(val)) return '0.00';
    return Number(val).toLocaleString('en-IN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
};

const printReport = () => {
    window.print();
};

const exportSummaryCsv = () => {
    const items = filteredItems.value;
    if (!items.length) return;

    const headers = [
        'Item Code',
        'Item Description',
        'Category',
        'Size / Grade',
        'Type',
        'Primary Unit',
        'Secondary Unit',
        'Opening Stock (Primary)',
        'Opening Stock (Secondary)',
        'Inward / Produced (Primary)',
        'Inward / Produced (Secondary)',
        'Consumed in Batching (Primary)',
        'Consumed in Batching (Secondary)',
        'Outward Dispatched (Primary)',
        'Outward Dispatched (Secondary)',
        'Closing Stock (Primary)',
        'Closing Stock (Secondary)',
        'Status'
    ];

    const rows = items.map((item) => [
        `"${item.code}"`,
        `"${item.item_name}"`,
        `"${item.category}"`,
        `"${item.size || '-'}"`,
        `"${item.type === 'fg' ? 'Finished Good' : 'Raw Material'}"`,
        `"${item.primary_unit}"`,
        `"${item.secondary_unit}"`,
        item.opening_primary,
        item.opening_secondary,
        item.inward_primary,
        item.inward_secondary,
        item.consumed_primary,
        item.consumed_secondary,
        item.outward_primary,
        item.outward_secondary,
        item.closing_primary,
        item.closing_secondary,
        `"${item.closing_primary > 0 ? 'In Stock' : 'Nil Balance'}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Stock_Report_${reportStore.fromDateFilter}_to_${reportStore.toDateFilter}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const exportLedgerCsv = () => {
    const data = reportStore.ledgerData;
    if (!data || !data.entries.length) return;

    const headers = [
        'Date',
        'Action',
        'Reference No',
        'Party / Plant / Mixer',
        'Batch / Lot No',
        'Vehicle No',
        'Destination',
        'Qty In (Primary)',
        'Qty In (Secondary)',
        'Qty Out (Primary)',
        'Qty Out (Secondary)',
        'Balance (Primary)',
        'Balance (Secondary)',
        'Primary Unit',
        'Secondary Unit',
        'Remarks'
    ];

    const rows = data.entries.map((entry) => [
        `"${entry.date}"`,
        `"${entry.action_label}"`,
        `"${entry.reference_no}"`,
        `"${entry.party_name}"`,
        `"${entry.batch_no || ''}"`,
        `"${entry.vehicle_no || ''}"`,
        `"${entry.destination || ''}"`,
        entry.qty_in_primary,
        entry.qty_in_secondary,
        entry.qty_out_primary,
        entry.qty_out_secondary,
        entry.balance_primary ?? 0,
        entry.balance_secondary ?? 0,
        `"${entry.unit_primary}"`,
        `"${entry.unit_secondary}"`,
        `"${entry.remarks || ''}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `Stock_Ledger_${data.item?.name || 'item'}_${reportStore.fromDateFilter}_to_${reportStore.toDateFilter}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <AppLayout title="Stock & Inventory Report" subtitle="Current inventory balances, material receipts, consumption & periodic stock summary">
        <div class="space-y-6">
            <!-- Filter & Action Toolbar -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-4 print:hidden">
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
                    <!-- Search Input -->
                    <div class="relative min-w-[240px] flex-1 max-w-md">
                        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="reportStore.searchQuery"
                            type="text"
                            placeholder="Search item, grade, category, code..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white font-medium"
                        />
                    </div>

                    <!-- Actions Right -->
                    <div class="flex items-center gap-2 self-end lg:self-auto">
                        <button
                            type="button"
                            @click="exportSummaryCsv"
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-colors shadow-xs"
                            title="Export filtered stock report to CSV"
                        >
                            <Download class="w-3.5 h-3.5 text-slate-500" />
                            <span>Export CSV</span>
                        </button>
                        <button
                            type="button"
                            @click="printReport"
                            class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-colors shadow-xs"
                            title="Print this Report"
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
                            class="flex items-center gap-1.5 px-3 py-2 bg-slate-50 hover:bg-amber-50 text-slate-600 hover:text-amber-800 text-xs font-semibold rounded-xl border border-slate-200 transition-colors"
                            title="Reset all search & filters"
                        >
                            <RotateCcw class="w-3.5 h-3.5" />
                            <span class="hidden sm:inline">Reset</span>
                        </button>
                    </div>
                </div>

                <!-- Filters Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 pt-3 border-t border-slate-100">
                    <!-- Category Filter -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Category</label>
                        <select
                            v-model="reportStore.categoryFilter"
                            @change="handleFilterChange"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-800 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        >
                            <option value="all">All Categories</option>
                            <option v-for="cat in reportStore.categories" :key="cat.id" :value="cat.name">
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Type Filter -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Item Type</label>
                        <select
                            v-model="reportStore.typeFilter"
                            @change="handleFilterChange"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-800 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        >
                            <option value="all">All Types (RM & FG)</option>
                            <option value="rm">Raw Materials (RM)</option>
                            <option value="fg">Finished Goods (FG)</option>
                        </select>
                    </div>

                    <!-- Stock Status Filter -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Stock Level</label>
                        <select
                            v-model="statusFilter"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-slate-800 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        >
                            <option value="all">All Stock Levels</option>
                            <option value="in_stock">In Stock (> 0)</option>
                            <option value="nil">Nil / Zero Stock (0)</option>
                        </select>
                    </div>

                    <!-- From Date -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Period From</label>
                        <input
                            type="date"
                            v-model="reportStore.fromDateFilter"
                            @change="handleFilterChange"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white cursor-pointer"
                        />
                    </div>

                    <!-- To Date -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Period To</label>
                        <input
                            type="date"
                            v-model="reportStore.toDateFilter"
                            @change="handleFilterChange"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white cursor-pointer"
                        />
                    </div>
                </div>
            </div>

            <!-- Stock Report List Container -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- List Header Banner -->
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Boxes class="w-4 h-4 text-amber-600" />
                        <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Inventory Stock Balances</span>
                    </div>
                    <div class="text-[11px] text-slate-500 font-mono">
                        Period: <span class="font-bold text-slate-800">{{ reportStore.fromDateFilter }}</span> to <span class="font-bold text-slate-800">{{ reportStore.toDateFilter }}</span>
                        • <span class="font-semibold text-slate-700">{{ totalItems }} items</span>
                    </div>
                </div>

                <!-- Desktop Table View (>= md) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider font-bold border-b border-slate-200 text-[10px]">
                                <th class="py-3 px-4">Item & Description</th>
                                <th class="py-3 px-3 text-center">Type</th>
                                <th class="py-3 px-3 text-right">Opening Stock</th>
                                <th class="py-3 px-3 text-right bg-emerald-50/70 text-emerald-800">Inward / Produced (+)</th>
                                <th class="py-3 px-3 text-right bg-amber-50/70 text-amber-800">Consumed in Mix (-)</th>
                                <th class="py-3 px-3 text-right bg-rose-50/70 text-rose-800">Outward / Dispatched (-)</th>
                                <th class="py-3 px-4 text-right bg-slate-100/80 font-bold text-slate-900">Closing Available</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-4 text-right print:hidden">Audit Ledger</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="reportStore.isLoading && paginatedItems.length === 0">
                                <td colspan="9" class="py-12 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2 text-xs">
                                        <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                                        <span>Loading inventory stock report...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="paginatedItems.length === 0">
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <Boxes class="w-10 h-10 mx-auto mb-2 text-slate-300" />
                                    <div class="text-sm font-bold text-slate-700">No Stock Items Found</div>
                                    <p class="text-xs text-slate-400 mt-1">Try adjusting your filters or date range.</p>
                                </td>
                            </tr>

                            <!-- Rows -->
                            <tr
                                v-for="item in paginatedItems"
                                :key="item.item_id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <!-- Item & Description -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                        <span>{{ item.item_name }}</span>
                                        <span v-if="item.is_stock_exempt" class="text-[9px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-normal">Exempt</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                        {{ item.category }} {{ item.size ? `• ${item.size}` : '' }} • Code: {{ item.code }}
                                    </div>
                                </td>

                                <!-- Type -->
                                <td class="py-3 px-3 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                        :class="item.type === 'fg' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200'"
                                    >
                                        {{ item.type === 'fg' ? 'FG' : 'RM' }}
                                    </span>
                                </td>

                                <!-- Opening Stock -->
                                <td class="py-3 px-3 text-right font-mono">
                                    <div class="text-slate-800 font-semibold">{{ formatNumber(item.opening_primary) }} <span class="text-[10px] text-slate-500 font-normal">{{ item.primary_unit }}</span></div>
                                    <div v-if="item.secondary_unit && item.secondary_unit !== item.primary_unit" class="text-[10px] text-slate-400 font-normal">
                                        {{ formatNumber(item.opening_secondary) }} {{ item.secondary_unit }}
                                    </div>
                                </td>

                                <!-- Inward / Produced (+) -->
                                <td class="py-3 px-3 text-right font-mono bg-emerald-50/40 text-emerald-800">
                                    <div class="font-bold">+{{ formatNumber(item.inward_primary) }} <span class="text-[10px] text-emerald-600 font-normal">{{ item.primary_unit }}</span></div>
                                    <div v-if="item.secondary_unit && item.secondary_unit !== item.primary_unit" class="text-[10px] text-emerald-600 font-normal">
                                        +{{ formatNumber(item.inward_secondary) }} {{ item.secondary_unit }}
                                    </div>
                                </td>

                                <!-- Consumed in Mix (-) -->
                                <td class="py-3 px-3 text-right font-mono bg-amber-50/40 text-amber-800">
                                    <template v-if="item.type === 'rm'">
                                        <div class="font-bold">-{{ formatNumber(item.consumed_primary) }} <span class="text-[10px] text-amber-600 font-normal">{{ item.primary_unit }}</span></div>
                                        <div v-if="item.secondary_unit && item.secondary_unit !== item.primary_unit" class="text-[10px] text-amber-600 font-normal">
                                            -{{ formatNumber(item.consumed_secondary) }} {{ item.secondary_unit }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400 font-normal">—</span>
                                    </template>
                                </td>

                                <!-- Outward / Dispatched (-) -->
                                <td class="py-3 px-3 text-right font-mono bg-rose-50/40 text-rose-800">
                                    <div class="font-bold">-{{ formatNumber(item.outward_primary) }} <span class="text-[10px] text-rose-600 font-normal">{{ item.primary_unit }}</span></div>
                                    <div v-if="item.secondary_unit && item.secondary_unit !== item.primary_unit" class="text-[10px] text-rose-600 font-normal">
                                        -{{ formatNumber(item.outward_secondary) }} {{ item.secondary_unit }}
                                    </div>
                                </td>

                                <!-- Closing Available Stock -->
                                <td class="py-3 px-4 text-right font-mono bg-slate-50 font-bold">
                                    <div class="text-slate-900 text-sm font-black">{{ formatNumber(item.closing_primary) }} <span class="text-[10px] text-slate-500 font-semibold">{{ item.primary_unit }}</span></div>
                                    <div v-if="item.secondary_unit && item.secondary_unit !== item.primary_unit" class="text-[10px] text-slate-500 font-normal">
                                        {{ formatNumber(item.closing_secondary) }} {{ item.secondary_unit }}
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="py-3 px-3 text-center">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="item.closing_primary > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200'"
                                    >
                                        <CheckCircle2 v-if="item.closing_primary > 0" class="w-3 h-3 text-emerald-600" />
                                        <AlertCircle v-else class="w-3 h-3 text-slate-400" />
                                        <span>{{ item.closing_primary > 0 ? 'In Stock' : 'Nil Balance' }}</span>
                                    </span>
                                </td>

                                <!-- Action -->
                                <td class="py-3 px-4 text-right print:hidden">
                                    <button
                                        type="button"
                                        @click="openLedgerModal(item)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-slate-100 hover:bg-amber-500 hover:text-white text-slate-700 rounded-lg text-xs font-semibold border border-slate-200 hover:border-amber-500 transition-colors shadow-xs"
                                        title="View item transaction ledger & movements"
                                    >
                                        <Eye class="w-3.5 h-3.5" />
                                        <span>Ledger</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards View (< md) -->
                <div class="md:hidden divide-y divide-slate-100">
                    <div
                        v-for="item in paginatedItems"
                        :key="item.item_id"
                        class="p-4 space-y-3 hover:bg-slate-50 transition-colors"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                    <span>{{ item.item_name }}</span>
                                    <span
                                        class="px-1.5 py-0.2 rounded text-[9px] font-bold uppercase"
                                        :class="item.type === 'fg' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200'"
                                    >
                                        {{ item.type === 'fg' ? 'FG' : 'RM' }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                    {{ item.category }} {{ item.size ? `• ${item.size}` : '' }} • Code: {{ item.code }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                                :class="item.closing_primary > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200'"
                            >
                                {{ item.closing_primary > 0 ? 'In Stock' : 'Nil' }}
                            </span>
                        </div>

                        <!-- 4 Stat Grid -->
                        <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs font-mono">
                            <div>
                                <span class="text-[10px] text-slate-500 uppercase font-sans block">Opening</span>
                                <span class="font-bold text-slate-800">{{ formatNumber(item.opening_primary) }} {{ item.primary_unit }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-emerald-700 uppercase font-sans block">Inward (+)</span>
                                <span class="font-bold text-emerald-800">+{{ formatNumber(item.inward_primary) }} {{ item.primary_unit }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-amber-700 uppercase font-sans block">Consumed (-)</span>
                                <span class="font-bold text-amber-800">-{{ formatNumber(item.consumed_primary) }} {{ item.primary_unit }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-rose-700 uppercase font-sans block">Outward (-)</span>
                                <span class="font-bold text-rose-800">-{{ formatNumber(item.outward_primary) }} {{ item.primary_unit }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <div>
                                <span class="text-[10px] text-slate-500 uppercase font-bold block">Closing Balance:</span>
                                <span class="font-mono font-black text-slate-900 text-sm">{{ formatNumber(item.closing_primary) }} {{ item.primary_unit }}</span>
                            </div>
                            <button
                                type="button"
                                @click="openLedgerModal(item)"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-amber-500 hover:text-white text-slate-700 rounded-lg text-xs font-semibold border border-slate-200 transition-colors flex items-center gap-1 shadow-xs"
                            >
                                <Eye class="w-3.5 h-3.5" />
                                <span>Ledger</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination Footer (5 Items Per Page) -->
                <div
                    v-if="totalItems > 0"
                    class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600 print:hidden"
                >
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (currentPage - 1) * perPage + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(currentPage * perPage, totalItems) }}</span>
                        of <span class="font-bold text-slate-900">{{ totalItems }}</span> items
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

        <!-- Item Movement Audit Ledger Modal -->
        <div
            v-if="isLedgerModalOpen"
            @click.self="closeLedgerModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700">
                            <FileSpreadsheet class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 flex items-center gap-2">
                                <span>{{ activeLedgerItem?.item_name || 'Item Ledger' }}</span>
                                <span
                                    class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase"
                                    :class="activeLedgerItem?.type === 'fg' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200'"
                                >
                                    {{ activeLedgerItem?.type === 'fg' ? 'Finished Good' : 'Raw Material' }}
                                </span>
                            </h3>
                            <p class="text-[11px] text-slate-500 font-mono">
                                Code: {{ activeLedgerItem?.code }} • Period: {{ reportStore.fromDateFilter }} to {{ reportStore.toDateFilter }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="exportLedgerCsv"
                            class="px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 transition-colors shadow-xs flex items-center gap-1.5"
                            title="Export item ledger to CSV"
                        >
                            <Download class="w-3.5 h-3.5 text-slate-500" />
                            <span>Export</span>
                        </button>
                        <button
                            @click="closeLedgerModal"
                            class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-5 max-h-[80vh] overflow-y-auto custom-scrollbar">
                    <!-- Opening / Totals Summary Grid -->
                    <div v-if="reportStore.ledgerData" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-slate-50 border border-slate-200 p-3 rounded-xl font-mono text-xs">
                            <div class="text-[10px] font-bold text-slate-500 uppercase font-sans">Opening Stock</div>
                            <div class="text-sm font-bold text-slate-900 mt-0.5">
                                {{ formatNumber(reportStore.ledgerData.opening_stock.primary) }} {{ reportStore.ledgerData.opening_stock.unit_primary }}
                            </div>
                        </div>

                        <div class="bg-emerald-50/60 border border-emerald-200 p-3 rounded-xl font-mono text-xs">
                            <div class="text-[10px] font-bold text-emerald-800 uppercase font-sans">Inward Receipts</div>
                            <div class="text-sm font-bold text-emerald-800 mt-0.5">
                                +{{ formatNumber(reportStore.ledgerData.summary.total_in_primary) }} {{ reportStore.ledgerData.opening_stock.unit_primary }}
                            </div>
                        </div>

                        <div class="bg-rose-50/60 border border-rose-200 p-3 rounded-xl font-mono text-xs">
                            <div class="text-[10px] font-bold text-rose-800 uppercase font-sans">Consumed / Outward</div>
                            <div class="text-sm font-bold text-rose-800 mt-0.5">
                                -{{ formatNumber(reportStore.ledgerData.summary.total_out_primary) }} {{ reportStore.ledgerData.opening_stock.unit_primary }}
                            </div>
                        </div>

                        <div class="bg-amber-50/60 border border-amber-200 p-3 rounded-xl font-mono text-xs">
                            <div class="text-[10px] font-bold text-amber-800 uppercase font-sans">Closing Balance</div>
                            <div class="text-sm font-black text-amber-900 mt-0.5">
                                {{ formatNumber(reportStore.ledgerData.closing_stock.primary) }} {{ reportStore.ledgerData.closing_stock.unit_primary }}
                            </div>
                        </div>
                    </div>

                    <!-- Ledger Transactions Table -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                        <div v-if="reportStore.isLedgerLoading" class="py-12 text-center text-slate-500">
                            <RefreshCw class="w-5 h-5 animate-spin text-amber-500 mx-auto mb-2" />
                            <span class="text-xs">Loading item transaction audit trail...</span>
                        </div>

                        <table v-else class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider font-bold border-b border-slate-200 text-[10px]">
                                    <th class="py-2.5 px-3">Date</th>
                                    <th class="py-2.5 px-3">Action</th>
                                    <th class="py-2.5 px-3">Ref Document #</th>
                                    <th class="py-2.5 px-3">Party / Vehicle</th>
                                    <th class="py-2.5 px-3 text-right bg-emerald-50 text-emerald-800">In (+)</th>
                                    <th class="py-2.5 px-3 text-right bg-rose-50 text-rose-800">Out (-)</th>
                                    <th class="py-2.5 px-3 text-right bg-slate-100 font-bold text-slate-900">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono text-slate-700">
                                <!-- Opening Row -->
                                <tr class="bg-slate-50 text-slate-500 italic">
                                    <td class="py-2 px-3 font-sans">{{ reportStore.fromDateFilter }}</td>
                                    <td class="py-2 px-3 font-sans font-semibold">Opening Stock</td>
                                    <td class="py-2 px-3 font-sans text-slate-400">—</td>
                                    <td class="py-2 px-3 font-sans text-slate-400">Balance B/F</td>
                                    <td class="py-2 px-3 text-right text-slate-400">—</td>
                                    <td class="py-2 px-3 text-right text-slate-400">—</td>
                                    <td class="py-2 px-3 text-right font-bold text-slate-900">
                                        {{ formatNumber(reportStore.ledgerData?.opening_stock?.primary) }} <span class="text-[10px] font-normal text-slate-500">{{ reportStore.ledgerData?.opening_stock?.unit_primary }}</span>
                                    </td>
                                </tr>

                                <!-- Event Rows -->
                                <tr
                                    v-for="(entry, idx) in reportStore.ledgerData?.entries || []"
                                    :key="idx"
                                    class="hover:bg-slate-50 transition-colors"
                                >
                                    <td class="py-2.5 px-3 font-sans text-slate-900 font-medium">{{ entry.date }}</td>
                                    <td class="py-2.5 px-3 font-sans">
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                                            :class="entry.action === 'in' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                                        >
                                            <ArrowDownLeft v-if="entry.action === 'in'" class="w-3 h-3" />
                                            <ArrowUpRight v-else class="w-3 h-3" />
                                            <span>{{ entry.action_label }}</span>
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 font-sans font-bold text-slate-900">
                                        {{ entry.reference_no }}
                                    </td>
                                    <td class="py-2.5 px-3 font-sans text-slate-600">
                                        {{ entry.party_name || '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right bg-emerald-50/40 font-bold text-emerald-800">
                                        {{ entry.qty_in_primary > 0 ? `+${formatNumber(entry.qty_in_primary)}` : '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right bg-rose-50/40 font-bold text-rose-800">
                                        {{ entry.qty_out_primary > 0 ? `-${formatNumber(entry.qty_out_primary)}` : '—' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right bg-slate-50 font-bold text-slate-900">
                                        {{ formatNumber(entry.balance_primary) }} <span class="text-[10px] font-normal text-slate-500">{{ entry.unit_primary }}</span>
                                    </td>
                                </tr>

                                <tr v-if="!reportStore.ledgerData?.entries?.length && !reportStore.isLedgerLoading">
                                    <td colspan="7" class="py-8 text-center text-slate-400 font-sans">
                                        <Clock class="w-8 h-8 mx-auto mb-1 text-slate-300" />
                                        <div class="text-xs font-semibold text-slate-600">No stock movements recorded in this period</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button
                        type="button"
                        @click="closeLedgerModal"
                        class="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold shadow-xs transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
