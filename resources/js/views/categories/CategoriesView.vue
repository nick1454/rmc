<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useCategoryStore } from '../../stores/category';
import { useEscapeKey } from '../../composables/useEscapeKey';
import type { Category, CategoryForm } from '../../types/category';
import AppLayout from '../../components/layout/AppLayout.vue';
import {
    Plus,
    Search,
    Edit3,
    Trash2,
    Tag,
    AlertCircle,
    CheckCircle2,
    X,
    RefreshCw,
    Boxes
} from 'lucide-vue-next';

const categoryStore = useCategoryStore();

// Modal state
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingCategory = ref<Category | null>(null);
const deletingCategory = ref<Category | null>(null);

const initialForm: CategoryForm = {
    name: '',
    status: 'active',
};

const form = ref<CategoryForm>({ ...initialForm });

onMounted(async () => {
    await categoryStore.fetchCategories();
});

const handleSearch = () => {
    categoryStore.fetchCategories(1);
};

const handleFilterChange = () => {
    categoryStore.fetchCategories(1);
};

const openCreateModal = () => {
    editingCategory.value = null;
    form.value = { ...initialForm };
    isModalOpen.value = true;
};

const openEditModal = (category: Category) => {
    editingCategory.value = category;
    form.value = {
        name: category.name,
        status: category.status || 'active',
    };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingCategory.value = null;
};

const submitForm = async () => {
    let success = false;
    if (editingCategory.value) {
        success = await categoryStore.updateCategory(editingCategory.value.id, form.value);
    } else {
        success = await categoryStore.createCategory(form.value);
    }

    if (success) {
        closeModal();
    }
};

const toggleStatus = async (category: Category) => {
    const nextStatus = category.status === 'active' ? 'inactive' : 'active';
    await categoryStore.updateCategory(category.id, {
        name: category.name,
        status: nextStatus,
    });
};

const confirmDelete = (category: Category) => {
    deletingCategory.value = category;
    isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
    if (deletingCategory.value) {
        await categoryStore.deleteCategory(deletingCategory.value.id);
        isDeleteModalOpen.value = false;
        deletingCategory.value = null;
    }
};

const closeAllModals = () => {
    if (isDeleteModalOpen.value) {
        isDeleteModalOpen.value = false;
        deletingCategory.value = null;
    } else if (isModalOpen.value) {
        closeModal();
    }
};

useEscapeKey(closeAllModals);
</script>

<template>
    <AppLayout title="Category Master" subtitle="Manage item categories, status, and classification">
        <div class="space-y-6">
            <!-- Toast Feedback Notifications -->
            <div
                v-if="categoryStore.successMessage"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-center justify-between shadow-xs"
            >
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="w-5 h-5 flex-shrink-0" />
                    <span>{{ categoryStore.successMessage }}</span>
                </div>
                <button @click="categoryStore.clearMessages" class="text-emerald-700 hover:text-emerald-900">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div
                v-if="categoryStore.error"
                class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center justify-between shadow-xs"
            >
                <div class="flex items-center gap-2">
                    <AlertCircle class="w-5 h-5 flex-shrink-0" />
                    <span>{{ categoryStore.error }}</span>
                </div>
                <button @click="categoryStore.clearMessages" class="text-red-700 hover:text-red-900">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Controls: Search, Status Filter & Add Button -->
            <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                <!-- Search Box -->
                <div class="relative flex-1 max-w-md">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <Search class="w-4 h-4" />
                    </div>
                    <input
                        v-model="categoryStore.searchQuery"
                        @keyup.enter="handleSearch"
                        type="text"
                        placeholder="Search categories by name..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 placeholder-slate-400 text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 focus:bg-white transition-all"
                    />
                </div>

                <!-- Action Controls -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Status Filter -->
                    <select
                        v-model="categoryStore.statusFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <button
                        type="button"
                        @click="categoryStore.fetchCategories(categoryStore.meta.current_page)"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors"
                        title="Refresh List"
                    >
                        <RefreshCw :class="['w-4 h-4', categoryStore.isLoading ? 'animate-spin' : '']" />
                    </button>

                    <button
                        type="button"
                        @click="openCreateModal"
                        class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-1.5 shadow-md shadow-amber-500/20 transition-all active:scale-95"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Add Category</span>
                    </button>
                </div>
            </div>

            <!-- Categories Data Table & Mobile Cards -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
                <!-- Mobile Card List (< md) -->
                <div class="block md:hidden divide-y divide-slate-100">
                    <div v-if="categoryStore.isLoading && categoryStore.categories.length === 0" class="p-8 text-center text-slate-400">
                        <div class="inline-flex items-center gap-2">
                            <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                            <span class="text-xs">Loading categories from database...</span>
                        </div>
                    </div>

                    <div v-else-if="categoryStore.categories.length === 0" class="p-8 text-center text-slate-500 space-y-2">
                        <Tag class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="font-semibold text-sm text-slate-700">No Categories Found</div>
                        <p class="text-xs text-slate-400">Create a category to begin classification.</p>
                    </div>

                    <div
                        v-for="cat in categoryStore.categories"
                        :key="cat.id"
                        class="p-4 space-y-3 hover:bg-slate-50/80 transition-colors"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <div class="p-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-700">
                                    <Tag class="w-4 h-4" />
                                </div>
                                <div>
                                    <div class="font-bold text-sm text-slate-900">{{ cat.name }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">By {{ cat.creator?.name || 'System' }}</div>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="toggleStatus(cat)"
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border transition-colors cursor-pointer',
                                    cat.status === 'active'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                                        : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'
                                ]"
                                title="Click to toggle status"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="cat.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                <span class="capitalize">{{ cat.status }}</span>
                            </button>
                        </div>

                        <!-- Card Footer: Items count and Actions -->
                        <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                            <div class="inline-flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 text-xs font-mono">
                                <Boxes class="w-3.5 h-3.5 text-slate-400" />
                                <span>{{ cat.items_count ?? 0 }} Items</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="openEditModal(cat)"
                                    class="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold flex items-center gap-1.5 transition-colors border border-amber-200"
                                >
                                    <Edit3 class="w-3.5 h-3.5" />
                                    <span>Edit</span>
                                </button>
                                <button
                                    type="button"
                                    @click="confirmDelete(cat)"
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
                                <th class="py-3.5 px-4">Category Name</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-4">Items Associated</th>
                                <th class="py-3.5 px-4">Created By</th>
                                <th class="py-3.5 px-4">Updated By</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="categoryStore.isLoading && categoryStore.categories.length === 0">
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <div class="inline-flex items-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading categories from database...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="categoryStore.categories.length === 0">
                                <td colspan="6" class="py-12 text-center text-slate-500">
                                    <div class="space-y-2">
                                        <Tag class="w-8 h-8 text-slate-400 mx-auto" />
                                        <div class="font-semibold text-slate-700">No Categories Found</div>
                                        <p class="text-[11px] text-slate-400">Create a category to begin classification.</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="cat in categoryStore.categories"
                                :key="cat.id"
                                class="hover:bg-slate-50/80 transition-colors"
                            >
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="p-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-700">
                                            <Tag class="w-4 h-4" />
                                        </div>
                                        <span class="font-bold text-slate-900 text-sm">
                                            {{ cat.name }}
                                        </span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <button
                                        type="button"
                                        @click="toggleStatus(cat)"
                                        :class="[
                                            'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors cursor-pointer',
                                            cat.status === 'active'
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100'
                                                : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'
                                        ]"
                                        title="Click to toggle status"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="cat.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                        <span class="capitalize">{{ cat.status }}</span>
                                    </button>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="inline-flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200 text-slate-700 text-xs font-mono">
                                        <Boxes class="w-3.5 h-3.5 text-slate-400" />
                                        <span>{{ cat.items_count ?? 0 }} Items</span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 text-[11px] text-slate-500">
                                    <div class="text-slate-700 font-medium">{{ cat.creator?.name || 'System' }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ cat.created_at ? new Date(cat.created_at).toLocaleDateString() : '—' }}</div>
                                </td>

                                <td class="py-3.5 px-4 text-[11px] text-slate-500">
                                    <div class="text-slate-700 font-medium">{{ cat.updater?.name || '—' }}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">{{ cat.updated_at ? new Date(cat.updated_at).toLocaleDateString() : '—' }}</div>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditModal(cat)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-amber-700 hover:bg-amber-50 transition-colors"
                                            title="Edit Category"
                                        >
                                            <Edit3 class="w-3.5 h-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(cat)"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                            title="Delete Category"
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
                <div v-if="categoryStore.meta.total > 0" class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600">
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (categoryStore.meta.current_page - 1) * categoryStore.meta.per_page + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(categoryStore.meta.current_page * categoryStore.meta.per_page, categoryStore.meta.total) }}</span>
                        of <span class="font-bold text-slate-900">{{ categoryStore.meta.total }}</span> categories
                    </div>

                    <div class="flex items-center gap-1.5" v-if="categoryStore.meta.last_page > 1">
                        <button
                            type="button"
                            :disabled="categoryStore.meta.current_page === 1"
                            @click="categoryStore.fetchCategories(categoryStore.meta.current_page - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in categoryStore.meta.last_page"
                            :key="p"
                            @click="categoryStore.fetchCategories(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all',
                                categoryStore.meta.current_page === p
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            {{ p }}
                        </button>
                        <button
                            type="button"
                            :disabled="categoryStore.meta.current_page === categoryStore.meta.last_page"
                            @click="categoryStore.fetchCategories(categoryStore.meta.current_page + 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Category Modal -->
        <div
            v-if="isModalOpen"
            @click.self="closeModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-amber-50 text-amber-600 border border-amber-200">
                            <Tag class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                {{ editingCategory ? 'Edit Category' : 'Add Category' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Configure category classification</p>
                        </div>
                    </div>
                    <button @click="closeModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form @submit.prevent="submitForm" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Category Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Aggregate, Cement, Chemical, Silica..."
                            autofocus
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status *</label>
                        <select
                            v-model="form.status"
                            required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none focus:bg-white"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
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
                            :disabled="categoryStore.isSaving || !form.name.trim()"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-md shadow-amber-500/20 disabled:opacity-50"
                        >
                            <RefreshCw v-if="categoryStore.isSaving" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ categoryStore.isSaving ? 'Saving...' : (editingCategory ? 'Update Category' : 'Create Category') }}</span>
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
                        <h3 class="text-base font-bold text-slate-900">Delete Category</h3>
                        <p class="text-xs text-slate-600 mt-1">
                            Are you sure you want to delete <span class="font-bold text-slate-900">{{ deletingCategory?.name }}</span>? This will soft-delete the record and set deleted_by.
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
                        :disabled="categoryStore.isSaving"
                        @click="handleDelete"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold uppercase tracking-wider disabled:opacity-50 shadow-xs"
                    >
                        {{ categoryStore.isSaving ? 'Deleting...' : 'Confirm Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
