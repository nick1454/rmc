import { defineStore } from 'pinia';
import api from '../services/api';
import type {
    Party,
    PartyForm,
    PartyMetaResponse,
    PartyPaginationMeta,
} from '../types/party';

interface PartyState {
    parties: Party[];
    meta: PartyPaginationMeta;
    suppliers: Party[];
    customers: Party[];
    agents: Party[];
    nextSupplierCode: string;
    nextCustomerCode: string;
    nextAgentCode: string;
    searchQuery: string;
    typeFilter: string;
    statusFilter: string;
    isLoading: boolean;
    isSaving: boolean;
    error: string | null;
    successMessage: string | null;
}

export const usePartyStore = defineStore('party', {
    state: (): PartyState => ({
        parties: [],
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 5,
            total: 0,
        },
        suppliers: [],
        customers: [],
        agents: [],
        nextSupplierCode: 'SUP-0001',
        nextCustomerCode: 'CUS-0001',
        nextAgentCode: 'AGT-0001',
        searchQuery: '',
        typeFilter: 'all',
        statusFilter: 'all',
        isLoading: false,
        isSaving: false,
        error: null,
        successMessage: null,
    }),

    actions: {
        async fetchParties(page: number = 1): Promise<void> {
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

                if (this.typeFilter !== 'all') {
                    params.type = this.typeFilter;
                }

                if (this.statusFilter !== 'all') {
                    params.status = this.statusFilter;
                }

                const response = await api.get('/parties', { params });
                this.parties = response.data.data;
                if (response.data.meta) {
                    this.meta = response.data.meta;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch parties list';
            } finally {
                this.isLoading = false;
            }
        },

        async fetchMeta(): Promise<void> {
            try {
                const response = await api.get('/parties/meta');
                const data: PartyMetaResponse = response.data.data;
                this.suppliers = data.suppliers || [];
                this.customers = data.customers || [];
                this.agents = data.agents || [];
                this.nextSupplierCode = data.next_supplier_code || 'SUP-0001';
                this.nextCustomerCode = data.next_customer_code || 'CUS-0001';
                this.nextAgentCode = data.next_agent_code || 'AGT-0001';
            } catch (err: any) {
                console.error('Failed to load party metadata', err);
            }
        },

        async createParty(form: PartyForm): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            this.successMessage = null;
            try {
                const response = await api.post('/parties', form);
                this.successMessage = response.data.message || 'Party created successfully';
                await this.fetchParties(1);
                await this.fetchMeta();
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to create party';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async updateParty(id: number, form: PartyForm): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            this.successMessage = null;
            try {
                const response = await api.put(`/parties/${id}`, form);
                this.successMessage = response.data.message || 'Party updated successfully';
                await this.fetchParties(this.meta.current_page);
                await this.fetchMeta();
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to update party';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        async deleteParty(id: number): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            this.successMessage = null;
            try {
                const response = await api.delete(`/parties/${id}`);
                this.successMessage = response.data.message || 'Party deleted successfully';
                await this.fetchParties(this.meta.current_page);
                await this.fetchMeta();
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to delete party';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        clearMessages() {
            this.error = null;
            this.successMessage = null;
        },
    },
});
