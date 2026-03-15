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
        $rawData = json_decode((string) file_get_contents(base_path('docs/data_pegawai.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rawData as $item) {
            $tanggalMasuk = $this->parseTanggalIndonesia($item['tmt']);
            $tanggalLahir = $this->extractTanggalLahirDariNip($item['nip']);
            $statusKepegawaian = $this->resolveStatusKepegawaian($item['gol']);

            Pegawai::query()->updateOrCreate(
                ['nip' => $item['nip']],
                [
                    'nip' => $item['nip'],
                    'nip_lama' => null,
                    'nama_lengkap' => Str::title(strtolower($item['nama'])),
                    'tempat_lahir' => fake('id_ID')->city(),
                    'tanggal_lahir' => $tanggalLahir,
                    'jenis_kelamin' => fake()->randomElement(JenisKelamin::cases())->value,
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

        // Hanya seed dari JSON, tidak menambah data test
        $this->command->info('Seeded '.Pegawai::query()->count().' pegawai dari JSON.');
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

        if (str_contains($normalizedNama, 'm.s.i') || str_contains($normalizedNama, 'm.h.') || str_contains($normalizedNama, 'm.hum.') || str_contains($normalizedNama, 'm.kn.')) {
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
