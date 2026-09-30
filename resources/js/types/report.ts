export interface StockReportKpis {
    total_rm_items: number;
    total_fg_items: number;
    total_inward_ton: number;
    total_consumed_ton: number;
    total_rm_outward_ton: number;
    total_fg_produced_cbm: number;
    total_fg_dispatched_cbm: number;
    total_fg_closing_cbm: number;
}

export interface StockReportItem {
    item_id: number;
    code: string;
    item_name: string;
    category: string;
    size: string | null;
    type: 'rm' | 'fg';
    primary_unit: string;
    secondary_unit: string;
    conversion_factor: number;
    opening_primary: number;
    opening_secondary: number;
    inward_primary: number;
    inward_secondary: number;
    consumed_primary: number;
    consumed_secondary: number;
    outward_primary: number;
    outward_secondary: number;
    closing_primary: number;
    closing_secondary: number;
    status: 'in_stock' | 'out_of_stock';
    is_stock_exempt: boolean;
}

export interface StockReportFilters {
    from_date: string;
    to_date: string;
    type: 'all' | 'rm' | 'fg';
    category: string;
    search: string;
}

export interface StockSummaryData {
    kpis: StockReportKpis;
    items: StockReportItem[];
    filters: StockReportFilters;
}

export interface StockLedgerItemInfo {
    id: number;
    code: string;
    name: string;
    category: string;
    size: string | null;
    type: 'rm' | 'fg';
    primary_unit: string;
    secondary_unit: string;
    conversion_factor: number;
}

export interface StockLedgerEntry {
    date: string;
    timestamp: number;
    module: 'inward' | 'production' | 'outward';
    action: 'in' | 'out';
    action_label: string;
    reference_no: string;
    batch_no?: string;
    party_name: string;
    vehicle_no?: string;
    destination?: string;
    qty_in_primary: number;
    qty_in_secondary: number;
    qty_out_primary: number;
    qty_out_secondary: number;
    balance_primary?: number;
    balance_secondary?: number;
    unit_primary: string;
    unit_secondary: string;
    remarks?: string;
}

export interface StockLedgerData {
    item: StockLedgerItemInfo;
    opening_stock: {
        primary: number;
        secondary: number;
        unit_primary: string;
        unit_secondary: string;
    };
    entries: StockLedgerEntry[];
    closing_stock: {
        primary: number;
        secondary: number;
        unit_primary: string;
        unit_secondary: string;
    };
    summary: {
        total_in_primary: number;
        total_in_secondary: number;
        total_out_primary: number;
        total_out_secondary: number;
        net_change_primary: number;
        net_change_secondary: number;
    };
}

export interface DispatchReportReceiptItem {
    id: number;
    payment_no: string;
    payment_date: string;
    amount: number;
    discount_amount: number;
    commission_amount: number;
    payment_mode: string;
    reference_no?: string;
    bank_name?: string;
    party_id?: number | null;
    party_name?: string;
    party?: {
        name?: string;
        code?: string;
        phone?: string;
        gstin?: string;
        city?: string;
        address?: string;
    } | null;
    commission_agent_name?: string;
    remarks?: string;
    is_locked?: boolean;
    allocated_paid?: number;
    allocated_discount?: number;
    allocated_commission?: number;
    items?: Array<{
        id?: number;
        stock_outward_id?: number;
        entry_no?: string;
        challan_no?: string;
        invoice_date?: string;
        invoice_amount?: number;
        discount_amount?: number;
        paid_amount?: number;
        commission_amount?: number;
        commission_agent_name?: string;
        remarks?: string;
    }>;
}

export interface DispatchReportRow {
    id: number;
    item_id: number | null;
    entry_no: string;
    date: string;
    party_id: number | null;
    party_name: string;
    party_code?: string;
    location: string;
    grade: string;
    opc: string;
    quant: number;
    unit: string;
    rate: number;
    advance: number;
    payment_mode: string;
    amount: number;
    receive_pay: number;
    pending: number;
    relief: number;
    discount?: number;
    commission: number;
    in_acc: string;
    seller: string;
    note: string;
    vehicle_no?: string;
    challan_no?: string;
    payment_status?: string;
    receipts?: DispatchReportReceiptItem[];
    primary_receipt_no?: string | null;
    primary_receipt_id?: number | null;
}

export interface DispatchReportTotals {
    count: number;
    total_qty: number;
    total_amount: number;
    total_advance: number;
    total_received: number;
    total_pending: number;
    total_relief: number;
    total_discount?: number;
    total_commission: number;
}

export interface DispatchReportSelectedParty {
    id: number;
    name: string;
    code?: string;
    phone?: string;
    address?: string;
    city?: string;
    gstin?: string;
}

