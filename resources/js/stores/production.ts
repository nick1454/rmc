import { defineStore } from 'pinia';
import api from '../services/api';
import type {
    Production,
    ProductionForm,
    ProductionMeta,
    ProductionPaginationMeta,
    RmStockItem,
    StockShortage,
    StockValidationResult,
} from '../types/production';
import type { Item } from '../types/item';

interface ProductionState {
    productions: Production[];
    meta: ProductionPaginationMeta;
    fgItems: Item[];
    rmStock: RmStockItem[];
    nextProductionNo: string;
    nextBatchNo: string;
    searchQuery: string;
    fgFilter: string | number;
    fromDateFilter: string;
    toDateFilter: string;
    isLoading: boolean;
    isSaving: boolean;
    isValidatingStock: boolean;
    error: string | null;
    validationErrors: string[];
    shortages: StockShortage[];
    successMessage: string | null;
}

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

export const useProductionStore = defineStore('production', {
    state: (): ProductionState => ({
        productions: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 5,
            total: 0,
        },
        fgItems: [],
        rmStock: [],
        nextProductionNo: 'PRD-0001',
        nextBatchNo: 'BAT-' + getTodayString().replace(/-/g, '') + '-001',
        searchQuery: '',
        fgFilter: 'all',
        fromDateFilter: getMonthFirstDateString(),
        toDateFilter: getMonthLastDateString(),
        isLoading: false,
        isSaving: false,
        isValidatingStock: false,
        error: null,
        validationErrors: [],
        shortages: [],
        successMessage: null,
    }),

    actions: {
        async fetchProductions(page: number = 1): Promise<void> {
            this.isLoading = true;
            this.error = null;
            try {
                const params: Record<string, any> = {
                    page,
                    per_page: this.meta.per_page,
                };

                if (this.searchQuery.trim()) {
                    params.search = this.searchQuery.trim();
                }

                if (this.fgFilter !== 'all') {
                    params.fg_item_id = this.fgFilter;
                }

                if (this.fromDateFilter) {
                    params.from_date = this.fromDateFilter;
                }

                if (this.toDateFilter) {
                    params.to_date = this.toDateFilter;
                }

                const response = await api.get('/productions', { params });
                this.productions = response.data.data;
                if (response.data.meta) {
                    this.meta = response.data.meta;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch production entries';
            } finally {
                this.isLoading = false;
            }
        },

        async fetchMeta(date?: string): Promise<void> {
            try {
                const params = date ? { date } : {};
                const response = await api.get('/productions/meta', { params });
                const data: ProductionMeta = response.data.data;
                this.fgItems = data.fg_items || [];
                this.rmStock = data.rm_stock || [];
                this.nextProductionNo = data.next_production_no || 'PRD-0001';
                this.nextBatchNo = data.next_batch_no || ('BAT-' + (date || getTodayString()).replace(/-/g, '') + '-001');
            } catch (err: any) {
                console.error('Failed to load production metadata', err);
            }
        },

        async createProduction(form: ProductionForm): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            this.validationErrors = [];
            this.shortages = [];
            this.successMessage = null;

            try {
                const response = await api.post('/productions', form);
                this.successMessage = response.data.message || 'Production batch saved successfully';
                await this.fetchProductions(1);
                await this.fetchMeta();
                return true;
            } catch (err: any) {
                const resData = err.response?.data;
                this.error = resData?.message || 'Failed to record production batch';

                if (resData?.shortages) {
                    this.shortages = resData.shortages;
                }

                if (resData?.errors?.stock) {
                    this.validationErrors = resData.errors.stock;
                } else if (resData?.errors) {
                    this.validationErrors = Object.values(resData.errors).flat() as string[];
                }

                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async deleteProduction(id: number): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            this.validationErrors = [];
            this.successMessage = null;
            try {
                const response = await api.delete(`/productions/${id}`);
                this.successMessage = response.data.message || 'Production batch deleted successfully';
                await this.fetchProductions(this.meta.current_page);
                await this.fetchMeta();
                return true;
            } catch (err: any) {
                const resData = err.response?.data;
                this.error = resData?.message || 'Failed to delete production batch';
                if (resData?.dispatches && Array.isArray(resData.dispatches)) {
                    this.validationErrors = resData.dispatches;
                }
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async validateStock(recipeItems: any[]): Promise<StockValidationResult | null> {
            this.isValidatingStock = true;
            try {
                const response = await api.post('/productions/validate-stock', {
                    recipe_items: recipeItems,
                });
                return response.data.data;
            } catch (err: any) {
                console.error('Stock validation check failed', err);
                return null;
            } finally {
                this.isValidatingStock = false;
            }
        },

        clearMessages() {
            this.error = null;
            this.validationErrors = [];
            this.shortages = [];
            this.successMessage = null;
        },
    },
});
