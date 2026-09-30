<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { usePartyStore } from '../../stores/party';
import type { Party, PartyForm, PartyType } from '../../types/party';
import AppLayout from '../../components/layout/AppLayout.vue';
import { useEscapeKey } from '../../composables/useEscapeKey';
import {
    Plus,
    Search,
    Edit3,
    Trash2,
    Eye,
    Building2,
    Truck,
    UserCheck,
    AlertCircle,
    CheckCircle2,
    X,
    RefreshCw,
    Phone,
    Mail,
    MapPin,
    FileText,
    Layers,
    Shield,
    Hash,
    Briefcase
} from 'lucide-vue-next';

const partyStore = usePartyStore();

// Modal States
const isFormModalOpen = ref(false);
const isViewModalOpen = ref(false);
const isDeleteModalOpen = ref(false);

const editingParty = ref<Party | null>(null);
const viewingParty = ref<Party | null>(null);
const deletingParty = ref<Party | null>(null);

const initialForm = (): PartyForm => ({
    name: '',
    type: 'supplier',
    code: partyStore.nextSupplierCode || '',
    contact_person: '',
    phone: '',
    email: '',
    gstin: '',
    pan_no: '',
    address: '',
    city: 'Pune',
    state: 'Maharashtra',
    pincode: '',
    status: 'active',
    remarks: '',
});

const form = ref<PartyForm>(initialForm());

onMounted(async () => {
    await partyStore.fetchMeta();
    await partyStore.fetchParties(1);
});

const handleSearch = () => {
    partyStore.fetchParties(1);
};

const handleFilterChange = () => {
    partyStore.fetchParties(1);
};

const setTypeTab = (type: string) => {
    partyStore.typeFilter = type;
    partyStore.fetchParties(1);
};

// When changing party type in the form, auto-suggest appropriate code
const onTypeChange = (newType: PartyType) => {
    form.value.type = newType;
    if (!editingParty.value) {
        if (newType === 'supplier') {
            form.value.code = partyStore.nextSupplierCode;
        } else if (newType === 'customer') {
            form.value.code = partyStore.nextCustomerCode;
        } else if (newType === 'agent') {
            form.value.code = partyStore.nextAgentCode;
        } else {
            form.value.code = 'PTY-' + (partyStore.parties.length + 1).toString().padStart(4, '0');
        }
    }
};

const openCreateModal = () => {
    partyStore.clearMessages();
    editingParty.value = null;
    let initialType: PartyType = 'supplier';
    let initialCode = partyStore.nextSupplierCode;

    if (partyStore.typeFilter === 'customer') {
        initialType = 'customer';
        initialCode = partyStore.nextCustomerCode;
    } else if (partyStore.typeFilter === 'agent') {
        initialType = 'agent';
        initialCode = partyStore.nextAgentCode;
    } else if (partyStore.typeFilter === 'both') {
        initialType = 'both';
        initialCode = 'PTY-' + (partyStore.parties.length + 1).toString().padStart(4, '0');
    }

    form.value = {
        ...initialForm(),
        type: initialType,
        code: initialCode,
    };
    isFormModalOpen.value = true;
};

const openEditModal = (party: Party) => {
    partyStore.clearMessages();
    editingParty.value = party;
    form.value = {
        name: party.name,
        type: party.type,
        code: party.code,
        contact_person: party.contact_person || '',
        phone: party.phone || '',
        email: party.email || '',
        gstin: party.gstin || '',
        pan_no: party.pan_no || '',
        address: party.address || '',
        city: party.city || '',
        state: party.state || '',
        pincode: party.pincode || '',
        status: party.status,
        remarks: party.remarks || '',
    };
    isFormModalOpen.value = true;
};

const openViewModal = (party: Party) => {
    viewingParty.value = party;
    isViewModalOpen.value = true;
};

const closeFormModal = () => {
    isFormModalOpen.value = false;
    editingParty.value = null;
    partyStore.clearMessages();
};

const closeViewModal = () => {
    isViewModalOpen.value = false;
    viewingParty.value = null;
};

const confirmDelete = (party: Party) => {
    deletingParty.value = party;
    isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
    if (deletingParty.value) {
        await partyStore.deleteParty(deletingParty.value.id);
        isDeleteModalOpen.value = false;
        deletingParty.value = null;
    }
};

const submitForm = async () => {
    let success = false;
    if (editingParty.value) {
        success = await partyStore.updateParty(editingParty.value.id, form.value);
    } else {
        success = await partyStore.createParty(form.value);
    }

    if (success) {
        closeFormModal();
    }
};

const closeAllModals = () => {
    if (isDeleteModalOpen.value) {
        isDeleteModalOpen.value = false;
        deletingParty.value = null;
    } else if (isViewModalOpen.value) {
        closeViewModal();
    } else if (isFormModalOpen.value) {
        closeFormModal();
    }
};

useEscapeKey(closeAllModals);

const getTypeBadgeClass = (type: PartyType) => {
    switch (type) {
        case 'supplier':
            return 'bg-amber-50 border-amber-200 text-amber-800';
        case 'customer':
            return 'bg-emerald-50 border-emerald-200 text-emerald-800';
        case 'agent':
            return 'bg-sky-50 border-sky-200 text-sky-800';
        case 'both':
            return 'bg-purple-50 border-purple-200 text-purple-800';
        default:
            return 'bg-slate-100 border-slate-200 text-slate-700';
    }
};

const getTypeLabel = (type: PartyType) => {
    switch (type) {
        case 'supplier':
            return 'Supplier / Vendor';
        case 'customer':
            return 'Customer';
        case 'agent':
            return 'Agent / Broker';
        case 'both':
            return 'Dual Role (Both)';
        default:
            return type;
    }
};
</script>

<template>
    <AppLayout title="Customer / Vendor Master" subtitle="Manage Suppliers, Contractors & Customers in Unified Ledger">
        <div class="space-y-6">
            <!-- Feedback Alerts -->
            <div
                v-if="partyStore.successMessage"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="w-5 h-5 flex-shrink-0 text-emerald-600" />
                    <span class="text-xs font-semibold">{{ partyStore.successMessage }}</span>
                </div>
                <button @click="partyStore.clearMessages" class="text-emerald-600 hover:text-emerald-800">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <div
                v-if="partyStore.error"
                class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 flex items-center justify-between shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <AlertCircle class="w-5 h-5 flex-shrink-0 text-red-600" />
                    <span class="text-xs font-semibold">{{ partyStore.error }}</span>
                </div>
                <button @click="partyStore.clearMessages" class="text-red-600 hover:text-red-800">
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Segmented Filter Tabs for Quick Classification -->
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-2">
                <button
                    type="button"
                    @click="setTypeTab('all')"
                    :class="[
                        'px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 transition-all',
                        partyStore.typeFilter === 'all'
                            ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
                            : 'bg-white hover:bg-slate-50 text-slate-600 border border-slate-200'
                    ]"
                >
                    <Building2 class="w-4 h-4" />
                    <span>All Parties</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-700 font-bold">{{ partyStore.meta.total }}</span>
                </button>

                <button
                    type="button"
                    @click="setTypeTab('supplier')"
                    :class="[
                        'px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 transition-all',
                        partyStore.typeFilter === 'supplier'
                            ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
                            : 'bg-white hover:bg-slate-50 text-slate-600 border border-slate-200'
                    ]"
                >
                    <Truck class="w-4 h-4 text-amber-600" />
                    <span>Suppliers / Vendors (Inwards)</span>
                </button>

                <button
                    type="button"
                    @click="setTypeTab('customer')"
                    :class="[
                        'px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 transition-all',
                        partyStore.typeFilter === 'customer'
                            ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
                            : 'bg-white hover:bg-slate-50 text-slate-600 border border-slate-200'
                    ]"
                >
                    <UserCheck class="w-4 h-4 text-emerald-600" />
                    <span>Customers / Clients</span>
                </button>

                <button
                    type="button"
                    @click="setTypeTab('agent')"
                    :class="[
                        'px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 transition-all',
                        partyStore.typeFilter === 'agent'
                            ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
                            : 'bg-white hover:bg-slate-50 text-slate-600 border border-slate-200'
                    ]"
                >
                    <Briefcase class="w-4 h-4 text-sky-600" />
                    <span>Agents / Brokers</span>
                </button>

                <button
                    type="button"
                    @click="setTypeTab('both')"
                    :class="[
                        'px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 transition-all',
                        partyStore.typeFilter === 'both'
                            ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
                            : 'bg-white hover:bg-slate-50 text-slate-600 border border-slate-200'
                    ]"
                >
                    <Layers class="w-4 h-4 text-purple-600" />
                    <span>Dual Role (Both)</span>
                </button>
            </div>

            <!-- Action & Search Bar -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row gap-4 items-stretch sm:items-center justify-between">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <!-- Search Input -->
                    <div class="relative min-w-[240px] flex-1 max-w-md">
                        <Search class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="partyStore.searchQuery"
                            type="text"
                            placeholder="Search name, code, contact, GSTIN, phone..."
                            @input="handleSearch"
                            class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 text-xs placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                        />
                    </div>

                    <!-- Type / Role Filter -->
                    <select
                        v-model="partyStore.typeFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Types (Customer, Supplier & Agent)</option>
                        <option value="supplier">Suppliers / Vendors Only</option>
                        <option value="customer">Customers Only</option>
                        <option value="agent">Agents / Brokers Only</option>
                        <option value="both">Dual Role (Both)</option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="partyStore.statusFilter"
                        @change="handleFilterChange"
                        class="px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white"
                    >
                        <option value="all">All Status</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>

                    <button
                        type="button"
                        @click="partyStore.fetchParties(partyStore.meta.current_page)"
                        class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors"
                        title="Refresh List"
                    >
                        <RefreshCw :class="['w-4 h-4', partyStore.isLoading ? 'animate-spin' : '']" />
                    </button>
                </div>

                <!-- Add Button -->
                <button
                    type="button"
                    @click="openCreateModal"
                    class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 shadow-md shadow-amber-500/20 transition-all active:scale-95 whitespace-nowrap"
                >
                    <Plus class="w-4 h-4" />
                    <span>New Customer / Vendor</span>
                </button>
            </div>

            <!-- Parties Data Table & Mobile Cards -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <!-- Mobile Card List (< md) -->
                <div class="block md:hidden divide-y divide-slate-100">
                    <!-- Mobile Loading State -->
                    <div v-if="partyStore.isLoading && partyStore.parties.length === 0" class="p-8 text-center text-slate-500">
                        <div class="inline-flex items-center gap-2">
                            <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                            <span class="text-xs">Loading party records...</span>
                        </div>
                    </div>

                    <!-- Mobile Empty State -->
                    <div v-else-if="partyStore.parties.length === 0" class="p-8 text-center text-slate-500 space-y-2">
                        <Building2 class="w-8 h-8 text-slate-400 mx-auto" />
                        <div class="font-semibold text-sm text-slate-800">No Customers or Vendors Found</div>
                        <p class="text-xs text-slate-500">Add suppliers or customers to link with stock invoices and orders.</p>
                    </div>

                    <!-- Mobile Party Cards -->
                    <div
                        v-for="party in partyStore.parties"
                        :key="party.id"
                        class="p-4 space-y-3 hover:bg-slate-50 transition-colors"
                    >
                        <!-- Top Row: Code, Name, Status -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-xs font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                        {{ party.code }}
                                    </span>
                                    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-md border font-bold text-[10px]', getTypeBadgeClass(party.type)]">
                                        <Truck v-if="party.type === 'supplier'" class="w-2.5 h-2.5" />
                                        <UserCheck v-else-if="party.type === 'customer'" class="w-2.5 h-2.5" />
                                        <Briefcase v-else-if="party.type === 'agent'" class="w-2.5 h-2.5" />
                                        <Layers v-else class="w-2.5 h-2.5" />
                                        <span>{{ getTypeLabel(party.type) }}</span>
                                    </span>
                                </div>
                                <div class="font-bold text-sm text-slate-900 pt-0.5">
                                    {{ party.name }}
                                </div>
                                <div v-if="party.city || party.state" class="text-xs text-slate-500 flex items-center gap-1">
                                    <MapPin class="w-3 h-3 text-slate-400 flex-shrink-0" />
                                    <span>{{ party.city }}{{ party.state ? `, ${party.state}` : '' }}</span>
                                </div>
                            </div>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase flex-shrink-0',
                                    party.status === 'active'
                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                        : 'bg-slate-100 text-slate-600 border border-slate-200'
                                ]"
                            >
                                {{ party.status }}
                            </span>
                        </div>

                        <!-- Middle Row: Contact Person, Phone, GSTIN -->
                        <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Contact</span>
                                <span class="text-slate-800 font-medium truncate block">{{ party.contact_person || '—' }}</span>
                                <a
                                    v-if="party.phone"
                                    :href="`tel:${party.phone}`"
                                    class="text-[11px] font-mono text-cyan-700 hover:underline flex items-center gap-1 mt-0.5 font-bold"
                                >
                                    <Phone class="w-3 h-3 text-cyan-600 flex-shrink-0" />
                                    <span>{{ party.phone }}</span>
                                </a>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase text-slate-400 font-semibold block">Tax / GSTIN</span>
                                <span v-if="party.gstin" class="font-mono font-bold text-amber-700 text-[11px] truncate block">{{ party.gstin }}</span>
                                <span v-else class="text-slate-400 text-[11px] block">No GSTIN</span>
                                <span v-if="party.pan_no" class="text-[10px] text-slate-500 font-mono block">PAN: {{ party.pan_no }}</span>
                            </div>
                        </div>

                        <!-- Bottom Row: Action Buttons -->
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button
                                type="button"
                                @click="openViewModal(party)"
                                class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold flex items-center gap-1.5 transition-colors border border-slate-200"
                            >
                                <Eye class="w-3.5 h-3.5 text-cyan-600" />
                                <span>Details</span>
                            </button>
                            <button
                                type="button"
                                @click="openEditModal(party)"
                                class="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs font-semibold flex items-center gap-1.5 transition-colors border border-amber-200"
                            >
                                <Edit3 class="w-3.5 h-3.5 text-amber-600" />
                                <span>Edit</span>
                            </button>
                            <button
                                type="button"
                                @click="confirmDelete(party)"
                                class="px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-700 text-xs font-semibold flex items-center gap-1.5 transition-colors border border-red-200"
                            >
                                <Trash2 class="w-3.5 h-3.5 text-red-600" />
                                <span>Delete</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Table (>= md) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-700 uppercase tracking-wider border-b border-slate-200 font-bold text-[11px]">
                                <th class="py-3.5 px-4">Code</th>
                                <th class="py-3.5 px-4">Company / Party Name</th>
                                <th class="py-3.5 px-4">Type</th>
                                <th class="py-3.5 px-4">Contact Person & Phone</th>
                                <th class="py-3.5 px-4">GSTIN / City</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <!-- Loading State -->
                            <tr v-if="partyStore.isLoading && partyStore.parties.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <div class="inline-flex items-center gap-2">
                                        <RefreshCw class="w-5 h-5 animate-spin text-amber-500" />
                                        <span>Loading party records...</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-else-if="partyStore.parties.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-500">
                                    <div class="space-y-2">
                                        <Building2 class="w-8 h-8 text-slate-400 mx-auto" />
                                        <div class="font-semibold text-slate-800">No Customers or Vendors Found</div>
                                        <p class="text-[11px] text-slate-500">Add suppliers or customers to link with stock invoices and orders.</p>
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="party in partyStore.parties"
                                :key="party.id"
                                class="hover:bg-slate-50 transition-colors"
                            >
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-800">
                                    {{ party.code }}
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 text-sm">
                                        {{ party.name }}
                                    </div>
                                    <div v-if="party.city || party.state" class="text-[10px] text-slate-500 flex items-center gap-1">
                                        <MapPin class="w-3 h-3 text-slate-400" />
                                        <span>{{ party.city }}{{ party.state ? `, ${party.state}` : '' }}</span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border font-bold text-[10px]', getTypeBadgeClass(party.type)]">
                                        <Truck v-if="party.type === 'supplier'" class="w-3 h-3" />
                                        <UserCheck v-else-if="party.type === 'customer'" class="w-3 h-3" />
                                        <Briefcase v-else-if="party.type === 'agent'" class="w-3 h-3" />
                                        <Layers v-else class="w-3 h-3" />
                                        <span>{{ getTypeLabel(party.type) }}</span>
                                    </span>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="font-medium text-slate-800">
                                        {{ party.contact_person || '—' }}
                                    </div>
                                    <div v-if="party.phone" class="text-[10px] font-mono text-cyan-700 flex items-center gap-1 font-semibold">
                                        <Phone class="w-3 h-3 text-cyan-600" />
                                        <span>{{ party.phone }}</span>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 font-mono">
                                    <div v-if="party.gstin" class="text-xs text-amber-800 font-bold">
                                        {{ party.gstin }}
                                    </div>
                                    <div v-else class="text-[11px] text-slate-400">
                                        No GSTIN
                                    </div>
                                    <div v-if="party.pan_no" class="text-[10px] text-slate-500">
                                        PAN: {{ party.pan_no }}
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase',
                                            party.status === 'active'
                                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                : 'bg-slate-100 text-slate-600 border border-slate-200'
                                        ]"
                                    >
                                        {{ party.status }}
                                    </span>
                                </td>

                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openViewModal(party)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors border border-slate-200"
                                            title="View Details"
                                        >
                                            <Eye class="w-3.5 h-3.5 text-cyan-600" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openEditModal(party)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors border border-slate-200"
                                            title="Edit Party"
                                        >
                                            <Edit3 class="w-3.5 h-3.5 text-amber-600" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(party)"
                                            class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors border border-slate-200"
                                            title="Delete Party"
                                        >
                                            <Trash2 class="w-3.5 h-3.5 text-red-600" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="partyStore.meta.total > 0"
                    class="p-4 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600"
                >
                    <div>
                        Showing <span class="font-bold text-slate-900">{{ (partyStore.meta.current_page - 1) * partyStore.meta.per_page + 1 }}</span>
                        to <span class="font-bold text-slate-900">{{ Math.min(partyStore.meta.current_page * partyStore.meta.per_page, partyStore.meta.total) }}</span>
                        of <span class="font-bold text-slate-900">{{ partyStore.meta.total }}</span> entries
                    </div>
                    <div class="flex items-center gap-1.5" v-if="partyStore.meta.last_page > 1">
                        <button
                            type="button"
                            :disabled="partyStore.meta.current_page === 1"
                            @click="partyStore.fetchParties(partyStore.meta.current_page - 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Previous
                        </button>
                        <button
                            v-for="p in partyStore.meta.last_page"
                            :key="p"
                            @click="partyStore.fetchParties(p)"
                            :class="[
                                'w-8 h-8 rounded-lg font-bold text-xs transition-all',
                                partyStore.meta.current_page === p
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            {{ p }}
                        </button>
                        <button
                            type="button"
                            :disabled="partyStore.meta.current_page === partyStore.meta.last_page"
                            @click="partyStore.fetchParties(partyStore.meta.current_page + 1)"
                            class="px-2.5 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed text-xs font-semibold shadow-xs transition-colors"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Party Form Modal -->
        <div
            v-if="isFormModalOpen"
            @click.self="closeFormModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-700">
                            <Building2 class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">
                                {{ editingParty ? `Edit Party: ${editingParty.name}` : 'New Customer / Vendor Entry' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">Single form CRUD for Customers, Suppliers and Dual Partners</p>
                        </div>
                    </div>
                    <button @click="closeFormModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form @submit.prevent="submitForm" class="p-6 space-y-6 max-h-[82vh] overflow-y-auto custom-scrollbar">
                    <!-- Compact Party Type / Role Selector -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-700">
                            Party Role / Classification *
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-slate-50 p-1.5 rounded-xl border border-slate-200">
                            <!-- Supplier Option -->
                            <button
                                type="button"
                                @click="onTypeChange('supplier')"
                                :class="[
                                    'py-2 px-2 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all',
                                    form.type === 'supplier'
                                        ? 'bg-amber-500 text-white shadow-sm font-extrabold'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white'
                                ]"
                            >
                                <Truck class="w-3.5 h-3.5 flex-shrink-0" :class="form.type === 'supplier' ? 'text-white' : 'text-amber-600'" />
                                <span class="whitespace-normal text-[11px] sm:text-xs">Supplier</span>
                            </button>

                            <!-- Customer Option -->
                            <button
                                type="button"
                                @click="onTypeChange('customer')"
                                :class="[
                                    'py-2 px-2 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all',
                                    form.type === 'customer'
                                        ? 'bg-emerald-600 text-white shadow-sm font-extrabold'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white'
                                ]"
                            >
                                <UserCheck class="w-3.5 h-3.5 flex-shrink-0" :class="form.type === 'customer' ? 'text-white' : 'text-emerald-600'" />
                                <span class="whitespace-normal text-[11px] sm:text-xs">Customer</span>
                            </button>

                            <!-- Agent Option -->
                            <button
                                type="button"
                                @click="onTypeChange('agent')"
                                :class="[
                                    'py-2 px-2 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all',
                                    form.type === 'agent'
                                        ? 'bg-sky-600 text-white shadow-sm font-extrabold'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white'
                                ]"
                            >
                                <Briefcase class="w-3.5 h-3.5 flex-shrink-0" :class="form.type === 'agent' ? 'text-white' : 'text-sky-600'" />
                                <span class="whitespace-normal text-[11px] sm:text-xs">Agent</span>
                            </button>

                            <!-- Both Option -->
                            <button
                                type="button"
                                @click="onTypeChange('both')"
                                :class="[
                                    'py-2 px-2 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 transition-all',
                                    form.type === 'both'
                                        ? 'bg-purple-600 text-white shadow-sm font-extrabold'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-white'
                                ]"
                            >
                                <Layers class="w-3.5 h-3.5 flex-shrink-0" :class="form.type === 'both' ? 'text-white' : 'text-purple-600'" />
                                <span class="whitespace-normal text-[11px] sm:text-xs">Dual Role</span>
                            </button>
                        </div>
                    </div>

                    <!-- Core Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Name -->
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Company / Party Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g. UltraTech Cement Ltd, L&T Construction..."
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- Code -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Party Code</label>
                            <input
                                v-model="form.code"
                                type="text"
                                placeholder="e.g. SUP-0001, CUS-0001"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono font-bold text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- Contact Person -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Contact Person</label>
                            <input
                                v-model="form.contact_person"
                                type="text"
                                placeholder="e.g. Rajesh Sharma"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Phone / Mobile</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                placeholder="e.g. +91 98230 11223"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Email Address</label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="e.g. sales@company.com"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- GSTIN -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">GSTIN Number</label>
                            <input
                                v-model="form.gstin"
                                type="text"
                                maxlength="15"
                                placeholder="e.g. 27AAACU1234F1Z5"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-amber-800 font-mono font-bold text-xs uppercase focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- PAN No -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">PAN Number</label>
                            <input
                                v-model="form.pan_no"
                                type="text"
                                maxlength="10"
                                placeholder="e.g. AAACU1234F"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs uppercase focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Status</label>
                            <select
                                v-model="form.status"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs font-semibold focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <!-- Address -->
                        <div class="sm:col-span-3">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Address / Site Office Location</label>
                            <textarea
                                v-model="form.address"
                                rows="2"
                                placeholder="Full street address, industrial zone, landmark..."
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            ></textarea>
                        </div>

                        <!-- City -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">City</label>
                            <input
                                v-model="form.city"
                                type="text"
                                placeholder="e.g. Pune, Mumbai"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- State -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">State</label>
                            <input
                                v-model="form.state"
                                type="text"
                                placeholder="e.g. Maharashtra"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- Pincode -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Pincode</label>
                            <input
                                v-model="form.pincode"
                                type="text"
                                placeholder="e.g. 411005"
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 font-mono text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>

                        <!-- Remarks -->
                        <div class="sm:col-span-3">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Remarks / Dispatch Notes</label>
                            <input
                                v-model="form.remarks"
                                type="text"
                                placeholder="Payment terms, special project notes..."
                                class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-slate-900 text-xs focus:ring-2 focus:ring-amber-500 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeFormModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="partyStore.isSaving"
                            class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-xs uppercase tracking-wider flex items-center gap-2 shadow-md shadow-amber-500/20 transition-all active:scale-95 disabled:opacity-50"
                        >
                            <RefreshCw v-if="partyStore.isSaving" class="w-4 h-4 animate-spin" />
                            <span>{{ editingParty ? 'Update Party' : 'Save Party' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- View Party Details Modal -->
        <div
            v-if="isViewModalOpen && viewingParty"
            @click.self="closeViewModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs overflow-y-auto"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden my-8">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-cyan-50 border border-cyan-200 text-cyan-700">
                            <Building2 class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900">
                                {{ viewingParty.name }}
                            </h3>
                            <p class="text-[11px] text-slate-500 font-mono">{{ viewingParty.code }} — {{ getTypeLabel(viewingParty.type) }}</p>
                        </div>
                    </div>
                    <button @click="closeViewModal" class="p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Contact Person</div>
                            <div class="text-xs font-semibold text-slate-800">{{ viewingParty.contact_person || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Phone</div>
                            <div class="text-xs font-mono font-bold text-cyan-700">{{ viewingParty.phone || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Email</div>
                            <div class="text-xs text-slate-700 truncate">{{ viewingParty.email || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">GSTIN</div>
                            <div class="text-xs font-mono font-bold text-amber-800">{{ viewingParty.gstin || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">PAN Number</div>
                            <div class="text-xs font-mono text-slate-700">{{ viewingParty.pan_no || '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400">Status</div>
                            <div class="text-xs font-bold text-emerald-700 uppercase">{{ viewingParty.status }}</div>
                        </div>
                        <div class="sm:col-span-3">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Address</div>
                            <div class="text-xs text-slate-700">
                                {{ viewingParty.address || '—' }}
                                <span v-if="viewingParty.city">({{ viewingParty.city }}, {{ viewingParty.state }} - {{ viewingParty.pincode }})</span>
                            </div>
                        </div>
                        <div v-if="viewingParty.remarks" class="sm:col-span-3">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Remarks</div>
                            <div class="text-xs text-slate-700">{{ viewingParty.remarks }}</div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-200 flex items-center justify-end bg-slate-50">
                    <button
                        type="button"
                        @click="closeViewModal"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div
            v-if="isDeleteModalOpen && deletingParty"
            @click.self="isDeleteModalOpen = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md shadow-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 text-red-600">
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200">
                        <AlertCircle class="w-6 h-6 text-red-600" />
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-900 text-sm">Delete Party Record?</h4>
                        <p class="text-xs text-slate-500 font-mono">{{ deletingParty.code }} — {{ deletingParty.name }}</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Are you sure you want to delete this {{ getTypeLabel(deletingParty.type) }}? Existing stock transactions referencing this record will remain preserved.
                </p>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="handleDelete"
                        :disabled="partyStore.isSaving"
                        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                    >
                        <RefreshCw v-if="partyStore.isSaving" class="w-4 h-4 animate-spin" />
                        <span>Delete Party</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
