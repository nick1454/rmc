import type { Party } from './party';
import type { StockOutward } from './outward';

export interface OutwardPaymentItemInput {
    stock_outward_id: number;
    paid_amount: number;
    discount_amount?: number;
    commission_agent_id?: number | null;
    commission_agent_name?: string;
    commission_amount?: number;
    remarks?: string;
    // UI Helpers
    entry_no?: string;
    challan_no?: string;
    invoice_date?: string;
    total_amount?: number;
    already_paid?: number;
    already_discounted?: number;
    balance_due?: number;
    items_summary?: string;
}

export interface OutwardPaymentItem {
    id: number;
    outward_payment_id: number;
    stock_outward_id: number;
    invoice_amount: number | string;
    paid_amount: number | string;
    discount_amount?: number | string;
    commission_agent_id?: number | null;
    commission_agent_name?: string | null;
    commission_amount?: number | string;
    remarks?: string | null;
    outward?: StockOutward;
    commissionAgent?: Party | null;
}

export interface OutwardPayment {
    id: number;
    payment_no: string;
    payment_date: string;
    party_id?: number | null;
    party_name?: string | null;
    payment_mode: string;
    reference_no?: string | null;
    bank_name?: string | null;
    discount_amount?: number | string;
    commission_agent_id?: number | null;
    commission_agent_name?: string | null;
    commission_amount?: number | string;
    amount: number | string;
    remarks?: string | null;
    is_locked?: boolean;
    locked_at?: string | null;
    locked_by?: number | { id: number; name: string } | null;
    lockedBy?: { id: number; name: string } | null;
    created_by?: number | null;
    updated_by?: number | null;
    created_at?: string;
    updated_at?: string;
    items?: OutwardPaymentItem[];
    items_count?: number;
    party?: Party | null;
    commissionAgent?: Party | null;
    creator?: { id: number; name: string } | null;
    updater?: { id: number; name: string } | null;
}

export interface OutwardPaymentForm {
    payment_no: string;
    payment_date: string;
    party_id: number | null;
    party_name: string;
    payment_mode: string;
    reference_no: string;
    bank_name: string;
    discount_amount: number;
    commission_agent_id?: number | null;
    commission_agent_name?: string;
    commission_amount: number;
    amount: number;
    remarks: string;
    items: OutwardPaymentItemInput[];
}

export interface PendingOutwardInvoice {
    id: number;
    entry_no: string;
    challan_no?: string | null;
    invoice_date?: string | null;
    destination?: string | null;
    agent_id?: number | null;
    agent_name?: string | null;
    total_amount: number;
    already_paid_others: number;
    already_discounted_others?: number;
    current_allocated: number;
    current_discount?: number;
    balance_due: number;
    payment_status: 'unpaid' | 'partially_paid' | 'paid' | string;
    items_summary: string;
}

export interface PendingOutwardSale {
    id: number;
    entry_no: string;
    entry_type: string;
    party_id: number;
    party_name: string;
    party_code?: string;
    party_city?: string;
    party_phone?: string;
    agent_name?: string;
    invoice_date: string;
    challan_no: string;
    vehicle_no: string;
    destination: string;
    total_amount: number;
    already_paid: number;
    already_discounted: number;
    balance_due: number;
    payment_status: string;
    items_summary: string;
}

export interface OutwardPaymentMeta {
    next_payment_no: string;
    customers: Party[];
    agents: Party[];
    payment_modes: { value: string; label: string }[];
}
