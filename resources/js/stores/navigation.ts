import { defineStore } from 'pinia';
import api from '../services/api';

export interface NavigationItem {
    id: number;
    title: string;
    path: string;
    icon: string;
    badge?: string | null;
}

export interface NavigationCategory {
    id: number;
    name: string;
    slug: string;
    items: NavigationItem[];
}

interface NavigationResponse {
    status: string;
    role?: {
        id: number | null;
        slug: string;
        name: string;
    };
    categories: NavigationCategory[];
}

interface NavigationState {
    categories: NavigationCategory[];
    isLoading: boolean;
    error: string | null;
}

const defaultCategories: NavigationCategory[] = [
    {
        id: 1,
        name: 'Operations',
        slug: 'operations',
        items: [
            {
                id: 1,
                title: 'Dashboard',
                path: '/dashboard',
                icon: 'LayoutDashboard',
                badge: null,
            },
        ],
    },
    {
        id: 2,
        name: 'Master Data',
        slug: 'master-data',
        items: [
            {
                id: 2,
                title: 'Item Master',
                path: '/items',
                icon: 'Layers',
                badge: null,
            },
        ],
    },
];

export const useNavigationStore = defineStore('navigation', {
    state: (): NavigationState => ({
        categories: defaultCategories,
        isLoading: false,
        error: null,
    }),

    getters: {
        allCategories: (state): NavigationCategory[] => state.categories,
    },

    actions: {
        async fetchNavigation(): Promise<void> {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await api.get<NavigationResponse>('/navigation');
                if (response.data && response.data.categories && response.data.categories.length > 0) {
                    this.categories = response.data.categories;
                }
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to load navigation';
            } finally {
                this.isLoading = false;
            }
        },

        reset(): void {
            this.categories = defaultCategories;
            this.error = null;
        },
    },
});
