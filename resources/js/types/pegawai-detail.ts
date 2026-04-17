import type {
    Agama,
    GolonganDarah,
    JenisKelamin,
    JenjangPendidikan,
    StatusKepegawaian,
    StatusPegawai,
    StatusPerkawinan,
} from '@/types/kepegawaian';

export type ReferenceOption = {
    id: string;
    kode?: string;
    nama: string;
    golongan?: string;
    ruang?: string;
};

export type PegawaiDetail = {
    id: string;
    nip: string | null;
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
    no_karpeg: string | null;
    no_karis_karsu: string | null;
    npwp: string | null;
    no_bpjs_kesehatan: string | null;
    no_bpjs_ketenagakerjaan: string | null;
    no_taspen: string | null;
    foto: string | null;
    foto_url: string | null;
    keterangan: string | null;
    pangkat: ReferenceOption | null;
    jabatan: ReferenceOption | null;
    unit_kerja: ReferenceOption | null;
    user: {
        name: string;
        email: string;
    } | null;
    keluarga: Array<{
        id: string;
        nama: string;
        hubungan: string;
        jenis_kelamin: string;
        tanggal_lahir: string | null;
        pekerjaan: string | null;
    }>;
    riwayat_pangkat: Array<{
        id: string;
        pangkat: ReferenceOption | null;
        no_sk: string;
        tanggal_sk: string | null;
        tmt: string | null;
        masa_kerja_tahun: number;
        masa_kerja_bulan: number;
        is_aktif: boolean;
    }>;
    riwayat_jabatan: Array<{
        id: string;
        jabatan: ReferenceOption | null;
        unit_kerja: ReferenceOption | null;
        no_sk: string;
        tanggal_sk: string | null;
        tmt: string | null;
        is_aktif: boolean;
    }>;
    riwayat_pendidikan: Array<{
        id: string;
        jenjang: JenjangPendidikan;
        nama_sekolah: string;
        jurusan: string | null;
        tahun_lulus: number;
    }>;
    riwayat_diklat: Array<{
        id: string;
        nama_diklat: string;
        jenis_diklat: { nama: string } | null;
        penyelenggara: string;
        tanggal_mulai: string | null;
        tanggal_selesai: string | null;
        jam_pelajaran: number | null;
    }>;
    penghargaan: Array<{
        id: string;
        nama_penghargaan: string;
        jenis_penghargaan: { nama: string } | null;
        no_sk: string | null;
        tanggal_sk: string | null;
        tahun: number | null;
    }>;
    hukuman_disiplin: Array<{
        id: string;
        jenis_hukuman_disiplin: { nama: string } | null;
        pelanggaran: string;
        no_sk: string;
        tanggal_sk: string;
        tmt_berlaku: string;
        tmt_selesai: string | null;
    }>;
    dokumen_pegawai: Array<{
        id: string;
        jenis_dokumen: string;
        nomor_dokumen: string | null;
        tanggal_dokumen: string | null;
        file_path: string | null;
    }>;
};
