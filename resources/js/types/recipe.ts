import type { Item } from './item';

export interface RecipeItem {
    id?: number;
    fg_item_id: number;
    rm_item_id: number;
    quantity: number;
    unit: string;
    rm_item?: Item;
    created_by?: number | null;
    created_at?: string;
    updated_at?: string;
}

export interface RecipeItemInput {
    id?: number;
    rm_item_id: number;
    quantity: number;
    unit: string;
}

export interface MasterRecipeResponse {
    status: string;
    message?: string;
    data: {
        fg_item: Item;
        recipe_items: RecipeItem[];
    };
}
