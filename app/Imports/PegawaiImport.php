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
use Illuminate\Support\Str;
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
    /** @var Collection<int, RefJabatan> */
    private Collection $jabatanList;

    /** @var Collection<int, RefUnitKerja> */
    private Collection $unitKerjaList;

    /** @var Collection<int, RefPangkat> */
    private Collection $pangkatList;

    public function __construct(private readonly PegawaiImport $parent) {}

    public function collection(Collection $rows): void
    {
        $this->preloadReferensi();

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

            try {
                // Parse otomatis dari 18 digit NIP dengan validasi tanggal kalender ketat
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
                    $existing = Pegawai::withoutGlobalScopes()
                        ->where('nip', $nip)
                        ->lockForUpdate()
                        ->first();

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
                    ];

                    if (! $existing || $refJabatanId !== null) {
                        $data['ref_jabatan_id'] = $refJabatanId;
                    }

                    if (! $existing || $refUnitKerjaId !== null) {
                        $data['ref_unit_kerja_id'] = $refUnitKerjaId;
                    }

                    if (! $existing || $refPangkatId !== null) {
                        $data['ref_pangkat_id'] = $refPangkatId;
                    }

                    if ($email !== null) {
                        $data['email'] = $email;
                    }

                    if ($noTelepon !== null) {
                        $data['no_telepon'] = $noTelepon;
                    }

                    if (! $existing) {
                        $pegawai = new Pegawai($data);
                        // password bukan fillable; set eksplisit sandi acak yang di-hash oleh cast model
                        $pegawai->password = Str::random(32);
                        $pegawai->save();
                        $this->parent->importedCount++;
                    } else {
                        if ($existing->trashed()) {
                            $existing->restore();
                        }

                        $existing->update($data);
                        $pegawai = $existing;
                        $this->parent->updatedCount++;
                    }

                    // Sinkronkan Riwayat Jabatan Aktif tanpa menimpa no_sk/tanggal_sk/tmt atau referensi yang kosong saat re-import
                    if ($refJabatanId !== null || $refUnitKerjaId !== null) {
                        $riwayatJabatanAktif = RiwayatJabatan::query()
                            ->where('pegawai_id', $pegawai->id)
                            ->where('is_aktif', true)
                            ->first();

                        if ($riwayatJabatanAktif !== null) {
                            $updateJabatan = [];

                            if ($refJabatanId !== null) {
                                $updateJabatan['ref_jabatan_id'] = $refJabatanId;
                            }

                            if ($refUnitKerjaId !== null) {
                                $updateJabatan['ref_unit_kerja_id'] = $refUnitKerjaId;
                            }

                            if ($updateJabatan !== []) {
                                $riwayatJabatanAktif->update($updateJabatan);
                            }
                        } elseif ($refJabatanId !== null) {
                            RiwayatJabatan::query()->create([
                                'pegawai_id' => $pegawai->id,
                                'ref_jabatan_id' => $refJabatanId,
                                'ref_unit_kerja_id' => $refUnitKerjaId,
                                'no_sk' => '-',
                                'tanggal_sk' => $tanggalMasuk,
                                'tmt' => $tanggalMasuk,
                                'is_aktif' => true,
                            ]);
                        }
                    }

                    // Sinkronkan Riwayat Pangkat Aktif tanpa menimpa no_sk/tanggal_sk/tmt yang sudah ada
                    if ($refPangkatId !== null) {
                        $riwayatPangkatAktif = RiwayatPangkat::query()
                            ->where('pegawai_id', $pegawai->id)
                            ->where('is_aktif', true)
                            ->first();

                        if ($riwayatPangkatAktif !== null) {
                            $riwayatPangkatAktif->update([
                                'ref_pangkat_id' => $refPangkatId,
                            ]);
                        } else {
                            RiwayatPangkat::query()->create([
                                'pegawai_id' => $pegawai->id,
                                'ref_pangkat_id' => $refPangkatId,
                                'no_sk' => '-',
                                'tanggal_sk' => $tanggalMasuk,
                                'tmt' => $tanggalMasuk,
                                'is_aktif' => true,
                            ]);
                        }
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

    private function preloadReferensi(): void
    {
        $this->jabatanList = RefJabatan::query()->get(['id', 'nama']);
        $this->unitKerjaList = RefUnitKerja::query()->get(['id', 'nama']);
        $this->pangkatList = RefPangkat::query()->get(['id', 'kode', 'nama']);
    }

    /**
     * Mem-parse tanggal lahir (YYYYMMDD) dari 8 digit awal NIP secara ketat tanpa rollover kalender.
     */
    private function parseTanggalLahirDariNip(string $nip): string
    {
        $raw = substr($nip, 0, 8);
        $parsed = Carbon::createFromFormat('!Ymd', $raw);
        $errors = Carbon::getLastErrors();

        if (
            ! $parsed instanceof Carbon
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $parsed->format('Ymd') !== $raw
        ) {
            throw new \InvalidArgumentException("Tanggal lahir pada NIP tidak valid: {$raw}");
        }

        return $parsed->toDateString();
    }

    /**
     * Mem-parse TMT pengangkatan awal (YYYYMM) dari digit 9..14 NIP secara ketat tanpa rollover kalender.
     */
    private function parseTanggalMasukDariNip(string $nip): string
    {
        $raw = substr($nip, 8, 6);
        $parsed = Carbon::createFromFormat('!Ym', $raw);
        $errors = Carbon::getLastErrors();

        if (
            ! $parsed instanceof Carbon
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $parsed->format('Ym') !== $raw
        ) {
            throw new \InvalidArgumentException("Tanggal masuk pada NIP tidak valid: {$raw}");
        }

        return $parsed->startOfMonth()->toDateString();
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
        $lower = strtolower($jabatan);

        $found = $this->jabatanList->first(fn (RefJabatan $item): bool => $item->nama === $jabatan)
            ?? $this->jabatanList->first(fn (RefJabatan $item): bool => strtolower($item->nama) === $lower)
            ?? $this->jabatanList->first(fn (RefJabatan $item): bool => str_contains(strtolower($item->nama), $lower));

        return $found?->id;
    }

    private function resolveUnitKerjaId(?string $unitKerja): ?string
    {
        if (empty($unitKerja)) {
            return null;
        }

        $unitKerja = trim($unitKerja);
        $lower = strtolower($unitKerja);

        $found = $this->unitKerjaList->first(fn (RefUnitKerja $item): bool => $item->nama === $unitKerja)
            ?? $this->unitKerjaList->first(fn (RefUnitKerja $item): bool => strtolower($item->nama) === $lower)
            ?? $this->unitKerjaList->first(fn (RefUnitKerja $item): bool => str_contains(strtolower($item->nama), $lower));

        return $found?->id;
    }

    private function resolvePangkatId(?string $golongan): ?string
    {
        if (empty($golongan)) {
            return null;
        }

        $golongan = trim($golongan);
        $lower = strtolower($golongan);

        $found = $this->pangkatList->first(
            fn (RefPangkat $item): bool => $item->kode === $golongan || $item->nama === $golongan
        ) ?? $this->pangkatList->first(
            fn (RefPangkat $item): bool => strtolower((string) $item->kode) === $lower || strtolower($item->nama) === $lower
        );

        return $found?->id;
    }
}
