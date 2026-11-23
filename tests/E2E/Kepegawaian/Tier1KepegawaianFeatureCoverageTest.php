<?php

use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use App\Enums\StatusPegawai;
use App\Enums\StatusPengajuanPerubahanData;
use App\Exports\KgbMonitoringExport;
use App\Imports\PegawaiImport;
use App\Models\DokumenPegawai;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Models\SyncConsumer;
use App\Notifications\KgbJatuhTempoNotification;
use Carbon\Carbon;
use Database\Seeders\IamSeeder;
use Database\Seeders\PermissionSikepP1Seeder;
use Google\Client as GoogleClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Maatwebsite\Excel\Facades\Excel;
use Masbug\Flysystem\GoogleDriveAdapter;
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
    ->group('tier1');

test('F-08 infrastruktur disk google drive, paket adapter, dan registrasi driver AppServiceProvider terkonfigurasi dengan lengkap', function (): void {
    $diskConfig = config('filesystems.disks.google');

    expect($diskConfig)->toBeArray()
        ->and($diskConfig['driver'] ?? null)->toBe('google')
        ->and($diskConfig)->toHaveKeys(['clientId', 'clientSecret', 'refreshToken', 'folder']);

    $composerJson = json_decode((string) file_get_contents(base_path('composer.json')), true);
    expect($composerJson['require'] ?? [])->toHaveKey('masbug/flysystem-google-drive-ext');

    $envExample = (string) file_get_contents(base_path('.env.example'));
    expect($envExample)
        ->toContain('GOOGLE_DRIVE_CLIENT_ID')
        ->toContain('GOOGLE_DRIVE_CLIENT_SECRET')
        ->toContain('GOOGLE_DRIVE_REFRESH_TOKEN')
        ->toContain('GOOGLE_DRIVE_FOLDER');

    $mockClient = Mockery::mock(GoogleClient::class)->shouldIgnoreMissing();
    $mockClient->shouldReceive('setClientId')->once()->with('e2e-client-id');
    $mockClient->shouldReceive('setClientSecret')->once()->with('e2e-client-secret');
    $mockClient->shouldReceive('refreshToken')->once()->with('e2e-refresh-token')->andReturn([]);

    app()->instance(GoogleClient::class, $mockClient);

    config([
        'filesystems.disks.google' => [
            'driver' => 'google',
            'clientId' => 'e2e-client-id',
            'clientSecret' => 'e2e-client-secret',
            'refreshToken' => 'e2e-refresh-token',
            'folder' => '/',
        ],
    ]);

    Storage::forgetDisk('google');
    $disk = Storage::disk('google');

    expect($disk)->toBeInstanceOf(FilesystemAdapter::class)
        ->and($disk->getAdapter())->toBeInstanceOf(GoogleDriveAdapter::class);
});

test('F-08 operator dapat mengunggah dokumen pdf dan gambar valid ke disk google secara terisolasi', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();
    Storage::fake('google');

    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    $filePdf = UploadedFile::fake()->create('sk-pangkat.pdf', 256, 'application/pdf');
    $fileJpg = UploadedFile::fake()->image('ijazah.jpg', 400, 400);

    $this->actingAs($operator)
        ->post(
            route('kepegawaian.pegawai.dokumen.store', $pegawai),
            KepegawaianE2eHelper::payloadUploadDokumen([
                'jenis_dokumen' => 'SK Pangkat',
                'file' => $filePdf,
            ]),
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.dokumen.index', $pegawai));

    $this->actingAs($operator)
        ->post(
            route('kepegawaian.pegawai.dokumen.store', $pegawai),
            KepegawaianE2eHelper::payloadUploadDokumen([
                'jenis_dokumen' => 'Ijazah',
                'nomor_dokumen' => 'IJZ-002/2026',
                'file' => $fileJpg,
            ]),
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.dokumen.index', $pegawai));

    $dokumenList = DokumenPegawai::query()->where('pegawai_id', $pegawai->id)->get();
    expect($dokumenList)->toHaveCount(2);

    foreach ($dokumenList as $dokumen) {
        expect($dokumen->file_path)->not->toBeNull();
        Storage::disk('google')->assertExists((string) $dokumen->file_path);
    }
});

test('F-08 endpoint unduhan dokumen pegawai terotorisasi mengembalikan berkas dari disk google', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();
    Storage::fake('google');

    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->create();

    $filePdf = UploadedFile::fake()->create('arsip-resmi.pdf', 128, 'application/pdf');

    $this->actingAs($operator)
        ->post(
            route('kepegawaian.pegawai.dokumen.store', $pegawai),
            KepegawaianE2eHelper::payloadUploadDokumen(['file' => $filePdf]),
        )
        ->assertSessionHasNoErrors();

    $dokumen = DokumenPegawai::query()->where('pegawai_id', $pegawai->id)->firstOrFail();
    $downloadUrl = KepegawaianE2eHelper::urlDownloadDokumen($pegawai, $dokumen);

    expect($downloadUrl)->not->toBeNull();

    $viewerBerizin = KepegawaianE2eHelper::buatPegawaiDenganPermission(['pegawai.view']);

    $this->actingAs($viewerBerizin)
        ->get((string) $downloadUrl)
        ->assertSuccessful();
});

test('F-12 kolom notifiable_id pada tabel notifications kompatibel dengan ulid dan menyimpan notifikasi pegawai', function (): void {
    $columnType = strtolower(Schema::getColumnType('notifications', 'notifiable_id'));
    expect($columnType)->not->toContain('int');

    $pegawai = Pegawai::factory()->create();
    expect(strlen($pegawai->id))->toBe(26);

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
            return [
                'title' => 'Uji Notifikasi ULID',
                'body' => 'Pesan verifikasi kompatibilitas ULID.',
                'link' => '/self-service',
            ];
        }
    };

    $pegawai->notify($notifikasi);

    $tersimpan = DatabaseNotification::query()
        ->where('notifiable_type', Pegawai::class)
        ->where('notifiable_id', $pegawai->id)
        ->first();

    expect($tersimpan)->not->toBeNull()
        ->and($tersimpan?->notifiable_id)->toBe($pegawai->id);

    $this->actingAs($pegawai)
        ->getJson(route('notifications.index'))
        ->assertSuccessful()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.title', 'Uji Notifikasi ULID');
});

test('F-28 dan F-29 pengguna berizin dapat mengakses monitoring kp dengan tahun default konsisten antara index dan export', function (): void {
    Carbon::setTestNow('2026-02-01');
    KepegawaianE2eHelper::seedReferensiDasar();

    $pangkat = RefPangkat::query()->where('kode', 'III/a')->firstOrFail();

    $pegawaiTahunIni = KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
        '2022-05-01',
        ['nama_lengkap' => 'ASN KP Tahun Ini'],
        $pangkat,
    );
    KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
        '2023-05-01',
        ['nama_lengkap' => 'ASN KP Tahun Depan'],
        $pangkat,
    );

    $userMonitoringKp = KepegawaianE2eHelper::buatPegawaiDenganPermission(['kenaikan-pangkat.monitoring.view']);

    $this->actingAs($userMonitoringKp)
        ->get(route('monitoring.kenaikan-pangkat.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kenaikan-pangkat/index')
            ->where('filters.tahun', 2026)
            ->has('pegawaiList.data', 1)
            ->where('pegawaiList.data.0.id', $pegawaiTahunIni->id),
        );

    Excel::fake();

    $this->actingAs($userMonitoringKp)
        ->get(route('monitoring.kenaikan-pangkat.export'))
        ->assertSuccessful();

    Excel::assertDownloaded('kp-monitoring.xlsx', function ($export) use ($pegawaiTahunIni): bool {
        $rows = $export->collection();

        return $rows->count() === 1
            && $rows->first()['nip'] === $pegawaiTahunIni->nip;
    });
});

test('F-29 pengguna dengan izin monitoring view dapat mengakses index dan export monitoring kgb', function (): void {
    Carbon::setTestNow('2026-01-15');
    KepegawaianE2eHelper::seedReferensiDasar();

    $pegawai = KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif('2024-02-01', [
        'nama_lengkap' => 'ASN KGB Segera',
    ]);

    $userMonitoring = KepegawaianE2eHelper::buatPegawaiDenganPermission(['monitoring.view']);

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kgb.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->has('pegawaiList.data', 1)
            ->where('pegawaiList.data.0.id', $pegawai->id),
        );

    Excel::fake();

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kgb.export'))
        ->assertSuccessful();

    Excel::assertDownloaded('kgb-monitoring.xlsx');
});

test('F-30 riwayat pangkat aktif yang di-soft-delete tidak memicu galat 500 pada monitoring kgb maupun kp', function (): void {
    Carbon::setTestNow('2026-01-15');
    KepegawaianE2eHelper::seedReferensiDasar();

    $pangkat = RefPangkat::query()->where('kode', 'III/a')->firstOrFail();

    $pegawaiSoftDeletedRiwayat = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);
    $riwayatTerhapus = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiSoftDeletedRiwayat->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => '2022-02-01',
        'is_aktif' => true,
    ]);
    $riwayatTerhapus->delete();

    $pegawaiValid = KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
        '2022-03-01',
        ['nama_lengkap' => 'ASN Riwayat Valid'],
        $pangkat,
    );

    $userMonitoring = KepegawaianE2eHelper::buatPegawaiDenganPermission([
        'monitoring.view',
        'kenaikan-pangkat.monitoring.view',
    ]);

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kgb.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pegawaiList.data', 1)
            ->where('pegawaiList.data.0.id', $pegawaiValid->id),
        );

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kenaikan-pangkat.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pegawaiList.data', 1)
            ->where('pegawaiList.data.0.id', $pegawaiValid->id),
        );

    Excel::fake();
    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kenaikan-pangkat.export'))
        ->assertSuccessful();
});

test('F-31 kgb:notify mempertahankan id ulid pegawai dan terdaftar pada jadwal artisan', function (): void {
    Carbon::setTestNow('2026-01-15');
    NotificationFacade::fake();

    $pegawai = KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif('2024-02-01', [
        'email' => 'asn.kgb@contoh.test',
    ]);

    $pegawaiTerhapusRiwayat = Pegawai::factory()->create(['email' => 'terhapus.kgb@contoh.test']);
    $riwayatDihapus = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiTerhapusRiwayat->id,
        'tmt' => '2024-02-01',
        'is_aktif' => true,
    ]);
    $riwayatDihapus->delete();

    $this->artisan('kgb:notify')->assertSuccessful();

    NotificationFacade::assertSentTo($pegawai, KgbJatuhTempoNotification::class);
    NotificationFacade::assertNotSentTo($pegawaiTerhapusRiwayat, KgbJatuhTempoNotification::class);

    $this->artisan('schedule:list')
        ->expectsOutputToContain('kgb:notify')
        ->assertSuccessful();
});

test('F-70 export kgb tidak terpotong batas 100 baris saat data melebihi 105 pegawai', function (): void {
    Carbon::setTestNow('2026-01-15');
    KepegawaianE2eHelper::seedReferensiDasar();

    $pangkat = RefPangkat::query()->where('kode', 'III/b')->firstOrFail();

    foreach (range(1, 108) as $urutan) {
        KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
            '2024-02-01',
            ['nama_lengkap' => sprintf('ASN Export %03d', $urutan)],
            $pangkat,
        );
    }

    $userMonitoring = KepegawaianE2eHelper::buatPegawaiDenganPermission(['monitoring.view']);
    Excel::fake();

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kgb.export'))
        ->assertSuccessful();

    Excel::assertDownloaded('kgb-monitoring.xlsx', function (KgbMonitoringExport $export): bool {
        return $export->collection()->count() === 108;
    });
});

test('F-32 self service mengembalikan status kgb dan kp akurat walau di luar 15 baris pertama paginasi', function (): void {
    Carbon::setTestNow('2026-01-15');
    KepegawaianE2eHelper::seedReferensiDasar();

    $pangkat = RefPangkat::query()->where('kode', 'III/a')->firstOrFail();

    foreach (range(1, 18) as $i) {
        KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
            '2022-01-20',
            ['nama_lengkap' => sprintf('AAAA Pegawai Awal %02d', $i)],
            $pangkat,
        );
    }

    $pegawaiAkhir = KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
        '2024-03-15',
        ['nama_lengkap' => 'ZZZZ Pegawai Akhir'],
        $pangkat,
    );

    $this->actingAs($pegawaiAkhir)
        ->get(route('self-service.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/index')
            ->where('pegawai.id', $pegawaiAkhir->id)
            ->where('kgbInfo.tanggal_kgb_berikutnya', '2026-03-15')
            ->where('kpInfo.tmt_kp_berikutnya', '2028-03-15')
            ->where('kpInfo.periode_usul', 'Maret 2028'),
        );
});

test('F-32 self service detail merender profil dan riwayat read-only tanpa launcher tautan edit', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();

    $pegawai = KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif('2023-04-01');
    Keluarga::factory()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => HubunganKeluarga::Anak->value,
        'nama' => 'Anak Read Only',
    ]);

    $this->actingAs($pegawai)
        ->get(route('self-service.detail'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/detail')
            ->where('pegawai.id', $pegawai->id)
            ->has('pegawai.riwayat_pangkat', 1)
            ->has('pegawai.keluarga', 1),
        );

    $tsxContent = (string) file_get_contents(resource_path('js/pages/self-service/detail.tsx'));
    expect($tsxContent)
        ->not->toContain('PegawaiController.edit')
        ->not->toContain('Menu Pengelolaan Data');
});

test('F-35 seeder PermissionSikepP1Seeder menautkan permission ke role IamSeeder secara idempoten tanpa galat tipe ulid', function (): void {
    $app = IamApplication::query()->where('slug', 'kepegawaian')->firstOrFail();

    $p1Slugs = [
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

    $this->seed(PermissionSikepP1Seeder::class);

    $p1PermIds = IamPermission::query()
        ->where('iam_application_id', $app->id)
        ->whereIn('slug', $p1Slugs)
        ->pluck('id');

    expect($p1PermIds)->toHaveCount(count($p1Slugs));

    $adminRole = IamRole::query()
        ->where('iam_application_id', $app->id)
        ->where('slug', 'admin')
        ->firstOrFail();

    $jumlahLinkAdminPertama = IamRolePermission::query()
        ->where('iam_role_id', $adminRole->id)
        ->whereIn('iam_permission_id', $p1PermIds)
        ->count();

    expect($jumlahLinkAdminPertama)->toBe(count($p1Slugs));

    // Jalankan ulang untuk memastikan idempotensi tanpa duplikasi maupun galat ULID
    $this->seed(PermissionSikepP1Seeder::class);

    $jumlahLinkAdminKedua = IamRolePermission::query()
        ->where('iam_role_id', $adminRole->id)
        ->whereIn('iam_permission_id', $p1PermIds)
        ->count();

    expect($jumlahLinkAdminKedua)->toBe($jumlahLinkAdminPertama);

    // Role operasional non-admin dari IamSeeder juga menerima mapping permission tanpa TypeError
    $nonAdminRoleIds = IamRole::query()
        ->where('iam_application_id', $app->id)
        ->whereIn('slug', ['operator', 'viewer', 'validator'])
        ->pluck('id');

    $jumlahLinkNonAdmin = IamRolePermission::query()
        ->whereIn('iam_role_id', $nonAdminRoleIds)
        ->whereIn('iam_permission_id', $p1PermIds)
        ->count();

    expect($jumlahLinkNonAdmin)->toBeGreaterThan(0);
});

test('F-52 foto pegawai disimpan ke disk local, memiliki endpoint download terotorisasi, dan canUpdate terikat permission', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['foto' => null]);

    $fileFoto = UploadedFile::fake()->image('pas-foto.jpg', 300, 300);

    $this->actingAs($admin)
        ->post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $fileFoto])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $pegawai->refresh();
    expect($pegawai->foto)->not->toBeNull();
    Storage::disk('local')->assertExists((string) $pegawai->foto);
    Storage::disk('public')->assertMissing((string) $pegawai->foto);

    $urlDownloadFoto = KepegawaianE2eHelper::urlDownloadFoto($pegawai);
    expect($urlDownloadFoto)->not->toBeNull();

    $viewerBerizin = KepegawaianE2eHelper::buatPegawaiDenganPermission(['pegawai.view']);
    $this->actingAs($viewerBerizin)
        ->get((string) $urlDownloadFoto)
        ->assertSuccessful();

    $showTsx = (string) file_get_contents(resource_path('js/pages/kepegawaian/pegawai/show.tsx'));
    expect($showTsx)->not->toContain('canUpdate={true}');
});

test('F-53 pegawai import mendukung xlsx dan csv serta mem-parse periode masuk dengan format !Ym secara akurat', function (): void {
    Carbon::setTestNow('2026-03-31');
    KepegawaianE2eHelper::seedReferensiDasar();

    $csvPath = KepegawaianE2eHelper::buatFileCsvImport([
        [
            '199505102020021001',
            'Budi Santoso, S.H.',
            'Kasubbag Kepegawaian, Organisasi, dan Tatalaksana',
            'Subbag Kepegawaian, Organisasi, dan Tata Laksana',
            'III/a',
            'budi.csv@contoh.test',
            '081200000001',
        ],
    ], 'csv_ym');

    $xlsxPath = KepegawaianE2eHelper::buatFileXlsxImport([
        [
            '199608172021042002',
            'Sari Lestari, S.Kom.',
            'Sekretaris',
            'Sekretaris',
            'III/b',
            'sari.xlsx@contoh.test',
            '081200000002',
        ],
    ], 'xlsx_ym');

    try {
        $importCsv = new PegawaiImport;
        Excel::import($importCsv, $csvPath);

        $importXlsx = new PegawaiImport;
        Excel::import($importXlsx, $xlsxPath);

        expect($importCsv->errors)->toBeEmpty()
            ->and($importXlsx->errors)->toBeEmpty();

        $pegawaiCsv = Pegawai::query()->where('nip', '199505102020021001')->firstOrFail();
        $pegawaiXlsx = Pegawai::query()->where('nip', '199608172021042002')->firstOrFail();

        expect($pegawaiCsv->tanggal_masuk?->toDateString())->toBe('2020-02-01')
            ->and($pegawaiCsv->jenis_kelamin)->toBe(JenisKelamin::LakiLaki)
            ->and($pegawaiXlsx->tanggal_masuk?->toDateString())->toBe('2021-04-01')
            ->and($pegawaiXlsx->jenis_kelamin)->toBe(JenisKelamin::Perempuan);
    } finally {
        KepegawaianE2eHelper::hapusFileSementara($csvPath);
        KepegawaianE2eHelper::hapusFileSementara($xlsxPath);
    }
});

test('F-53 re-import memulihkan pegawai soft-deleted dan menjaga tmt serta tanggal_sk riwayat aktif eksisting', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();

    $pangkatLama = RefPangkat::query()->where('kode', 'III/a')->firstOrFail();
    $pegawai = Pegawai::factory()->create([
        'nip' => '199201012019031005',
        'nama_lengkap' => 'Nama Sebelum Dihapus',
        'ref_pangkat_id' => $pangkatLama->id,
    ]);

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pangkatLama->id,
        'no_sk' => 'SK-EKSISTING-01',
        'tanggal_sk' => '2023-03-15',
        'tmt' => '2023-04-01',
        'is_aktif' => true,
    ]);

    $pegawai->delete();
    expect(Pegawai::query()->find($pegawai->id))->toBeNull();

    $csvPath = KepegawaianE2eHelper::buatFileCsvImport([
        [
            '199201012019031005',
            'Nama Setelah Reimport',
            'Sekretaris',
            'Sekretaris',
            'III/b',
            'reimport@contoh.test',
            '081200000009',
        ],
    ], 'reimport_restore');

    try {
        $import = new PegawaiImport;
        Excel::import($import, $csvPath);

        $pulih = Pegawai::query()->find($pegawai->id);
        expect($pulih)->not->toBeNull()
            ->and($pulih?->trashed())->toBeFalse()
            ->and($pulih?->nama_lengkap)->toBe('Nama Setelah Reimport');

        $riwayatAktif = RiwayatPangkat::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('is_aktif', true)
            ->firstOrFail();

        expect($riwayatAktif->tmt?->toDateString())->toBe('2023-04-01')
            ->and($riwayatAktif->tanggal_sk?->toDateString())->toBe('2023-03-15');
    } finally {
        KepegawaianE2eHelper::hapusFileSementara($csvPath);
    }
});

test('F-54 pengajuan perubahan keluarga AyahKandung dipetakan ke domain orang_tua dan disetujui atau ditolak validator', function (): void {
    $operator = Pegawai::factory()->operator()->create();
    $validator = Pegawai::factory()->validator()->create();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($operator)
        ->post(route('kepegawaian.pegawai.keluarga.store', $pegawai), [
            'hubungan' => HubunganKeluarga::AyahKandung->value,
            'nama' => 'Bapak Kandung Uji',
            'jenis_kelamin' => JenisKelamin::LakiLaki->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.keluarga.index', $pegawai));

    $pengajuan = PengajuanPerubahanData::query()
        ->where('subject_pegawai_id', $pegawai->id)
        ->where('domain', 'orang_tua')
        ->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan?->status)->toBe(StatusPengajuanPerubahanData::Pending);

    $this->actingAs($validator)
        ->post(route('kepegawaian.pengajuan.approve', $pengajuan))
        ->assertRedirect(route('kepegawaian.pengajuan.show', $pengajuan));

    expect($pengajuan->fresh()?->status)->toBe(StatusPengajuanPerubahanData::Approved)
        ->and(Keluarga::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('hubungan', HubunganKeluarga::AyahKandung->value)
            ->where('nama', 'Bapak Kandung Uji')
            ->exists())->toBeTrue();
});

test('F-55 F-56 F-71 aturan model preventLazyLoading, inheritance PengajuanPerubahanData, dan SyncConsumer scopeActive terpenuhi', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(is_subclass_of(PengajuanPerubahanData::class, App\Models\Model::class))->toBeTrue()
        ->and(in_array(TwoFactorAuthenticatable::class, class_uses_recursive(Pegawai::class), true))->toBeFalse();

    $reflection = new ReflectionMethod(SyncConsumer::class, 'scopeActive');
    $paramType = $reflection->getParameters()[0]?->getType();
    $returnType = $reflection->getReturnType();

    expect($paramType?->getName())->toBe(Builder::class)
        ->and($returnType?->getName())->toBe(Builder::class);

    $aktif = SyncConsumer::factory()->create(['is_active' => true]);
    SyncConsumer::factory()->create(['is_active' => false]);

    $hasil = SyncConsumer::query()->active()->pluck('id')->all();
    expect($hasil)->toBe([$aktif->id]);
});

test('F-57 menghapus riwayat pangkat aktif menyinkronkan ulang kolom denormalisasi ref_pangkat_id pada pegawai', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();

    $operator = Pegawai::factory()->operator()->create();
    $pangkat = RefPangkat::query()->where('kode', 'III/c')->firstOrFail();

    $pegawai = Pegawai::factory()->create(['ref_pangkat_id' => $pangkat->id]);
    $riwayatAktif = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pangkat->id,
        'is_aktif' => true,
        'tmt' => '2024-01-01',
    ]);

    $this->actingAs($operator)
        ->delete(route('kepegawaian.pegawai.riwayat-pangkat.destroy', [$pegawai, $riwayatAktif]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kepegawaian.pegawai.riwayat-pangkat.index', $pegawai));

    expect($pegawai->fresh()?->ref_pangkat_id)->toBeNull();
});

test('F-71 pencarian pegawai dengan karakter literal persen dan garis bawah melakukan escape wildcard LIKE dengan benar', function (): void {
    $admin = Pegawai::factory()->admin()->create(['nama_lengkap' => 'Admin Biasa']);
    $targetPersen = Pegawai::factory()->create(['nama_lengkap' => 'Target 50% Khusus']);
    Pegawai::factory()->create(['nama_lengkap' => 'Target 500 Biasa']);
    $targetUnderscore = Pegawai::factory()->create(['nama_lengkap' => 'Target_Khusus']);
    Pegawai::factory()->create(['nama_lengkap' => 'TargetXKhusus']);

    $this->actingAs($admin)
        ->get(route('kepegawaian.pegawai.index', ['search' => '50%']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawai.total', 1)
            ->where('pegawai.data.0.id', $targetPersen->id),
        );

    $this->actingAs($admin)
        ->get(route('kepegawaian.pegawai.index', ['search' => 'Target_Khusus']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawai.total', 1)
            ->where('pegawai.data.0.id', $targetUnderscore->id),
        );
});

test('F-38 dan F-40 workflow deploy.yml memenuhi kontrak robocopy, down/up, rollback penuh, queue:restart, dan TLS 1.2', function (): void {
    $deployYml = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

    expect($deployYml)
        ->toContain('/XJ')
        ->toContain("'public\\storage'")
        ->toContain('php artisan down')
        ->toContain('php artisan up')
        ->toContain('php artisan optimize:clear')
        ->toContain('php artisan queue:restart')
        ->toContain('Tls12');

    // Langkah rollback tidak boleh meng-exclude vendor, public\build, atau bootstrap\cache
    $posisiRollback = strpos($deployYml, 'Rollback on failure');
    expect($posisiRollback)->not->toBeFalse();
    $posisiAkhirRollback = strpos($deployYml, '/XF', (int) $posisiRollback);
    expect($posisiAkhirRollback)->not->toBeFalse();
    $bagianRollback = substr($deployYml, (int) $posisiRollback, (int) $posisiAkhirRollback - (int) $posisiRollback);

    expect($bagianRollback)
        ->toContain('robocopy')
        ->toContain('/XD')
        ->not->toContain("'vendor'")
        ->not->toContain("'public\\build'")
        ->not->toContain("'bootstrap\\cache'");
});

test('F-66 workflow lint.yml, tests.yml, dan deploy.yml memakai mode check-only, npm ci, fail-on-risky, dan runner terisolasi PR', function (): void {
    $lintYml = (string) file_get_contents(base_path('.github/workflows/lint.yml'));
    $testsYml = (string) file_get_contents(base_path('.github/workflows/tests.yml'));
    $deployYml = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));

    expect($lintYml)
        ->toContain('actions/setup-node')
        ->toContain('npm ci')
        ->toContain('lint:check')
        ->toContain('format:check')
        ->toContain('types:check');

    expect($testsYml)
        ->toContain('npm ci')
        ->toContain('--fail-on-risky');

    expect($deployYml)
        ->toContain("github.event_name == 'pull_request'")
        ->toContain('ubuntu-latest');
});

test('F-39 dan F-67 package.json menempatkan puppeteer di dependencies dan paket dev di devDependencies serta menjaga name lockfile', function (): void {
    $packageJson = json_decode((string) file_get_contents(base_path('package.json')), true);
    $dependencies = $packageJson['dependencies'] ?? [];
    $devDependencies = $packageJson['devDependencies'] ?? [];

    expect($dependencies)->toHaveKey('puppeteer')
        ->and($devDependencies)->not->toHaveKey('puppeteer');

    $paketDevWajib = [
        '@tailwindcss/vite',
        '@types/react',
        '@types/react-dom',
        '@vitejs/plugin-react',
        'concurrently',
        'globals',
        'laravel-vite-plugin',
        'typescript',
        'vite',
    ];

    foreach ($paketDevWajib as $paket) {
        expect($devDependencies)->toHaveKey($paket)
            ->and($dependencies)->not->toHaveKey($paket);
    }

    $lockHead = (string) file_get_contents(base_path('package-lock.json'), false, null, 0, 512);
    expect($lockHead)->toContain('"name": "kepegawaian_apps"');
});

test('F-68 dan F-73 eslint mengabaikan worktrees, aset mati terhapus, dan SeedingConfigConventionTest memulihkan $_SERVER', function (): void {
    $eslintConfig = (string) file_get_contents(base_path('eslint.config.js'));
    expect($eslintConfig)->toContain('.worktrees/**');

    expect(is_dir(public_path('css/filament')))->toBeFalse()
        ->and(is_dir(public_path('js/filament')))->toBeFalse()
        ->and(is_dir(public_path('vendor/swagger-api')))->toBeFalse();

    $conventionTest = (string) file_get_contents(base_path('tests/Feature/CodeQuality/SeedingConfigConventionTest.php'));
    expect($conventionTest)->toContain('$_SERVER');
});
