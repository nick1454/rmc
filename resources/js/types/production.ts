import type { Item } from './item';

export interface ProductionRecipeItemInput {
    rm_item_id: number;
    recipe_qty_per_cbm: number;
    actual_qty_per_cbm: number;
    unit: string;
    total_qty: number;
    // UI Helpers
    rm_item?: Item;
    available_stock?: number;
    is_short?: boolean;
    shortage_qty?: number;
    is_exempt?: boolean;
}

export interface ProductionForm {
    production_no: string;
    production_date: string;
    fg_item_id: number;
    cbm: number;
    plant_name: string;
    batch_no: string;
    remarks: string;
    recipe_items: ProductionRecipeItemInput[];
}

export interface ProductionRecipeItem {
    id: number;
    production_id: number;
    rm_item_id: number;
    recipe_qty_per_cbm: number;
    actual_qty_per_cbm: number;
    unit: string;
    total_qty: number;
    rm_item?: Item;
}

export interface ProductionConsumption {
    id: number;
    production_id: number;
    item_id: number;
    consumed_qty: number;
    unit: string;
    consumed_primary_qty: number;
    primary_unit: string;
    consumed_date: string;
    item?: Item;
}

export interface Production {
    id: number;
    production_no: string;
    production_date: string;
    fg_item_id: number;
    cbm: number;
    plant_name: string;
    batch_no?: string | null;
    remarks?: string | null;
    fg_item?: Item;
    recipe_items?: ProductionRecipeItem[];
    consumptions?: ProductionConsumption[];
    created_by?: number;
    creator?: {
        id: number;
        name: string;
    };
    updater?: {
        id: number;
        name: string;
    };
    created_at?: string;
    updated_at?: string;
}

export interface RmStockItem {
    item_id: number;
    item_name: string;
    category: string;
    size: string;
    primary_unit: string;
    secondary_unit: string;
    conversion_factor: number;
    total_inward_primary: number;
    total_inward_secondary: number;
    total_consumed_primary: number;
    total_consumed_secondary: number;
    available_primary: number;
    available_secondary: number;
}

export interface StockShortage {
    item_id: number;
    item_name: string;
    category: string;
    size: string;
    required_qty: number;
    available_qty: number;
    shortage_qty: number;
    unit: string;
    primary_required_qty: number;
    primary_available_qty: number;
    primary_shortage_qty: number;
    primary_unit: string;
}

export interface StockValidationResult {
    is_valid: boolean;
    shortages: StockShortage[];
    stock_snapshot?: Record<number, RmStockItem>;
}

export interface ProductionMeta {
    fg_items: Item[];
    rm_stock: RmStockItem[];
    next_production_no: string;
    next_batch_no?: string;
}

export interface ProductionPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
