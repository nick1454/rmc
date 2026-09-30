import { defineStore } from 'pinia';
import api from '../services/api';
import type { User, AuthResponse, UserResponse } from '../types/auth';

interface AuthState {
    user: User | null;
    token: string | null;
    isLoading: boolean;
    error: string | null;
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: (() => {
            const saved = localStorage.getItem('rmc_user');
            if (saved) {
                try {
                    return JSON.parse(saved);
                } catch {
                    return null;
                }
            }
            return null;
        })(),
        token: localStorage.getItem('rmc_token'),
        isLoading: false,
        error: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token && !!state.user,
        userName: (state) => state.user?.name || 'Operator',
        userRole: (state) => state.user?.role || 'Plant Administrator',
        userPlant: (state) => state.user?.plant || 'Central Batching Unit',
    },

    actions: {
        async login(email: string, password: string): Promise<boolean> {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await api.post<AuthResponse>('/auth/login', {
                    email,
                    password,
                });

                const { token, user } = response.data;
                this.token = token;
                this.user = user;

                localStorage.setItem('rmc_token', token);
                localStorage.setItem('rmc_user', JSON.stringify(user));

                return true;
            } catch (err: any) {
                this.error =
                    err.response?.data?.message ||
                    err.response?.data?.errors?.email?.[0] ||
                    'Authentication failed. Please verify your credentials.';
                return false;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchUser(): Promise<void> {
            if (!this.token) return;

            try {
                const response = await api.get<UserResponse>('/auth/me');
                this.user = response.data.user;
                localStorage.setItem('rmc_user', JSON.stringify(this.user));
            } catch (err) {
                this.logout();
            }
        },

        async logout(): Promise<void> {
            try {
                if (this.token) {
                    await api.post('/auth/logout');
                }
            } catch {
                // Ignore API network errors on logout
            } finally {
                this.user = null;
                this.token = null;
                this.error = null;
                localStorage.removeItem('rmc_token');
                localStorage.removeItem('rmc_user');
            }
        },
    },
});
