<?php

use App\Enums\StatusPegawai;
use App\Enums\StatusPengajuanPerubahanData;
use App\Models\DokumenPegawai;
use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Services\PengajuanPerubahanData\ApprovePengajuanPerubahanDataService;
use App\Services\PengajuanPerubahanData\RejectPengajuanPerubahanDataService;
use App\Services\RiwayatPangkatService;
use Carbon\Carbon;
use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\E2E\Kepegawaian\Support\KepegawaianE2eHelper;
use Tests\TestCase;

require_once __DIR__.'/Support/KepegawaianE2eHelper.php';

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutVite();
        $this->seed(IamSeeder::class);
    })
    ->afterEach(function (): void {
        Carbon::setTestNow();
    })
    ->group('tier2');

test('F-08 menolak input file_path string bebas bermuatan javascript atau data URI serta memblokir mass assignment file_path', function (string $uriBerbahaya): void {
    KepegawaianE2eHelper::seedReferensiDasar();
    Storage::fake('google');

    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($operator)
        ->from(route('kepegawaian.pegawai.dokumen.index', $pegawai))
        ->post(
            route('kepegawaian.pegawai.dokumen.store', $pegawai),
            KepegawaianE2eHelper::payloadUploadDokumen([
                'file_path' => $uriBerbahaya,
            ]),
        )
        ->assertSessionHasErrors('file');

    expect(DokumenPegawai::query()->where('pegawai_id', $pegawai->id)->count())->toBe(0);

    $dokumen = new DokumenPegawai;
    $dokumen->fill(['file_path' => $uriBerbahaya]);
    expect($dokumen->file_path)->toBeNull();
})->with([
    'skema javascript' => 'javascript:alert(1)',
    'skema data html' => 'data:text/html,<script>alert(1)</script>',
]);

test('F-08 menolak atau mengabaikan input file_path bermuatan javascript atau data URI pada dokumen.update tanpa upload file baru', function (string $uriBerbahaya): void {
    KepegawaianE2eHelper::seedReferensiDasar();
    Storage::fake('google');

    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    Storage::disk('google')->put('dokumen/asli-aman.pdf', 'isi-dokumen-asli');
    $dokumen = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'jenis_dokumen' => 'SK Pangkat',
    ]);
    $dokumen->forceFill(['file_path' => 'dokumen/asli-aman.pdf'])->save();

    $respons = $this->actingAs($operator)
        ->from(route('kepegawaian.pegawai.dokumen.index', $pegawai))
        ->put(
            route('kepegawaian.pegawai.dokumen.update', [$pegawai, $dokumen]),
            KepegawaianE2eHelper::payloadUploadDokumen([
                'nomor_dokumen' => 'SK-800/REV/2026',
                'file_path' => $uriBerbahaya,
            ]),
        );

    expect($respons->status())->not->toBe(500);

    $pathTerbaru = (string) $dokumen->fresh()?->file_path;
    expect($pathTerbaru)
        ->toBe('dokumen/asli-aman.pdf')
        ->not->toBe($uriBerbahaya);
})->with([
    'skema javascript' => 'javascript:alert(1)',
    'skema data html' => 'data:text/html,<script>alert(1)</script>',
]);

test('F-08 menolak upload dokumen dengan MIME tidak valid atau ukuran file berlebih', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();
    Storage::fake('google');

    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    $fileHtml = UploadedFile::fake()->create('xss.html', 10, 'text/html');
    $this->actingAs($operator)
        ->post(
            route('kepegawaian.pegawai.dokumen.store', $pegawai),
            KepegawaianE2eHelper::payloadUploadDokumen(['file' => $fileHtml]),
        )
        ->assertSessionHasErrors('file');

    $fileBesar = UploadedFile::fake()->create('dokumen-raksasa.pdf', 15360, 'application/pdf');
    $this->actingAs($operator)
        ->post(
            route('kepegawaian.pegawai.dokumen.store', $pegawai),
            KepegawaianE2eHelper::payloadUploadDokumen(['file' => $fileBesar]),
        )
        ->assertSessionHasErrors('file');

    expect(Storage::disk('google')->allFiles())->toBeEmpty();
});

test('F-08 menolak akses dokumen lintas-pegawai (IDOR) dan pengguna tanpa izin pegawai.view', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();
    Storage::fake('google');

    $operator = Pegawai::factory()->operator()->create();
    $pegawaiA = Pegawai::factory()->create();
    $pegawaiB = Pegawai::factory()->create();

    Storage::disk('google')->put('dokumen/b-rahasia.pdf', 'isi-rahasia');
    $dokumenB = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawaiB->id,
        'jenis_dokumen' => 'SK Pangkat',
        'file_path' => 'dokumen/b-rahasia.pdf',
    ]);

    // Operator mencoba mengubah atau menghapus dokumen B melalui rute pegawai A
    $this->actingAs($operator)
        ->delete(route('kepegawaian.pegawai.dokumen.destroy', [$pegawaiA, $dokumenB]))
        ->assertNotFound();

    $downloadUrlLintasPegawai = KepegawaianE2eHelper::urlDownloadDokumen($pegawaiA, $dokumenB);
    expect($downloadUrlLintasPegawai)->not->toBeNull();

    $this->actingAs($operator)
        ->get((string) $downloadUrlLintasPegawai)
        ->assertNotFound();

    // Viewer tanpa izin pegawai.view dilarang mengunduh dokumen B
    $viewerTanpaIzin = Pegawai::factory()->viewer()->create();
    $downloadUrlSah = KepegawaianE2eHelper::urlDownloadDokumen($pegawaiB, $dokumenB);
    expect($downloadUrlSah)->not->toBeNull();

    $this->actingAs($viewerTanpaIzin)
        ->get((string) $downloadUrlSah)
        ->assertForbidden();
});

test('F-08 komponen dan controller dokumen-pegawai menyaring legacy URI berbahaya serta merender tautan unduhan aman', function (): void {
    Storage::fake('google');

    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    $dokumenLegacy = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'jenis_dokumen' => 'SK Pangkat',
    ]);
    $dokumenLegacy->forceFill(['file_path' => 'javascript:alert(1)'])->save();

    $this->actingAs($operator)
        ->get(route('kepegawaian.pegawai.dokumen.index', $pegawai))
        ->assertSuccessful()
        ->assertInertia(function (Assert $page): void {
            $page->component('kepegawaian/pegawai/dokumen-pegawai')
                ->has('dokumen', 1);

            $item = $page->toArray()['props']['dokumen'][0];
            $hasDownloadUrlProp = array_key_exists('download_url', $item)
                || array_key_exists('file_url', $item)
                || array_key_exists('preview_url', $item);

            if ($hasDownloadUrlProp) {
                foreach (['download_url', 'file_url', 'preview_url'] as $urlKey) {
                    if (array_key_exists($urlKey, $item) && $item[$urlKey] !== null) {
                        expect(strtolower(trim((string) $item[$urlKey])))
                            ->not->toMatch('/^(javascript|data|vbscript):/i');
                    }
                }
            } else {
                expect(strtolower(trim((string) ($item['file_path'] ?? ''))))
                    ->not->toMatch('/^(javascript|data|vbscript):/i');
            }
        });

    $tsx = (string) file_get_contents(resource_path('js/pages/kepegawaian/pegawai/dokumen-pegawai.tsx'));

    expect($tsx)
        ->not->toContain('href={item.file_path}')
        ->not->toContain('id="file_path"')
        ->not->toMatch('/href=\{\s*item\.(?:file_path|download_url|file_url|url)\s*\}/');

    $memilikiSanitasiAtauRouteAman = (bool) preg_match('/https?:|startsWith\(\s*[\'"]\/[\'"]|download/', $tsx);
    expect($memilikiSanitasiAtauRouteAman)->toBeTrue();
});

test('F-11 dataset PII asli PA Penajam tidak ada di working tree dan template import bebas PII', function (): void {
    $fileTerlarang = [
        base_path('docs/data-pegawai.html'),
        base_path('docs/data_pegawai.json'),
        base_path('docs/org_structure.json'),
    ];

    foreach ($fileTerlarang as $file) {
        expect(file_exists($file))->toBeFalse("File PII terlarang masih ditemukan: {$file}");
    }

    expect(KepegawaianE2eHelper::pelanggaranPiiTemplateImport())->toBeEmpty();
});

test('F-11 direktori app, database, tests, dan docs bebas dari nama dan NIP asli PA Penajam', function (): void {
    $polaPii = KepegawaianE2eHelper::daftarPolaPiiPaPenajam();
    $pelanggaran = [];

    foreach (['app', 'database', 'tests', 'docs'] as $dir) {
        $baseDir = base_path($dir);
        if (! is_dir($baseDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['php', 'ts', 'tsx', 'js', 'json', 'md', 'yaml', 'yml', 'html', 'txt', 'csv'], true)) {
                continue;
            }

            $isi = (string) file_get_contents($file->getPathname());
            foreach ($polaPii as $pola) {
                if ($pola !== '' && stripos($isi, $pola) !== false) {
                    $pelanggaran[] = $file->getPathname().' -> '.$pola;
                }
            }
        }
    }

    expect($pelanggaran)->toBeEmpty();
});

test('F-12 kolom notifiable_id menyimpan dan meng-query string ULID 26 karakter tanpa terpotong menjadi nol', function (): void {
    $ulidEksplisit = (string) Str::ulid();
    $pegawai = Pegawai::factory()->create(['id' => $ulidEksplisit]);

    $notifikasi = new class extends Notification
    {
        /**
         * @return array<int, string>
         */
        public function via(object $notifiable): array
        {
            return ['database'];
        }

        /**
         * @return array<string, mixed>
         */
        public function toArray(object $notifiable): array
        {
            return ['title' => 'Cek ULID'];
        }
    };

    $pegawai->notify($notifikasi);

    $records = DatabaseNotification::query()
        ->where('notifiable_type', Pegawai::class)
        ->where('notifiable_id', $ulidEksplisit)
        ->get();

    expect($records)->toHaveCount(1)
        ->and($records->first()?->notifiable_id)->toBe($ulidEksplisit)
        ->and((string) $records->first()?->notifiable_id)->not->toBe('0');
});

test('F-29 pengguna tanpa izin monitoring ditolak 403 pada index dan export monitoring kgb maupun kp', function (): void {
    $operator = Pegawai::factory()->operator()->create();
    $viewer = Pegawai::factory()->viewer()->create();

    foreach ([$operator, $viewer] as $userTanpaMonitoring) {
        $this->actingAs($userTanpaMonitoring)
            ->get(route('monitoring.kgb.index'))
            ->assertForbidden();

        $this->actingAs($userTanpaMonitoring)
            ->get(route('monitoring.kgb.export'))
            ->assertForbidden();

        $this->actingAs($userTanpaMonitoring)
            ->get(route('monitoring.kenaikan-pangkat.index'))
            ->assertForbidden();

        $this->actingAs($userTanpaMonitoring)
            ->get(route('monitoring.kenaikan-pangkat.export'))
            ->assertForbidden();
    }
});

test('F-52 menolak mass assignment foto pada Pegawai dan menolak akses foto tanpa izin pegawai.view', function (): void {
    Storage::fake('local');

    $pegawai = Pegawai::factory()->create(['foto' => 'fotos/asli.webp']);
    Storage::disk('local')->put('fotos/asli.webp', 'binary-webp-content');

    $pegawai->fill(['foto' => 'fotos/injeksi.webp']);
    expect($pegawai->foto)->toBe('fotos/asli.webp');

    $urlDownloadFoto = KepegawaianE2eHelper::urlDownloadFoto($pegawai);
    expect($urlDownloadFoto)->not->toBeNull();

    $viewerTanpaIzin = Pegawai::factory()->viewer()->create();
    $this->actingAs($viewerTanpaIzin)
        ->get((string) $urlDownloadFoto)
        ->assertForbidden();
});

test('F-54 menolak approval dan rejection ganda pada pengajuan yang bukan berstatus Pending', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Nama Tetap']);

    $sudahDisetujui = PengajuanPerubahanData::factory()->create([
        'subject_pegawai_id' => $pegawai->id,
        'target_type' => 'pegawai',
        'target_id' => $pegawai->id,
        'status' => StatusPengajuanPerubahanData::Approved,
        'after_payload' => ['nama_lengkap' => 'Nama Diubah Paksa'],
    ]);

    $this->actingAs($validator)
        ->post(route('kepegawaian.pengajuan.approve', $sudahDisetujui))
        ->assertForbidden();

    $this->actingAs($validator)
        ->post(route('kepegawaian.pengajuan.reject', $sudahDisetujui), [
            'alasan_penolakan' => 'Tolak ulang',
        ])
        ->assertForbidden();

    $approveService = app(ApprovePengajuanPerubahanDataService::class);
    $rejectService = app(RejectPengajuanPerubahanDataService::class);

    expect(fn () => $approveService->handle($sudahDisetujui->fresh(), $validator))
        ->toThrow(DomainException::class);

    expect(fn () => $rejectService->handle($sudahDisetujui->fresh(), $validator, 'Tolak langsung via service'))
        ->toThrow(DomainException::class);

    expect($pegawai->fresh()?->nama_lengkap)->toBe('Nama Tetap');
});

test('F-54 menolak modifikasi keluarga milik pegawai lain dan mengabaikan field nik saat approval profil', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawaiA = Pegawai::factory()->create(['nama_lengkap' => 'Pemilik Asli']);
    $pegawaiB = Pegawai::factory()->create();

    $keluargaMilikB = Keluarga::factory()->create([
        'pegawai_id' => $pegawaiB->id,
        'nama' => 'Anggota Keluarga B',
    ]);

    // Pengajuan atas nama pegawai A namun target_id menunjuk ke keluarga milik pegawai B
    $pengajuanLintasPegawai = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawaiA->id,
        'subject_pegawai_id' => $pegawaiA->id,
        'domain' => 'anak',
        'aksi' => 'update',
        'target_type' => 'keluarga',
        'target_id' => $keluargaMilikB->id,
        'status' => StatusPengajuanPerubahanData::Pending,
        'after_payload' => ['nama' => 'Dibajak Oleh A'],
    ]);

    $approveService = app(ApprovePengajuanPerubahanDataService::class);

    expect(fn () => $approveService->handle($pengajuanLintasPegawai, $validator))
        ->toThrow(DomainException::class);

    expect($keluargaMilikB->fresh()?->nama)->toBe('Anggota Keluarga B');

    // Pengajuan profil tidak boleh menyertakan field nik yang tidak valid di skema pegawai
    $pengajuanNik = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawaiA->id,
        'subject_pegawai_id' => $pegawaiA->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawaiA->id,
        'status' => StatusPengajuanPerubahanData::Pending,
        'after_payload' => [
            'nama_lengkap' => 'Pemilik Diperbarui',
            'nik' => '9999999999999999',
        ],
    ]);

    $approveService->handle($pengajuanNik, $validator);

    expect($pegawaiA->fresh()?->nama_lengkap)->toBe('Pemilik Diperbarui');
});

test('F-28 parameter per_page non-numerik dan negatif di monitoring kp tidak melempar TypeError', function (string $perPageInput): void {
    $userMonitoringKp = KepegawaianE2eHelper::buatPegawaiDenganPermission(['kenaikan-pangkat.monitoring.view']);

    $this->actingAs($userMonitoringKp)
        ->get(route('monitoring.kenaikan-pangkat.index', ['per_page' => $perPageInput]))
        ->assertSuccessful();
})->with([
    'teks non-numerik' => 'abc',
    'nol' => '0',
    'negatif' => '-5',
]);

test('F-31 kgb:notify tidak mengirim notifikasi duplikat pada hari atau periode yang sama', function (): void {
    Carbon::setTestNow('2026-01-15');

    KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif('2024-02-01', [
        'email' => 'antiduplikat.kgb@contoh.test',
    ]);

    $this->artisan('kgb:notify')
        ->expectsOutput('Notifikasi KGB terkirim ke 1 pegawai.')
        ->assertSuccessful();

    $this->artisan('kgb:notify')
        ->expectsOutput('Notifikasi KGB terkirim ke 0 pegawai.')
        ->assertSuccessful();
});

test('F-53 import pegawai menolak format file excel lama berekstensi .xls', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();

    $dir = storage_path('framework/testing');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $xlsPath = $dir.'/legacy_format_'.Str::lower(Str::random(6)).'.xls';
    file_put_contents($xlsPath, 'dummy-xls-content');

    try {
        $this->artisan('pegawai:import', ['file' => $xlsPath])
            ->assertFailed();
    } finally {
        KepegawaianE2eHelper::hapusFileSementara($xlsPath);
    }
});

test('F-71 pencarian LIKE dengan karakter wildcard % dan _ tidak membocorkan seluruh baris pegawai', function (): void {
    $admin = Pegawai::factory()->admin()->create(['nama_lengkap' => 'Admin Pencari']);
    Pegawai::factory()->create(['nama_lengkap' => 'Pegawai Biasa Satu']);
    Pegawai::factory()->create(['nama_lengkap' => 'Pegawai Biasa Dua']);

    $this->actingAs($admin)
        ->get(route('kepegawaian.pegawai.index', ['search' => '%']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('pegawai.total', 0),
        );

    $this->actingAs($admin)
        ->get(route('kepegawaian.pegawai.index', ['search' => '_']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('pegawai.total', 0),
        );
});

test('F-57 mutasi berturut-turut riwayat pangkat aktif selalu menjaga tepat satu record aktif per pegawai', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();

    $pangkatA = RefPangkat::query()->where('kode', 'III/a')->firstOrFail();
    $pangkatB = RefPangkat::query()->where('kode', 'III/b')->firstOrFail();
    $pangkatC = RefPangkat::query()->where('kode', 'III/c')->firstOrFail();

    $pegawai = Pegawai::factory()->create(['ref_pangkat_id' => $pangkatA->id]);
    $service = app(RiwayatPangkatService::class);

    $r1 = $service->store($pegawai, [
        'ref_pangkat_id' => $pangkatA->id,
        'no_sk' => 'SK-1',
        'tanggal_sk' => '2020-01-01',
        'tmt' => '2020-02-01',
        'is_aktif' => true,
    ]);

    $r2 = $service->store($pegawai, [
        'ref_pangkat_id' => $pangkatB->id,
        'no_sk' => 'SK-2',
        'tanggal_sk' => '2022-01-01',
        'tmt' => '2022-02-01',
        'is_aktif' => true,
    ]);

    $service->update($r1->fresh(), $pegawai, [
        'ref_pangkat_id' => $pangkatC->id,
        'no_sk' => 'SK-3',
        'tanggal_sk' => '2024-01-01',
        'tmt' => '2024-02-01',
        'is_aktif' => true,
    ]);

    $jumlahAktif = RiwayatPangkat::query()
        ->where('pegawai_id', $pegawai->id)
        ->where('is_aktif', true)
        ->count();

    expect($jumlahAktif)->toBe(1)
        ->and($r2->fresh()?->is_aktif)->toBeFalse()
        ->and($pegawai->fresh()?->ref_pangkat_id)->toBe($pangkatC->id);
});

test('F-32 pegawai tanpa riwayat pangkat aktif tetap dapat mengakses self-service tanpa galat 500', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);

    $this->actingAs($pegawai)
        ->get(route('self-service.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('self-service/index')
            ->where('pegawai.id', $pegawai->id)
            ->where('kgbInfo', null)
            ->where('kpInfo', null),
        );

    $this->actingAs($pegawai)
        ->get(route('self-service.detail'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('self-service/detail')
            ->where('pegawai.id', $pegawai->id),
        );
});
