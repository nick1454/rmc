<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useItemStore } from '../../stores/item';
import { useRecipeStore } from '../../stores/recipe';
import { useEscapeKey } from '../../composables/useEscapeKey';
import type { Item, ItemForm } from '../../types/item';
import type { RecipeItemInput } from '../../types/recipe';
import AppLayout from '../../components/layout/AppLayout.vue';
import {
    Plus,
    Search,
    Edit3,
    Trash2,
    Boxes,
    AlertCircle,
    CheckCircle2,
    X,
    RefreshCw,
    Layers,
    Scale,
    FlaskConical
} from 'lucide-vue-next';

const itemStore = useItemStore();
const recipeStore = useRecipeStore();

// Modals state
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const isSizeModalOpen = ref(false);
const isUnitModalOpen = ref(false);
const isRecipeModalOpen = ref(false);
const unitModalTarget = ref<'primary' | 'secondary' | 'both'>('both');

const editingItem = ref<Item | null>(null);
const deletingItem = ref<Item | null>(null);
const selectedFgItem = ref<Item | null>(null);
const recipeFormItems = ref<RecipeItemInput[]>([]);

// Quick add inputs
const newSizeInput = ref('');
const newUnitInput = ref('');

const initialForm: ItemForm = {
    category: 'Aggregate',
    size: '',
    secondary_unit: 'kgs',
    primary_unit: 'ton',
    conversion_factor: 1000,
    type: 'rm',
    hsn: '',
    gst_per: 5,
};

const form = ref<ItemForm>({ ...initialForm });

onMounted(async () => {
    await itemStore.fetchMeta();
    await itemStore.fetchItems();
});

const handleSearch = () => {
    itemStore.fetchItems(1);
};

const handleFilterChange = () => {
    itemStore.fetchItems(1);
};

const openCreateModal = () => {
    editingItem.value = null;
    form.value = { ...initialForm };
    isModalOpen.value = true;
};

const openEditModal = (item: Item) => {
    editingItem.value = item;
    form.value = {
        category: item.category,
        size: item.size || '',
        secondary_unit: item.secondary_unit || 'kgs',
        primary_unit: item.primary_unit || 'ton',
        conversion_factor: item.conversion_factor ? Number(item.conversion_factor) : 1,
        type: item.type || 'rm',
        hsn: item.hsn || '',
        gst_per: item.gst_per ? Number(item.gst_per) : 0,
    };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingItem.value = null;
};

// Quick Add Size Modal
const openAddSizeModal = () => {
    newSizeInput.value = '';
    isSizeModalOpen.value = true;
};

const closeAddSizeModal = () => {
    isSizeModalOpen.value = false;
    newSizeInput.value = '';
};

const submitAddSize = async () => {
    if (!newSizeInput.value.trim()) return;
    const addedSize = newSizeInput.value.trim();
    if (!itemStore.metaOptions.sizes.includes(addedSize)) {
        itemStore.metaOptions.sizes.push(addedSize);
    }
    form.value.size = addedSize;
    closeAddSizeModal();
};

// Quick Add Unit Modal
const openAddUnitModal = (target: 'primary' | 'secondary' | 'both' = 'both') => {
    unitModalTarget.value = target;
    newUnitInput.value = '';
    isUnitModalOpen.value = true;
};

const closeAddUnitModal = () => {
    isUnitModalOpen.value = false;
    newUnitInput.value = '';
};

const submitAddUnit = async () => {
    if (!newUnitInput.value.trim()) return;
    const addedUnit = newUnitInput.value.trim().toLowerCase();

    if (unitModalTarget.value === 'primary' || unitModalTarget.value === 'both') {
        if (!itemStore.metaOptions.primary_units.includes(addedUnit)) {
            itemStore.metaOptions.primary_units.push(addedUnit);
        }
        form.value.primary_unit = addedUnit;
    }

    if (unitModalTarget.value === 'secondary' || unitModalTarget.value === 'both') {
        if (!itemStore.metaOptions.secondary_units.includes(addedUnit)) {
            itemStore.metaOptions.secondary_units.push(addedUnit);
        }
        form.value.secondary_unit = addedUnit;
    }

    closeAddUnitModal();
};

const submitForm = async () => {
    let success = false;
    if (editingItem.value) {
        success = await itemStore.updateItem(editingItem.value.id, form.value);
    } else {
        success = await itemStore.createItem(form.value);
    }

    if (success) {
        closeModal();
    }
};

const confirmDelete = (item: Item) => {
    deletingItem.value = item;
    isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
    if (!deletingItem.value) return;
    const success = await itemStore.deleteItem(deletingItem.value.id);
    if (success) {
        isDeleteModalOpen.value = false;
        deletingItem.value = null;
    }
};

// Master Recipe Modal
const openRecipeModal = async (fgItem: Item) => {
    selectedFgItem.value = fgItem;
    isRecipeModalOpen.value = true;

    // Load recipe items from API
    await recipeStore.fetchRecipe(fgItem.id);

    if (recipeStore.recipeItems && recipeStore.recipeItems.length > 0) {
        recipeFormItems.value = recipeStore.recipeItems.map((r) => ({
            rm_item_id: r.rm_item_id,
            quantity: Number(r.quantity),
            unit: r.unit || 'kgs',
        }));
    } else {
        // Initialize with default raw material templates if empty
        const defaultRm = itemStore.rmItems.slice(0, 4);
        recipeFormItems.value = defaultRm.map((rm) => ({
            rm_item_id: rm.id,
            quantity: 0,
            unit: rm.secondary_unit || 'kgs',
        }));
    }
};

const closeRecipeModal = () => {
    isRecipeModalOpen.value = false;
    selectedFgItem.value = null;
    recipeFormItems.value = [];
};

const addRecipeIngredient = () => {
    const defaultRmId = itemStore.rmItems[0]?.id || 0;
    recipeFormItems.value.push({
        rm_item_id: defaultRmId,
        quantity: 0,
        unit: 'kgs',
    });
};

const removeRecipeIngredient = (index: number) => {
    recipeFormItems.value.splice(index, 1);
};

const totalRecipeMixWeight = computed(() => {
    return recipeFormItems.value.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0);
});

const submitRecipeForm = async () => {
    if (!selectedFgItem.value) return;

    const validItems = recipeFormItems.value.filter((i) => i.rm_item_id > 0 && Number(i.quantity) > 0);

    const success = await recipeStore.saveRecipe(selectedFgItem.value.id, validItems);
    if (success) {
        await itemStore.fetchItems(itemStore.meta.current_page);
        closeRecipeModal();
    }
};

const closeAllModals = () => {
    if (isSizeModalOpen.value) {
        closeAddSizeModal();
    } else if (isUnitModalOpen.value) {
        closeAddUnitModal();
    } else if (isDeleteModalOpen.value) {
        isDeleteModalOpen.value = false;
        deletingItem.value = null;
    } else if (isRecipeModalOpen.value) {
        closeRecipeModal();
    } else if (isModalOpen.value) {
        closeModal();
    }
};

useEscapeKey(closeAllModals);

const getCategoryBadgeClass = (category: string) => {
    switch (category) {
        case 'Aggregate':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Cement':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Dust':
            return 'bg-yellow-50 text-yellow-800 border-yellow-200';
        case 'AddMixture':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'Silo Pouch':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'RMC':
            return 'bg-cyan-50 text-cyan-700 border-cyan-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <AppLayout title="Item Master" subtitle="Manage item categories, sizes, units, and conversion rates">
        <div class="space-y-6">
            <!-- Toast Feedback Notifications -->
            <div
                v-if="itemStore.successMessage"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between shadow-xs"
            >
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="w-5 h-5 flex-shrink-0" />
                    <span>{{ itemStore.successMessage }}</span>
                </div>
                <button @click="itemStore.clearMessages" class="text-emerald-700 hover:text-emerald-900">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div
                v-if="itemStore.error"
                class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center justify-between shadow-xs"
            >
                <div class="flex items-center gap-2">
                    <AlertCircle class="w-5 h-5 flex-shrink-0" />
                    <span>{{ itemStore.error }}</span>
                </div>
                <button @click="itemStore.clearMessages" class="text-red-700 hover:text-red-900">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Controls: Search, Category Filter, Type Filter & Add Button -->
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                <!-- Search Box -->
                <div class="relative flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <Search class="w-4 h-4" />
                    </div>
                    <input
                        v-model="itemStore.searchQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        placeholder="Search by category, size, HSN..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 placeholder-slate-400 text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:bg-white transition-all"
                    />
                </div>

                <!-- Action Controls -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Category Filter -->
                    <select
                        v-model="itemStore.categoryFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                        <option value="all">All Categories</option>
                        <option v-for="cat in itemStore.metaOptions.categories" :key="cat" :value="cat">
                            {{ cat }}
                        </option>
                    </select>

                    <!-- Type Filter -->
                    <select
                        v-model="itemStore.typeFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                        <option value="all">All Types</option>
                        <option value="rm">RM (Raw Material)</option>
                        <option value="fg">FG (Finished Goods)</option>
                    </select>

                    <button
                        type="button"
                        @click="itemStore.fetchItems(itemStore.meta.current_page)"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors"
                        title="Refresh List"
                    >
                        <RefreshCw :class="['w-4 h-4', itemStore.isLoading ? 'animate-spin' : '']" />
                    </button>

                    <button
                        type="button"
                        @click="openCreateModal"
                        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-1.5 shadow-md shadow-amber-500/20 transition-all active:scale-95"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Add Item</span>
                    </button>
                </div>
            </div>

            <!-- Items Data Table & Mobile Cards -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                <!-- Mobile Card List (< md) -->
                <div class="block md:hidden divide-y divide-slate-100">
                    <div v-if="itemStore.isLoading && itemStore.items.length === 0" class="p-8 text-center text-slate-400">
                        <div class="inline-flex items-center gap-2">
                            <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                            <span class="text-xs">Loading items from database...</span>
                        </div>
                    </div>

                    <div v-else-if="itemStore.items.length === 0" class="p-8 text-center text-slate-500 space-y-2">
                        <Boxes class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="font-semibold text-sm text-slate-700">No Items Found</div>
                        <p class="text-xs text-slate-400">Add an item or adjust filter.</p>
                    </div>

                    <div
                        v-for="item in itemStore.items"
                        :key="item.id"
                        class="p-4 space-y-3 hover:bg-slate-50/80 transition-colors"
                    >
                        <!-- Top Row: Category, Size, Type -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span :class="['inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold border', getCategoryBadgeClass(item.category)]">
                                        {{ item.category }}
                                    </span>
                                    <span
                                        v-if="item.type === 'rm'"
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200"
                                    >
                                        RM
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200"
                                    >
                                        FG
                                    </span>
                                </div>
                                <div class="font-bold text-sm text-slate-900 pt-0.5">
                                    <span v-if="item.size" class="font-mono text-amber-700">Size: {{ item.size }}</span>
                                    <span v-else class="text-slate-500">{{ item.category }} (Standard)</span>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="text-xs font-bold text-amber-700 font-mono">GST {{ item.gst_per }}%</span>
                                <span v-if="item.hsn" class="text-[10px] text-slate-500 font-mono block">HSN: {{ item.hsn }}</span>
                            </div>
                        </div>

                        <!-- Unit & Conversion Detail -->
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1.5 text-slate-700">
                                <Scale class="w-3.5 h-3.5 text-amber-600 flex-shrink-0" />
                                <span>1 {{ item.primary_unit }} = <strong class="text-slate-900">{{ Number(item.conversion_factor) }}</strong> {{ item.secondary_unit }}</span>
                            </div>
                            <span class="text-[10px] text-slate-500 uppercase font-mono">{{ item.primary_unit }} / {{ item.secondary_unit }}</span>
                        </div>

                        <!-- Master Recipe Button for FG -->
                        <div v-if="item.type === 'fg'" class="pt-1">
                            <button
                                v-if="item.recipe_items_count && item.recipe_items_count > 0"
                                type="button"
                                @click="openRecipeModal(item)"
                                class="w-full py-2 px-3 rounded-xl bg-cyan-50 hover:bg-cyan-100 text-cyan-700 border border-cyan-200 font-semibold text-xs transition-all flex items-center justify-center gap-2"
                            >
                                <FlaskConical class="w-4 h-4 text-cyan-600" />
                                <span>Master Recipe ({{ item.recipe_items_count }} RM Items)</span>
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="openRecipeModal(item)"
                                class="w-full py-2 px-3 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 font-semibold text-xs transition-all flex items-center justify-center gap-2"
                            >
                                <Plus class="w-4 h-4" />
                                <span>Configure Master Recipe</span>
                            </button>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                            <div class="text-[10px] text-slate-400 font-mono">
                                By {{ item.creator?.name || 'Admin' }}
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="openEditModal(item)"
                                    class="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold flex items-center gap-1.5 transition-colors border border-amber-200"
                                >
                                    <Edit3 class="w-3.5 h-3.5" />
                                    <span>Edit</span>
                                </button>
                                <button
                                    type="button"
                                    @click="confirmDelete(item)"
                                    class="px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold flex items-center gap-1.5 transition-colors border border-red-200"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                    <span>Delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table (>= md) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600 uppercase tracking-wider border-b border-slate-200 font-bold text-[11px]">
                                <th class="py-3.5 px-4">Category</th>
                                <th class="py-3.5 px-4">Size</th>
                                <th class="py-3.5 px-4">Type</th>
                                <th class="py-3.5 px-4">Primary Unit</th>
                                <th class="py-3.5 px-4">Secondary Unit</th>
                                <th class="py-3.5 px-4">Conversion Value</th>
                                <th class="py-3.5 px-4">Master Recipe</th>
                                <th class="py-3.5 px-4">HSN</th>
                                <th class="py-3.5 px-4">GST %</th>
                                <th class="py-3.5 px-4">Created By</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="itemStore.isLoading && itemStore.items.length === 0">
                                <td colspan="11" class="py-12 text-center text-slate-400">
                                    <div class="inline-flex items-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading items from database...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="itemStore.items.length === 0">
                                <td colspan="11" class="py-12 text-center text-slate-500">
                                    <div class="space-y-2">
                                        <Boxes class="w-8 h-8 text-slate-400 mx-auto" />
                                        <div class="font-semibold text-slate-700">No Items Found</div>
                                        <p class="text-[11px] text-slate-400">Add an item or adjust filter.</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="item in itemStore.items"
                                :key="item.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <td class="py-3.5 px-4">
                                    <span :class="['inline-flex px-2.5 py-1 rounded-md text-[11px] font-bold border', getCategoryBadgeClass(item.category)]">
                                        {{ item.category }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 font-mono font-bold">
                                    <span v-if="item.size" class="text-amber-800 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                        {{ item.size }}
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span
                                        v-if="item.type === 'rm'"
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200"
                                    >
                                        RM
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200"
                                    >
                                        FG
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 font-semibold text-slate-900">
                                    {{ item.primary_unit }}
                                </td>

                                <td class="py-3.5 px-4 font-semibold text-slate-900">
                                    {{ item.secondary_unit }}
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-700">
                                    <div class="inline-flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200 text-[11px]">
                                        <Scale class="w-3.5 h-3.5 text-amber-600" />
                                        <span>1 {{ item.primary_unit }} = <strong class="text-slate-900">{{ Number(item.conversion_factor) }}</strong> {{ item.secondary_unit }}</span>
                                    </div>
                                </td>

                                <!-- Master Recipe Column -->
                                <td class="py-3.5 px-4">
                                    <div v-if="item.type === 'fg'">
                                        <button
                                            v-if="item.recipe_items_count && item.recipe_items_count > 0"
                                            type="button"
                                            @click="openRecipeModal(item)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-700 border border-cyan-200 font-semibold text-[11px] transition-all shadow-xs active:scale-95"
                                            title="View / Edit Master Recipe"
                                        >
                                            <FlaskConical class="w-3.5 h-3.5 text-cyan-600" />
                                            <span>{{ item.recipe_items_count }} RM Items</span>
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            @click="openRecipeModal(item)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 font-semibold text-[11px] transition-all active:scale-95"
                                            title="Configure Recipe Formulation"
                                        >
                                            <Plus class="w-3.5 h-3.5" />
                                            <span>Set Recipe</span>
                                        </button>
                                    </div>
                                    <span v-else class="text-slate-400 font-mono text-[11px]">—</span>
                                </td>

                                <td class="py-3.5 px-4 font-mono text-slate-500">
                                    {{ item.hsn || '—' }}
                                </td>

                                <td class="py-3.5 px-4 font-bold text-amber-700 font-mono">
                                    {{ item.gst_per }}%
                                </td>

                                <td class="py-3.5 px-4 text-[11px] text-slate-500">
                                    <div class="text-slate-700 font-medium">{{ item.creator?.name || 'Admin' }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ item.created_at ? new Date(item.created_at).toLocaleDateString() : '—' }}</div>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditModal(item)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-amber-700 hover:bg-amber-50 transition-colors"
                                            title="Edit Item"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(item)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Delete Item"
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
                <div v-if="itemStore.meta.total > 0" class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (itemStore.meta.current_page - 1) * itemStore.meta.per_page + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(itemStore.meta.current_page * itemStore.meta.per_page, itemStore.meta.total) }}</span>
                        of <span class="font-bold text-slate-900">{{ itemStore.meta.total }}</span> items
                    </div>

                    <div class="flex items-center gap-1.5" v-if="itemStore.meta.last_page > 1">
                        <button
                            type="button"
                            :disabled="itemStore.meta.current_page === 1"
                            @click="itemStore.fetchItems(itemStore.meta.current_page - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in itemStore.meta.last_page"
                            :key="p"
                            @click="itemStore.fetchItems(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all',
                                itemStore.meta.current_page === p
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            {{ p }}
                        </button>
                        <button
                            type="button"
                            :disabled="itemStore.meta.current_page === itemStore.meta.last_page"
                            @click="itemStore.fetchItems(itemStore.meta.current_page + 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Item Modal -->
        <div
            v-if="isModalOpen"
            @click.self="closeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-amber-50 text-amber-600 border border-amber-200">
                            <Boxes class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                {{ editingItem ? 'Edit Item' : 'Add Item' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Configure item master details</p>
                        </div>
                    </div>
                    <button @click="closeModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form @submit.prevent="submitForm" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto custom-scrollbar">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Category (Dropdown) -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Category *</label>
                            <select
                                v-model="form.category"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                            >
                                <option v-for="cat in itemStore.metaOptions.categories" :key="cat" :value="cat">
                                    {{ cat }}
                                </option>
                            </select>
                        </div>

                        <!-- Size (Dropdown with Quick Add Button) -->
                        <div class="sm:col-span-2">
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-slate-700">Size</label>
                                <button
                                    type="button"
                                    @click="openAddSizeModal"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 px-2 py-0.5 rounded border border-amber-200 transition-colors"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>Add Size</span>
                                </button>
                            </div>
                            <select
                                v-model="form.size"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                            >
                                <option value="">(None / Standard)</option>
                                <option v-for="sz in itemStore.metaOptions.sizes" :key="sz" :value="sz">
                                    {{ sz }}
                                </option>
                            </select>
                        </div>

                        <!-- Type (rm / fg dropdown) -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Type *</label>
                            <select
                                v-model="form.type"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                            >
                                <option value="rm">rm (Raw Material)</option>
                                <option value="fg">fg (Finished Goods)</option>
                            </select>
                        </div>

                        <!-- Primary Unit (Dropdown with Add Button) -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-slate-700">Primary Unit *</label>
                                <button
                                    type="button"
                                    @click="openAddUnitModal('primary')"
                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 px-1.5 py-0.5 rounded border border-amber-200 transition-colors"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>Add</span>
                                </button>
                            </div>
                            <select
                                v-model="form.primary_unit"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none font-semibold focus:bg-white"
                            >
                                <option v-for="u in itemStore.metaOptions.primary_units" :key="u" :value="u">
                                    {{ u }}
                                </option>
                            </select>
                        </div>

                        <!-- Secondary Unit (Dropdown with Add Button) -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-slate-700">Secondary Unit *</label>
                                <button
                                    type="button"
                                    @click="openAddUnitModal('secondary')"
                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 px-1.5 py-0.5 rounded border border-amber-200 transition-colors"
                                >
                                    <Plus class="w-3 h-3" />
                                    <span>Add</span>
                                </button>
                            </div>
                            <select
                                v-model="form.secondary_unit"
                                required
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none font-semibold focus:bg-white"
                            >
                                <option v-for="u in itemStore.metaOptions.secondary_units" :key="u" :value="u">
                                    {{ u }}
                                </option>
                            </select>
                        </div>

                        <!-- Unit Conversion Factor Column -->
                        <div class="sm:col-span-2 bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-semibold text-slate-800">
                                    Conversion Value (Secondary Units per 1 Primary Unit) *
                                </label>
                            </div>
                            <div class="relative">
                                <input
                                    v-model.number="form.conversion_factor"
                                    type="number"
                                    step="any"
                                    min="0"
                                    required
                                    placeholder="e.g. 1000 for 1 ton = 1000 kgs"
                                    class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs font-mono font-bold focus:ring-2 focus:ring-amber-500 focus:outline-none"
                                />
                            </div>
                            <div class="text-[11px] text-amber-700 font-mono flex items-center gap-1.5">
                                <Scale class="w-3.5 h-3.5 text-amber-600" />
                                <span>Formula: 1 <strong>{{ form.primary_unit || 'Unit' }}</strong> = <strong>{{ form.conversion_factor || 1 }}</strong> <strong>{{ form.secondary_unit || 'Unit' }}</strong></span>
                            </div>
                        </div>

                        <!-- HSN -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">HSN</label>
                            <input
                                v-model="form.hsn"
                                type="text"
                                placeholder="e.g. 25171010"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                            />
                        </div>

                        <!-- GST % -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">GST % (gstper) *</label>
                            <input
                                v-model.number="form.gst_per"
                                type="number"
                                step="any"
                                min="0"
                                required
                                placeholder="e.g. 5, 18, 28"
                                class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                            />
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="itemStore.isSaving"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-md shadow-amber-500/20 disabled:opacity-50"
                        >
                            <RefreshCw v-if="itemStore.isSaving" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ itemStore.isSaving ? 'Saving...' : (editingItem ? 'Update Item' : 'Create Item') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Add Size Modal -->
        <div
            v-if="isSizeModalOpen"
            @click.self="closeAddSizeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-sm p-5 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-amber-50 text-amber-600 border border-amber-200">
                            <Layers class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Add New Size</h3>
                    </div>
                    <button @click="closeAddSizeModal" class="text-slate-400 hover:text-slate-700">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitAddSize" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Size Specification *</label>
                        <input
                            v-model="newSizeInput"
                            type="text"
                            required
                            placeholder="e.g. 40mm, opc53, 6mm"
                            autofocus
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                        />
                        <p class="text-[10px] text-slate-500 mt-1">This will be added instantly to your size options.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="closeAddSizeModal"
                            class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!newSizeInput.trim()"
                            class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs uppercase tracking-wider disabled:opacity-40 shadow-xs"
                        >
                            Add Size
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Add Unit Modal -->
        <div
            v-if="isUnitModalOpen"
            @click.self="closeAddUnitModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-sm p-5 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-amber-50 text-amber-600 border border-amber-200">
                            <Scale class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Add New Unit</h3>
                    </div>
                    <button @click="closeAddUnitModal" class="text-slate-400 hover:text-slate-700">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitAddUnit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Unit Code / Name *</label>
                        <input
                            v-model="newUnitInput"
                            type="text"
                            required
                            placeholder="e.g. ltr, bag, mtr, box"
                            autofocus
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                        />
                        <p class="text-[10px] text-slate-500 mt-1">This will be added instantly to your unit options.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="closeAddUnitModal"
                            class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!newUnitInput.trim()"
                            class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs uppercase tracking-wider disabled:opacity-40 shadow-xs"
                        >
                            Add Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Master Recipe Builder Modal -->
        <div
            v-if="isRecipeModalOpen"
            @click.self="closeRecipeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-cyan-50 text-cyan-600 border border-cyan-200">
                            <FlaskConical class="w-6 h-6" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900">
                                    Master Mix Recipe Formulation
                                </h3>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-cyan-50 text-cyan-700 border border-cyan-200">
                                    {{ selectedFgItem?.category }} {{ selectedFgItem?.size }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Configure standard raw material ingredients per <strong>1 {{ selectedFgItem?.primary_unit || 'cbm' }}</strong>
                            </p>
                        </div>
                    </div>
                    <button @click="closeRecipeModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form @submit.prevent="submitRecipeForm" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto custom-scrollbar">
                    <!-- Loading Indicator -->
                    <div v-if="recipeStore.isLoading" class="py-12 text-center text-slate-400">
                        <div class="inline-flex items-center gap-2">
                            <RefreshCw class="w-5 h-5 animate-spin text-cyan-600" />
                            <span>Loading master recipe formulation...</span>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- Header Metrics Banner -->
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500">Target Finished Good</div>
                                <div class="text-sm font-bold text-slate-900 mt-0.5">
                                    {{ selectedFgItem?.category }} {{ selectedFgItem?.size ? `(${selectedFgItem.size})` : '' }}
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-right">
                                <div>
                                    <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500">Base Batch Size</div>
                                    <div class="text-xs font-mono font-bold text-amber-700">1 {{ selectedFgItem?.primary_unit || 'cbm' }}</div>
                                </div>
                                <div class="border-l border-slate-200 pl-4">
                                    <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500">Total Mix Weight</div>
                                    <div class="text-sm font-mono font-bold text-cyan-700">
                                        {{ totalRecipeMixWeight.toFixed(2) }} kgs
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ingredients Table -->
                        <div class="border border-slate-200 rounded-xl overflow-hidden bg-white">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-600 uppercase tracking-wider text-[10px] font-bold border-b border-slate-200">
                                        <th class="py-2.5 px-3">#</th>
                                        <th class="py-2.5 px-3">Raw Material (RM Item)</th>
                                        <th class="py-2.5 px-3 w-32">Quantity</th>
                                        <th class="py-2.5 px-3 w-24">Unit</th>
                                        <th class="py-2.5 px-3 text-right">Proportion</th>
                                        <th class="py-2.5 px-2 text-center w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr v-for="(row, idx) in recipeFormItems" :key="idx" class="hover:bg-slate-50 transition-colors">
                                        <td class="py-2 px-3 text-slate-400 font-mono text-[11px]">{{ idx + 1 }}</td>
                                        <td class="py-2 px-3">
                                            <select
                                                v-model.number="row.rm_item_id"
                                                required
                                                class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 text-xs font-medium focus:ring-1 focus:ring-cyan-500 focus:outline-none focus:bg-white"
                                            >
                                                <option value="0" disabled>Select Raw Material...</option>
                                                <option v-for="rm in itemStore.rmItems" :key="rm.id" :value="rm.id">
                                                    {{ rm.category }} {{ rm.size ? `(${rm.size})` : '' }} - [{{ rm.primary_unit }}]
                                                </option>
                                            </select>
                                        </td>
                                        <td class="py-2 px-3">
                                            <input
                                                v-model.number="row.quantity"
                                                type="number"
                                                step="any"
                                                min="0"
                                                required
                                                placeholder="0.00"
                                                class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 font-mono text-xs font-bold focus:ring-1 focus:ring-cyan-500 focus:outline-none focus:bg-white"
                                            />
                                        </td>
                                        <td class="py-2 px-3">
                                            <select
                                                v-model="row.unit"
                                                required
                                                class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-slate-900 text-xs font-semibold focus:ring-1 focus:ring-cyan-500 focus:outline-none focus:bg-white"
                                            >
                                                <option value="kgs">kgs</option>
                                                <option value="ton">ton</option>
                                                <option value="ltr">ltr</option>
                                                <option value="pcs">pcs</option>
                                                <option value="bag">bag</option>
                                            </select>
                                        </td>
                                        <td class="py-2 px-3 text-right font-mono text-[11px] text-slate-500">
                                            {{ totalRecipeMixWeight > 0 ? ((Number(row.quantity) / totalRecipeMixWeight) * 100).toFixed(1) : '0.0' }}%
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <button
                                                type="button"
                                                @click="removeRecipeIngredient(idx)"
                                                class="p-1 rounded text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                                title="Remove Row"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Add Row Button -->
                        <div class="flex items-center justify-between pt-1">
                            <button
                                type="button"
                                @click="addRecipeIngredient"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-50 hover:bg-cyan-100 text-cyan-700 font-bold text-xs border border-cyan-200 transition-colors shadow-xs"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                <span>Add RM Ingredient</span>
                            </button>

                            <div class="text-[11px] text-slate-500 flex items-center gap-1">
                                <span>Standard Baseline Density:</span>
                                <span class="font-bold text-slate-800">~{{ Number(selectedFgItem?.conversion_factor || 2400) }} kg/cbm</span>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeRecipeModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="recipeStore.isSaving || recipeFormItems.length === 0"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-700 hover:to-blue-700 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-md shadow-cyan-600/20 disabled:opacity-50"
                        >
                            <RefreshCw v-if="recipeStore.isSaving" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ recipeStore.isSaving ? 'Saving...' : 'Save Master Recipe' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal (SoftDelete) -->
        <div
            v-if="isDeleteModalOpen"
            @click.self="isDeleteModalOpen = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 shadow-2xl space-y-4">
                <div class="flex items-start gap-3">
                    <div class="p-3 rounded-xl bg-red-50 text-red-600 border border-red-200">
                        <Trash2 class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Delete Item</h3>
                        <p class="text-xs text-slate-600 mt-1">
                            Are you sure you want to delete <span class="font-bold text-slate-900">{{ deletingItem?.category }} {{ deletingItem?.size ? `(${deletingItem.size})` : '' }}</span>? This will soft-delete the record.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="itemStore.isSaving"
                        @click="handleDelete"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold uppercase tracking-wider disabled:opacity-50 shadow-xs"
                    >
                        {{ itemStore.isSaving ? 'Deleting...' : 'Confirm Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
