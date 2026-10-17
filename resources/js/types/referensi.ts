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
    /** Jumlah pegawai yang ter-assign (diisi via withCount dengan alias pegawai_count). */
    pegawai_count?: number;
    /** Jumlah permission milik role (diisi via withCount). */
    permissions_count?: number;
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
