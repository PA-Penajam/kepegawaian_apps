import type { ReactNode } from 'react';

export type RefJenisDokumen = {
    id: string;
    nama: string;
    keterangan: string | null;
    created_at: string;
    updated_at: string;
};

export type RefStatusKepegawaian = {
    id: string;
    kode: string;
    nama: string;
    keterangan: string | null;
    created_at: string;
    updated_at: string;
};

export type RefStatusPegawai = {
    id: string;
    kode: string;
    nama: string;
    keterangan: string | null;
    created_at: string;
    updated_at: string;
};

export type RefRole = {
    id: string;
    nama: string;
    keterangan: string | null;
    is_system: boolean;
    permissions?: RefPermission[];
    created_at: string;
    updated_at: string;
};

export type RefPermission = {
    id: string;
    nama: string;
    group: string | null;
    keterangan: string | null;
    created_at: string;
    updated_at: string;
};

export type ReferensiFormData = {
    nama: string;
    keterangan?: string;
};

export type ReferensiWithKodeFormData = {
    kode: string;
    nama: string;
    keterangan?: string;
};

export type CrudTableColumn<T> = {
    key: keyof T | string;
    header: string;
    cell?: (item: T) => ReactNode;
};

export type PaginatedData<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number;
        last_page: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        path: string;
        per_page: number;
        to: number;
        total: number;
    };
};
