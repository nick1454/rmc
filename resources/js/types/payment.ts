import type { Party } from './party';
import type { StockInward } from './inward';

export interface PurchasePaymentItemInput {
    stock_inward_id: number;
    paid_amount: number;
    remarks?: string;
    // UI Helpers
    inward_no?: string;
    challan_no?: string;
    challan_date?: string;
    grand_total?: number;
    already_paid?: number;
    balance_due?: number;
    materials_summary?: string;
}

export interface PurchasePaymentItem {
    id: number;
    purchase_payment_id: number;
    stock_inward_id: number;
    invoice_amount: number | string;
    paid_amount: number | string;
    remarks?: string | null;
    inward?: StockInward;
}

export interface PurchasePayment {
    id: number;
    payment_no: string;
    payment_date: string;
    party_id?: number | null;
    party_name?: string | null;
    payment_mode: string;
    reference_no?: string | null;
    bank_name?: string | null;
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
    items?: PurchasePaymentItem[];
    items_count?: number;
    party?: Party | null;
    creator?: { id: number; name: string } | null;
    updater?: { id: number; name: string } | null;
}

export interface PurchasePaymentForm {
    payment_no: string;
    payment_date: string;
    party_id: number | null;
    party_name: string;
    payment_mode: string;
    reference_no: string;
    bank_name: string;
    amount: number;
    remarks: string;
    items: PurchasePaymentItemInput[];
}

export interface PendingInwardInvoice {
    id: number;
    inward_no: string;
    challan_no?: string | null;
    challan_date?: string | null;
    grand_total: number;
    already_paid_others: number;
    current_allocated: number;
    balance_due: number;
    payment_status: 'unpaid' | 'partially_paid' | 'paid' | string;
    materials_summary: string;
}

export interface PurchasePaymentMeta {
    next_payment_no: string;
    suppliers: Party[];
    payment_modes: { value: string; label: string }[];
}
