import { defineStore } from 'pinia';
import api from '../services/api';
import type {
    FinancialReportMeta,
    CustomerOutstandingResponse,
    SupplierPayablesResponse,
    CashBankBookResponse,
    PartyLedgerResponse,
} from '../types/financialReport';

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

export const useFinancialReportStore = defineStore('financialReport', {
    state: () => ({
        meta: null as FinancialReportMeta | null,
        metaLoading: false,

        // 1. Customer Outstanding & Ageing
        customerOutstanding: null as CustomerOutstandingResponse | null,
        customerOutstandingLoading: false,
        customerOutstandingFilters: {
            as_of_date: getTodayDateString(),
            customer_id: 'all',
            agent_id: 'all',
            ageing_bracket: 'all',
            search: '',
        },

        // 2. Supplier Payables
        supplierPayables: null as SupplierPayablesResponse | null,
        supplierPayablesLoading: false,
        supplierPayablesFilters: {
            as_of_date: getTodayDateString(),
            supplier_id: 'all',
            ageing_bracket: 'all',
            search: '',
        },

        // 3. Cash & Bank Book
        cashBankBook: null as CashBankBookResponse | null,
        cashBankBookLoading: false,
        cashBankBookFilters: {
            from_date: getMonthFirstDateString(),
            to_date: getTodayDateString(),
            payment_mode: 'all',
            bank_name: 'all',
            type: 'all',
            search: '',
        },

        // 4. Party Ledger Statement
        partyLedger: null as PartyLedgerResponse | null,
        partyLedgerLoading: false,
        partyLedgerFilters: {
            party_id: '' as string | number,
            from_date: getMonthFirstDateString(),
            to_date: getTodayDateString(),
        },

        error: null as string | null,
    }),

    actions: {
        async fetchMeta() {
            this.metaLoading = true;
            try {
                const response = await api.get('/reports/financial/meta');
                if (response.data.status === 'success') {
                    this.meta = response.data.data;
                }
            } catch (err: any) {
                console.error('Failed to load financial report meta:', err);
            } finally {
                this.metaLoading = false;
            }
        },

        // 1. Customer Outstanding
        async fetchCustomerOutstanding() {
            this.customerOutstandingLoading = true;
            this.error = null;
            try {
                const params: Record<string, any> = {};
                if (this.customerOutstandingFilters.as_of_date) {
                    params.as_of_date = this.customerOutstandingFilters.as_of_date;
                }
                if (this.customerOutstandingFilters.customer_id !== 'all') {
                    params.customer_id = this.customerOutstandingFilters.customer_id;
                }
                if (this.customerOutstandingFilters.agent_id !== 'all') {
                    params.agent_id = this.customerOutstandingFilters.agent_id;
                }
                if (this.customerOutstandingFilters.ageing_bracket !== 'all') {
                    params.ageing_bracket = this.customerOutstandingFilters.ageing_bracket;
                }
                if (this.customerOutstandingFilters.search.trim()) {
                    params.search = this.customerOutstandingFilters.search.trim();
                }

                const response = await api.get('/reports/financial/customer-outstanding', { params });
                if (response.data.status === 'success') {
                    this.customerOutstanding = response.data.data;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch customer outstanding report';
            } finally {
                this.customerOutstandingLoading = false;
            }
        },

        resetCustomerOutstandingFilters() {
            this.customerOutstandingFilters = {
                as_of_date: getTodayDateString(),
                customer_id: 'all',
                agent_id: 'all',
                ageing_bracket: 'all',
                search: '',
            };
            this.fetchCustomerOutstanding();
        },

        // 2. Supplier Payables
        async fetchSupplierPayables() {
            this.supplierPayablesLoading = true;
            this.error = null;
            try {
                const params: Record<string, any> = {};
                if (this.supplierPayablesFilters.as_of_date) {
                    params.as_of_date = this.supplierPayablesFilters.as_of_date;
                }
                if (this.supplierPayablesFilters.supplier_id !== 'all') {
                    params.supplier_id = this.supplierPayablesFilters.supplier_id;
                }
                if (this.supplierPayablesFilters.ageing_bracket !== 'all') {
                    params.ageing_bracket = this.supplierPayablesFilters.ageing_bracket;
                }
                if (this.supplierPayablesFilters.search.trim()) {
                    params.search = this.supplierPayablesFilters.search.trim();
                }

                const response = await api.get('/reports/financial/supplier-payables', { params });
                if (response.data.status === 'success') {
                    this.supplierPayables = response.data.data;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch supplier payables report';
            } finally {
                this.supplierPayablesLoading = false;
            }
        },

        resetSupplierPayablesFilters() {
            this.supplierPayablesFilters = {
                as_of_date: getTodayDateString(),
                supplier_id: 'all',
                ageing_bracket: 'all',
                search: '',
            };
            this.fetchSupplierPayables();
        },

        // 3. Cash & Bank Book
        async fetchCashBankBook() {
            this.cashBankBookLoading = true;
            this.error = null;
            try {
                const params: Record<string, any> = {};
                if (this.cashBankBookFilters.from_date) {
                    params.from_date = this.cashBankBookFilters.from_date;
                }
                if (this.cashBankBookFilters.to_date) {
                    params.to_date = this.cashBankBookFilters.to_date;
                }
                if (this.cashBankBookFilters.payment_mode !== 'all') {
                    params.payment_mode = this.cashBankBookFilters.payment_mode;
                }
                if (this.cashBankBookFilters.bank_name !== 'all') {
                    params.bank_name = this.cashBankBookFilters.bank_name;
                }
                if (this.cashBankBookFilters.type !== 'all') {
                    params.type = this.cashBankBookFilters.type;
                }
                if (this.cashBankBookFilters.search.trim()) {
                    params.search = this.cashBankBookFilters.search.trim();
                }

                const response = await api.get('/reports/financial/cash-bank-book', { params });
                if (response.data.status === 'success') {
                    this.cashBankBook = response.data.data;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch cash & bank book';
            } finally {
                this.cashBankBookLoading = false;
            }
        },

        resetCashBankBookFilters() {
            this.cashBankBookFilters = {
                from_date: getMonthFirstDateString(),
                to_date: getTodayDateString(),
                payment_mode: 'all',
                bank_name: 'all',
                type: 'all',
                search: '',
            };
            this.fetchCashBankBook();
        },

        // 4. Party Ledger Statement
        async fetchPartyLedger() {
            if (!this.partyLedgerFilters.party_id) {
                this.partyLedger = null;
                return;
            }
            this.partyLedgerLoading = true;
            this.error = null;
            try {
                const params: Record<string, any> = {
                    party_id: this.partyLedgerFilters.party_id,
                };
                if (this.partyLedgerFilters.from_date) {
                    params.from_date = this.partyLedgerFilters.from_date;
                }
                if (this.partyLedgerFilters.to_date) {
                    params.to_date = this.partyLedgerFilters.to_date;
                }

                const response = await api.get('/reports/financial/party-ledger', { params });
                if (response.data.status === 'success') {
                    this.partyLedger = response.data.data;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch party ledger statement';
            } finally {
                this.partyLedgerLoading = false;
            }
        },

        resetPartyLedgerFilters() {
            this.partyLedgerFilters.from_date = getMonthFirstDateString();
            this.partyLedgerFilters.to_date = getTodayDateString();
            if (this.partyLedgerFilters.party_id) {
                this.fetchPartyLedger();
            }
        },
    },
});
