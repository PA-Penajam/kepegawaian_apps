<?php

declare(strict_types=1);

namespace Tests\E2E\KenaikanPangkat\Support;

use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;

/**
 * Helper terpusat untuk pengujian E2E domain Usulan Kenaikan Pangkat (KP) & Berkas Checklist.
 *
 * Kelas ini menyiapkan aktor, permission IAM eksplisit tanpa bergantung pada
 * PermissionSikepP1Seeder (pra-M5), fixture pegawai & riwayat pangkat aktif,
 * serta fake PDF renderer yang tetap mengeksekusi rendering view Blade nyata.
 */
final class KpE2eHelper
{
    /**
     * Daftar lengkap slug permission KP & Checklist sesuai standar SIKEP P1.
     *
     * @return array<int, string>
     */
    public static function semuaPermissionKp(): array
    {
        return [
            'kenaikan-pangkat.usulan.view',
            'kenaikan-pangkat.usulan.create',
            'kenaikan-pangkat.usulan.update',
            'kenaikan-pangkat.usulan.delete',
            'kenaikan-pangkat.usulan.submit',
            'kenaikan-pangkat.usulan.verifikasi-kasubbag',
            'kenaikan-pangkat.usulan.verifikasi-sekretaris',
            'kenaikan-pangkat.usulan.tanda-tangan-ketua',
            'kenaikan-pangkat.usulan.kirim-biro',
            'kenaikan-pangkat.usulan.upload-sk',
            'kenaikan-pangkat.usulan.finalize',
            'kenaikan-pangkat.monitoring.view',
            'checklist.template.view',
            'checklist.template.create',
            'checklist.template.update',
            'checklist.template.delete',
            'checklist.submission.view',
            'checklist.submission.update-item',
            'checklist.submission.validate-item',
        ];
    }

    /**
     * Memberikan daftar slug permission IAM pada aplikasi "kepegawaian" kepada pegawai.
     *
     * @param  array<int, string>  $permissionSlugs
     */
    public static function beriPermissions(Pegawai $pegawai, array $permissionSlugs, ?string $roleSlug = null): Pegawai
    {
        $app = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();

        $resolvedRoleSlug = $roleSlug ?? 'kp-e2e-'.substr(md5(implode('|', $permissionSlugs)), 0, 12);

        $role = IamRole::query()->firstOrCreate(
            ['iam_application_id' => $app->id, 'slug' => $resolvedRoleSlug],
            ['nama' => 'Role E2E '.$resolvedRoleSlug, 'is_system' => false],
        );

        foreach ($permissionSlugs as $slug) {
            $permission = IamPermission::query()->firstOrCreate(
                ['iam_application_id' => $app->id, 'slug' => $slug],
                ['nama' => $slug, 'group' => 'kenaikan-pangkat-e2e'],
            );

            IamRolePermission::query()->firstOrCreate([
                'iam_role_id' => $role->id,
                'iam_permission_id' => $permission->id,
            ]);
        }

        IamUserRole::query()->firstOrCreate(
            ['user_id' => $pegawai->id, 'iam_role_id' => $role->id],
            ['assigned_at' => now()],
        );

        return $pegawai->refresh();
    }

    /**
     * Membuat pegawai PNS aktif beserta satu riwayat pangkat aktif.
     *
     * @param  array<string, mixed>  $pegawaiAttributes
     * @return array{pegawai: Pegawai, pangkat: RefPangkat, riwayat: RiwayatPangkat}
     */
    public static function buatPegawaiPnsDenganPangkatAktif(
        ?RefPangkat $pangkat = null,
        string $tmt = '2021-04-01',
        array $pegawaiAttributes = [],
        int $masaKerjaTahun = 4,
        int $masaKerjaBulan = 0,
    ): array {
        $pangkat ??= RefPangkat::query()->where('kode', 'III/a')->first()
            ?? RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III', 'ruang' => 'a']);

        $pegawai = Pegawai::factory()->create(array_merge([
            'status_pegawai' => StatusPegawai::Aktif->value,
            'status_kepegawaian' => StatusKepegawaian::PNS->value,
            'ref_pangkat_id' => $pangkat->id,
        ], $pegawaiAttributes));

        $riwayat = RiwayatPangkat::factory()->create([
            'pegawai_id' => $pegawai->id,
            'ref_pangkat_id' => $pangkat->id,
            'tmt' => $tmt,
            'tanggal_sk' => $tmt,
            'masa_kerja_tahun' => $masaKerjaTahun,
            'masa_kerja_bulan' => $masaKerjaBulan,
            'is_aktif' => true,
        ]);

        return [
            'pegawai' => $pegawai,
            'pangkat' => $pangkat,
            'riwayat' => $riwayat,
        ];
    }

    /**
     * Menyiapkan paket aktor workflow KP dengan permission terisolasi per peran.
     *
     * @return array{
     *     pemohon: Pegawai,
     *     kasubbag: Pegawai,
     *     sekretaris: Pegawai,
     *     ketua: Pegawai,
     *     biro: Pegawai,
     *     adminSk: Pegawai,
     *     pangkatAsal: RefPangkat,
     *     pangkatTujuan: RefPangkat,
     *     riwayatAsal: RiwayatPangkat
     * }
     */
    public static function buatAktorWorkflowKp(string $tmtPangkatAsal = '2021-04-01'): array
    {
        $pangkatAsal = RefPangkat::query()->where('kode', 'III/a')->first()
            ?? RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III', 'ruang' => 'a']);
        $pangkatTujuan = RefPangkat::query()->where('kode', 'III/b')->first()
            ?? RefPangkat::factory()->create(['kode' => 'III/b', 'golongan' => 'III', 'ruang' => 'b']);

        ['pegawai' => $pemohon, 'riwayat' => $riwayatAsal] = self::buatPegawaiPnsDenganPangkatAktif(
            $pangkatAsal,
            $tmtPangkatAsal,
        );

        self::beriPermissions($pemohon, [
            'kenaikan-pangkat.usulan.view',
            'kenaikan-pangkat.usulan.create',
            'kenaikan-pangkat.usulan.update',
            'kenaikan-pangkat.usulan.delete',
            'kenaikan-pangkat.usulan.submit',
            'checklist.submission.view',
            'checklist.submission.update-item',
        ], 'kp-e2e-pemohon');

        $kasubbag = self::beriPermissions(
            Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
            [
                'kenaikan-pangkat.usulan.view',
                'kenaikan-pangkat.usulan.verifikasi-kasubbag',
                'checklist.submission.view',
                'checklist.submission.validate-item',
            ],
            'kp-e2e-kasubbag',
        );

        $sekretaris = self::beriPermissions(
            Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
            [
                'kenaikan-pangkat.usulan.view',
                'kenaikan-pangkat.usulan.verifikasi-sekretaris',
                'checklist.submission.view',
                'checklist.submission.validate-item',
            ],
            'kp-e2e-sekretaris',
        );

        $ketua = self::beriPermissions(
            Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
            [
                'kenaikan-pangkat.usulan.view',
                'kenaikan-pangkat.usulan.tanda-tangan-ketua',
            ],
            'kp-e2e-ketua',
        );

        $biro = self::beriPermissions(
            Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
            [
                'kenaikan-pangkat.usulan.view',
                'kenaikan-pangkat.usulan.kirim-biro',
            ],
            'kp-e2e-biro',
        );

        $adminSk = self::beriPermissions(
            Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
            [
                'kenaikan-pangkat.usulan.view',
                'kenaikan-pangkat.usulan.upload-sk',
                'kenaikan-pangkat.usulan.finalize',
            ],
            'kp-e2e-admin-sk',
        );

        return [
            'pemohon' => $pemohon,
            'kasubbag' => $kasubbag,
            'sekretaris' => $sekretaris,
            'ketua' => $ketua,
            'biro' => $biro,
            'adminSk' => $adminSk,
            'pangkatAsal' => $pangkatAsal,
            'pangkatTujuan' => $pangkatTujuan,
            'riwayatAsal' => $riwayatAsal,
        ];
    }

    /**
     * Membuat draft usulan KP lengkap melalui service untuk persiapan skenario lanjutan.
     */
    public static function buatDraftUsulanSiap(
        Pegawai $pemohon,
        RefPangkat $pangkatAsal,
        RefPangkat $pangkatTujuan,
        string $tmtPangkatAsal = '2021-04-01',
        int $bulan = 4,
        ?int $tahun = null,
    ): UsulanKenaikanPangkat {
        return app(UsulanKenaikanPangkatService::class)->createDraft([
            'pegawai_id' => $pemohon->id,
            'ref_pangkat_asal_id' => $pangkatAsal->id,
            'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
            'tmt_pangkat_asal' => $tmtPangkatAsal,
            'periode_usul_bulan' => $bulan,
            'periode_usul_tahun' => $tahun ?? (int) now()->addYear()->format('Y'),
            'catatan_pengusul' => 'Usulan kenaikan pangkat reguler E2E',
        ], $pemohon);
    }

    /**
     * Mengunggah file dummy dan memvalidasi seluruh item wajib checklist menjadi STATUS_VALID.
     */
    public static function lengkapiDanValidasiChecklistWajib(
        UsulanKenaikanPangkat $usulan,
        Pegawai $validator,
    ): BerkasChecklistSubmission {
        $checklistService = app(ChecklistBerkasService::class);
        $submission = $usulan->checklistSubmission()->with(['template.items', 'items.item'])->firstOrFail();

        $requiredItemIds = $submission->template->items()->wajib()->pluck('id');

        $submission->items()
            ->whereIn('berkas_checklist_item_id', $requiredItemIds)
            ->get()
            ->each(function (BerkasChecklistSubmissionItem $submissionItem) use ($checklistService, $validator): void {
                $file = UploadedFile::fake()->create("{$submissionItem->id}.pdf", 64, 'application/pdf');
                $checklistService->uploadFile($submissionItem, $file);
                $checklistService->validateItem(
                    $submissionItem->fresh(),
                    $validator,
                    BerkasChecklistSubmissionItem::STATUS_VALID,
                    'Terverifikasi lengkap',
                );
            });

        $freshSubmission = $submission->fresh(['template.items', 'items.item']);
        $checklistService->recalculatePersentase($freshSubmission);

        return $freshSubmission->fresh(['template.items', 'items.item']);
    }

    /**
     * Memasang fake Spatie PdfBuilder yang tetap merender view Blade secara nyata
     * dan merekam level transaksi DB saat eksekusi save() berlangsung.
     *
     * @return object{renderedHtml: string, viewName: string, transactionLevels: array<int, int>, savedPaths: array<int, string>}
     */
    public static function pasangFakePdfDenganRenderBlade(): object
    {
        $tracker = new class
        {
            public string $renderedHtml = '';

            public string $viewName = '';

            /** @var array<int, int> */
            public array $transactionLevels = [];

            /** @var array<int, string> */
            public array $savedPaths = [];
        };

        Pdf::swap(new class($tracker) extends FakePdfBuilder
        {
            public function __construct(private readonly object $tracker) {}

            public function save(string $path): self
            {
                $this->tracker->transactionLevels[] = DB::transactionLevel();
                $this->tracker->viewName = $this->viewName;

                if ($this->viewName !== '') {
                    $rendered = view($this->viewName, $this->viewData)->render();
                    $this->html = $rendered;
                    $this->tracker->renderedHtml = $rendered;
                }

                parent::save($path);

                $dir = dirname($path);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($path, "%PDF-1.4\n% E2E Fake PDF\n");
                $this->tracker->savedPaths[] = $path;

                return $this;
            }
        });

        return $tracker;
    }

    /**
     * Membuat header otentikasi HMAC-SHA256 untuk endpoint API kenaikan pangkat.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string|int>
     */
    public static function buatHeaderHmacApi(
        string $method,
        string $path,
        array $query = [],
        string $rawBody = '[]',
        ?int $timestamp = null,
        ?string $secret = null,
    ): array {
        $resolvedTimestamp = $timestamp ?? now()->timestamp;
        $resolvedSecret = $secret ?? (string) config('kepegawaian.secret_key');
        $queryString = http_build_query(collect($query)->sortKeys()->all());
        $bodyHash = hash('sha256', $rawBody);
        $payload = strtoupper($method).':'.$path.':'.$queryString.':'.$bodyHash.':'.$resolvedTimestamp;

        return [
            'X-Timestamp' => $resolvedTimestamp,
            'X-Signature' => hash_hmac('sha256', $payload, $resolvedSecret),
            'Accept' => 'application/json',
        ];
    }

    /**
     * Mendeteksi URL rute POST/PATCH validasi item checklist dari Route::getRoutes().
     */
    public static function urlValidasiChecklist(
        UsulanKenaikanPangkat $usulan,
        BerkasChecklistSubmissionItem $item,
    ): string {
        $route = self::temukanRuteValidasiChecklist();

        if ($route !== null) {
            $uri = '/'.ltrim($route->uri(), '/');
            $paramCount = preg_match_all('/\{[^}]+\}/', $uri);
            $index = 0;

            $replaced = preg_replace_callback('/\{([^}]+)\}/', static function (array $matches) use (&$index, $paramCount, $usulan, $item): string {
                $index++;
                $paramName = strtolower($matches[1]);

                if ($paramCount === 1) {
                    return (string) $item->id;
                }

                if (str_contains($paramName, 'usulan')) {
                    return (string) $usulan->id;
                }

                if (str_contains($paramName, 'item') || str_contains($paramName, 'checklist')) {
                    return (string) $item->id;
                }

                return $index === 1 ? (string) $usulan->id : (string) $item->id;
            }, $uri);

            return $replaced ?? "/kenaikan-pangkat/usulan/{$usulan->id}/checklist/{$item->id}/validate";
        }

        return "/kenaikan-pangkat/usulan/{$usulan->id}/checklist/{$item->id}/validate";
    }

    /**
     * Mendeteksi metode HTTP (POST atau PATCH) untuk rute validasi item checklist.
     */
    public static function methodValidasiChecklist(): string
    {
        $route = self::temukanRuteValidasiChecklist();

        if ($route !== null) {
            $methods = array_map('strtoupper', $route->methods());

            if (! in_array('POST', $methods, true) && in_array('PATCH', $methods, true)) {
                return 'PATCH';
            }
        }

        return 'POST';
    }

    /**
     * Mencari definisi rute Laravel untuk endpoint validasi item checklist usulan KP.
     */
    private static function temukanRuteValidasiChecklist(): ?LaravelRoute
    {
        $candidateNames = [
            'kenaikan-pangkat.usulan.checklist.validate',
            'kenaikan-pangkat.usulan.checklist.validasi',
            'kenaikan-pangkat.usulan.checklist.validate-item',
            'kenaikan-pangkat.checklist.validate',
            'kenaikan-pangkat.checklist.validasi',
        ];

        $routes = Route::getRoutes();

        foreach ($candidateNames as $name) {
            $namedRoute = $routes->getByName($name);

            if ($namedRoute !== null) {
                return $namedRoute;
            }
        }

        foreach ($routes as $route) {
            $uri = strtolower($route->uri());
            $methods = array_map('strtoupper', $route->methods());
            $supportsMutation = in_array('POST', $methods, true) || in_array('PATCH', $methods, true);

            if (
                $supportsMutation
                && str_contains($uri, 'kenaikan-pangkat')
                && str_contains($uri, 'checklist')
                && (str_contains($uri, 'validate') || str_contains($uri, 'validasi'))
            ) {
                return $route;
            }
        }

        return null;
    }
}
