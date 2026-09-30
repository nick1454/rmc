import type { Item } from './item';
import type { Party } from './party';

export interface StockInwardItemInput {
    item_id: number;
    qty_primary: number;
    unit_primary: string;
    qty_secondary: number;
    unit_secondary: string;
    conversion_factor: number;
    rate_per_unit?: number;
    taxable_amount?: number;
    gst_rate?: number;
    gst_amount?: number;
    total_amount?: number;
    remarks?: string;
    item?: Item;
}

export interface StockInwardItem {
    id: number;
    stock_inward_id: number;
    item_id: number;
    qty_primary: number | string;
    unit_primary: string;
    qty_secondary: number | string;
    unit_secondary: string;
    conversion_factor: number | string;
    rate_per_unit?: number | string;
    taxable_amount?: number | string;
    gst_rate?: number | string;
    gst_amount?: number | string;
    total_amount?: number | string;
    remarks?: string | null;
    item?: Item;
}

export interface StockInward {
    id: number;
    inward_no: string;
    inward_date: string;
    supplier_party_id?: number | null;
    supplier_name?: string | null;
    challan_no?: string | null;
    challan_date?: string | null;
    supplier_bill_no?: string | null;
    supplier_bill_date?: string | null;
    vehicle_no?: string | null;
    driver_name?: string | null;
    driver_phone?: string | null;
    received_by_user_id?: number | null;
    remarks?: string | null;
    subtotal_amount?: number | string;
    tax_amount?: number | string;
    freight_amount?: number | string;
    other_charges?: number | string;
    discount_amount?: number | string;
    grand_total?: number | string;
    paid_amount?: number | string;
    payment_status?: 'unpaid' | 'partially_paid' | 'paid' | string;
    created_by?: number | null;
    updated_by?: number | null;
    created_at?: string;
    updated_at?: string;
    items?: StockInwardItem[];
    supplier?: Party | null;
    creator?: { id: number; name: string } | null;
    received_by?: { id: number; name: string } | null;
}

export interface StockInwardForm {
    inward_no: string;
    inward_date: string;
    supplier_party_id?: number | null;
    supplier_name: string;
    challan_no: string;
    challan_date: string;
    supplier_bill_no: string;
    supplier_bill_date: string;
    vehicle_no: string;
    driver_name: string;
    driver_phone: string;
    received_by_user_id?: number | null;
    remarks: string;
    freight_amount: number;
    other_charges: number;
    discount_amount: number;
    items: StockInwardItemInput[];
}

export interface StockInwardMeta {
    next_inward_no: string;
    suppliers: Party[];
    rm_items: Item[];
    users: { id: number; name: string }[];
}
