import type { Item } from './item';
import type { Party } from './party';

export interface StockOutwardItemInput {
    id?: number;
    item_id: number;
    qty: number;
    unit: string;
    secondary_qty: number;
    secondary_unit: string;
    conversion_factor: number;
    rate?: number;
    pump_cost?: number;
    discount_percent?: number;
    discount_amount?: number;
    gst_rate?: number;
    taxable_amount?: number;
    gst_amount?: number;
    total_amount?: number;
    commission_agent_id?: number | null;
    commission_agent_name?: string;
    commission_rate?: number;
    commission_amount?: number;
    lotno?: string;
    remarks?: string;
    item?: Item;
}

export interface StockOutwardItem {
    id: number;
    stock_outward_id: number;
    item_id: number;
    qty: number | string;
    unit: string;
    secondary_qty: number | string;
    secondary_unit: string;
    conversion_factor: number | string;
    rate?: number | string;
    pump_cost?: number | string;
    discount_percent?: number | string;
    discount_amount?: number | string;
    taxable_amount?: number | string;
    gst_rate?: number | string;
    gst_amount?: number | string;
    total_amount?: number | string;
    commission_agent_id?: number | null;
    commission_agent_name?: string | null;
    commission_rate?: number | string;
    commission_amount?: number | string;
    lotno?: string | null;
    remarks?: string | null;
    item?: Item;
    commissionAgent?: Party | null;
}

export interface StockOutward {
    id: number;
    entry_no: string;
    entry_type: 'sales' | 'transfer' | 'return' | 'sample' | 'other' | string;
    party_id?: number | null;
    customer?: string | null;
    agent_id?: number | null;
    agent_name?: string | null;
    invoice_date: string;
    challan_no?: string | null;
    challan_date?: string | null;
    vehicle_no?: string | null;
    driver_name?: string | null;
    driver_phone?: string | null;
    destination?: string | null;
    plant_name?: string | null;
    subtotal?: number | string;
    tax_amount?: number | string;
    discount_amount?: number | string;
    commission_amount?: number | string;
    freight_charges?: number | string;
    total_amount?: number | string;
    paid_amount?: number | string;
    payment_discount_amount?: number | string;
    payment_status?: 'unpaid' | 'partially_paid' | 'paid' | string;
    remarks?: string | null;
    created_by?: number | null;
    updated_by?: number | null;
    created_at?: string;
    updated_at?: string;
    items?: StockOutwardItem[];
    party?: Party | null;
    agent?: Party | null;
    creator?: { id: number; name: string } | null;
    updater?: { id: number; name: string } | null;
}

export interface StockOutwardForm {
    entry_no: string;
    entry_type: string;
    party_id?: number | null;
    customer: string;
    agent_id?: number | null;
    agent_name: string;
    invoice_date: string;
    challan_no: string;
    challan_date: string;
    vehicle_no: string;
    driver_name: string;
    driver_phone: string;
    destination: string;
    plant_name: string;
    discount_amount: number;
    commission_amount: number;
    freight_charges: number;
    remarks: string;
    items: StockOutwardItemInput[];
}

export interface StockOutwardMeta {
    next_entry_no: string;
    parties: Party[];
    customers: Party[];
    agents: Party[];
    items: Item[];
    users: { id: number; name: string }[];
}
