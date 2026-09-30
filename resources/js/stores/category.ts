import { defineStore } from 'pinia';
import api from '../services/api';
import type { Category, CategoryForm, CategoriesResponse } from '../types/category';

interface CategoryState {
    categories: Category[];
    allActiveCategories: Category[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    searchQuery: string;
    statusFilter: string;
    isLoading: boolean;
    isSaving: boolean;
    error: string | null;
    successMessage: string | null;
}

export const useCategoryStore = defineStore('category', {
    state: (): CategoryState => ({
        categories: [],
        allActiveCategories: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 5,
            total: 0,
        },
        searchQuery: '',
        statusFilter: 'all',
        isLoading: false,
        isSaving: false,
        error: null,
        successMessage: null,
    }),

    actions: {
        async fetchCategories(page: number = 1): Promise<void> {
            this.isLoading = true;
            this.error = null;
            try {
                const params: Record<string, any> = { page, per_page: this.meta.per_page };
                if (this.searchQuery.trim()) {
                    params.search = this.searchQuery.trim();
                }
                if (this.statusFilter && this.statusFilter !== 'all') {
                    params.status = this.statusFilter;
                }

                const response = await api.get<CategoriesResponse>('/categories', { params });
                this.categories = response.data.data;
                if (response.data.meta) {
                    this.meta = response.data.meta;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch categories.';
            } finally {
                this.isLoading = false;
            }
        },

        async fetchAllActive(): Promise<void> {
            try {
                const response = await api.get<CategoriesResponse>('/categories', {
                    params: { all: true, status: 'active' },
                });
                if (response.data?.data) {
                    this.allActiveCategories = response.data.data;
                }
            } catch (err: any) {
                console.error('Failed to fetch active categories', err);
            }
        },

        async createCategory(form: CategoryForm): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            try {
                await api.post('/categories', form);
                this.successMessage = 'Category created successfully.';
                await this.fetchCategories(1);
                await this.fetchAllActive();
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to create category.';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async updateCategory(id: number, form: CategoryForm): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            try {
                await api.put(`/categories/${id}`, form);
                this.successMessage = 'Category updated successfully.';
                await this.fetchCategories(this.meta.current_page);
                await this.fetchAllActive();
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to update category.';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async deleteCategory(id: number): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            try {
                await api.delete(`/categories/${id}`);
                this.successMessage = 'Category deleted successfully.';
                await this.fetchCategories(this.meta.current_page);
                await this.fetchAllActive();
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to delete category.';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        clearMessages(): void {
            this.error = null;
            this.successMessage = null;
        },
    },
});
