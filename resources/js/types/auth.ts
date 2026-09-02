export type User = {
    id: string;
    nama_lengkap: string;
    nip: string;
    email: string | null;
    foto: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    roles: string[];
    permissions: string[];
    pending_pengajuan_count?: number;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** true jika user login via SSO PA Penajam */
    sso?: boolean;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
