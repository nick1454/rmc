<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AppLayout from '../../components/layout/AppLayout.vue';
import CustomerOutstandingReportView from './CustomerOutstandingReportView.vue';
import SupplierPayablesReportView from './SupplierPayablesReportView.vue';
import CashBankBookReportView from './CashBankBookReportView.vue';
import PartyLedgerReportView from './PartyLedgerReportView.vue';
import {
    Users,
    Building2,
    Landmark,
    BookOpen,
    BarChart3
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();

type TabKey = 'customer-outstanding' | 'supplier-payables' | 'cash-bank-book' | 'party-ledger';

const activeTab = ref<TabKey>('customer-outstanding');

onMounted(() => {
    if (route.query.tab) {
        activeTab.value = route.query.tab as TabKey;
    }
});

watch(() => route.query.tab, (newTab) => {
    if (newTab) {
        activeTab.value = newTab as TabKey;
    }
});

const switchTab = (tab: TabKey) => {
    activeTab.value = tab;
    router.replace({ query: { ...route.query, tab } });
};
</script>

<template>
    <div class="financial-reports-hub">
        <!-- Tab Navigation Bar -->
        <div class="px-4 sm:px-6 lg:px-8 pt-6">
            <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap gap-2">
                <button
                    @click="switchTab('customer-outstanding')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition"
                    :class="activeTab === 'customer-outstanding'
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100'"
                >
                    <Users class="w-4 h-4" />
                    Customer Outstanding & Ageing
                </button>

                <button
                    @click="switchTab('supplier-payables')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition"
                    :class="activeTab === 'supplier-payables'
                        ? 'bg-teal-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100'"
                >
                    <Building2 class="w-4 h-4" />
                    Supplier Payables
                </button>

                <button
                    @click="switchTab('cash-bank-book')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition"
                    :class="activeTab === 'cash-bank-book'
                        ? 'bg-emerald-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100'"
                >
                    <Landmark class="w-4 h-4" />
                    Cash & Bank Book
                </button>

                <button
                    @click="switchTab('party-ledger')"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition"
                    :class="activeTab === 'party-ledger'
                        ? 'bg-violet-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-slate-100'"
                >
                    <BookOpen class="w-4 h-4" />
                    Party Ledger Statement
                </button>
            </div>
        </div>

        <!-- Render Tab Component -->
        <div class="tab-content">
            <CustomerOutstandingReportView v-if="activeTab === 'customer-outstanding'" />
            <SupplierPayablesReportView v-else-if="activeTab === 'supplier-payables'" />
            <CashBankBookReportView v-else-if="activeTab === 'cash-bank-book'" />
            <PartyLedgerReportView v-else-if="activeTab === 'party-ledger'" />
        </div>
    </div>
</template>
