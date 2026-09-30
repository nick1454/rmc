export type PartyType = 'supplier' | 'customer' | 'both' | 'agent';
export type PartyStatus = 'active' | 'inactive';

export interface Party {
    id: number;
    name: string;
    type: PartyType;
    code: string;
    contact_person?: string | null;
    phone?: string | null;
    email?: string | null;
    gstin?: string | null;
    pan_no?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    pincode?: string | null;
    status: PartyStatus;
    remarks?: string | null;
    inwards_count?: number;
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

export interface PartyForm {
    name: string;
    type: PartyType;
    code: string;
    contact_person: string;
    phone: string;
    email: string;
    gstin: string;
    pan_no: string;
    address: string;
    city: string;
    state: string;
    pincode: string;
    status: PartyStatus;
    remarks: string;
}

export interface PartyMetaResponse {
    suppliers: Party[];
    customers: Party[];
    agents?: Party[];
    next_supplier_code: string;
    next_customer_code: string;
    next_agent_code?: string;
}

export interface PartyPaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
