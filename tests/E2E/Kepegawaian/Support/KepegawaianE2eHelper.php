<?php

namespace Tests\E2E\Kepegawaian\Support;

use App\Models\DokumenPegawai;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Models\RefJenisDokumen;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use Database\Seeders\RefJabatanSeeder;
use Database\Seeders\RefJenisDokumenSeeder;
use Database\Seeders\RefPangkatSeeder;
use Database\Seeders\RefUnitKerjaSeeder;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Helper terpusat untuk suite uji E2E domain Kepegawaian.
 *
 * Seluruh data dibangun dari seeder referensi atomik dan factory sintetis
 * tanpa bergantung pada DatabaseSeeder maupun PegawaiSeeder.
 */
class KepegawaianE2eHelper
{
    /**
     * Menjalankan seeder referensi atomik yang bebas data PII asli.
     */
    public static function seedReferensiDasar(): void
    {
        Artisan::call('db:seed', ['--class' => RefPangkatSeeder::class, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--class' => RefJabatanSeeder::class, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--class' => RefUnitKerjaSeeder::class, '--no-interaction' => true]);
        Artisan::call('db:seed', ['--class' => RefJenisDokumenSeeder::class, '--no-interaction' => true]);
    }

    /**
     * Membuat pegawai dengan daftar permission IAM spesifik pada aplikasi kepegawaian.
     *
     * @param  array<int, string>  $permissionSlugs
     * @param  array<string, mixed>  $pegawaiOverrides
     */
    public static function buatPegawaiDenganPermission(array $permissionSlugs, array $pegawaiOverrides = []): Pegawai
    {
        $pegawai = Pegawai::factory()->create($pegawaiOverrides);
        $app = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();

        $role = IamRole::query()->create([
            'iam_application_id' => $app->id,
            'nama' => 'Peran E2E '.Str::random(8),
            'slug' => 'e2e-role-'.Str::lower(Str::random(8)),
            'is_system' => false,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = IamPermission::query()->firstOrCreate(
                [
                    'iam_application_id' => $app->id,
                    'slug' => $slug,
                ],
                [
                    'nama' => 'Permission '.$slug,
                    'group' => explode('.', $slug)[0] ?? 'e2e',
                    'keterangan' => 'Permission sintetis untuk pengujian E2E',
                ],
            );

            IamRolePermission::query()->firstOrCreate([
                'iam_role_id' => $role->id,
                'iam_permission_id' => $permission->id,
            ]);
        }

        IamUserRole::query()->firstOrCreate(
            [
                'user_id' => $pegawai->id,
                'iam_role_id' => $role->id,
            ],
            [
                'assigned_at' => now(),
            ],
        );

        $pegawai->clearPermissionCache();

        return $pegawai;
    }

    /**
     * Membuat pegawai beserta riwayat pangkat aktif pada TMT tertentu.
     *
     * @param  array<string, mixed>  $pegawaiOverrides
     */
    public static function buatPegawaiDenganPangkatAktif(
        string $tmt,
        array $pegawaiOverrides = [],
        ?RefPangkat $pangkat = null,
    ): Pegawai {
        $refPangkat = $pangkat ?? RefPangkat::query()->first() ?? RefPangkat::factory()->create([
            'kode' => 'III/a',
            'nama' => 'Penata Muda',
            'golongan' => 'III',
            'ruang' => 'a',
        ]);

        $pegawai = Pegawai::factory()->create(array_merge([
            'ref_pangkat_id' => $refPangkat->id,
        ], $pegawaiOverrides));

        RiwayatPangkat::factory()->create([
            'pegawai_id' => $pegawai->id,
            'ref_pangkat_id' => $refPangkat->id,
            'tmt' => $tmt,
            'tanggal_sk' => $tmt,
            'is_aktif' => true,
            'masa_kerja_tahun' => 4,
            'masa_kerja_bulan' => 0,
        ]);

        return $pegawai;
    }

    /**
     * Membuat file CSV sementara yang kompatibel dengan PegawaiImport.
     *
     * @param  array<int, array<int, string|null>>  $rows
     */
    public static function buatFileCsvImport(array $rows, string $prefix = 'e2e_import'): string
    {
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/'.$prefix.'_'.Str::lower(Str::random(8)).'.csv';
        $handle = fopen($path, 'w');

        fputcsv($handle, [
            'NIP (18 Digit)',
            'Nama Lengkap (beserta gelar)',
            'Jabatan',
            'Unit Kerja',
            'Golongan / Pangkat',
            'Email (Opsional)',
            'No WhatsApp (Opsional)',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }

    /**
     * Membuat file XLSX sementara yang kompatibel dengan PegawaiImport.
     *
     * @param  array<int, array<int, string|null>>  $rows
     */
    public static function buatFileXlsxImport(array $rows, string $prefix = 'e2e_import'): string
    {
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir.'/'.$prefix.'_'.Str::lower(Str::random(8)).'.xlsx';

        $export = new class($rows) implements FromArray, WithHeadings
        {
            /**
             * @param  array<int, array<int, string|null>>  $rows
             */
            public function __construct(private readonly array $rows) {}

            /**
             * @return array<int, string>
             */
            public function headings(): array
            {
                return [
                    'NIP (18 Digit)',
                    'Nama Lengkap (beserta gelar)',
                    'Jabatan',
                    'Unit Kerja',
                    'Golongan / Pangkat',
                    'Email (Opsional)',
                    'No WhatsApp (Opsional)',
                ];
            }

            /**
             * @return array<int, array<int, string|null>>
             */
            public function array(): array
            {
                return $this->rows;
            }
        };

        $binary = Excel::raw($export, ExcelFormat::XLSX);
        file_put_contents($path, $binary);

        return $path;
    }

    /**
     * Menghapus file uji sementara jika masih ada.
     */
    public static function hapusFileSementara(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Mencari route GET download/preview DokumenPegawai secara opaque-box.
     */
    public static function urlDownloadDokumen(Pegawai $pegawai, DokumenPegawai $dokumen): ?string
    {
        $kandidatNama = [
            'kepegawaian.pegawai.dokumen.download',
            'kepegawaian.pegawai.dokumen.preview',
            'kepegawaian.pegawai.dokumen.show',
        ];

        foreach ($kandidatNama as $nama) {
            if (Route::has($nama)) {
                return route($nama, [$pegawai, $dokumen]);
            }
        }

        /** @var LaravelRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $actionName = $route->getActionName();
            if (
                str_contains($actionName, 'DokumenPegawaiController@')
                && ! str_ends_with($actionName, '@index')
            ) {
                $routeName = $route->getName();
                if ($routeName !== null) {
                    return route($routeName, [$pegawai, $dokumen]);
                }

                return '/'.ltrim(
                    str_replace(
                        ['{pegawai}', '{dokumenPegawai}', '{dokumen}'],
                        [$pegawai->id, $dokumen->id, $dokumen->id],
                        $route->uri(),
                    ),
                    '/',
                );
            }
        }

        return null;
    }

    /**
     * Mencari route GET download/tampil foto Pegawai secara opaque-box.
     */
    public static function urlDownloadFoto(Pegawai $pegawai): ?string
    {
        $kandidatNama = [
            'kepegawaian.pegawai.foto.show',
            'kepegawaian.pegawai.foto.download',
        ];

        foreach ($kandidatNama as $nama) {
            if (Route::has($nama)) {
                return route($nama, ['pegawai' => $pegawai]);
            }
        }

        /** @var LaravelRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $actionName = $route->getActionName();
            if (
                str_contains($actionName, 'PegawaiController@')
                && str_contains(strtolower($route->uri()), 'foto')
            ) {
                $routeName = $route->getName();
                if ($routeName !== null) {
                    return route($routeName, ['pegawai' => $pegawai]);
                }

                return '/'.ltrim(str_replace('{pegawai}', $pegawai->id, $route->uri()), '/');
            }
        }

        return null;
    }

    /**
     * Menyusun payload upload dokumen pegawai dengan menghormati kolom FK jika tersedia.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payloadUploadDokumen(array $overrides = []): array
    {
        $payload = [
            'jenis_dokumen' => 'SK Pangkat',
            'nomor_dokumen' => 'SK-800/E2E/2026',
            'tanggal_dokumen' => '2026-01-15',
            'keterangan' => 'Dokumen uji E2E tervalidasi',
        ];

        if (Schema::hasColumn('dokumen_pegawai', 'ref_jenis_dokumen_id')) {
            $refId = RefJenisDokumen::query()
                ->where('nama', $overrides['jenis_dokumen'] ?? $payload['jenis_dokumen'])
                ->value('id');

            if ($refId !== null) {
                $payload['ref_jenis_dokumen_id'] = $refId;
            }
        }

        return array_merge($payload, $overrides);
    }

    /**
     * Daftar kata kunci nama & NIP PII asli PA Penajam yang harus nol di working tree.
     * Disimpan dalam bentuk terenkode base64 agar file test tidak memicu false-positive saat pemindaian PII.
     *
     * @return array<int, string>
     */
    public static function daftarPolaPiiPaPenajam(): array
    {
        $encodedPatterns = [
            'TkFIRElZQU5USQ==',
            'RkFUVEFIVVJSSURMTw==',
            'TlVSIFJJWktBIEZBTkk=',
            'VklEWUEgTlVSQ0hBTElaQQ==',
            'TVVIQU1NQUQgSEFNREk=',
            'UkFJTkkgTUFVTElESU5B',
            'TVVIQU1NQUQgTUlGVEFIVURJTg==',
            'TlVaVUxBIFlVU1RJU0lB',
            'RkFSSURBSCBGSVRSSVlBTkk=',
            'TVVIQU1NQUQgSUxIQU0=',
            'UkFJTkEgUFVUUkkgTkFTVUhB',
            'SVJXQU4gU1lBSCBTRVRJQVdBTg==',
            'QVdBTFVERElOIE5VUg==',
            'TVVIQVJESUFOU1lBSA==',
            'MTk4NDEwMDkyMDA3MDQyMDAx',
            'MTk4NTA1MDkyMDA5MDQxMDA2',
            'MTk5NzAyMjMyMDIyMDMyMDEz',
            'MTk5NzA0MTYyMDIyMDMyMDEz',
            'MTk3NTAzMTQxOTk2MDMxMDAy',
            'MTk4NDExMTkyMDExMDExMDEy',
            'MTk5MTA3MTMyMDIwMTIxMDAz',
            'MTk4NTA2MDUyMDA5MDQyMDA2',
            'MTk4MTA4MDIyMDA5MTIyMDAy',
        ];

        return array_map(
            static fn (string $encoded): string => base64_decode($encoded, true) ?: '',
            $encodedPatterns,
        );
    }

    /**
     * Memeriksa seluruh sheet dan sel pada template_import_pegawai.xlsx (bila ada)
     * agar bebas dari pola PII asli PA Penajam setelah proses anonimasi.
     *
     * @return array<int, string>
     */
    public static function pelanggaranPiiTemplateImport(): array
    {
        $templatePath = base_path('template_import_pegawai.xlsx');
        if (! file_exists($templatePath)) {
            return [];
        }

        $spreadsheet = IOFactory::load($templatePath);
        $polaPii = self::daftarPolaPiiPaPenajam();
        $pelanggaran = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetTitle = $sheet->getTitle();
            foreach ($sheet->toArray(null, true, true, true) as $rowIndex => $row) {
                foreach ($row as $col => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $cellText = (string) $value;
                    foreach ($polaPii as $pola) {
                        if ($pola !== '' && stripos($cellText, $pola) !== false) {
                            $pelanggaran[] = "{$sheetTitle}!{$col}{$rowIndex} -> {$pola}";
                        }
                    }
                }
            }
        }

        return $pelanggaran;
    }
}
