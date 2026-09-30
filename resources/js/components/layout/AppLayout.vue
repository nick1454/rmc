<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { setDocumentTitle } from '../../utils/title';
import { useAuthStore } from '../../stores/auth';
import { useNavigationStore } from '../../stores/navigation';
import { useEscapeKey } from '../../composables/useEscapeKey';
import RmcLogo from '../RmcLogo.vue';
import DynamicIcon from '../ui/DynamicIcon.vue';
import {
    LogOut,
    Menu,
    X,
    User as UserIcon,
    ChevronRight,
    CircleDot,
    PanelLeftClose,
    PanelLeftOpen
} from 'lucide-vue-next';

interface Props {
    title?: string;
    subtitle?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Dashboard',
    subtitle: '',
});

watch(
    () => props.title,
    (newTitle) => {
        if (newTitle) {
            setDocumentTitle(newTitle);
        }
    },
    { immediate: true }
);

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const navStore = useNavigationStore();

const isMobileMenuOpen = ref(false);
const isLoggingOut = ref(false);

// Screen responsiveness state
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false);

const updateScreenSize = () => {
    if (typeof window !== 'undefined') {
        isMobile.value = window.innerWidth < 768;
    }
};

// Sidebar collapsed state with localStorage persistence (desktop only)
const isSidebarCollapsed = ref(localStorage.getItem('rmc_sidebar_collapsed') === 'true');

// On mobile, the sidebar drawer is ALWAYS fully detailed when opened.
// On desktop, it follows the user's isSidebarCollapsed preference.
const isCollapsed = computed(() => !isMobile.value && isSidebarCollapsed.value);

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    localStorage.setItem('rmc_sidebar_collapsed', String(isSidebarCollapsed.value));
};

useEscapeKey(() => {
    if (isMobileMenuOpen.value) {
        isMobileMenuOpen.value = false;
    }
});

onMounted(async () => {
    updateScreenSize();
    window.addEventListener('resize', updateScreenSize);
    if (authStore.isAuthenticated) {
        await navStore.fetchNavigation();
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateScreenSize);
    }
});

const handleLogout = async () => {
    isLoggingOut.value = true;
    navStore.reset();
    await authStore.logout();
    router.push('/login');
};

const isActive = (path: string) => {
    return route.path === path || route.path.startsWith(path + '/');
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-800 flex flex-col md:flex-row antialiased font-sans">
        <!-- Mobile Header Bar -->
        <header class="md:hidden flex items-center justify-between px-3.5 py-2.5 bg-white border-b border-slate-200 sticky top-0 z-40">
            <div class="flex items-center gap-2">
                <RmcLogo size="sm" variant="icon" theme="light" />
                <div class="truncate max-w-[170px]">
                    <div class="text-xs font-bold text-slate-900 truncate">{{ title }}</div>
                    <div v-if="subtitle" class="text-[10px] text-slate-500 truncate">{{ subtitle }}</div>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                    class="p-2 rounded-xl bg-slate-100 text-slate-700 hover:text-slate-900 border border-slate-200 active:scale-95 transition-all cursor-pointer"
                    aria-label="Toggle Navigation Menu"
                >
                    <Menu v-if="!isMobileMenuOpen" class="w-5 h-5" />
                    <X v-else class="w-5 h-5" />
                </button>
            </div>
        </header>

        <!-- Mobile Drawer Backdrop -->
        <div
            v-if="isMobileMenuOpen"
            @click="isMobileMenuOpen = false"
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-40 md:hidden transition-opacity"
        ></div>

        <!-- Sidebar Navigation -->
        <aside
            :class="[
                'fixed md:static inset-y-0 left-0 z-50 bg-white border-r border-slate-200 flex flex-col transition-all duration-300 ease-in-out shrink-0 select-none',
                // Mobile sizing & transform
                isMobileMenuOpen ? 'translate-x-0 shadow-2xl w-72 max-w-[85vw]' : '-translate-x-full md:translate-x-0',
                // Desktop dynamic width
                isCollapsed ? 'md:w-20' : 'md:w-68'
            ]"
        >
            <!-- Sidebar Header: App Logo & Collapse Toggle -->
            <div
                class="h-16 border-b border-slate-100 flex items-center px-4"
                :class="isCollapsed ? 'justify-center' : 'justify-between'"
            >
                <router-link
                    to="/dashboard"
                    class="focus:outline-none flex items-center overflow-hidden transition-all"
                    @click="isMobileMenuOpen = false"
                    :title="isCollapsed ? 'RMC ERP' : ''"
                >
                    <RmcLogo
                        :size="isCollapsed ? 'sm' : 'md'"
                        :variant="isCollapsed ? 'icon' : 'full'"
                        theme="light"
                    />
                </router-link>

                <!-- Desktop Collapse Button in Expanded Mode -->
                <button
                    v-if="!isCollapsed"
                    type="button"
                    @click="toggleSidebar"
                    class="hidden md:flex p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                    title="Collapse Sidebar"
                >
                    <PanelLeftClose class="w-4 h-4" />
                </button>

                <!-- Mobile Close Button -->
                <button
                    type="button"
                    @click="isMobileMenuOpen = false"
                    class="md:hidden p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <!-- Active Batching Plant & Status -->
            <div class="px-3 pt-3 pb-1">
                <!-- Expanded Plant View (Always on Mobile, on Desktop when expanded) -->
                <div v-if="!isCollapsed" class="px-3 py-2 rounded-xl bg-slate-50 border border-slate-200/80 text-xs flex items-center justify-between shadow-xs">
                    <div class="flex items-center gap-2">
                        <CircleDot class="w-3.5 h-3.5 text-emerald-500 animate-pulse shrink-0" />
                        <div class="overflow-hidden">
                            <div class="text-[10px] uppercase font-bold text-slate-400">Active Plant</div>
                            <div class="font-bold text-slate-800 truncate max-w-[120px]">Plant-01 Central</div>
                        </div>
                    </div>
                    <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 shrink-0">
                        Online
                    </span>
                </div>

                <!-- Collapsed Plant Indicator with Tooltip (Desktop Collapsed only) -->
                <div v-else class="flex justify-center">
                    <div class="w-11 h-11 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-center relative group cursor-pointer shadow-xs hover:border-emerald-300 transition-colors">
                        <CircleDot class="w-4 h-4 text-emerald-500 animate-pulse" />
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white"></span>

                        <!-- Floating Tooltip -->
                        <div class="absolute left-full ml-3 px-3 py-1.5 bg-slate-900 text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-150 z-50 flex items-center gap-2">
                            <span>Plant-01 Central</span>
                            <span class="text-[9px] bg-emerald-600 text-white px-1.5 py-0.5 rounded font-bold">Online</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category-Wise Navigation Links Loaded from Database -->
            <nav class="flex-1 px-2.5 py-3 overflow-y-auto overflow-x-hidden space-y-4">
                <div v-for="(category, catIndex) in navStore.allCategories" :key="category.id" class="space-y-1">
                    <!-- Category Name Header (Expanded / Mobile Mode) -->
                    <div
                        v-if="!isCollapsed"
                        class="px-2.5 text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1"
                    >
                        {{ category.name }}
                    </div>

                    <!-- Category Divider (Collapsed Mode - except first) -->
                    <div
                        v-else-if="catIndex > 0"
                        class="my-2 mx-2 border-t border-slate-200/60"
                    ></div>

                    <!-- Category Items / Permitted Links -->
                    <router-link
                        v-for="item in category.items"
                        :key="item.id"
                        :to="item.path"
                        :class="[
                            'group relative select-none transition-all',
                            isCollapsed
                                ? 'flex items-center justify-center w-11 h-11 mx-auto rounded-xl my-1'
                                : 'flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold',
                            isActive(item.path)
                                ? 'bg-amber-50 text-amber-900 border border-amber-300 font-bold shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'
                        ]"
                        @click="isMobileMenuOpen = false"
                    >
                        <!-- Expanded / Mobile Nav Content -->
                        <template v-if="!isCollapsed">
                            <div class="flex items-center gap-3 truncate">
                                <DynamicIcon
                                    :name="item.icon"
                                    :class="[
                                        'w-4 h-4 shrink-0 transition-colors',
                                        isActive(item.path) ? 'text-amber-600' : 'text-slate-400 group-hover:text-amber-600'
                                    ]"
                                />
                                <span class="truncate">{{ item.title }}</span>
                            </div>

                            <span
                                v-if="item.badge"
                                class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200 shrink-0"
                            >
                                {{ item.badge }}
                            </span>
                            <ChevronRight
                                v-else-if="isActive(item.path)"
                                class="w-3.5 h-3.5 text-amber-600 shrink-0"
                            />
                        </template>

                        <!-- Collapsed Mode Icon + Floating Tooltip -->
                        <template v-else>
                            <DynamicIcon
                                :name="item.icon"
                                :class="[
                                    'w-5 h-5 transition-colors',
                                    isActive(item.path) ? 'text-amber-600' : 'text-slate-500 group-hover:text-amber-600'
                                ]"
                            />

                            <!-- Badge Indicator Dot -->
                            <span
                                v-if="item.badge"
                                class="absolute top-1.5 right-1.5 w-2.5 h-2.5 rounded-full bg-amber-500 ring-2 ring-white"
                            ></span>

                            <!-- Floating Flyout Tooltip on Hover -->
                            <div class="absolute left-full ml-3 px-3 py-1.5 bg-slate-900 text-white text-xs font-medium rounded-lg shadow-xl whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-150 z-50 flex items-center gap-2">
                                <span>{{ item.title }}</span>
                                <span
                                    v-if="item.badge"
                                    class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-amber-500 text-white"
                                >
                                    {{ item.badge }}
                                </span>
                            </div>
                        </template>
                    </router-link>
                </div>
            </nav>

            <!-- Sidebar Footer: Logged in User & Logout -->
            <div class="p-2.5 border-t border-slate-100 bg-slate-50/70">
                <!-- Expanded / Mobile User Info -->
                <div v-if="!isCollapsed" class="flex items-center justify-between p-2 rounded-xl bg-white border border-slate-200 shadow-xs">
                    <div class="flex items-center gap-2.5 overflow-hidden">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center font-bold text-white shrink-0 shadow-xs">
                            <UserIcon class="w-4 h-4 text-white" />
                        </div>
                        <div class="truncate">
                            <div class="text-xs font-bold text-slate-800 truncate">
                                {{ authStore.userName }}
                            </div>
                            <div class="text-[10px] text-amber-700 font-medium truncate">
                                {{ authStore.userRole }}
                            </div>
                        </div>
                    </div>

                    <!-- Logout Action Button -->
                    <button
                        type="button"
                        @click="handleLogout"
                        :disabled="isLoggingOut"
                        class="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 border border-transparent hover:border-red-200 transition-all shrink-0 cursor-pointer"
                        title="Sign Out"
                    >
                        <LogOut class="w-4 h-4" />
                    </button>
                </div>

                <!-- Collapsed User Avatar & Logout Buttons (Desktop Collapsed only) -->
                <div v-else class="flex flex-col items-center gap-2">
                    <div class="relative group">
                        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center font-bold text-white shadow-xs cursor-pointer">
                            <UserIcon class="w-5 h-5 text-white" />
                        </div>
                        <!-- Tooltip with user name & role -->
                        <div class="absolute left-full ml-3 bottom-0 px-3 py-2 bg-slate-900 text-white text-xs rounded-lg shadow-xl whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-150 z-50">
                            <div class="font-bold">{{ authStore.userName }}</div>
                            <div class="text-[10px] text-amber-400">{{ authStore.userRole }}</div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="handleLogout"
                        :disabled="isLoggingOut"
                        class="w-11 h-11 rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50 border border-transparent hover:border-red-200 transition-all flex items-center justify-center relative group cursor-pointer"
                        title="Sign Out"
                    >
                        <LogOut class="w-5 h-5" />
                        <!-- Floating Tooltip -->
                        <div class="absolute left-full ml-3 px-2.5 py-1 bg-slate-900 text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-150 z-50">
                            Sign Out
                        </div>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Workspace View Area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <!-- Desktop Header Bar -->
            <header class="h-16 bg-white/90 border-b border-slate-200 px-4 sm:px-6 hidden md:flex items-center justify-between sticky top-0 z-30 backdrop-blur-md">
                <div class="flex items-center gap-3">
                    <!-- Modern Desktop Sidebar Toggle Button -->
                    <button
                        type="button"
                        @click="toggleSidebar"
                        class="p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 border border-slate-200 transition-all active:scale-95 shadow-2xs cursor-pointer"
                        :title="isSidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'"
                        aria-label="Toggle Sidebar"
                    >
                        <PanelLeftOpen v-if="isSidebarCollapsed" class="w-4 h-4 text-amber-600" />
                        <PanelLeftClose v-else class="w-4 h-4" />
                    </button>

                    <div>
                        <h1 class="text-lg font-bold text-slate-900 tracking-tight">{{ title }}</h1>
                        <p v-if="subtitle" class="text-xs text-slate-500">{{ subtitle }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                        Batching Operations Active
                    </span>

                    <button
                        type="button"
                        @click="handleLogout"
                        :disabled="isLoggingOut"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold uppercase tracking-wider text-slate-700 bg-slate-100 hover:bg-red-50 hover:text-red-600 hover:border-red-200 border border-slate-200 transition-all active:scale-95 disabled:opacity-50 cursor-pointer"
                    >
                        <LogOut class="w-3.5 h-3.5" />
                        <span>{{ isLoggingOut ? 'Exiting...' : 'Logout' }}</span>
                    </button>
                </div>
            </header>

            <!-- Main Content Area Slot -->
            <main class="flex-1 overflow-y-auto p-3 sm:p-6 md:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
