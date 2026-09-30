import { defineStore } from 'pinia';
import api from '../services/api';
import type { StockSummaryData, StockLedgerData, StockReportItem } from '../types/report';

interface StockReportState {
    summaryData: StockSummaryData | null;
    ledgerData: StockLedgerData | null;
    selectedItemId: number | null;
    activeTab: 'summary' | 'ledger';
    categories: Array<{ id: number; name: string }>;
    itemsList: Array<{
        id: number;
        category: string;
        size: string | null;
        type: 'rm' | 'fg';
        primary_unit: string;
        secondary_unit: string;
        conversion_factor?: number;
        hsn?: string;
    }>;
    fromDateFilter: string;
    toDateFilter: string;
    typeFilter: 'all' | 'rm' | 'fg';
    categoryFilter: string;
    searchQuery: string;
    hideZeroBalance: boolean;
    isLoading: boolean;
    isLedgerLoading: boolean;
    error: string | null;
}

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

export const useStockReportStore = defineStore('stockReport', {
    state: (): StockReportState => ({
        summaryData: null,
        ledgerData: null,
        selectedItemId: null,
        activeTab: 'summary',
        categories: [],
        itemsList: [],
        fromDateFilter: getMonthFirstDateString(),
        toDateFilter: getMonthLastDateString(),
        typeFilter: 'all',
        categoryFilter: 'all',
        searchQuery: '',
        hideZeroBalance: false,
        isLoading: false,
        isLedgerLoading: false,
        error: null,
    }),

    getters: {
        filteredSummaryItems(state): StockReportItem[] {
            if (!state.summaryData?.items) {
                return [];
            }
            let list = state.summaryData.items;

            if (state.hideZeroBalance) {
                list = list.filter((item) => item.closing_primary > 0 || item.inward_primary > 0 || item.consumed_primary > 0 || item.outward_primary > 0);
            }

            return list;
        },

        selectedItemInfo(state) {
            if (!state.selectedItemId) return null;
            return state.itemsList.find((i) => i.id === state.selectedItemId) || null;
        },
    },

    actions: {
        async fetchMeta() {
            try {
                const response = await api.get('/reports/stock/meta');
                if (response.data.status === 'success') {
                    this.categories = response.data.data.categories || [];
                    this.itemsList = response.data.data.items || [];
                }
            } catch (err: any) {
                console.error('Failed to load stock report meta:', err);
            }
        },

        async fetchSummary() {
            this.isLoading = true;
            this.error = null;
            try {
                const params: Record<string, string> = {
                    from_date: this.fromDateFilter,
                    to_date: this.toDateFilter,
                };
                if (this.typeFilter !== 'all') {
                    params.type = this.typeFilter;
                }
                if (this.categoryFilter !== 'all') {
                    params.category = this.categoryFilter;
                }
                if (this.searchQuery.trim()) {
                    params.search = this.searchQuery.trim();
                }

                const response = await api.get('/reports/stock/summary', { params });
                if (response.data.status === 'success') {
                    this.summaryData = response.data.data;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch stock summary report';
            } finally {
                this.isLoading = false;
            }
        },

        async fetchLedger(itemId?: number) {
            const targetId = itemId || this.selectedItemId;
            if (!targetId) return;

            this.selectedItemId = targetId;
            this.isLedgerLoading = true;
            this.error = null;
            try {
                const params = {
                    from_date: this.fromDateFilter,
                    to_date: this.toDateFilter,
                };

                const response = await api.get(`/reports/stock/ledger/${targetId}`, { params });
                if (response.data.status === 'success') {
                    this.ledgerData = response.data.data;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch item stock ledger';
            } finally {
                this.isLedgerLoading = false;
            }
        },

        selectItemForLedger(itemId: number) {
            this.selectedItemId = itemId;
            this.activeTab = 'ledger';
            this.fetchLedger(itemId);
        },

        resetFilters() {
            this.fromDateFilter = getMonthFirstDateString();
            this.toDateFilter = getMonthLastDateString();
            this.typeFilter = 'all';
            this.categoryFilter = 'all';
            this.searchQuery = '';
            this.hideZeroBalance = false;
            this.fetchSummary();
            if (this.selectedItemId) {
                this.fetchLedger();
            }
        },
    },
});
