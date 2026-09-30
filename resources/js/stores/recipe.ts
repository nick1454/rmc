import { defineStore } from 'pinia';
import api from '../services/api';
import type { Item } from '../types/item';
import type { RecipeItem, RecipeItemInput, MasterRecipeResponse } from '../types/recipe';

interface RecipeState {
    activeFgItem: Item | null;
    recipeItems: RecipeItem[];
    isLoading: boolean;
    isSaving: boolean;
    error: string | null;
    successMessage: string | null;
}

export const useRecipeStore = defineStore('recipe', {
    state: (): RecipeState => ({
        activeFgItem: null,
        recipeItems: [],
        isLoading: false,
        isSaving: false,
        error: null,
        successMessage: null,
    }),

    getters: {
        totalMixWeight: (state): number => {
            return state.recipeItems.reduce((sum, item) => {
                const qty = Number(item.quantity) || 0;
                return sum + qty;
            }, 0);
        },
    },

    actions: {
        async fetchRecipe(fgItemId: number): Promise<boolean> {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await api.get<MasterRecipeResponse>(`/items/${fgItemId}/recipe`);
                if (response.data?.data) {
                    this.activeFgItem = response.data.data.fg_item;
                    this.recipeItems = response.data.data.recipe_items.map((r) => ({
                        ...r,
                        quantity: Number(r.quantity),
                    }));
                }
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to fetch master recipe.';
                return false;
            } finally {
                this.isLoading = false;
            }
        },

        async saveRecipe(fgItemId: number, items: RecipeItemInput[]): Promise<boolean> {
            this.isSaving = true;
            this.error = null;
            try {
                const payload = {
                    items: items.map((i) => ({
                        rm_item_id: Number(i.rm_item_id),
                        quantity: Number(i.quantity),
                        unit: i.unit,
                    })),
                };

                const response = await api.post<MasterRecipeResponse>(`/items/${fgItemId}/recipe`, payload);
                if (response.data?.data) {
                    this.activeFgItem = response.data.data.fg_item;
                    this.recipeItems = response.data.data.recipe_items.map((r) => ({
                        ...r,
                        quantity: Number(r.quantity),
                    }));
                }
                this.successMessage = 'Master recipe saved successfully.';
                return true;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Failed to save master recipe.';
                return false;
            } finally {
                this.isSaving = false;
            }
        },

        clearMessages(): void {
            this.error = null;
            this.successMessage = null;
        },
    },
});
