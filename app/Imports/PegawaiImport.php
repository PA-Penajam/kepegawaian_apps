<?php

namespace App\Imports;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PegawaiImport implements WithMultipleSheets
{
    public int $importedCount = 0;

    public int $updatedCount = 0;

    public int $skippedCount = 0;

    /** @var array<int, array{row: int, nip: string, error: string}> */
    public array $errors = [];

    public function sheets(): array
    {
        return [
            0 => new PegawaiDataImportSheet($this),
        ];
    }
}

class PegawaiDataImportSheet implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public function __construct(private readonly PegawaiImport $parent) {}

    public function collection(Collection $rows): void
    {
        $rowIndex = 1;

        foreach ($rows as $row) {
            $rowIndex++;
            $rawNip = $this->extractValue($row, ['nip_18_digit', 'nip', 'nip_18_digit_angka']);
            $namaLengkap = $this->extractValue($row, ['nama_lengkap_beserta_gelar', 'nama_lengkap', 'nama']);

            if (empty($rawNip) || empty($namaLengkap)) {
                $this->parent->skippedCount++;

                continue;
            }

            // Bersihkan format NIP (hapus tanda petik, spasi, dash)
            $nip = preg_replace('/[^0-9]/', '', (string) $rawNip);

            if (strlen($nip) !== 18) {
                $this->parent->errors[] = [
                    'row' => $rowIndex,
                    'nip' => (string) $rawNip,
                    'error' => "NIP harus berjumlah 18 digit angka (ditemukan: {$nip})",
                ];

                continue;
            }

            $jabatanRaw = $this->extractValue($row, ['jabatan', 'nama_jabatan']);
            $unitKerjaRaw = $this->extractValue($row, ['unit_kerja', 'nama_unit_kerja']);
            $golonganRaw = $this->extractValue($row, ['golongan_pangkat', 'golongan', 'pangkat']);
            $emailRaw = $this->extractValue($row, ['email_opsional', 'email']);
            $noWaRaw = $this->extractValue($row, ['no_whatsapp_opsional', 'no_wa', 'no_telepon', 'telepon']);

            // Parse otomatis dari 18 digit NIP
            $tanggalLahir = $this->parseTanggalLahirDariNip($nip);
            $tanggalMasuk = $this->parseTanggalMasukDariNip($nip);
            $jenisKelamin = $this->parseJenisKelaminDariNip($nip);
            $statusKepegawaian = $this->resolveStatusKepegawaian($golonganRaw);

            // Relasi Referensi
            $refJabatanId = $this->resolveJabatanId($jabatanRaw);
            $refUnitKerjaId = $this->resolveUnitKerjaId($unitKerjaRaw);
            $refPangkatId = $this->resolvePangkatId($golonganRaw);

            $email = ! empty($emailRaw) ? trim((string) $emailRaw) : null;
            $noTelepon = ! empty($noWaRaw) ? trim((string) $noWaRaw) : null;

            try {
                DB::transaction(function () use (
                    $nip,
                    $namaLengkap,
                    $tanggalLahir,
                    $tanggalMasuk,
                    $jenisKelamin,
                    $statusKepegawaian,
                    $refJabatanId,
                    $refUnitKerjaId,
                    $refPangkatId,
                    $email,
                    $noTelepon
                ) {
                    $existing = Pegawai::withoutGlobalScopes()->where('nip', $nip)->first();

                    $data = [
                        'nip' => $nip,
                        'nama_lengkap' => trim((string) $namaLengkap),
                        'tempat_lahir' => 'Penajam',
                        'tanggal_lahir' => $tanggalLahir,
                        'jenis_kelamin' => $jenisKelamin->value,
                        'agama' => Agama::Islam->value,
                        'status_perkawinan' => StatusPerkawinan::Kawin->value,
                        'status_kepegawaian' => $statusKepegawaian->value,
                        'status_pegawai' => StatusPegawai::Aktif->value,
                        'tmt_cpns' => $tanggalMasuk,
                        'tanggal_masuk' => $tanggalMasuk,
                        'tanggal_pensiun_bup' => Carbon::parse($tanggalLahir)->addYears(60)->toDateString(),
                        'ref_jabatan_id' => $refJabatanId,
                        'ref_unit_kerja_id' => $refUnitKerjaId,
                        'ref_pangkat_id' => $refPangkatId,
                    ];

                    if ($email !== null) {
                        $data['email'] = $email;
                    }

                    if ($noTelepon !== null) {
                        $data['no_telepon'] = $noTelepon;
                    }

                    if (! $existing) {
                        $data['password'] = Hash::make('Password123!');
                        $pegawai = Pegawai::create($data);
                        $this->parent->importedCount++;
                    } else {
                        $existing->update($data);
                        $pegawai = $existing;
                        $this->parent->updatedCount++;
                    }

                    // Sinkronkan Riwayat Jabatan Aktif jika belum ada atau update
                    if ($refJabatanId !== null) {
                        RiwayatJabatan::updateOrCreate(
                            ['pegawai_id' => $pegawai->id, 'is_aktif' => true],
                            [
                                'ref_jabatan_id' => $refJabatanId,
                                'ref_unit_kerja_id' => $refUnitKerjaId,
                                'no_sk' => '-',
                                'tanggal_sk' => $tanggalMasuk,
                                'tmt' => $tanggalMasuk,
                            ]
                        );
                    }

                    // Sinkronkan Riwayat Pangkat Aktif jika belum ada atau update
                    if ($refPangkatId !== null) {
                        RiwayatPangkat::updateOrCreate(
                            ['pegawai_id' => $pegawai->id, 'is_aktif' => true],
                            [
                                'ref_pangkat_id' => $refPangkatId,
                                'no_sk' => '-',
                                'tanggal_sk' => $tanggalMasuk,
                                'tmt' => $tanggalMasuk,
                            ]
                        );
                    }
                });
            } catch (\Throwable $e) {
                $this->parent->errors[] = [
                    'row' => $rowIndex,
                    'nip' => $nip,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>|Collection  $row
     * @param  array<int, string>  $possibleKeys
     */
    private function extractValue(array|Collection $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function parseTanggalLahirDariNip(string $nip): string
    {
        $raw = substr($nip, 0, 8);

        return Carbon::createFromFormat('Ymd', $raw)->toDateString();
    }

    private function parseTanggalMasukDariNip(string $nip): string
    {
        $raw = substr($nip, 8, 6);

        return Carbon::createFromFormat('Ym', $raw)->startOfMonth()->toDateString();
    }

    private function parseJenisKelaminDariNip(string $nip): JenisKelamin
    {
        $digit = substr($nip, 14, 1);

        return $digit === '1' ? JenisKelamin::LakiLaki : JenisKelamin::Perempuan;
    }

    private function resolveStatusKepegawaian(?string $golongan): StatusKepegawaian
    {
        if ($golongan === null) {
            return StatusKepegawaian::PNS;
        }

        $upper = strtoupper(trim($golongan));
        if (in_array($upper, ['IX', 'V', 'PPPK', 'P3K'], true)) {
            return StatusKepegawaian::PPPK;
        }
        if (in_array($upper, ['I', 'HONORER', 'PPNPN'], true)) {
            return StatusKepegawaian::Honorer;
        }

        return StatusKepegawaian::PNS;
    }

    private function resolveJabatanId(?string $jabatan): ?string
    {
        if (empty($jabatan)) {
            return null;
        }

        $jabatan = trim($jabatan);

        $found = RefJabatan::query()->where('nama', $jabatan)->first();
        if ($found) {
            return $found->id;
        }

        $found = RefJabatan::query()->whereRaw('LOWER(nama) = ?', [strtolower($jabatan)])->first();
        if ($found) {
            return $found->id;
        }

        $found = RefJabatan::query()->where('nama', 'LIKE', "%{$jabatan}%")->first();

        return $found?->id;
    }

    private function resolveUnitKerjaId(?string $unitKerja): ?string
    {
        if (empty($unitKerja)) {
            return null;
        }

        $unitKerja = trim($unitKerja);

        $found = RefUnitKerja::query()->where('nama', $unitKerja)->first();
        if ($found) {
            return $found->id;
        }

        $found = RefUnitKerja::query()->whereRaw('LOWER(nama) = ?', [strtolower($unitKerja)])->first();
        if ($found) {
            return $found->id;
        }

        $found = RefUnitKerja::query()->where('nama', 'LIKE', "%{$unitKerja}%")->first();

        return $found?->id;
    }

    private function resolvePangkatId(?string $golongan): ?string
    {
        if (empty($golongan)) {
            return null;
        }

        $golongan = trim($golongan);

        $found = RefPangkat::query()->where('kode', $golongan)->orWhere('nama', $golongan)->first();
        if ($found) {
            return $found->id;
        }

        $found = RefPangkat::query()->whereRaw('LOWER(kode) = ?', [strtolower($golongan)])
            ->orWhereRaw('LOWER(nama) = ?', [strtolower($golongan)])
            ->first();

        return $found?->id;
    }
}
