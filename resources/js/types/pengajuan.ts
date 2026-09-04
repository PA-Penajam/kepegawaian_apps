export type StatusPengajuan = 'pending' | 'approved' | 'rejected';

export type PengajuanListItem = {
    id: string;
    domain: string;
    aksi: string;
    status: StatusPengajuan;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    validator: string | null;
};

export type DiffItem = {
    field: string;
    label: string;
    before: string | number | null;
    after: string | number | null;
    change_type: 'added' | 'removed' | 'updated' | 'unchanged';
};

export type PengajuanDetail = {
    id: string;
    nomor_pengajuan: string;
    domain: string;
    aksi: string;
    status: StatusPengajuan;
    before_payload: Record<string, unknown> | null;
    after_payload: Record<string, unknown>;
    lampiran_paths: string[] | null;
    alasan_penolakan: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    validator: { id: string; nama_lengkap: string } | null;
};
