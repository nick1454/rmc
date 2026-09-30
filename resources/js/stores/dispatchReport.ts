import { defineStore } from 'pinia';
import api from '../services/api';
import type { DispatchReportRow, DispatchReportTotals, DispatchReportSelectedParty } from '../types/report';

interface DispatchReportState {
    records: DispatchReportRow[];
    selectedParty: DispatchReportSelectedParty | null;
    totals: DispatchReportTotals | null;
    customers: Array<{ id: number; name: string; code?: string; phone?: string; city?: string }>;
    agents: Array<{ id: number; name: string; code?: string }>;
    grades: string[];
    items: Array<{ id: number; category: string; size?: string; type: string; primary_unit: string }>;
    partyFilter: string | number;
    agentFilter: string | number;
    gradeFilter: string;
    paymentStatusFilter: 'all' | 'paid' | 'partially_paid' | 'unpaid';
    fromDateFilter: string;
    toDateFilter: string;
    searchQuery: string;
    sortOrder: 'asc' | 'desc';
    isLoading: boolean;
    error: string | null;
}

const getMonthFirstDateString = (): string => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}-01`;
};

const getTodayDateString = (): string => {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

export const useDispatchReportStore = defineStore('dispatchReport', {
    state: (): DispatchReportState => ({
        records: [],
        selectedParty: null,
        totals: null,
        customers: [],
        agents: [],
        grades: [],
        items: [],
        partyFilter: 'all',
        agentFilter: 'all',
        gradeFilter: 'all',
        paymentStatusFilter: 'all',
        fromDateFilter: getMonthFirstDateString(),
        toDateFilter: getTodayDateString(),
        searchQuery: '',
        sortOrder: 'asc',
        isLoading: false,
        error: null,
    }),

    getters: {
        filteredRecords(state): DispatchReportRow[] {
            return state.records;
        },
        hasActiveFilters(state): boolean {
            return (
                state.partyFilter !== 'all' ||
                state.agentFilter !== 'all' ||
                state.gradeFilter !== 'all' ||
                state.paymentStatusFilter !== 'all' ||
                state.searchQuery.trim() !== ''
            );
        },
    },

    actions: {
        async fetchMeta() {
            try {
                const response = await api.get('/reports/dispatch-payment/meta');
                if (response.data.status === 'success') {
                    this.customers = response.data.data.customers || [];
                    this.agents = response.data.data.agents || [];
                    this.grades = response.data.data.grades || [];
                    this.items = response.data.data.items || [];
                }
            } catch (err: any) {
                console.error('Failed to load dispatch report meta:', err);
            }
        },

        async fetchReport() {
            this.isLoading = true;
            this.error = null;
            try {
                const params: Record<string, any> = {
                    sort: this.sortOrder,
                };

                if (this.fromDateFilter) {
                    params.from_date = this.fromDateFilter;
                }
                if (this.toDateFilter) {
                    params.to_date = this.toDateFilter;
                }
                if (this.partyFilter !== 'all') {
                    params.party_id = this.partyFilter;
                }
                if (this.agentFilter !== 'all') {
                    params.agent_id = this.agentFilter;
                }
                if (this.gradeFilter !== 'all') {
                    params.grade = this.gradeFilter;
                }
                if (this.paymentStatusFilter !== 'all') {
                    params.payment_status = this.paymentStatusFilter;
                }
                if (this.searchQuery.trim()) {
                    params.search = this.searchQuery.trim();
                }

                const response = await api.get('/reports/dispatch-payment', { params });
                if (response.data.status === 'success') {
                    this.records = response.data.data.records || [];
                    this.selectedParty = response.data.data.selected_party || null;
                    this.totals = response.data.data.totals || null;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch dispatch payment report';
            } finally {
                this.isLoading = false;
            }
        },

        setDatePreset(preset: 'today' | 'this_week' | 'this_month' | 'last_month' | 'all') {
            const today = new Date();
            const year = today.getFullYear();
            const month = today.getMonth();

            const formatDate = (d: Date) => {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            };

            if (preset === 'today') {
                this.fromDateFilter = formatDate(today);
                this.toDateFilter = formatDate(today);
            } else if (preset === 'this_week') {
                const dayOfWeek = today.getDay(); // 0 is Sunday
                const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Monday
                const monday = new Date(today.setDate(diff));
                this.fromDateFilter = formatDate(monday);
                this.toDateFilter = getTodayDateString();
            } else if (preset === 'this_month') {
                this.fromDateFilter = getMonthFirstDateString();
                this.toDateFilter = getTodayDateString();
            } else if (preset === 'last_month') {
                const firstDayLastMonth = new Date(year, month - 1, 1);
                const lastDayLastMonth = new Date(year, month, 0);
                this.fromDateFilter = formatDate(firstDayLastMonth);
                this.toDateFilter = formatDate(lastDayLastMonth);
            } else if (preset === 'all') {
                this.fromDateFilter = '';
                this.toDateFilter = '';
            }

            this.fetchReport();
        },

        resetFilters() {
            this.partyFilter = 'all';
            this.agentFilter = 'all';
            this.gradeFilter = 'all';
            this.paymentStatusFilter = 'all';
            this.fromDateFilter = getMonthFirstDateString();
            this.toDateFilter = getTodayDateString();
            this.searchQuery = '';
            this.sortOrder = 'asc';
            this.fetchReport();
        },
    },
});
