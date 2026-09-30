import { defineStore } from 'pinia';
import api from '../services/api';
import type { Item, ItemForm, ItemsResponse, ItemMetaResponse } from '../types/item';

interface ItemState {
    items: Item[];
    rmItems: Item[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    metaOptions: {
        categories: string[];
        sizes: string[];
        types: string[];
        primary_units: string[];
        secondary_units: string[];
    };
    searchQuery: string;
    categoryFilter: string;
    typeFilter: string;
    isLoading: boolean;
    isSaving: boolean;
    error: string | null;
    successMessage: string | null;
}

export const useItemStore = defineStore('item', {
    state: (): ItemState => ({
        items: [],
        rmItems: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 5,
            total: 0,
        },
        metaOptions: {
            categories: ['Aggregate', 'Cement', 'Dust', 'AddMixture', 'Silo Pouch', 'RMC'],
            sizes: ['10mm', '20mm', '30mm', 'opc40', 'M10', 'M15', 'M20', 'M25', 'M30', 'M35', 'M40'],
            types: ['rm', 'fg'],
            primary_units: ['ton', 'kgs', 'cbm', 'pcs', 'bag', 'ltr'],
            secondary_units: ['ton', 'kgs', 'cbm', 'pcs', 'bag', 'ltr'],
        },
        searchQuery: '',
        categoryFilter: 'all',
        typeFilter: 'all',
        isLoading: false,
        isSaving: false,
        error: null,
        successMessage: null,
    }),

    actions: {
        async fetchItems(page: number = 1): Promise<void> {
            this.isLoading = true;
            this.error = null;
            try {
                const params: Record<string, any> = { page, per_page: this.meta.per_page };
                if (this.searchQuery.trim()) {
                    params.search = this.searchQuery.trim();
                }
                if (this.categoryFilter && this.categoryFilter !== 'all') {
                    params.category = this.categoryFilter;
                }
                if (this.typeFilter && this.typeFilter !== 'all') {
                    params.type = this.typeFilter;
                }

                const response = await api.get<ItemsResponse>('/items', { params });
                this.items = response.data.data;
                if (response.data.meta) {
                    this.meta = response.data.meta;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch items.';
            } finally {
                this.isLoading = false;
            }
        },

        async fetchRmItems(): Promise<void> {
            try {
                const response = await api.get<ItemsResponse>('/items', {
                    params: { all: true, type: 'rm' },
                });
                if (response.data?.data) {
                    this.rmItems = response.data.data;
                }
            } catch (err: any) {
                console.error('Failed to fetch raw materials', err);
            }
        },

        registerSize(size: string): void {
            const trimmed = size.trim();
            if (trimmed && !this.metaOptions.sizes.includes(trimmed)) {
                this.metaOptions.sizes.push(trimmed);
            }
        },

        registerUnit(unit: string): void {
            const trimmed = unit.trim();
            if (trimmed) {
                if (!this.metaOptions.primary_units.includes(trimmed)) {
                    this.metaOptions.primary_units.push(trimmed);
                }
                if (!this.metaOptions.secondary_units.includes(trimmed)) {
                    this.metaOptions.secondary_units.push(trimmed);
                }
            }
        },

        async fetchMeta(): Promise<void> {
            try {
                const response = await api.get<ItemMetaResponse>('/items/meta/types');
                if (response.data?.data) {
                    this.metaOptions = response.data.data;
                }
            } catch {
                // Keep default state
            }
        },

        async createItem(form: ItemForm): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            try {
                await api.post('/items', form);
                this.successMessage = 'Item created successfully.';
                await this.fetchItems(1);
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to create item. Check validation errors.';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async updateItem(id: number, form: ItemForm): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            try {
                await api.put(`/items/${id}`, form);
                this.successMessage = 'Item updated successfully.';
                await this.fetchItems(this.meta.current_page);
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to update item.';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async deleteItem(id: number): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            try {
                await api.delete(`/items/${id}`);
                this.successMessage = 'Item deleted successfully.';
                await this.fetchItems(this.meta.current_page);
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to delete item.';
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
