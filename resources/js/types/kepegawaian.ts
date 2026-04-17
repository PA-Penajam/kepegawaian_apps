/**
 * Tipe-tipe untuk domain kepegawaian PA Penajam
 * Mirror dari App\Enums\* di backend
 */

// Status Pegawai
export type StatusPegawai =
    | 'aktif'
    | 'mutasi_keluar'
    | 'pensiun'
    | 'meninggal'
    | 'diberhentikan';

export const StatusPegawaiLabels: Record<StatusPegawai, string> = {
    aktif: 'Aktif',
    mutasi_keluar: 'Mutasi Keluar',
    pensiun: 'Pensiun',
    meninggal: 'Meninggal',
    diberhentikan: 'Diberhentikan',
};

// Jenis Kelamin
export type JenisKelamin = 'laki_laki' | 'perempuan';

export const JenisKelaminLabels: Record<JenisKelamin, string> = {
    laki_laki: 'Laki-Laki',
    perempuan: 'Perempuan',
};

// Status Perkawinan
export type StatusPerkawinan =
    | 'belum_kawin'
    | 'kawin'
    | 'cerai_hidup'
    | 'cerai_mati';

export const StatusPerkawinanLabels: Record<StatusPerkawinan, string> = {
    belum_kawin: 'Belum Kawin',
    kawin: 'Kawin',
    cerai_hidup: 'Cerai Hidup',
    cerai_mati: 'Cerai Mati',
};

// Agama
export type Agama =
    | 'islam'
    | 'kristen'
    | 'katolik'
    | 'hindu'
    | 'buddha'
    | 'konghucu';

export const AgamaLabels: Record<Agama, string> = {
    islam: 'Islam',
    kristen: 'Kristen',
    katolik: 'Katolik',
    hindu: 'Hindu',
    buddha: 'Buddha',
    konghucu: 'Konghucu',
};

// Jenis Jabatan
export type JenisJabatan = 'struktural' | 'fungsional' | 'pelaksana';

export const JenisJabatanLabels: Record<JenisJabatan, string> = {
    struktural: 'Struktural',
    fungsional: 'Fungsional',
    pelaksana: 'Pelaksana',
};

// Golongan Darah
export type GolonganDarah = 'A' | 'B' | 'AB' | 'O';

export const GolonganDarahLabels: Record<GolonganDarah, string> = {
    A: 'A',
    B: 'B',
    AB: 'AB',
    O: 'O',
};

// Status Kepegawaian
export type StatusKepegawaian = 'pns' | 'pppk' | 'honorer';

export const StatusKepegawaianLabels: Record<StatusKepegawaian, string> = {
    pns: 'PNS',
    pppk: 'PPPK',
    honorer: 'Honorer',
};

// Hubungan Keluarga
export type HubunganKeluarga =
    | 'Suami'
    | 'Istri'
    | 'Anak'
    | 'AyahKandung'
    | 'IbuKandung';

export const HubunganKeluargaLabels: Record<HubunganKeluarga, string> = {
    Suami: 'Suami',
    Istri: 'Istri',
    Anak: 'Anak',
    AyahKandung: 'Ayah Kandung',
    IbuKandung: 'Ibu Kandung',
};

// Jenjang Pendidikan
export type JenjangPendidikan =
    | 'sd'
    | 'smp'
    | 'sma'
    | 'd1'
    | 'd2'
    | 'd3'
    | 'd4'
    | 's1'
    | 's2'
    | 's3';

export const JenjangPendidikanLabels: Record<JenjangPendidikan, string> = {
    sd: 'SD',
    smp: 'SMP',
    sma: 'SMA',
    d1: 'D1',
    d2: 'D2',
    d3: 'D3',
    d4: 'D4',
    s1: 'S1',
    s2: 'S2',
    s3: 'S3',
};

// Pagination
export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type KepegawaianPaginatedData<T> = {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export type PegawaiListSortBy = 'nip' | 'nama' | 'pangkat';

export type PegawaiListFilters = {
    search: string | null;
    golongan: string | null;
    unit_kerja: string | null;
    status_pegawai: StatusPegawai | null;
    sort_by: PegawaiListSortBy | null;
    sort_dir: 'asc' | 'desc' | null;
};

export type PegawaiGolonganOption = {
    id: string;
    kode: string;
    nama: string;
};

export type PegawaiUnitKerjaOption = {
    id: string;
    nama: string;
};

export type PegawaiListFilterOptions = {
    golongan: PegawaiGolonganOption[];
    unitKerja: PegawaiUnitKerjaOption[];
    statusPegawai: StatusPegawai[];
};

// Models
export type RefPangkat = {
    id: string;
    kode: string;
    nama: string;
    golongan: string;
    ruang: string;
};

export type RefJabatan = {
    id: string;
    nama: string;
    jenis: JenisJabatan;
    kelas: number | null;
    nilai_jabatan: number | null;
    indeks_jabatan: number | null;
};

export type RefUnitKerja = {
    id: string;
    nama: string;
    kode: string | null;
    parent_id: string | null;
    level: number;
};

export type Pegawai = {
    id: string;
    nip: string | null;
    nip_lama: string | null;
    nama_lengkap: string;
    tempat_lahir: string;
    tanggal_lahir: string;
    jenis_kelamin: JenisKelamin;
    agama: Agama;
    status_perkawinan: StatusPerkawinan;
    golongan_darah: GolonganDarah | null;
    alamat: string | null;
    no_telepon: string | null;
    email: string | null;
    status_kepegawaian: StatusKepegawaian;
    status_pegawai: StatusPegawai;
    tmt_cpns: string | null;
    tmt_pns: string | null;
    pendidikan_terakhir: string | null;
    tanggal_masuk: string | null;
    tanggal_pensiun_bup: string | null;
    ref_pangkat_id: string | null;
    ref_jabatan_id: string | null;
    ref_unit_kerja_id: string | null;
    no_karpeg: string | null;
    no_karis_karsu: string | null;
    npwp: string | null;
    no_bpjs_kesehatan: string | null;
    no_bpjs_ketenagakerjaan: string | null;
    no_taspen: string | null;
    foto: string | null;
    foto_url: string | null;
    keterangan: string | null;
    created_at: string;
    updated_at: string;

    // Relations
    pangkat?: Pick<RefPangkat, 'id' | 'kode' | 'nama'> | null;
    jabatan?: Pick<RefJabatan, 'id' | 'nama'> | null;
    unit_kerja?: Pick<RefUnitKerja, 'id' | 'nama'> | null;
};
