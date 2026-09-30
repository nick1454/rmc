<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useProductionStore } from '../../stores/production';
import type {
    Production,
    ProductionForm,
    ProductionRecipeItemInput,
    RmStockItem,
} from '../../types/production';
import type { Item } from '../../types/item';
import AppLayout from '../../components/layout/AppLayout.vue';
import { useEscapeKey } from '../../composables/useEscapeKey';
import {
    Plus,
    Search,
    Trash2,
    Eye,
    Factory,
    AlertCircle,
    CheckCircle2,
    X,
    RefreshCw,
    Calendar,
    FileText,
    Layers,
    User,
    ChevronRight,
    Scale,
    AlertTriangle,
    Package,
    ShieldAlert,
    Gauge,
    Info,
    RotateCcw
} from 'lucide-vue-next';

const productionStore = useProductionStore();

// Modal States
const isFormModalOpen = ref(false);
const isViewModalOpen = ref(false);
const isDeleteModalOpen = ref(false);

const viewingProduction = ref<Production | null>(null);
const deletingProduction = ref<Production | null>(null);

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

const todayStr = getTodayString();

const generateBatchNo = (dateStr?: string): string => {
    const d = dateStr || todayStr;
    const formatted = d.replace(/-/g, '');
    return `BAT-${formatted}-001`;
};

const initialForm = (): ProductionForm => ({
    production_no: productionStore.nextProductionNo || 'PRD-0001',
    production_date: todayStr,
    fg_item_id: 0,
    cbm: 6.0,
    plant_name: 'Plant-01 Central',
    batch_no: productionStore.nextBatchNo || generateBatchNo(todayStr),
    remarks: '',
    recipe_items: [],
});

const form = ref<ProductionForm>(initialForm());

onMounted(async () => {
    if (!productionStore.fromDateFilter) {
        productionStore.fromDateFilter = getMonthFirstDateString();
    }
    if (!productionStore.toDateFilter) {
        productionStore.toDateFilter = getMonthLastDateString();
    }
    await productionStore.fetchMeta();
    await productionStore.fetchProductions(1);
});

const resetFilters = () => {
    productionStore.searchQuery = '';
    productionStore.fgFilter = 'all';
    productionStore.fromDateFilter = getMonthFirstDateString();
    productionStore.toDateFilter = getMonthLastDateString();
    productionStore.fetchProductions(1);
};

const handleSearch = () => {
    productionStore.fetchProductions(1);
};

const handleFilterChange = () => {
    productionStore.fetchProductions(1);
};

// Map RM stock item helper
const getRmStock = (rmItemId: number): RmStockItem | undefined => {
    return productionStore.rmStock.find((s) => s.item_id === rmItemId);
};

// Check if item category is exempt from inventory stock checks (e.g. Water)
const isExemptItem = (category?: string): boolean => {
    return (category || '').trim().toLowerCase() === 'water';
};

// When Finished Good is selected, load its master recipe
const onFgChange = () => {
    const selectedFg = productionStore.fgItems.find((fg) => fg.id === form.value.fg_item_id);
    if (!selectedFg || !selectedFg.recipe_items || selectedFg.recipe_items.length === 0) {
        form.value.recipe_items = [];
        return;
    }

    const cbmVal = Number(form.value.cbm) || 0;

    form.value.recipe_items = selectedFg.recipe_items.map((rec) => {
        const dosage = Number(rec.quantity);
        const total = Number((dosage * cbmVal).toFixed(4));
        const stock = getRmStock(rec.rm_item_id);
        const cat = stock?.category || rec.rm_item?.category || '';
        const isExempt = isExemptItem(cat);
        const available = stock ? stock.available_secondary : 0;
        const isShort = !isExempt && total > available;

        return {
            rm_item_id: rec.rm_item_id,
            recipe_qty_per_cbm: dosage,
            actual_qty_per_cbm: dosage, // Editable, defaults to master recipe
            unit: rec.unit || 'kgs',
            total_qty: total,
            rm_item: rec.rm_item || (stock ? {
                id: stock.item_id,
                category: stock.category,
                size: stock.size,
                primary_unit: stock.primary_unit,
                secondary_unit: stock.secondary_unit,
                conversion_factor: stock.conversion_factor,
                type: 'rm',
            } as any : undefined),
            available_stock: available,
            is_exempt: isExempt,
            is_short: isShort,
            shortage_qty: isShort ? Number((total - available).toFixed(4)) : 0,
        };
    });
};

// When CBM changes, recalculate all total quantities and check live stock
const onCbmChange = () => {
    const cbmVal = Number(form.value.cbm) || 0;
    form.value.recipe_items.forEach((row) => {
        const dosage = Number(row.actual_qty_per_cbm) || 0;
        row.total_qty = Number((dosage * cbmVal).toFixed(4));

        const stock = getRmStock(row.rm_item_id);
        const cat = stock?.category || row.rm_item?.category || '';
        const isExempt = isExemptItem(cat);
        const available = stock ? stock.available_secondary : 0;

        row.available_stock = available;
        row.is_exempt = isExempt;
        row.is_short = !isExempt && row.total_qty > available;
        row.shortage_qty = row.is_short ? Number((row.total_qty - available).toFixed(4)) : 0;
    });
};

// When Actual Qty per CBM is edited in the table
const onActualQtyChange = (index: number) => {
    const row = form.value.recipe_items[index];
    const cbmVal = Number(form.value.cbm) || 0;
    const dosage = Number(row.actual_qty_per_cbm) || 0;
    row.total_qty = Number((dosage * cbmVal).toFixed(4));

    const stock = getRmStock(row.rm_item_id);
    const cat = stock?.category || row.rm_item?.category || '';
    const isExempt = isExemptItem(cat);
    const available = stock ? stock.available_secondary : 0;

    row.available_stock = available;
    row.is_exempt = isExempt;
    row.is_short = !isExempt && row.total_qty > available;
    row.shortage_qty = row.is_short ? Number((row.total_qty - available).toFixed(4)) : 0;
};

// Reset dosage back to master recipe
const resetToMasterDosage = (index: number) => {
    const row = form.value.recipe_items[index];
    row.actual_qty_per_cbm = row.recipe_qty_per_cbm;
    onActualQtyChange(index);
};

// Live computed check if any item is short in the form
const formShortages = computed(() => {
    return form.value.recipe_items.filter((item) => item.is_short);
});

const isFormValidForSave = computed(() => {
    if (!form.value.fg_item_id || !form.value.cbm || form.value.cbm <= 0) return false;
    if (form.value.recipe_items.length === 0) return false;
    if (formShortages.value.length > 0) return false;
    return true;
});

const onProductionDateChange = async () => {
    const dateVal = form.value.production_date;
    if (dateVal) {
        await productionStore.fetchMeta(dateVal);
        if (productionStore.nextBatchNo) {
            form.value.batch_no = productionStore.nextBatchNo;
        } else {
            form.value.batch_no = generateBatchNo(dateVal);
        }
    }
};

const regenerateBatchNo = async () => {
    const dateVal = form.value.production_date || getTodayString();
    await productionStore.fetchMeta(dateVal);
    if (productionStore.nextBatchNo) {
        form.value.batch_no = productionStore.nextBatchNo;
    } else {
        form.value.batch_no = generateBatchNo(dateVal);
    }
};

const regenerateProductionNo = async () => {
    await productionStore.fetchMeta(form.value.production_date || getTodayString());
    if (productionStore.nextProductionNo) {
        form.value.production_no = productionStore.nextProductionNo;
    }
};

const openCreateModal = async () => {
    productionStore.clearMessages();
    const curDate = getTodayString();
    await productionStore.fetchMeta(curDate);
    const firstFg = productionStore.fgItems[0];
    form.value = {
        ...initialForm(),
        production_no: productionStore.nextProductionNo || 'PRD-0001',
        production_date: curDate,
        batch_no: productionStore.nextBatchNo || generateBatchNo(curDate),
        fg_item_id: firstFg ? firstFg.id : 0,
        cbm: 6.0,
    };
    if (firstFg) {
        onFgChange();
    }
    isFormModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
};

const openViewModal = (prod: Production) => {
    viewingProduction.value = prod;
    isViewModalOpen.value = true;
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    viewingProduction.value = null;
};

const confirmDelete = (prod: Production) => {
    productionStore.clearMessages();
    deletingProduction.value = prod;
    isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
    if (deletingProduction.value) {
        await productionStore.deleteProduction(deletingProduction.value.id);
        isDeleteModalOpen.value = false;
        deletingProduction.value = null;
    }
};

const submitForm = async () => {
    await productionStore.createProduction(form.value);
    closeFormModal();
};

const closeAllModals = () => {
    if (isDeleteModalOpen.value) {
        isDeleteModalOpen.value = false;
        deletingProduction.value = null;
    } else if (isViewModalOpen.value) {
        closeViewModal();
    } else if (isFormModalOpen.value) {
        closeFormModal();
    }
};

useEscapeKey(closeAllModals);

const getFgName = (fgId: number) => {
    const it = productionStore.fgItems.find((f) => f.id === fgId);
    if (!it) return `FG #${fgId}`;
    return `${it.category} ${it.size ? `(${it.size})` : ''}`;
};
</script>

<template>
    <AppLayout title="Production Entry" subtitle="Batch Formulation, Volume Dispatch & Live Stock Deduction">
        <div class="space-y-6">
            <!-- Feedback Alerts -->
            <div
                v-if="productionStore.successMessage"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="w-5 h-5 flex-shrink-0 text-emerald-600" />
                    <span class="text-xs font-semibold">{{ productionStore.successMessage }}</span>
                </div>
                <button @click="productionStore.clearMessages" class="text-emerald-600 hover:text-emerald-800">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Global Error / Stock Shortage Alert -->
            <div
                v-if="productionStore.error || productionStore.validationErrors.length > 0"
                class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 space-y-2 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 font-bold text-xs">
                        <ShieldAlert class="w-5 h-5 flex-shrink-0 text-red-600" />
                        <span>{{ productionStore.error || 'Production Validation Failed' }}</span>
                    </div>
                    <button @click="productionStore.clearMessages" class="text-red-600 hover:text-red-800">
                        <X class="w-4 h-4" />
                    </button>
                </div>
                <ul v-if="productionStore.validationErrors.length > 0" class="pl-8 list-disc text-[11px] space-y-1 text-red-700 font-mono">
                    <li v-for="(msg, idx) in productionStore.validationErrors" :key="idx">
                        {{ msg }}
                    </li>
                </ul>
            </div>

            <!-- Live Raw Material Stock Inventory Overview Banner -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-700">
                        <Package class="w-4 h-4 text-amber-600" />
                        <span>Live Raw Material Stock Balances</span>
                    </div>
                    <span class="text-[11px] text-slate-500 font-mono">Total Inward − Consumed</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div
                        v-for="stock in productionStore.rmStock"
                        :key="stock.item_id"
                        class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between hover:border-amber-300 transition-colors"
                    >
                        <div class="flex items-center justify-between gap-1 mb-1">
                            <span class="text-xs font-bold text-slate-900 truncate" :title="stock.item_name">
                                {{ stock.category }} {{ stock.size ? `(${stock.size})` : '' }}
                            </span>
                        </div>
                        <div>
                            <div class="text-base font-bold font-mono text-cyan-800">
                                {{ stock.available_secondary.toLocaleString() }} <span class="text-[10px] text-slate-600">{{ stock.secondary_unit }}</span>
                            </div>
                            <div class="text-[10px] font-mono text-slate-500">
                                ≈ {{ stock.available_primary }} {{ stock.primary_unit }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Action Bar -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
                <!-- Search & Filters -->
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <!-- Search Input -->
                    <div class="relative min-w-[220px] flex-1 max-w-sm">
                        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="productionStore.searchQuery"
                            type="text"
                            placeholder="Search PRD no, batch, plant..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        />
                    </div>

                    <!-- FG Grade Filter -->
                    <select
                        v-model="productionStore.fgFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Finished Goods (RMC Grades)</option>
                        <option v-for="fg in productionStore.fgItems" :key="fg.id" :value="fg.id">
                            {{ fg.category }} {{ fg.size ? `(${fg.size})` : '' }}
                        </option>
                    </select>

                    <!-- From Date Filter -->
                    <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-300">
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">From:</span>
                        <input
                            v-model="productionStore.fromDateFilter"
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
                            v-model="productionStore.toDateFilter"
                            type="date"
                            @change="handleFilterChange"
                            class="bg-transparent text-slate-800 text-xs font-mono focus:outline-none cursor-pointer"
                            title="To Date"
                        />
                    </div>

                    <button
                        type="button"
                        @click="productionStore.fetchProductions(productionStore.meta.current_page)"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors"
                        title="Refresh List"
                    >
                        <RefreshCw :class="['w-4 h-4', productionStore.isLoading ? 'animate-spin' : '']" />
                    </button>

                    <button
                        type="button"
                        @click="resetFilters"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-amber-600 border border-slate-200 transition-colors"
                        title="Reset / Clear Filters"
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
                    <span>New Production Batch</span>
                </button>
            </div>

            <!-- Production Batches (Responsive Table for Desktop + Cards for Mobile) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- Mobile Card List View (< md) -->
                <div class="block md:hidden divide-y divide-slate-100">
                    <!-- Loading State -->
                    <div v-if="productionStore.isLoading && productionStore.productions.length === 0" class="py-12 text-center text-slate-500">
                        <div class="inline-flex items-center gap-2 text-xs">
                            <RefreshCw class="w-4 h-4 animate-spin text-amber-500" />
                            <span>Loading production records...</span>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="productionStore.productions.length === 0" class="py-12 px-4 text-center text-slate-500 space-y-2">
                        <Factory class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="font-bold text-slate-800 text-sm">No Production Batches Found</div>
                        <p class="text-xs text-slate-500">Record a new production batch to deduct raw material stock.</p>
                    </div>

                    <!-- Mobile Cards -->
                    <div
                        v-for="prod in productionStore.productions"
                        :key="prod.id"
                        class="p-4 space-y-3 hover:bg-slate-50 transition-colors"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-slate-900 text-xs">
                                    {{ prod.production_no }}
                                </span>
                                <span class="px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-800 font-bold text-[10px]">
                                    {{ prod.fg_item?.category || 'RMC' }} {{ prod.fg_item?.size ? `(${prod.fg_item.size})` : '' }}
                                </span>
                            </div>
                            <span class="text-[10px] font-mono text-slate-500">
                                {{ prod.production_date }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-[10px] uppercase font-bold text-slate-400">Batch Volume</div>
                                <div class="text-lg font-mono font-bold text-emerald-700">
                                    {{ Number(prod.cbm).toFixed(2) }} <span class="text-xs text-slate-600 font-normal">CBM</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] uppercase font-bold text-slate-400">Plant / TM</div>
                                <div class="text-xs font-semibold text-slate-800">
                                    {{ prod.plant_name }} {{ prod.batch_no ? `• ${prod.batch_no}` : '' }}
                                </div>
                            </div>
                        </div>

                        <!-- Materials breakdown chips -->
                        <div class="flex flex-wrap gap-1 pt-1">
                            <div
                                v-for="rec in (prod.recipe_items || [])"
                                :key="rec.id"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] font-mono"
                            >
                                <span class="text-slate-500">{{ rec.rm_item?.category || 'RM' }}:</span>
                                <span class="text-amber-800 font-bold">{{ Number(rec.total_qty).toLocaleString() }} {{ rec.unit }}</span>
                            </div>
                        </div>

                        <!-- Mobile Card Action Buttons -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                            <span class="text-[10px] text-slate-400">By {{ prod.creator?.name || 'Admin' }}</span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="openViewModal(prod)"
                                    class="px-3 py-1.5 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 border border-cyan-200 text-xs font-bold flex items-center gap-1"
                                >
                                    <Eye class="w-3.5 h-3.5" />
                                    <span>Slip</span>
                                </button>
                                <button
                                    type="button"
                                    @click="confirmDelete(prod)"
                                    class="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs"
                                    title="Delete Batch"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table View (>= md) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider border-b border-slate-200 font-bold text-[11px]">
                                <th class="py-3.5 px-4">Batch No</th>
                                <th class="py-3.5 px-4">Date</th>
                                <th class="py-3.5 px-4">RMC Grade (FG)</th>
                                <th class="py-3.5 px-4">Produced Qty (CBM)</th>
                                <th class="py-3.5 px-4">Plant</th>
                                <th class="py-3.5 px-4">Formulation / Materials Consumed</th>
                                <th class="py-3.5 px-4">Batch Ref</th>
                                <th class="py-3.5 px-4">Created By</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="productionStore.isLoading && productionStore.productions.length === 0">
                                <td colspan="9" class="py-12 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading production records...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="productionStore.productions.length === 0">
                                <td colspan="9" class="py-12 text-center text-slate-500">
                                    <div class="space-y-2">
                                        <Factory class="w-8 h-8 text-slate-400 mx-auto" />
                                        <div class="font-semibold text-slate-800">No Production Batches Found</div>
                                        <p class="text-[11px] text-slate-500">Record a new production entry to deduct raw material stock automatically.</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="prod in productionStore.productions"
                                :key="prod.id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900">
                                    {{ prod.production_no }}
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-600">
                                    {{ prod.production_date }}
                                </td>

                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 font-bold">
                                        {{ prod.fg_item?.category || 'RMC' }} {{ prod.fg_item?.size ? `(${prod.fg_item.size})` : '' }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 font-mono font-bold text-base text-emerald-700">
                                    {{ Number(prod.cbm).toFixed(2) }} <span class="text-xs text-slate-600">CBM</span>
                                </td>

                                <td class="py-3.5 px-4 text-slate-700">
                                    {{ prod.plant_name }}
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="flex flex-wrap gap-1.5 max-w-md">
                                        <div
                                            v-for="rec in (prod.recipe_items || [])"
                                            :key="rec.id"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-50 border border-slate-200 text-[10px] font-mono"
                                        >
                                            <span class="text-slate-500">{{ rec.rm_item?.category || 'RM' }}{{ rec.rm_item?.size ? ` ${rec.rm_item.size}` : '' }}:</span>
                                            <span class="text-amber-800 font-bold">{{ Number(rec.total_qty).toLocaleString() }} {{ rec.unit }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-500">
                                    {{ prod.batch_no || '—' }}
                                </td>

                                <td class="py-3.5 px-4 text-slate-600">
                                    {{ prod.creator?.name || 'Admin' }}
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openViewModal(prod)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-cyan-700 transition-colors border border-slate-200"
                                            title="View Batch Formulation & Deductions"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(prod)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-red-600 transition-colors border border-slate-200"
                                            title="Delete Batch & Restore Stock"
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
                    v-if="productionStore.meta.total > 0"
                    class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
                >
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (productionStore.meta.current_page - 1) * productionStore.meta.per_page + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(productionStore.meta.current_page * productionStore.meta.per_page, productionStore.meta.total) }}</span>
                        of <span class="font-bold text-slate-900">{{ productionStore.meta.total }}</span> entries
                    </div>
                    <div class="flex items-center gap-1.5" v-if="productionStore.meta.last_page > 1">
                        <button
                            type="button"
                            :disabled="productionStore.meta.current_page === 1"
                            @click="productionStore.fetchProductions(productionStore.meta.current_page - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in productionStore.meta.last_page"
                            :key="p"
                            @click="productionStore.fetchProductions(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all',
                                productionStore.meta.current_page === p
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            {{ p }}
                        </button>
                        <button
                            type="button"
                            :disabled="productionStore.meta.current_page === productionStore.meta.last_page"
                            @click="productionStore.fetchProductions(productionStore.meta.current_page + 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Entry Modal (Formulation, CBM & Stock Validation) -->
        <div
            v-if="isFormModalOpen"
            @click.self="closeFormModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700">
                            <Factory class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">
                                New Production Batch Entry
                            </h3>
                            <p class="text-[11px] text-slate-500">Formulate batch, calculate total material consumption, and validate raw material stock</p>
                        </div>
                    </div>
                    <button @click="closeFormModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form @submit.prevent="submitForm" class="p-6 space-y-6 max-h-[82vh] overflow-y-auto custom-scrollbar">
                    <!-- Batch Header Inputs -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                            <FileText class="w-3.5 h-3.5 text-amber-600" />
                            <span>Batch & Finished Good Parameters</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <!-- Production No -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-semibold text-slate-700">Production No *</label>
                                    <button
                                        type="button"
                                        @click="regenerateProductionNo"
                                        class="text-[10px] text-amber-700 hover:text-amber-800 flex items-center gap-0.5 font-mono font-bold"
                                        title="Auto Generate / Refresh Production No"
                                    >
                                        <RefreshCw class="w-2.5 h-2.5" />
                                        <span>Auto</span>
                                    </button>
                                </div>
                                <input
                                    v-model="form.production_no"
                                    type="text"
                                    required
                                    placeholder="e.g. PRD-0001"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Production Date -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Production Date *</label>
                                <input
                                    v-model="form.production_date"
                                    type="date"
                                    required
                                    @change="onProductionDateChange"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none cursor-pointer"
                                />
                            </div>

                            <!-- Select Finished Good (FG Grade) -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Finished Good (RMC Grade) *</label>
                                <select
                                    v-model.number="form.fg_item_id"
                                    required
                                    @change="onFgChange"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                >
                                    <option :value="0" disabled>Select Grade (M10-M40)...</option>
                                    <option v-for="fg in productionStore.fgItems" :key="fg.id" :value="fg.id">
                                        {{ fg.category }} {{ fg.size ? `(${fg.size})` : '' }}
                                    </option>
                                </select>
                            </div>

                            <!-- Produced Volume in CBM -->
                            <div>
                                <label class="block text-xs font-semibold text-emerald-800 mb-1">Production Qty (CBM) *</label>
                                <input
                                    v-model.number="form.cbm"
                                    type="number"
                                    step="any"
                                    min="0.1"
                                    required
                                    @input="onCbmChange"
                                    placeholder="e.g. 6.00"
                                    class="w-full px-3.5 py-2 bg-white border-2 border-emerald-500/50 rounded-xl text-emerald-800 font-mono font-bold text-sm focus:ring-2 focus:ring-emerald-400 focus:outline-none"
                                />
                            </div>

                            <!-- Plant Name -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Batching Plant</label>
                                <input
                                    v-model="form.plant_name"
                                    type="text"
                                    placeholder="e.g. Plant-01 Central"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Batch Ref / TM No -->
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-semibold text-slate-700">Batch / TM No</label>
                                    <button
                                        type="button"
                                        @click="regenerateBatchNo"
                                        class="text-[10px] text-emerald-700 hover:text-emerald-800 flex items-center gap-0.5 font-mono font-bold"
                                        title="Auto Generate / Refresh Batch No"
                                    >
                                        <RefreshCw class="w-2.5 h-2.5" />
                                        <span>Auto</span>
                                    </button>
                                </div>
                                <input
                                    v-model="form.batch_no"
                                    type="text"
                                    placeholder="e.g. BAT-20260922-001"
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-emerald-800 font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>

                            <!-- Remarks -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Remarks / Dispatch Notes</label>
                                <input
                                    v-model="form.remarks"
                                    type="text"
                                    placeholder="Client project, site location, slump notes..."
                                    class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Production Recipe & Stock Availability Table -->
                    <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                                <Layers class="w-3.5 h-3.5 text-amber-600" />
                                <span>Production Recipe & Material Consumption (Total for {{ form.cbm || 0 }} CBM)</span>
                            </div>
                            <div class="text-[11px] text-slate-500 flex items-center gap-2">
                                <Info class="w-3.5 h-3.5 text-amber-600" />
                                <span>Actual dosage per CBM is editable. Batch consumption is calculated automatically.</span>
                            </div>
                        </div>

                        <!-- Shortage Alert Banner inside Modal if shortages detected -->
                        <div
                            v-if="formShortages.length > 0"
                            class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 flex items-start gap-3 text-xs"
                        >
                            <AlertTriangle class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <div class="font-bold text-red-900">
                                    Stock Shortage Detected! ({{ formShortages.length }} item{{ formShortages.length > 1 ? 's' : '' }} short)
                                </div>
                                <div class="text-[11px] text-red-700">
                                    Production cannot be recorded until raw materials are inwarded or batch volume is adjusted:
                                </div>
                                <div class="flex flex-wrap gap-2 pt-1 font-mono text-[11px]">
                                    <span
                                        v-for="s in formShortages"
                                        :key="s.rm_item_id"
                                        class="px-2 py-0.5 rounded bg-red-100 border border-red-300 text-red-900 font-bold"
                                    >
                                        {{ s.rm_item?.category || 'RM' }}: Needs {{ s.total_qty }} {{ s.unit }} (Stock: {{ s.available_stock }} {{ s.unit }}, Short: {{ s.shortage_qty }} {{ s.unit }})
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto bg-white rounded-xl border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-700 uppercase tracking-wider border-b border-slate-200 font-bold text-[10px] bg-slate-50">
                                        <th class="py-2.5 px-3">#</th>
                                        <th class="py-2.5 px-3">Raw Material</th>
                                        <th class="py-2.5 px-3">Master Dosage (per CBM)</th>
                                        <th class="py-2.5 px-3 w-40">Actual Dosage (per CBM) *</th>
                                        <th class="py-2.5 px-3">Unit</th>
                                        <th class="py-2.5 px-3 font-mono">Total Required (Batch)</th>
                                        <th class="py-2.5 px-3 font-mono">Available Stock</th>
                                        <th class="py-2.5 px-3">Stock Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="(row, idx) in form.recipe_items"
                                        :key="row.rm_item_id"
                                        :class="['transition-colors', row.is_short ? 'bg-red-50 hover:bg-red-100/50' : 'hover:bg-slate-50']"
                                    >
                                        <td class="py-3 px-3 text-slate-500 font-mono">{{ idx + 1 }}</td>

                                        <!-- Raw Material Name -->
                                        <td class="py-3 px-3 font-bold text-slate-900">
                                            <div>{{ row.rm_item?.category || 'Item' }} {{ row.rm_item?.size ? `(${row.rm_item.size})` : '' }}</div>
                                            <div class="text-[10px] text-slate-400 font-normal font-mono">ID: {{ row.rm_item_id }}</div>
                                        </td>

                                        <!-- Master Recipe Dosage -->
                                        <td class="py-3 px-3 font-mono text-slate-500">
                                            {{ row.recipe_qty_per_cbm }} {{ row.unit }}/cbm
                                        </td>

                                        <!-- Editable Actual Dosage -->
                                        <td class="py-3 px-3">
                                            <div class="flex items-center gap-1.5">
                                                <input
                                                    v-model.number="row.actual_qty_per_cbm"
                                                    type="number"
                                                    step="any"
                                                    min="0.0001"
                                                    required
                                                    @input="onActualQtyChange(idx)"
                                                    class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-slate-900 font-mono font-bold text-xs focus:ring-1 focus:ring-amber-500 focus:outline-none"
                                                />
                                                <button
                                                    v-if="row.actual_qty_per_cbm !== row.recipe_qty_per_cbm"
                                                    type="button"
                                                    @click="resetToMasterDosage(idx)"
                                                    class="p-1 text-slate-400 hover:text-amber-600 text-[10px]"
                                                    title="Reset to Master Recipe Dosage"
                                                >
                                                    <RefreshCw class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </td>

                                        <!-- Unit -->
                                        <td class="py-3 px-3 font-mono text-slate-700">
                                            {{ row.unit }}
                                        </td>

                                        <!-- Calculated Total Batch Consumption -->
                                        <td class="py-3 px-3 font-mono font-bold text-amber-800">
                                            <div class="text-xs">
                                                {{ row.total_qty.toLocaleString() }} {{ row.unit }}
                                            </div>
                                            <div
                                                v-if="row.rm_item?.conversion_factor && Number(row.rm_item.conversion_factor) > 1"
                                                class="text-[10px] text-cyan-700 font-normal"
                                            >
                                                ≈ {{ (row.total_qty / Number(row.rm_item.conversion_factor)).toFixed(4) }} {{ row.rm_item.primary_unit }}
                                            </div>
                                        </td>

                                        <!-- Available Stock in Inventory -->
                                        <td class="py-3 px-3 font-mono">
                                            <div v-if="row.is_exempt" class="text-xs font-bold text-cyan-800">
                                                Direct Supply Line
                                                <div class="text-[10px] text-slate-500 font-normal">Not Stock Tracked</div>
                                            </div>
                                            <div v-else>
                                                <div class="text-xs font-bold text-slate-900">
                                                    {{ (row.available_stock || 0).toLocaleString() }} {{ row.unit }}
                                                </div>
                                                <div
                                                    v-if="row.rm_item?.conversion_factor && Number(row.rm_item.conversion_factor) > 1"
                                                    class="text-[10px] text-slate-500"
                                                >
                                                    ≈ {{ ((row.available_stock || 0) / Number(row.rm_item.conversion_factor)).toFixed(3) }} {{ row.rm_item.primary_unit }}
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Stock Sufficiency Status Badge -->
                                        <td class="py-3 px-3">
                                            <div v-if="row.is_exempt" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-cyan-50 border border-cyan-200 text-cyan-800 text-[10px] font-bold whitespace-nowrap">
                                                <CheckCircle2 class="w-3 h-3 text-cyan-600" />
                                                <span>Direct Line (Exempt)</span>
                                            </div>
                                            <div v-else-if="!row.is_short" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10px] font-bold">
                                                <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                                                <span>In Stock</span>
                                            </div>
                                            <div v-else class="inline-flex flex-col items-start gap-0.5">
                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 border border-red-200 text-red-800 text-[10px] font-bold">
                                                    <AlertCircle class="w-3 h-3 text-red-600" />
                                                    <span>Shortage!</span>
                                                </div>
                                                <span class="text-[9px] font-mono text-red-700 font-semibold">
                                                    -{{ row.shortage_qty?.toLocaleString() }} {{ row.unit }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Modal Action Buttons -->
                    <div class="pt-4 border-t border-slate-200 flex items-center justify-between">
                        <div class="text-xs">
                            <span v-if="formShortages.length > 0" class="text-red-700 font-bold flex items-center gap-1.5">
                                <ShieldAlert class="w-4 h-4 text-red-600" />
                                <span>Save blocked due to {{ formShortages.length }} stock shortage(s).</span>
                            </span>
                            <span v-else-if="form.recipe_items.length > 0" class="text-emerald-700 font-semibold flex items-center gap-1.5">
                                <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                                <span>All raw materials are available in stock. Ready to record batch.</span>
                            </span>
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
                                :disabled="!isFormValidForSave || productionStore.isSaving"
                                class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-md shadow-amber-500/20 transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                <RefreshCw v-if="productionStore.isSaving" class="w-4 h-4 animate-spin" />
                                <span>Save Production Batch & Deduct Stock</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- View Production Batch Formulation & Consumptions Modal -->
        <div
            v-if="isViewModalOpen && viewingProduction"
            @click.self="closeViewModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden my-8">
                <!-- Slip Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-cyan-50 border border-cyan-200 text-cyan-700">
                            <FileText class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">
                                Production Batch: {{ viewingProduction.production_no }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Batch formulation, volume output and material consumption ledger</p>
                        </div>
                    </div>
                    <button @click="closeViewModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Slip Content -->
                <div class="p-6 space-y-6 max-h-[80vh] overflow-y-auto custom-scrollbar">
                    <!-- Batch Overview Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Production Date</div>
                            <div class="text-xs font-mono font-bold text-slate-900">{{ viewingProduction.production_date }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">RMC Grade Produced</div>
                            <div class="text-xs font-bold text-amber-800">
                                {{ viewingProduction.fg_item?.category || 'RMC' }} {{ viewingProduction.fg_item?.size ? `(${viewingProduction.fg_item.size})` : '' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Produced Volume</div>
                            <div class="text-sm font-mono font-bold text-emerald-700">
                                {{ Number(viewingProduction.cbm).toFixed(2) }} CBM
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Batching Plant</div>
                            <div class="text-xs text-slate-900 font-semibold">{{ viewingProduction.plant_name }}</div>
                        </div>
                        <div v-if="viewingProduction.batch_no" class="sm:col-span-2">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Batch / TM No</div>
                            <div class="text-xs font-mono text-slate-700">{{ viewingProduction.batch_no }}</div>
                        </div>
                        <div v-if="viewingProduction.remarks" class="sm:col-span-2">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Remarks</div>
                            <div class="text-xs text-slate-700">{{ viewingProduction.remarks }}</div>
                        </div>
                    </div>

                    <!-- Formulation Breakdown Table -->
                    <div class="space-y-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-amber-800 flex items-center gap-2">
                            <Layers class="w-3.5 h-3.5 text-amber-600" />
                            <span>Formulation & Consumption Breakdown</span>
                        </div>

                        <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase text-[10px] border-b border-slate-200">
                                        <th class="py-2.5 px-3">Raw Material</th>
                                        <th class="py-2.5 px-3">Master (per CBM)</th>
                                        <th class="py-2.5 px-3">Actual (per CBM)</th>
                                        <th class="py-2.5 px-3 font-mono">Total Consumed</th>
                                        <th class="py-2.5 px-3 font-mono">Primary Unit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr v-for="item in (viewingProduction.recipe_items || [])" :key="item.id">
                                        <td class="py-2.5 px-3 font-bold text-slate-900">
                                            {{ item.rm_item?.category || 'RM' }} {{ item.rm_item?.size ? `(${item.rm_item.size})` : '' }}
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-slate-500">
                                            {{ Number(item.recipe_qty_per_cbm) }} {{ item.unit }}
                                        </td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-amber-800">
                                            {{ Number(item.actual_qty_per_cbm) }} {{ item.unit }}
                                        </td>
                                        <td class="py-2.5 px-3 font-mono font-bold text-emerald-700">
                                            {{ Number(item.total_qty).toLocaleString() }} {{ item.unit }}
                                        </td>
                                        <td class="py-2.5 px-3 font-mono text-cyan-800 text-[11px]">
                                            <span v-if="item.rm_item?.conversion_factor && Number(item.rm_item.conversion_factor) > 1">
                                                ≈ {{ (Number(item.total_qty) / Number(item.rm_item.conversion_factor)).toFixed(4) }} {{ item.rm_item.primary_unit }}
                                            </span>
                                            <span v-else>—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Separate Material Consumption Ledger Saved Records -->
                    <div class="space-y-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-cyan-800 flex items-center gap-2">
                            <Scale class="w-3.5 h-3.5 text-cyan-600" />
                            <span>Saved Inventory Consumption Records</span>
                        </div>

                        <div class="border border-slate-200 rounded-xl overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 uppercase text-[10px] border-b border-slate-200">
                                        <th class="py-2 px-3">Item</th>
                                        <th class="py-2 px-3">Consumed Qty</th>
                                        <th class="py-2 px-3">Recipe Unit</th>
                                        <th class="py-2 px-3">Consumed Primary</th>
                                        <th class="py-2 px-3">Primary Unit</th>
                                        <th class="py-2 px-3">Deduction Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700 font-mono text-[11px]">
                                    <tr v-for="c in (viewingProduction.consumptions || [])" :key="c.id">
                                        <td class="py-2 px-3 font-sans font-bold text-slate-900">
                                            {{ c.item?.category || 'Item' }} {{ c.item?.size ? `(${c.item.size})` : '' }}
                                        </td>
                                        <td class="py-2 px-3 text-amber-800 font-bold">
                                            {{ Number(c.consumed_qty).toLocaleString() }}
                                        </td>
                                        <td class="py-2 px-3 text-slate-600">
                                            {{ c.unit }}
                                        </td>
                                        <td class="py-2 px-3 text-cyan-800 font-bold">
                                            {{ c.consumed_primary_qty !== null ? Number(c.consumed_primary_qty).toFixed(4) : '—' }}
                                        </td>
                                        <td class="py-2 px-3 text-slate-600">
                                            {{ c.primary_unit || '—' }}
                                        </td>
                                        <td class="py-2 px-3 text-slate-500">
                                            {{ c.consumed_date }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Slip Footer -->
                <div class="px-6 py-4 border-t border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="text-[11px] text-slate-500">
                        Recorded by {{ viewingProduction.creator?.name || 'Admin' }}
                    </div>
                    <button
                        type="button"
                        @click="closeViewModal"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div
            v-if="isDeleteModalOpen && deletingProduction"
            @click.self="isDeleteModalOpen = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 text-red-600">
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm">Delete Production Batch?</h4>
                        <p class="text-xs text-slate-500 font-mono">{{ deletingProduction.production_no }} ({{ Number(deletingProduction.cbm).toFixed(2) }} CBM)</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Are you sure you want to delete this production batch? Deleting this record will <strong class="text-emerald-700">automatically restore all consumed raw materials</strong> back into the available stock balance.
                </p>

                <!-- Validation Error inside Modal -->
                <div
                    v-if="productionStore.error"
                    class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-800 space-y-1.5 text-xs"
                >
                    <div class="font-bold flex items-center gap-2 text-red-700">
                        <AlertTriangle class="w-4 h-4 shrink-0 text-red-600" />
                        <span>Cannot Delete: Dispatched Batch</span>
                    </div>
                    <p class="leading-relaxed text-[11px] text-red-800">{{ productionStore.error }}</p>
                    <ul v-if="productionStore.validationErrors.length > 0" class="pl-4 list-disc text-[10px] space-y-0.5 text-red-700 font-mono pt-1">
                        <li v-for="(dispatch, dIdx) in productionStore.validationErrors" :key="dIdx">
                            {{ dispatch }}
                        </li>
                    </ul>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                    >
                        {{ productionStore.error ? 'Close' : 'Cancel' }}
                    </button>
                    <button
                        v-if="!productionStore.error"
                        type="button"
                        @click="handleDelete"
                        :disabled="productionStore.isSaving"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                    >
                        <RefreshCw v-if="productionStore.isSaving" class="w-4 h-4 animate-spin" />
                        <span>Delete & Restore Stock</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
