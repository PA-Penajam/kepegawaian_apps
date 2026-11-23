<?php

declare(strict_types=1);

namespace Tests\E2E\Cuti\Support;

use App\Models\Cuti\CutiPengajuan;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Services\Cuti\SaldoLedgerService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;

/**
 * Helper terpusat untuk suite E2E Modul Cuti & Penomoran Surat.
 *
 * Seluruh pembuatan aktor, permission granular, perhitungan rentang hari kerja,
 * inisialisasi saldo, serta penandatanganan header HMAC API berada di sini
 * tanpa memanggil DatabaseSeeder atau PegawaiSeeder.
 */
final class CutiE2eHelper
{
    /**
     * Membuat pegawai dengan daftar permission IAM spesifik pada aplikasi kepegawaian.
     *
     * @param  array<int, string>  $permissionSlugs
     * @param  array<string, mixed>  $attributes
     */
    public static function buatPegawaiDenganPermissions(array $permissionSlugs, array $attributes = []): Pegawai
    {
        $pegawai = Pegawai::factory()->create($attributes);
        $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();

        if ($permissionSlugs === []) {
            return $pegawai;
        }

        $role = IamRole::create([
            'iam_application_id' => $app->id,
            'slug' => 'e2e-cuti-role-'.$pegawai->id,
            'nama' => 'E2E Cuti Role '.$pegawai->nip,
        ]);

        $permissions = IamPermission::where('iam_application_id', $app->id)
            ->whereIn('slug', $permissionSlugs)
            ->get();

        foreach ($permissions as $permission) {
            IamRolePermission::firstOrCreate([
                'iam_role_id' => $role->id,
                'iam_permission_id' => $permission->id,
            ]);
        }

        IamUserRole::create([
            'user_id' => $pegawai->id,
            'iam_role_id' => $role->id,
            'assigned_at' => now(),
        ]);

        return $pegawai;
    }

    /**
     * Membuat paket lengkap aktor workflow cuti dengan permission granular terpisah.
     *
     * @param  array<string, mixed>  $pemohonAttributes
     * @return array{
     *     pemohon: Pegawai,
     *     petugas: Pegawai,
     *     atasan: Pegawai,
     *     pejabat: Pegawai,
     *     admin: Pegawai
     * }
     */
    public static function buatAktorCuti(array $pemohonAttributes = []): array
    {
        $pemohon = self::buatPegawaiDenganPermissions([
            'cuti.pengajuan.create',
            'cuti.pengajuan.view-own',
            'cuti.pengajuan.cancel-own',
            'cuti.saldo.view-own',
        ], $pemohonAttributes);

        $petugas = self::buatPegawaiDenganPermissions([
            'cuti.pengajuan.verify',
            'cuti.pengajuan.view-all',
        ]);

        $atasan = self::buatPegawaiDenganPermissions([
            'cuti.pengajuan.approve-langsung',
            'cuti.pengajuan.view-team',
        ]);

        $pejabat = self::buatPegawaiDenganPermissions([
            'cuti.pengajuan.approve-pejabat',
            'cuti.pengajuan.view-team',
        ]);

        $admin = Pegawai::factory()->admin()->create();

        return [
            'pemohon' => $pemohon,
            'petugas' => $petugas,
            'atasan' => $atasan,
            'pejabat' => $pejabat,
            'admin' => $admin,
        ];
    }

    /**
     * Menginisialisasi saldo Cuti Tahunan (CT) pegawai melalui service ledger resmi.
     */
    public static function inisialisasiSaldoCt(
        Pegawai $pegawai,
        int $tahun,
        int $hari = 12,
        string $keterangan = 'Alokasi awal E2E',
    ): void {
        app(SaldoLedgerService::class)->kreditAlokasi(
            $pegawai->nip,
            'CT',
            $tahun,
            $hari,
            $keterangan,
        );
    }

    /**
     * Menghitung rentang tanggal mulai dan selesai untuk tepat N hari kerja (Senin–Jumat).
     *
     * @return array{mulai: string, selesai: string}
     */
    public static function rentangHariKerja(CarbonInterface $mulaiDari, int $jumlahHariKerja): array
    {
        $cursor = CarbonImmutable::parse($mulaiDari->toDateString());

        while ($cursor->isWeekend()) {
            $cursor = $cursor->addDay();
        }

        $mulai = $cursor;
        $terhitung = 1;

        while ($terhitung < $jumlahHariKerja) {
            $cursor = $cursor->addDay();

            if (! $cursor->isWeekend()) {
                $terhitung++;
            }
        }

        return [
            'mulai' => $mulai->toDateString(),
            'selesai' => $cursor->toDateString(),
        ];
    }

    /**
     * Membuat file lampiran PDF tiruan untuk pengujian upload dokumen cuti.
     */
    public static function buatFileLampiranPdf(string $nama = 'surat-keterangan.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 128, 'application/pdf');
    }

    /**
     * Membangun header request bertanda tangan HMAC-SHA256 sesuai kontrak VerifyHmacSignature.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function buatHeaderHmacApi(
        string $method,
        string $path,
        array $query = [],
        ?string $secret = null,
        ?int $timestamp = null,
        ?string $nonce = null,
        string $body = '[]',
    ): array {
        $resolvedSecret = $secret ?? (string) config('kepegawaian.secret_key');
        $resolvedTimestamp = $timestamp ?? now()->timestamp;
        $queryString = http_build_query(collect($query)->sortKeys()->all());
        $bodyHash = hash('sha256', $body);
        $canonical = strtoupper($method).':'.$path.':'.$queryString.':'.$bodyHash.':'.$resolvedTimestamp;
        $signature = hash_hmac('sha256', $canonical, $resolvedSecret);

        $headers = [
            'X-Timestamp' => (string) $resolvedTimestamp,
            'X-Signature' => $signature,
            'Accept' => 'application/json',
        ];

        if ($nonce !== null) {
            $headers['X-Nonce'] = $nonce;
        }

        return $headers;
    }

    /**
     * Membuat pengajuan cuti langsung melalui factory dengan aktor penanggung jawab terisi lengkap.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function buatPengajuanLangsung(
        Pegawai $pemohon,
        Pegawai $petugas,
        Pegawai $atasan,
        Pegawai $pejabat,
        array $attributes = [],
    ): CutiPengajuan {
        return CutiPengajuan::factory()->create(array_merge([
            'pegawai_nip' => $pemohon->nip,
            'jenis_cuti_kode' => 'CT',
            'state' => 'DIAJUKAN',
            'submitted_at' => now(),
            'petugas_kepegawaian_snapshot_nip' => $petugas->nip,
            'atasan_langsung_snapshot_nip' => $atasan->nip,
            'pejabat_berwenang_snapshot_nip' => $pejabat->nip,
            'petugas_kepegawaian_current_nip' => $petugas->nip,
            'atasan_langsung_current_nip' => $atasan->nip,
            'pejabat_berwenang_current_nip' => $pejabat->nip,
        ], $attributes));
    }
}
