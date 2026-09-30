export interface FinancialMetaItem {
    id: number;
    name: string;
    code?: string;
    type?: string;
    type_label?: string;
    phone?: string;
    gstin?: string;
}

export interface FinancialReportMeta {
    customers: FinancialMetaItem[];
    suppliers: FinancialMetaItem[];
    agents: { id: number; name: string }[];
    parties: FinancialMetaItem[];
    payment_modes: string[];
    banks: string[];
    today: string;
    first_day_of_month: string;
}

// 1. Customer Outstanding & Ageing
export interface OutstandingInvoice {
    outward_id: number;
    challan_no: string;
    date: string;
    vehicle_no: string;
    site_location: string;
    grand_total: number;
    received_amount: number;
    discount_amount: number;
    balance_due: number;
    age_days: number;
    age_bracket: '0_15' | '16_30' | '31_60' | '60_plus';
    payment_status: string;
}

export interface CustomerOutstandingItem {
    id: number;
    name: string;
    code: string;
    phone: string;
    gstin: string;
    city: string;
    agent_name: string;
    total_invoiced: number;
    total_received: number;
    total_discount: number;
    total_outstanding: number;
    age_0_15: number;
    age_16_30: number;
    age_31_60: number;
    age_60_plus: number;
    invoices_count: number;
    invoices?: OutstandingInvoice[];
}

export interface CustomerOutstandingKpis {
    total_customers: number;
    total_invoiced: number;
    total_received: number;
    total_discount: number;
    total_outstanding: number;
    total_0_15: number;
    total_16_30: number;
    total_31_60: number;
    total_60_plus: number;
}

export interface CustomerOutstandingResponse {
    kpis: CustomerOutstandingKpis;
    customers: CustomerOutstandingItem[];
    filters: {
        as_of_date: string;
        customer_id: string | number;
        agent_id: string | number;
        ageing_bracket: string;
        search: string;
    };
}

// 2. Supplier Payables
export interface SupplierInwardItem {
    inward_id: number;
    inward_no: string;
    bill_no: string;
    date: string;
    vehicle_no: string;
    items_summary: string;
    total_qty: number;
    grand_total: number;
    paid_amount: number;
    discount_amount: number;
    balance_payable: number;
    age_days: number;
    age_bracket: '0_15' | '16_30' | '31_60' | '60_plus';
    payment_status: string;
}

export interface SupplierPayableItem {
    id: number;
    name: string;
    code: string;
    phone: string;
    gstin: string;
    city: string;
    total_inward_amount: number;
    total_paid: number;
    total_discount: number;
    total_payable: number;
    age_0_15: number;
    age_16_30: number;
    age_31_60: number;
    age_60_plus: number;
    inwards_count: number;
    inwards?: SupplierInwardItem[];
}

export interface SupplierPayablesKpis {
    total_suppliers: number;
    total_inward_amount: number;
    total_paid: number;
    total_discount: number;
    total_payable: number;
    total_0_15: number;
    total_16_30: number;
    total_31_60: number;
    total_60_plus: number;
}

export interface SupplierPayablesResponse {
    kpis: SupplierPayablesKpis;
    suppliers: SupplierPayableItem[];
    filters: {
        as_of_date: string;
        supplier_id: string | number;
        ageing_bracket: string;
        search: string;
    };
}

// 3. Cash & Bank Book
export interface CashBankTransaction {
    id: number;
    type: 'receipt' | 'payment';
    type_label: string;
    date: string;
    voucher_no: string;
    reference_no: string;
    party_id: number;
    party_name: string;
    party_type: string;
    payment_mode: string;
    bank_name: string | null;
    reference_number: string | null;
    remarks: string | null;
    is_locked: boolean;
    inflow: number;
    outflow: number;
    discount: number;
    running_balance: number;
}

export interface CashBankSummaryByMode {
    mode: string;
    inflow: number;
    outflow: number;
    net: number;
}

export interface CashBankSummaryByBank {
    bank: string;
    inflow: number;
    outflow: number;
    net: number;
}

export interface CashBankBookKpis {
    total_inflow: number;
    total_outflow: number;
    net_cashflow: number;
    transactions_count: number;
}

export interface CashBankBookResponse {
    kpis: CashBankBookKpis;
    summary_by_mode: CashBankSummaryByMode[];
    summary_by_bank: CashBankSummaryByBank[];
    transactions: CashBankTransaction[];
    filters: {
        from_date: string;
        to_date: string;
        payment_mode: string;
        bank_name: string;
        type: string;
        search: string;
    };
}

// 4. Party Ledger Statement
export interface PartyLedgerEntry {
    date: string;
    voucher_type: string;
    voucher_no: string;
    reference_no: string;
    particulars: string;
    debit: number;
    credit: number;
    balance: number;
    balance_type: 'Dr' | 'Cr';
    remarks: string | null;
}

export interface PartyLedgerPartyInfo {
    id: number;
    name: string;
    code: string;
    type: string;
    type_label: string;
    phone: string;
    gstin: string;
    address: string;
    city: string;
}

export interface PartyLedgerBalance {
    amount: number;
    type: 'Dr' | 'Cr';
}

export interface PartyLedgerTotals {
    total_debit: number;
    total_credit: number;
    net_movement: number;
}

export interface PartyLedgerResponse {
    party: PartyLedgerPartyInfo | null;
    opening_balance: PartyLedgerBalance;
    entries: PartyLedgerEntry[];
    totals: PartyLedgerTotals;
    closing_balance: PartyLedgerBalance;
    filters: {
        party_id: string | number;
        from_date: string;
        to_date: string;
    };
}
