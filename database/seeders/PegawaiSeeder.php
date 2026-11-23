<?php

namespace Database\Seeders;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PegawaiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = $this->getSyntheticPegawaiData();

        foreach ($rawData as $item) {
            $tanggalMasuk = $this->parseTanggalIndonesia($item['tmt']);
            $tanggalLahir = $this->extractTanggalLahirDariNip($item['nip']);
            $statusKepegawaian = $this->resolveStatusKepegawaian($item['gol']);
            $jenisKelamin = substr($item['nip'], 14, 1) === '2'
                ? JenisKelamin::Perempuan->value
                : JenisKelamin::LakiLaki->value;

            Pegawai::query()->updateOrCreate(
                ['nip' => $item['nip']],
                [
                    'nip' => $item['nip'],
                    'nip_lama' => null,
                    'nama_lengkap' => Str::title(strtolower($item['nama'])),
                    'tempat_lahir' => fake('id_ID')->city(),
                    'tanggal_lahir' => $tanggalLahir,
                    'jenis_kelamin' => $jenisKelamin,
                    'agama' => Agama::Islam->value,
                    'status_perkawinan' => fake()->randomElement(StatusPerkawinan::cases())->value,
                    'golongan_darah' => null,
                    'alamat' => fake('id_ID')->address(),
                    'no_telepon' => fake()->numerify('08##########'),
                    'email' => sprintf('pegawai.%d@pa-penajam.go.id', $item['no']),
                    'status_kepegawaian' => $statusKepegawaian->value,
                    'status_pegawai' => StatusPegawai::Aktif->value,
                    'tmt_cpns' => $statusKepegawaian === StatusKepegawaian::PNS ? Carbon::parse($tanggalMasuk)->subYear()->toDateString() : null,
                    'tmt_pns' => $statusKepegawaian === StatusKepegawaian::PNS ? $tanggalMasuk : null,
                    'pendidikan_terakhir' => $this->resolvePendidikan($item['nama']),
                    'tanggal_masuk' => $tanggalMasuk,
                    'tanggal_pensiun_bup' => Carbon::parse($tanggalLahir)->addYears(60)->toDateString(),
                    'ref_pangkat_id' => $this->resolvePangkatId($item['gol']),
                    'ref_jabatan_id' => $this->resolveJabatanId($item['jabatan'], $item['unit_kerja']),
                    'ref_unit_kerja_id' => $this->resolveUnitKerjaId($item['jabatan'], $item['unit_kerja']),
                    'no_karpeg' => null,
                    'no_karis_karsu' => null,
                    'npwp' => null,
                    'no_bpjs_kesehatan' => null,
                    'no_bpjs_ketenagakerjaan' => null,
                    'no_taspen' => null,
                    'foto' => null,
                    'keterangan' => null,
                ],
            );
        }

        // Seed menggunakan dataset sintetis internal tanpa data PII asli
        $this->command?->info('Seeded '.Pegawai::query()->count().' pegawai sintetis.');
    }

    /**
     * Mengembalikan dataset pegawai sintetis untuk keperluan seeding pengembangan dan pengujian.
     *
     * @return array<int, array{no: int, nip: string, nama: string, jabatan: string, unit_kerja: string, gol: string, tmt: string}>
     */
    private function getSyntheticPegawaiData(): array
    {
        return [
            [
                'no' => 1,
                'nip' => '197804122002121001',
                'nama' => 'Dr. Hendra Wijaya, S.H., M.H.',
                'jabatan' => 'Ketua',
                'unit_kerja' => 'Pengadilan Agama Penajam',
                'gol' => 'IV/b',
                'tmt' => '1 Maret 2024',
            ],
            [
                'no' => 2,
                'nip' => '198006202005012001',
                'nama' => 'Dra. Ratna Dewi, S.H., M.Hum.',
                'jabatan' => 'Wakil Ketua',
                'unit_kerja' => 'Pengadilan Agama Penajam',
                'gol' => 'IV/a',
                'tmt' => '1 April 2024',
            ],
            [
                'no' => 3,
                'nip' => '198208152008051001',
                'nama' => 'Bambang Suryanto, S.H.I., M.H.',
                'jabatan' => 'Hakim',
                'unit_kerja' => 'Pengadilan Agama Penajam',
                'gol' => 'III/d',
                'tmt' => '1 Oktober 2022',
            ],
            [
                'no' => 4,
                'nip' => '197911032004041002',
                'nama' => 'Agus Setiawan, S.H.',
                'jabatan' => 'Panitera',
                'unit_kerja' => 'Kepaniteraan',
                'gol' => 'IV/a',
                'tmt' => '1 April 2023',
            ],
            [
                'no' => 5,
                'nip' => '198103222006041001',
                'nama' => 'Dedy Kurniawan, S.E., M.Si.',
                'jabatan' => 'Sekretaris',
                'unit_kerja' => 'Kesekretariatan',
                'gol' => 'IV/a',
                'tmt' => '1 Oktober 2023',
            ],
            [
                'no' => 6,
                'nip' => '198407102009042001',
                'nama' => 'Rina Kartika, S.H.I.',
                'jabatan' => 'Panitera Muda Permohonan',
                'unit_kerja' => 'Kepaniteraan | Panitera Muda Permohonan',
                'gol' => 'III/d',
                'tmt' => '1 April 2022',
            ],
            [
                'no' => 7,
                'nip' => '198609182011011003',
                'nama' => 'Fajar Pratama, S.H.',
                'jabatan' => 'Panitera Muda Gugatan',
                'unit_kerja' => 'Kepaniteraan | Panitera Muda Gugatan',
                'gol' => 'III/c',
                'tmt' => '1 Oktober 2021',
            ],
            [
                'no' => 8,
                'nip' => '198702142012122001',
                'nama' => 'Lestari Wulandari, S.H.',
                'jabatan' => 'Panitera Muda Hukum',
                'unit_kerja' => 'Kepaniteraan | Panitera Muda Hukum',
                'gol' => 'III/c',
                'tmt' => '1 April 2022',
            ],
            [
                'no' => 9,
                'nip' => '198505152010012002',
                'nama' => 'Siti Aminah, S.H.',
                'jabatan' => 'Kepala Subbagian Kepegawaian, Organisasi, dan Tatalaksana',
                'unit_kerja' => 'Subbagian Kepegawaian, Organisasi, dan Tatalaksana',
                'gol' => 'III/d',
                'tmt' => '1 April 2022',
            ],
            [
                'no' => 10,
                'nip' => '199001012020121001',
                'nama' => 'Ahmad Fauzi, S.Kom.',
                'jabatan' => 'Pranata Komputer Ahli Pertama',
                'unit_kerja' => 'Subbagian Perencanaan, Teknologi Informasi, dan Pelaporan',
                'gol' => 'III/a',
                'tmt' => '1 Desember 2020',
            ],
            [
                'no' => 11,
                'nip' => '198904252014031002',
                'nama' => 'Eko Prasetyo, S.E.',
                'jabatan' => 'Kepala Subbagian Umum dan Keuangan',
                'unit_kerja' => 'Subbagian Umum dan Keuangan',
                'gol' => 'III/c',
                'tmt' => '1 Oktober 2022',
            ],
            [
                'no' => 12,
                'nip' => '199108172019032004',
                'nama' => 'Maya Sari, S.H.',
                'jabatan' => 'Panitera Pengganti',
                'unit_kerja' => 'Kepaniteraan',
                'gol' => 'III/b',
                'tmt' => '1 Maret 2019',
            ],
            [
                'no' => 13,
                'nip' => '199305212020121005',
                'nama' => 'Dimas Saputra, A.Md.Kom.',
                'jabatan' => 'Jurusita Pengganti',
                'unit_kerja' => 'Kepaniteraan',
                'gol' => 'II/c',
                'tmt' => '1 Desember 2020',
            ],
            [
                'no' => 14,
                'nip' => '199506102024211008',
                'nama' => 'Rizki Ramadhan, A.Md.',
                'jabatan' => 'Operator - Layanan Operasional',
                'unit_kerja' => 'Subbagian Umum dan Keuangan',
                'gol' => 'IX',
                'tmt' => '1 Maret 2024',
            ],
            [
                'no' => 15,
                'nip' => '199612052024212009',
                'nama' => 'Putri Handayani',
                'jabatan' => 'Pengadministrasi Perkantoran',
                'unit_kerja' => 'Panitera Muda Gugatan',
                'gol' => 'V',
                'tmt' => '1 Maret 2024',
            ],
            [
                'no' => 16,
                'nip' => '199801142023011001',
                'nama' => 'Wahyu Nugroho',
                'jabatan' => 'Tenaga Pramubakti',
                'unit_kerja' => 'Subbagian Umum dan Keuangan',
                'gol' => 'I',
                'tmt' => '1 Januari 2023',
            ],
        ];
    }

    private function parseTanggalIndonesia(string $tanggal): string
    {
        $bulanMap = [
            'Januari' => '01',
            'Februari' => '02',
            'Maret' => '03',
            'April' => '04',
            'Mei' => '05',
            'Juni' => '06',
            'Juli' => '07',
            'Agustus' => '08',
            'September' => '09',
            'Oktober' => '10',
            'November' => '11',
            'Desember' => '12',
        ];

        [$hari, $bulanIndonesia, $tahun] = explode(' ', $tanggal);

        return sprintf('%s-%s-%02d', $tahun, $bulanMap[$bulanIndonesia], (int) $hari);
    }

    private function extractTanggalLahirDariNip(string $nip): string
    {
        if (strlen($nip) >= 8) {
            $tanggalLahir = substr($nip, 0, 8);

            return Carbon::createFromFormat('Ymd', $tanggalLahir)->toDateString();
        }

        return fake()->date('Y-m-d', '1995-12-31');
    }

    private function resolveStatusKepegawaian(string $gol): StatusKepegawaian
    {
        return match ($gol) {
            'IX', 'V' => StatusKepegawaian::PPPK,
            'I' => StatusKepegawaian::Honorer,
            default => StatusKepegawaian::PNS,
        };
    }

    private function resolvePangkatId(string $gol): ?string
    {
        return RefPangkat::query()->where('kode', $gol)->value('id');
    }

    private function resolveJabatanId(string $jabatan, ?string $unitKerjaRaw = null): ?string
    {
        $normalizedJabatan = strtolower($jabatan);
        $normalizedUnitKerja = $unitKerjaRaw ? strtolower($unitKerjaRaw) : '';

        // Pimpinan & Yudisial
        if (str_contains($normalizedJabatan, 'wakil ketua')) {
            return RefJabatan::query()->where('nama', 'Wakil Ketua')->value('id');
        }
        if (str_contains($normalizedJabatan, 'ketua')) {
            return RefJabatan::query()->where('nama', 'Ketua')->value('id');
        }
        if (str_contains($normalizedJabatan, 'hakim')) {
            return RefJabatan::query()->where('nama', 'Hakim')->value('id');
        }

        // Panitera Muda - tentukan dari UNIT KERJA (bukan jabatan)
        if (str_contains($normalizedJabatan, 'panitera muda')) {
            if (str_contains($normalizedUnitKerja, 'permohonan')) {
                return RefJabatan::query()->where('nama', 'Panitera Muda Permohonan')->value('id');
            }
            if (str_contains($normalizedUnitKerja, 'gugatan')) {
                return RefJabatan::query()->where('nama', 'Panitera Muda Gugatan')->value('id');
            }
            if (str_contains($normalizedUnitKerja, 'hukum')) {
                return RefJabatan::query()->where('nama', 'Panitera Muda Hukum')->value('id');
            }
        }

        // Panitera Pengganti & Jurusita
        if (str_contains($normalizedJabatan, 'panitera pengganti')) {
            return RefJabatan::query()->where('nama', 'Panitera Pengganti')->value('id');
        }
        if (str_contains($normalizedJabatan, 'juru sita pengganti') || str_contains($normalizedJabatan, 'jurusita pengganti')) {
            return RefJabatan::query()->where('nama', 'Jurusita Pengganti')->value('id');
        }
        if (str_contains($normalizedJabatan, 'jurusita')) {
            return RefJabatan::query()->where('nama', 'Jurusita')->value('id');
        }

        // Panitera umum (bukan muda)
        if (str_contains($normalizedJabatan, 'panitera')) {
            return RefJabatan::query()->where('nama', 'Panitera')->value('id');
        }

        // Sekretaris
        if (str_contains($normalizedJabatan, 'sekretaris')) {
            return RefJabatan::query()->where('nama', 'Sekretaris')->value('id');
        }

        // Kasubbag - tentukan dari unit kerja
        if (str_contains($normalizedJabatan, 'kepala subbagian') || str_contains($normalizedJabatan, 'kasubbag')) {
            return $this->resolveKasubbagId($normalizedUnitKerja);
        }

        // Default untuk staf/pelaksana (Klerek, Operator, dll)
        return RefJabatan::query()->where('nama', 'Staf/Pelaksana')->value('id');
    }

    private function resolveKasubbagId(string $normalizedUnitKerja): ?string
    {
        if (str_contains($normalizedUnitKerja, 'kepegawaian') || str_contains($normalizedUnitKerja, 'organisasi')) {
            return RefJabatan::query()->where('nama', 'Kasubbag Kepegawaian, Organisasi, dan Tatalaksana')->value('id');
        }
        if (str_contains($normalizedUnitKerja, 'perencanaan') || str_contains($normalizedUnitKerja, 'teknologi') || str_contains($normalizedUnitKerja, 'pelaporan')) {
            return RefJabatan::query()->where('nama', 'Kasubbag Perencanaan, TI, dan Pelaporan')->value('id');
        }

        return RefJabatan::query()->where('nama', 'Kasubbag Umum dan Keuangan')->value('id');
    }

    private function resolveUnitKerjaId(string $jabatan, string $unitKerjaRaw): ?string
    {
        $normalizedJabatan = strtolower($jabatan);
        $unitKerja = trim(explode('|', $unitKerjaRaw)[0]);
        $normalizedUnitKerja = strtolower($unitKerja);

        // 1. PIMPINAN & YUDISIAL -> Satker Pengadilan Agama Penajam
        if (str_contains($normalizedJabatan, 'ketua') || str_contains($normalizedJabatan, 'hakim')) {
            return RefUnitKerja::query()->where('kode', 'SATKER_PA_PENAJAM')->value('id');
        }

        // 2. PANITERA MUDA -> Unit kerja sesuai Panitera Muda
        if (str_contains($normalizedJabatan, 'panitera muda')) {
            if (str_contains($normalizedUnitKerja, 'permohonan')) {
                return RefUnitKerja::query()->where('kode', 'PANMUD_PERMOHONAN')->value('id');
            }
            if (str_contains($normalizedUnitKerja, 'gugatan')) {
                return RefUnitKerja::query()->where('kode', 'PANMUD_GUGATAN')->value('id');
            }
            if (str_contains($normalizedUnitKerja, 'hukum')) {
                return RefUnitKerja::query()->where('kode', 'PANMUD_HUKUM')->value('id');
            }
        }

        // 3. STAF/Pelaksana di Kepaniteraan -> Ikut unit kerja Panitera Muda-nya
        if (str_contains($normalizedUnitKerja, 'panitera muda permohonan')) {
            return RefUnitKerja::query()->where('kode', 'PANMUD_PERMOHONAN')->value('id');
        }
        if (str_contains($normalizedUnitKerja, 'panitera muda gugatan')) {
            return RefUnitKerja::query()->where('kode', 'PANMUD_GUGATAN')->value('id');
        }
        if (str_contains($normalizedUnitKerja, 'panitera muda hukum')) {
            return RefUnitKerja::query()->where('kode', 'PANMUD_HUKUM')->value('id');
        }

        // 4. Panitera (biasa), Panitera Pengganti, Jurusita -> Kepaniteraan
        if (str_contains($normalizedJabatan, 'panitera') || str_contains($normalizedJabatan, 'juru sita') || str_contains($normalizedJabatan, 'jurusita')) {
            return RefUnitKerja::query()->where('kode', 'PANITERA')->value('id');
        }

        // 5. Sekretaris -> Kesekretariatan
        if (str_contains($normalizedJabatan, 'sekretaris')) {
            return RefUnitKerja::query()->where('kode', 'SEKRETARIS')->value('id');
        }

        // 6. Kasubbag & Staf Subbagian -> Unit kerja sesuai subbagian
        if (str_contains($normalizedUnitKerja, 'kepegawaian')) {
            return RefUnitKerja::query()->where('kode', 'SUBBAG_KEPEGAWAIAN')->value('id');
        }
        if (str_contains($normalizedUnitKerja, 'perencanaan') || str_contains($normalizedUnitKerja, 'teknologi')) {
            return RefUnitKerja::query()->where('kode', 'SUBBAG_PERENCANAAN')->value('id');
        }
        if (str_contains($normalizedUnitKerja, 'umum')) {
            return RefUnitKerja::query()->where('kode', 'SUBBAG_UMUM')->value('id');
        }

        // Default: Satker
        return RefUnitKerja::query()->where('kode', 'SATKER_PA_PENAJAM')->value('id');
    }

    private function resolvePendidikan(string $nama): string
    {
        $normalizedNama = strtolower($nama);

        if (str_contains($normalizedNama, 'm.s.i') || str_contains($normalizedNama, 'm.si') || str_contains($normalizedNama, 'm.h.') || str_contains($normalizedNama, 'm.hum.') || str_contains($normalizedNama, 'm.kn.')) {
            return 'S2';
        }

        if (str_contains($normalizedNama, 's.h') || str_contains($normalizedNama, 's.e') || str_contains($normalizedNama, 's.kom') || str_contains($normalizedNama, 's.t')) {
            return 'S1';
        }

        if (str_contains($normalizedNama, 'a.md')) {
            return 'D3';
        }

        return 'SMA';
    }
}
