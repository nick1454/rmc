<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AppLayout from '../components/layout/AppLayout.vue';
import api from '../services/api';
import {
    Truck,
    Wallet,
    Layers,
    IndianRupee,
    RefreshCw,
    Plus,
    Boxes,
    FileText,
    ChevronRight,
    ArrowUpRight,
    CircleDot,
    Calendar,
    Building2,
    Clock,
    Activity,
    CheckCircle2,
    CreditCard
} from 'lucide-vue-next';

interface DashboardStats {
    today: {
        dispatches_count: number;
        dispatched_volume: number;
        outward_amount: number;
        received_amount: number;
    };
    month: {
        dispatches_count: number;
        dispatched_volume: number;
        outward_amount: number;
        received_amount: number;
    };
    financials: {
        customer_outstanding: number;
        supplier_payable: number;
        total_customers: number;
        total_suppliers: number;
        total_items: number;
    };
    recent_dispatches: Array<{
        id: number;
        entry_no: string;
        challan_no: string;
        date: string;
        customer_name: string;
        destination: string;
        vehicle_no: string;
        grade: string;
        quantity: number;
        unit: string;
        total_amount: number;
        paid_amount: number;
        payment_status: string;
    }>;
    recent_payments: Array<{
        id: number;
        payment_no: string;
        date: string;
        customer_name: string;
        amount: number;
        payment_mode: string;
        bank_name: string;
    }>;
    top_grades: Array<{
        item_id: number;
        name: string;
        code: string;
        total_qty: number;
        total_revenue: number;
    }>;
    key_stock: Array<{
        id: number;
        name: string;
        code: string;
        current_stock: number;
        unit: string;
    }>;
}

const router = useRouter();
const isLoading = ref(false);
const lastUpdated = ref<string>('');

const stats = ref<DashboardStats>({
    today: {
        dispatches_count: 0,
        dispatched_volume: 0,
        outward_amount: 0,
        received_amount: 0
    },
    month: {
        dispatches_count: 0,
        dispatched_volume: 0,
        outward_amount: 0,
        received_amount: 0
    },
    financials: {
        customer_outstanding: 0,
        supplier_payable: 0,
        total_customers: 0,
        total_suppliers: 0,
        total_items: 0
    },
    recent_dispatches: [],
    recent_payments: [],
    top_grades: [],
    key_stock: []
});

const formatCurrency = (val: number | null | undefined): string => {
    if (val === null || val === undefined || isNaN(val)) return '0.00';
    return Number(val).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

const formatQty = (val: number | null | undefined): string => {
    if (val === null || val === undefined || isNaN(val)) return '0.00';
    return Number(val).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

const fetchDashboardStats = async () => {
    try {
        isLoading.value = true;
        const response = await api.get('/dashboard/stats');
        if (response.data?.status === 'success' && response.data?.data) {
            stats.value = response.data.data;
            const now = new Date();
            lastUpdated.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    } catch (error) {
        console.error('Failed to load dashboard metrics:', error);
    } finally {
        isLoading.value = false;
    }
};

const navigateTo = (path: string) => {
    router.push(path);
};

onMounted(() => {
    fetchDashboardStats();
});
</script>

<template>
    <AppLayout title="Dashboard" subtitle="Ready Mix Concrete Operations, Dispatches & Financial Overview">
        <div class="space-y-5">
            <!-- Header Bar: Status & Refresh -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 sm:p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-600 font-bold">
                        <Activity class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-bold text-slate-900">Plant-01 Central Operations</h2>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Live Sync
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Real-time batch formulation, dispatched volume, collection receipts & raw material status
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <span v-if="lastUpdated" class="text-[11px] text-slate-400 font-medium">
                        Updated {{ lastUpdated }}
                    </span>
                    <button
                        type="button"
                        @click="fetchDashboardStats"
                        :disabled="isLoading"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 hover:text-slate-900 border border-slate-200 text-xs font-semibold transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                        title="Refresh Data"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" />
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Primary 4 KPI Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Today's Dispatches -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:border-amber-300 transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Today's Dispatch</span>
                            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center">
                                <Truck class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-2 flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-slate-900 tracking-tight">{{ formatQty(stats.today.dispatched_volume) }}</span>
                            <span class="text-xs font-bold text-amber-700">CUM</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500 font-medium">{{ stats.today.dispatches_count }} Challans / Trips</span>
                        <span class="font-bold text-slate-800">₹{{ formatCurrency(stats.today.outward_amount) }}</span>
                    </div>
                </div>

                <!-- 2. Today's Collections -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Today's Received</span>
                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center">
                                <Wallet class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-2 flex items-baseline gap-1.5">
                            <span class="text-xs font-bold text-emerald-700">₹</span>
                            <span class="text-2xl font-black text-slate-900 tracking-tight">{{ formatCurrency(stats.today.received_amount) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500 font-medium">Customer Collections</span>
                        <span class="font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">Recorded Today</span>
                    </div>
                </div>

                <!-- 3. Monthly Dispatches -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:border-blue-300 transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">This Month Dispatch</span>
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center">
                                <Layers class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-2 flex items-baseline gap-1.5">
                            <span class="text-2xl font-black text-slate-900 tracking-tight">{{ formatQty(stats.month.dispatched_volume) }}</span>
                            <span class="text-xs font-bold text-blue-700">CUM</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500 font-medium">{{ stats.month.dispatches_count }} Total Loads</span>
                        <span class="font-bold text-slate-800">₹{{ formatCurrency(stats.month.outward_amount) }}</span>
                    </div>
                </div>

                <!-- 4. Receivables & Payables -->
                <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs hover:border-indigo-300 transition-all flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Customer Outstanding</span>
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200 flex items-center justify-center">
                                <IndianRupee class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="mt-2 flex items-baseline gap-1.5">
                            <span class="text-xs font-bold text-indigo-700">₹</span>
                            <span class="text-2xl font-black text-slate-900 tracking-tight">{{ formatCurrency(stats.financials.customer_outstanding) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500 font-medium">Supplier Payables:</span>
                        <span class="font-bold text-rose-700">₹{{ formatCurrency(stats.financials.supplier_payable) }}</span>
                    </div>
                </div>
            </div>

            <!-- Quick Action Ribbon -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-3 sm:p-4 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-slate-900 uppercase tracking-wider">Quick Operations</span>
                    <span class="text-[11px] text-slate-400">Direct Entry Shortcuts</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-2.5">
                    <button
                        type="button"
                        @click="navigateTo('/outward')"
                        class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 hover:bg-amber-50 text-slate-700 hover:text-amber-900 border border-slate-200/80 hover:border-amber-300 text-xs font-semibold transition-all cursor-pointer group"
                    >
                        <Truck class="w-4 h-4 text-slate-500 group-hover:text-amber-600" />
                        <span class="truncate">New Dispatch</span>
                    </button>

                    <button
                        type="button"
                        @click="navigateTo('/inward')"
                        class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 hover:bg-blue-50 text-slate-700 hover:text-blue-900 border border-slate-200/80 hover:border-blue-300 text-xs font-semibold transition-all cursor-pointer group"
                    >
                        <Boxes class="w-4 h-4 text-slate-500 group-hover:text-blue-600" />
                        <span class="truncate">New Inward</span>
                    </button>

                    <button
                        type="button"
                        @click="navigateTo('/production')"
                        class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 hover:bg-emerald-50 text-slate-700 hover:text-emerald-900 border border-slate-200/80 hover:border-emerald-300 text-xs font-semibold transition-all cursor-pointer group"
                    >
                        <Layers class="w-4 h-4 text-slate-500 group-hover:text-emerald-600" />
                        <span class="truncate">Production Entry</span>
                    </button>

                    <button
                        type="button"
                        @click="navigateTo('/payments/outward')"
                        class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 hover:bg-emerald-50 text-slate-700 hover:text-emerald-900 border border-slate-200/80 hover:border-emerald-300 text-xs font-semibold transition-all cursor-pointer group"
                    >
                        <CreditCard class="w-4 h-4 text-slate-500 group-hover:text-emerald-600" />
                        <span class="truncate">Receive Payment</span>
                    </button>

                    <button
                        type="button"
                        @click="navigateTo('/reports/dispatch-payment')"
                        class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 hover:bg-purple-50 text-slate-700 hover:text-purple-900 border border-slate-200/80 hover:border-purple-300 text-xs font-semibold transition-all cursor-pointer group"
                    >
                        <FileText class="w-4 h-4 text-slate-500 group-hover:text-purple-600" />
                        <span class="truncate">Customer Payments</span>
                    </button>

                    <button
                        type="button"
                        @click="navigateTo('/reports/stock')"
                        class="flex items-center gap-2 p-2.5 rounded-xl bg-slate-50 hover:bg-indigo-50 text-slate-700 hover:text-indigo-900 border border-slate-200/80 hover:border-indigo-300 text-xs font-semibold transition-all cursor-pointer group"
                    >
                        <Boxes class="w-4 h-4 text-slate-500 group-hover:text-indigo-600" />
                        <span class="truncate">Stock Report</span>
                    </button>
                </div>
            </div>

            <!-- Main 2-Column Activity & Analytics Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- Left Column: Recent Dispatches & Receipts (7 cols) -->
                <div class="lg:col-span-7 space-y-5">
                    <!-- Recent Dispatches Table -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Truck class="w-4 h-4 text-amber-600" />
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Recent Concrete Dispatches</h3>
                            </div>
                            <router-link
                                to="/outward"
                                class="text-[11px] font-semibold text-amber-700 hover:text-amber-800 flex items-center gap-1 group"
                            >
                                <span>View Outwards</span>
                                <ChevronRight class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                            </router-link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                        <th class="py-2.5 px-3.5">Challan / Date</th>
                                        <th class="py-2.5 px-3.5">Customer & Site</th>
                                        <th class="py-2.5 px-3.5">Grade</th>
                                        <th class="py-2.5 px-3.5 text-right">Volume</th>
                                        <th class="py-2.5 px-3.5 text-right">Amount</th>
                                        <th class="py-2.5 px-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="d in stats.recent_dispatches"
                                        :key="d.id"
                                        class="hover:bg-slate-50/60 transition-colors"
                                    >
                                        <td class="py-2.5 px-3.5 whitespace-nowrap">
                                            <div class="font-bold text-slate-900">{{ d.challan_no }}</div>
                                            <div class="text-[10px] text-slate-400">{{ d.date }}</div>
                                        </td>
                                        <td class="py-2.5 px-3.5 max-w-[170px] truncate">
                                            <div class="font-semibold text-slate-800 truncate" :title="d.customer_name">{{ d.customer_name }}</div>
                                            <div class="text-[10px] text-slate-400 truncate" :title="d.destination">{{ d.destination }}</div>
                                        </td>
                                        <td class="py-2.5 px-3.5 whitespace-nowrap font-medium text-slate-700">
                                            <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">
                                                {{ d.grade }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-right whitespace-nowrap font-bold text-slate-900">
                                            {{ formatQty(d.quantity) }} <span class="text-[10px] text-slate-400 font-normal">{{ d.unit }}</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 text-right whitespace-nowrap font-bold text-slate-800">
                                            ₹{{ formatCurrency(d.total_amount) }}
                                        </td>
                                        <td class="py-2.5 px-3.5 text-center whitespace-nowrap">
                                            <span
                                                class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider"
                                                :class="[
                                                    d.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                    d.payment_status === 'partial' ? 'bg-amber-50 text-amber-700 border border-amber-200' :
                                                    'bg-slate-100 text-slate-600 border border-slate-200'
                                                ]"
                                            >
                                                {{ d.payment_status }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="stats.recent_dispatches.length === 0">
                                        <td colspan="6" class="py-6 text-center text-xs text-slate-400">
                                            No dispatches recorded yet.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Customer Receipts -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Wallet class="w-4 h-4 text-emerald-600" />
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Recent Customer Receipts</h3>
                            </div>
                            <router-link
                                to="/reports/dispatch-payment"
                                class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1 group"
                            >
                                <span>View Register</span>
                                <ChevronRight class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                            </router-link>
                        </div>

                        <div class="divide-y divide-slate-100">
                            <div
                                v-for="p in stats.recent_payments"
                                :key="p.id"
                                class="p-3.5 flex items-center justify-between hover:bg-slate-50/60 transition-colors"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold text-xs shrink-0">
                                        ₹
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-xs font-bold text-slate-900 truncate">{{ p.customer_name }}</div>
                                        <div class="text-[10px] text-slate-400 flex items-center gap-2 mt-0.5">
                                            <span>#{{ p.payment_no }}</span>
                                            <span>•</span>
                                            <span>{{ p.date }}</span>
                                            <span>•</span>
                                            <span class="font-semibold text-slate-600">{{ p.payment_mode }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-xs font-black text-emerald-700">₹{{ formatCurrency(p.amount) }}</div>
                                    <div class="text-[9px] text-slate-400 font-medium">Received</div>
                                </div>
                            </div>
                            <div v-if="stats.recent_payments.length === 0" class="p-6 text-center text-xs text-slate-400">
                                No customer payments recorded yet.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Analytics & Inventory (5 cols) -->
                <div class="lg:col-span-5 space-y-5">
                    <!-- Top Concrete Mix Grades -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-3.5">
                            <div class="flex items-center gap-2">
                                <Layers class="w-4 h-4 text-blue-600" />
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Top Concrete Grades</h3>
                            </div>
                            <span class="text-[10px] font-semibold text-slate-400">By Volume (CUM)</span>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="grade in stats.top_grades"
                                :key="grade.item_id"
                                class="space-y-1.5"
                            >
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-800">{{ grade.name }}</span>
                                    <span class="font-semibold text-slate-700">{{ formatQty(grade.total_qty) }} CUM</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div
                                        class="bg-gradient-to-r from-amber-500 to-amber-600 h-full rounded-full transition-all duration-500"
                                        :style="{ width: `${Math.min(100, (grade.total_qty / (stats.month.dispatched_volume || 1)) * 100)}%` }"
                                    ></div>
                                </div>
                            </div>

                            <div v-if="stats.top_grades.length === 0" class="py-4 text-center text-xs text-slate-400">
                                No dispatch data available yet.
                            </div>
                        </div>
                    </div>

                    <!-- Raw Material Stock Status -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-3.5">
                            <div class="flex items-center gap-2">
                                <Boxes class="w-4 h-4 text-indigo-600" />
                                <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Raw Material Balances</h3>
                            </div>
                            <router-link
                                to="/reports/stock"
                                class="text-[11px] font-semibold text-indigo-700 hover:text-indigo-800 flex items-center gap-0.5"
                            >
                                <span>Ledger</span>
                                <ChevronRight class="w-3.5 h-3.5" />
                            </router-link>
                        </div>

                        <div class="space-y-2.5">
                            <div
                                v-for="rm in stats.key_stock"
                                :key="rm.id"
                                class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200/60 text-xs"
                            >
                                <div class="truncate mr-2">
                                    <div class="font-bold text-slate-800 truncate">{{ rm.name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ rm.code || 'RM Item' }}</div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-black text-slate-900">
                                        {{ formatQty(rm.current_stock) }}
                                        <span class="text-[10px] font-normal text-slate-500">{{ rm.unit }}</span>
                                    </div>
                                    <span
                                        class="inline-block text-[9px] font-bold px-1.5 py-0.2 rounded"
                                        :class="rm.current_stock > 10 ? 'text-emerald-700 bg-emerald-50' : 'text-amber-700 bg-amber-50'"
                                    >
                                        {{ rm.current_stock > 10 ? 'Adequate' : 'Low Stock' }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="stats.key_stock.length === 0" class="py-4 text-center text-xs text-slate-400">
                                No raw material items configured.
                            </div>
                        </div>
                    </div>

                    <!-- Unified Master Directory Summary -->
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-4 shadow-sm">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Directory Quick Counts</div>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <div class="text-lg font-black text-amber-400">{{ stats.financials.total_customers }}</div>
                                <div class="text-[10px] text-slate-300 font-medium">Customers</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <div class="text-lg font-black text-emerald-400">{{ stats.financials.total_suppliers }}</div>
                                <div class="text-[10px] text-slate-300 font-medium">Suppliers</div>
                            </div>
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <div class="text-lg font-black text-blue-400">{{ stats.financials.total_items }}</div>
                                <div class="text-[10px] text-slate-300 font-medium">Items / Mixes</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
