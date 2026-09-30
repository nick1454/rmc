<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { setDocumentTitle } from '../utils/title';
import { useAuthStore } from '../stores/auth';
import { useNavigationStore } from '../stores/navigation';
import RmcLogo from '../components/RmcLogo.vue';
import { Lock, Mail, ArrowRight, ShieldCheck, Truck, Factory, Sparkles, UserCheck } from 'lucide-vue-next';

onMounted(() => {
    setDocumentTitle('Login');
});

const router = useRouter();
const authStore = useAuthStore();
const navStore = useNavigationStore();

const email = ref('admin@rmc.com');
const password = ref('password');
const rememberMe = ref(true);

const handleLogin = async () => {
    if (!email.value || !password.value) return;

    const success = await authStore.login(email.value, password.value);
    if (success) {
        await navStore.fetchNavigation();
        router.push('/dashboard');
    }
};

const setCredentials = (roleEmail: string) => {
    email.value = roleEmail;
    password.value = 'password';
};
</script>

<template>
    <div class="min-h-screen flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-100 relative overflow-hidden text-slate-800">
        <!-- Background Architectural Grid / Glow Accent -->
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#e2e8f080_1px,transparent_1px),linear-gradient(to_bottom,#e2e8f080_1px,transparent_1px)] bg-[size:3rem_3rem] pointer-events-none"></div>
        <div class="absolute top-1/4 -left-20 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-1/4 -right-20 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
            <!-- App Logo & Branding Header -->
            <div class="flex flex-col items-center text-center">
                <RmcLogo size="xl" variant="full" theme="light" />
                <h2 class="mt-5 text-2xl font-extrabold text-slate-900 tracking-tight">
                    Batching & Dispatch Portal
                </h2>
                <p class="mt-1 text-sm text-slate-500 font-medium">
                    Enterprise Ready Mix Concrete Management System
                </p>
            </div>

            <!-- Login Card -->
            <div class="mt-8 bg-white border border-slate-200 shadow-xl rounded-2xl p-8 sm:p-10">
                <!-- Error Alert -->
                <div
                    v-if="authStore.error"
                    class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-3"
                >
                    <span class="mt-0.5 font-bold">⚠️</span>
                    <div>{{ authStore.error }}</div>
                </div>

                <form @submit.prevent="handleLogin" class="space-y-5">
                    <!-- Email Address -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Operator Email / ID
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <Mail class="w-5 h-5" />
                            </div>
                            <input
                                v-model="email"
                                type="email"
                                required
                                placeholder="operator@rmc.com"
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-sm transition-all focus:bg-white"
                            />
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Password
                            </label>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <Lock class="w-5 h-5" />
                            </div>
                            <input
                                v-model="password"
                                type="password"
                                required
                                placeholder="••••••••"
                                class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 text-sm transition-all focus:bg-white"
                            />
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                            <input
                                v-model="rememberMe"
                                type="checkbox"
                                class="w-4 h-4 rounded bg-white border-slate-300 text-amber-500 focus:ring-amber-500"
                            />
                            <span>Keep active session</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="authStore.isLoading"
                        class="w-full py-3.5 px-4 flex items-center justify-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold rounded-xl shadow-lg shadow-amber-500/20 transition-all transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed text-sm uppercase tracking-wider"
                    >
                        <span v-if="authStore.isLoading" class="animate-spin mr-2">⚙️</span>
                        <span>{{ authStore.isLoading ? 'Authenticating...' : 'Sign In to RMC Console' }}</span>
                        <ArrowRight v-if="!authStore.isLoading" class="w-4 h-4 text-white" />
                    </button>
                </form>

                <!-- Quick Role Autofill Selector -->
                <div class="mt-6 pt-6 border-t border-slate-100 space-y-2">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                        <Sparkles class="w-3.5 h-3.5 text-amber-500" />
                        <span>Quick Test Roles (DB Permitted Links)</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="setCredentials('admin@rmc.com')"
                            :class="[
                                'p-2 rounded-xl text-left border transition-all',
                                email === 'admin@rmc.com'
                                    ? 'bg-amber-50 border-amber-300 text-amber-900 font-semibold'
                                    : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 hover:border-slate-300'
                            ]"
                        >
                            <div class="text-xs font-bold flex items-center gap-1">
                                <UserCheck class="w-3 h-3 text-amber-600" />
                                <span>Admin Role</span>
                            </div>
                            <div class="text-[10px] text-slate-500 mt-0.5">All Categories</div>
                        </button>

                        <button
                            type="button"
                            @click="setCredentials('driver@rmc.com')"
                            :class="[
                                'p-2 rounded-xl text-left border transition-all',
                                email === 'driver@rmc.com'
                                    ? 'bg-amber-50 border-amber-300 text-amber-900 font-semibold'
                                    : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 hover:border-slate-300'
                            ]"
                        >
                            <div class="text-xs font-bold flex items-center gap-1">
                                <Truck class="w-3 h-3 text-amber-600" />
                                <span>Driver Role</span>
                            </div>
                            <div class="text-[10px] text-slate-500 mt-0.5">Driver Portal Only</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Operational Feature Badges -->
            <div class="mt-8 grid grid-cols-3 gap-2 text-center text-[11px] text-slate-600">
                <div class="p-2.5 rounded-xl bg-white border border-slate-200 shadow-xs flex flex-col items-center gap-1">
                    <Factory class="w-4 h-4 text-amber-600" />
                    <span class="font-medium">Batching Control</span>
                </div>
                <div class="p-2.5 rounded-xl bg-white border border-slate-200 shadow-xs flex flex-col items-center gap-1">
                    <Truck class="w-4 h-4 text-amber-600" />
                    <span class="font-medium">Fleet & TMs</span>
                </div>
                <div class="p-2.5 rounded-xl bg-white border border-slate-200 shadow-xs flex flex-col items-center gap-1">
                    <ShieldCheck class="w-4 h-4 text-amber-600" />
                    <span class="font-medium">QC & Testing</span>
                </div>
            </div>
        </div>
    </div>
</template>
