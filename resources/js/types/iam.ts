// Tipe data untuk IAM (Identity and Access Management)

export type IamApplication = {
    id: number;
    nama: string;
    slug: string;
    url: string;
    deskripsi: string | null;
    api_key_display?: string; // API key yang sudah di-mask, bukan full key
    is_active: boolean;
    is_system: boolean;
    roles_count?: number;
    roles?: IamRole[];
    permissions?: IamPermission[];
    created_at: string;
    updated_at: string;
};

export type IamRole = {
    id: number;
    iam_application_id: number;
    nama: string;
    deskripsi: string | null;
    permissions?: IamPermission[];
    created_at: string;
    updated_at: string;
};

export type IamPermission = {
    id: number;
    iam_application_id: number;
    nama: string;
    slug: string;
    group: string | null;
    keterangan: string | null;
    deskripsi: string | null;
    created_at: string;
    updated_at: string;
};

export type IamUserRole = {
    id: number;
    user_id: number;
    iam_role_id: number;
    assigned_at: string;
    assigned_by: number;
    role?: IamRole;
    assignedByUser?: {
        id: number;
        name: string;
    };
};

export type IamUserAkses = {
    id: number;
    user_id: number;
    iam_role_id: number;
    assigned_at: string;
    assigned_by: number;
    role: IamRole & {
        application: IamApplication;
        permissions: IamPermission[];
    };
    assignedByUser: {
        id: number;
        name: string;
    };
};

export type IamAvailableApp = {
    id: number;
    nama: string;
    slug: string;
    roles: IamRole[];
};

// Interface untuk data paginasi dari Laravel
export interface IamPaginatedData<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}
