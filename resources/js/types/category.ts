export type CategoryStatus = 'active' | 'inactive';

export interface Category {
    id: number;
    name: string;
    status: CategoryStatus;
    items_count?: number;
    created_by?: number | null;
    updated_by?: number | null;
    deleted_by?: number | null;
    creator?: {
        id: number;
        name: string;
    } | null;
    updater?: {
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

export interface CategoryForm {
    name: string;
    status: CategoryStatus;
}

export interface CategoriesResponse {
    status: string;
    data: Category[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}
