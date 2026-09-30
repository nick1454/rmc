export type ItemCategory = 'Aggregate' | 'Cement' | 'Dust' | 'AddMixture' | 'Silo Pouch' | 'RMC';
export type ItemType = 'rm' | 'fg';

export interface ItemBatchStock {
    batch_no: string;
    batch_label: string;
    reference_no?: string;
    date?: string;
    source_location?: string;
    initial_qty?: number;
    dispatched_qty?: number;
    available_qty: number;
    unit: string;
    secondary_qty?: number;
    secondary_unit?: string;
    type?: string;
}

export interface Item {
    id: number;
    category: ItemCategory;
    size?: string | null;
    secondary_unit: string;
    primary_unit: string;
    conversion_factor: number;
    type: ItemType | string;
    hsn?: string | null;
    gst_per: number;
    batch_no?: string;
    available_primary?: number;
    available_secondary?: number;
    is_stock_exempt?: boolean;
    in_stock?: boolean;
    batches?: ItemBatchStock[];
    created_by?: number | null;
    deleted_by?: number | null;
    recipe_items_count?: number;
    creator?: {
        id: number;
        name: string;
    } | null;
    deleter?: {
        id: number;
        name: string;
    } | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
}

export interface ItemForm {
    category: ItemCategory;
    size: string;
    secondary_unit: string;
    primary_unit: string;
    conversion_factor: number;
    type: ItemType | string;
    hsn: string;
    gst_per: number;
}

export interface ItemsResponse {
    status: string;
    data: Item[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface ItemMetaResponse {
    status: string;
    data: {
        categories: string[];
        sizes: string[];
        types: string[];
        primary_units: string[];
        secondary_units: string[];
    };
}
