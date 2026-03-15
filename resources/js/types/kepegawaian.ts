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
    | 'suami'
    | 'istri'
    | 'anak'
    | 'ayah_kandung'
    | 'ibu_kandung';

export const HubunganKeluargaLabels: Record<HubunganKeluarga, string> = {
    suami: 'Suami',
    istri: 'Istri',
    anak: 'Anak',
    ayah_kandung: 'Ayah Kandung',
    ibu_kandung: 'Ibu Kandung',
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
