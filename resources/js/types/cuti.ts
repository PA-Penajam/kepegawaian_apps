/**
 * Tipe-tipe untuk domain Cuti (Leave Management)
 */

import type { KepegawaianPaginatedData } from './kepegawaian';

// === State & Enum ===

export type CutiState =
    | 'DRAFT'
    | 'DIAJUKAN'
    | 'DIVERIFIKASI'
    | 'DISETUJUI_ATASAN'
    | 'DITERUSKAN_INSTANSI_ATASAN'
    | 'DISETUJUI'
    | 'DITOLAK_KEPEGAWAIAN'
    | 'DITOLAK_ATASAN'
    | 'DITOLAK_PEJABAT'
    | 'DITOLAK_INSTANSI_ATASAN'
    | 'DIBATALKAN'
    | 'DICABUT_SETELAH_DISETUJUI';

export const CutiStateLabels: Record<CutiState, string> = {
    DRAFT: 'Draft',
    DIAJUKAN: 'Diajukan',
    DIVERIFIKASI: 'Diverifikasi',
    DISETUJUI_ATASAN: 'Disetujui Atasan',
    DITERUSKAN_INSTANSI_ATASAN: 'Diteruskan ke Instansi Atasan',
    DISETUJUI: 'Disetujui',
    DITOLAK_KEPEGAWAIAN: 'Ditolak Kepegawaian',
    DITOLAK_ATASAN: 'Ditolak Atasan',
    DITOLAK_PEJABAT: 'Ditolak Pejabat',
    DITOLAK_INSTANSI_ATASAN: 'Ditolak Instansi Atasan',
    DIBATALKAN: 'Dibatalkan',
    DICABUT_SETELAH_DISETUJUI: 'Dicabut Setelah Disetujui',
};

export type ApproverRole =
    'petugas_kepegawaian' | 'atasan_langsung' | 'pejabat_berwenang';

// === Model Types ===

export type CutiPengajuanPegawai = {
    id: string;
    nip: string;
    nama_lengkap: string;
};

export type CutiJenisCuti = {
    kode: string;
    nama: string;
};

export type CutiJenisMaster = {
    kode: string;
    nama: string;
    saldo_driven: boolean;
    butuh_lampiran: boolean;
    boleh_dicabut_setelah_disetujui?: boolean;
    penandatangan_surat?: 'ketua' | 'wakil_ketua' | 'panitera' | 'sekretaris';
    aktif?: boolean;
};

export type StatusSuratDinas =
    | 'BELUM_TERKIRIM'
    | 'MENUNGGU_NOMOR'
    | 'PERLU_PERBAIKAN'
    | 'NOMOR_TERBIT'
    | 'FINAL_TERSEDIA'
    | 'TERKIRIM'
    | 'DIBATALKAN';

export type SuratDinasRequestSummary = {
    id: string;
    jenis: string;
    klasifikasi: string;
    penandatangan: 'ketua' | 'wakil_ketua' | 'panitera' | 'sekretaris';
    perihal: string;
    tujuan_nama: string;
    tujuan_instansi: string | null;
    status: StatusSuratDinas;
    sisukma_public_id: string | null;
    nomor_agenda: string | null;
    nomor_surat: string | null;
    catatan_revisi: string | null;
    alasan_void: string | null;
    final_uploaded_at: string | null;
    last_error: string | null;
};

export type CutiKewenangan = {
    id: string;
    jenis_cuti_kode: string;
    jenis_jabatan: string;
    pemberi_izin: 'pta' | 'ma';
    instansi_nama: string;
    nama_aplikasi: string | null;
    url_aplikasi: string | null;
    aktif: boolean;
    jenis_cuti?: CutiJenisCuti;
};

export type CutiPerizinanEksternal = {
    id: string;
    pengajuan_id: string;
    pemberi_izin: 'pta' | 'ma';
    instansi_nama: string;
    nomor_registrasi_eksternal: string | null;
    tanggal_pengajuan_eksternal: string | null;
    catatan_pengajuan: string | null;
    sisukma_incoming_mail_public_id: string | null;
    sisukma_agenda_number: string | null;
    nomor_sk: string | null;
    tanggal_sk: string | null;
    keputusan: 'disetujui' | 'ditolak' | null;
    catatan_keputusan: string | null;
    recorded_by_nip: string | null;
    decided_at: string | null;
};

export type CutiLampiran = {
    id: string;
    pengajuan_id: string;
    jenis_lampiran: string;
    nama_file_asli: string;
    path_file: string;
    mime_type: string;
    size_bytes: number;
    checksum_sha256: string;
    uploaded_by_nip: string;
    created_at: string;
};

export type CutiApprovalStep = {
    id: string;
    pengajuan_id: string;
    role: string;
    action: string;
    aktor_pegawai_nip: string;
    catatan: string | null;
    acted_at: string;
    aktor?: CutiPengajuanPegawai;
};

export type CutiPengajuan = {
    id: string;
    nomor_pengajuan: string | null;
    pegawai_nip: string;
    jenis_cuti_kode: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    jumlah_hari_kerja: number;
    alasan: string | null;
    alamat_selama_cuti: string | null;
    nomor_telp_selama_cuti: string | null;
    state: CutiState;
    petugas_kepegawaian_current_nip?: string | null;
    atasan_langsung_current_nip?: string | null;
    pejabat_berwenang_current_nip?: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    cancelled_at: string | null;
    rejection_reason: string | null;
    created_at?: string;
    updated_at?: string;
    pegawai?: CutiPengajuanPegawai;
    jenis_cuti?: CutiJenisCuti;
    lampiran?: CutiLampiran[];
    approval_steps?: CutiApprovalStep[];
    state_history?: CutiStateHistory[];
    atasan_langsung_current?: CutiPengajuanPegawai | null;
    pejabat_berwenang_current?: CutiPengajuanPegawai | null;
    petugas_kepegawaian_current?: CutiPengajuanPegawai | null;
    surat_izin_request?: SuratDinasRequestSummary | null;
    perizinan_eksternal?: CutiPerizinanEksternal | null;
};

export type CutiStateHistory = {
    id: string;
    pengajuan_id?: string;
    state_from: string | null;
    state_to: string;
    aktor_pegawai_nip?: string | null;
    aktor?: CutiPengajuanPegawai | null;
    catatan?: string | null;
    created_at: string;
};

// === Saldo ===

export type SaldoBucketData = {
    CT: number;
    tahun: number;
    /** Hak awal (alokasi asli dari cuti_alokasi_tahunan.hak_awal) untuk bucket tahun ini */
    hak_awal: number;
};

export type AlokasiListItem = {
    id: string;
    pegawai_nip: string;
    pegawai: {
        id: string;
        nip: string;
        nama_lengkap: string;
    } | null;
    jenis_cuti_kode: string;
    tahun_hak: number;
    hak_awal: number;
    saldo_saat_ini: number;
};

export type AlokasiPaginated = KepegawaianPaginatedData<AlokasiListItem>;

// === Audit / Activity Log ===

export type ActivityLogEntry = {
    id: number;
    log_name: string;
    description: string;
    subject_type: string;
    subject_id: string;
    causer_type?: string;
    causer_id?: string;
    causer?: { nip: string; nama: string };
    attribute_changes?: {
        old?: Record<string, unknown>;
        attributes?: Record<string, unknown>;
    };
    properties: {
        old?: Record<string, unknown>;
        attributes?: Record<string, unknown>;
    };
    created_at: string;
};

export type ActivityLogPaginated = KepegawaianPaginatedData<ActivityLogEntry>;

// Pagination generik untuk cuti
export type CutiPaginatedData<T> = KepegawaianPaginatedData<T>;

// Badge variant mapping berdasarkan state
export const CutiStateBadgeVariant: Record<
    CutiState,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    DRAFT: 'outline',
    DIAJUKAN: 'secondary',
    DIVERIFIKASI: 'secondary',
    DISETUJUI_ATASAN: 'secondary',
    DITERUSKAN_INSTANSI_ATASAN: 'secondary',
    DISETUJUI: 'default',
    DITOLAK_KEPEGAWAIAN: 'destructive',
    DITOLAK_ATASAN: 'destructive',
    DITOLAK_PEJABAT: 'destructive',
    DITOLAK_INSTANSI_ATASAN: 'destructive',
    DIBATALKAN: 'outline',
    DICABUT_SETELAH_DISETUJUI: 'destructive',
};
