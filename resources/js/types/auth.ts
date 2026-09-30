export interface User {
    id: number;
    name: string;
    email: string;
    role?: string;
    plant?: string;
    avatar?: string;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
}

export interface AuthResponse {
    status: string;
    message?: string;
    token: string;
    user: User;
}

export interface UserResponse {
    status: string;
    user: User;
}
