<?php

use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use App\Enums\StatusPegawai;
use App\Enums\StatusPengajuanPerubahanData;
use App\Exports\KgbMonitoringExport;
use App\Imports\PegawaiImport;
use App\Models\DokumenPegawai;
use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Database\Seeders\IamSeeder;
use Database\Seeders\PermissionSikepP1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
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
    ->group('tier4');

test('skenario end-to-end 1: siklus hidup lengkap data pegawai dari import batch, self-service, pengajuan keluarga, upload google drive dan foto lokal, hingga notifikasi ulid dan unduhan terotorisasi', function (): void {
    Carbon::setTestNow('2026-03-31');
    KepegawaianE2eHelper::seedReferensiDasar();
    Storage::fake('google');
    Storage::fake('local');
    Storage::fake('public');

    $xlsxPath = KepegawaianE2eHelper::buatFileXlsxImport([
        [
            '199304152020021011',
            'Rizky Pratama, S.H.',
            'Kasubbag Kepegawaian, Organisasi, dan Tatalaksana',
            'Subbag Kepegawaian, Organisasi, dan Tata Laksana',
            'III/a',
            'rizky.workflow@contoh.test',
            '081200001111',
        ],
        [
            '199409202021042022',
            'Dewi Sartika, S.Kom.',
            'Sekretaris',
            'Sekretaris',
            'III/b',
            'dewi.workflow@contoh.test',
            '081200002222',
        ],
    ], 'workflow_batch');

    try {
        // Langkah 1: Import batch ASN sintetis dan verifikasi parsing '!Ym' di tanggal 31 Maret
        $import = new PegawaiImport;
        Excel::import($import, $xlsxPath);

        expect($import->errors)->toBeEmpty()
            ->and($import->importedCount)->toBe(2);

        $pegawai = Pegawai::query()->where('nip', '199304152020021011')->firstOrFail();
        expect($pegawai->tanggal_masuk?->toDateString())->toBe('2020-02-01')
            ->and($pegawai->jenis_kelamin)->toBe(JenisKelamin::LakiLaki);

        // Sesuaikan TMT pangkat aktif agar masuk jendela monitoring KGB & KP
        $riwayatAktif = RiwayatPangkat::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('is_aktif', true)
            ->firstOrFail();
        $riwayatAktif->update(['tmt' => '2024-05-01']);

        // Langkah 2: Pegawai login mengakses Self-Service (index & detail read-only)
        $this->actingAs($pegawai)
            ->get(route('self-service.index'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('self-service/index')
                ->where('pegawai.id', $pegawai->id)
                ->where('kgbInfo.tanggal_kgb_berikutnya', '2026-05-01')
                ->where('kpInfo.tmt_kp_berikutnya', '2028-05-01'),
            );

        $this->actingAs($pegawai)
            ->get(route('self-service.detail'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('self-service/detail')
                ->where('pegawai.id', $pegawai->id)
                ->has('pegawai.riwayat_pangkat', 1),
            );

        // Langkah 3: Operator mengajukan perubahan data keluarga (AyahKandung) lalu Validator menyetujui
        $operator = Pegawai::factory()->operator()->create();
        $validator = Pegawai::factory()->validator()->create();

        $this->actingAs($operator)
            ->post(route('kepegawaian.pegawai.keluarga.store', $pegawai), [
                'hubungan' => HubunganKeluarga::AyahKandung->value,
                'nama' => 'Hendra Pratama',
                'jenis_kelamin' => JenisKelamin::LakiLaki->value,
            ])
            ->assertSessionHasNoErrors();

        $pengajuan = PengajuanPerubahanData::query()
            ->where('subject_pegawai_id', $pegawai->id)
            ->where('domain', 'orang_tua')
            ->where('status', StatusPengajuanPerubahanData::Pending)
            ->firstOrFail();

        $this->actingAs($validator)
            ->post(route('kepegawaian.pengajuan.approve', $pengajuan))
            ->assertRedirect(route('kepegawaian.pengajuan.show', $pengajuan));

        expect(Keluarga::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('hubungan', HubunganKeluarga::AyahKandung->value)
            ->where('nama', 'Hendra Pratama')
            ->exists())->toBeTrue();

        // Langkah 4: Operator mengunggah dokumen resmi ke disk Google Drive & Admin mengunggah foto ke disk local
        $dokumenFile = UploadedFile::fake()->create('sk-cpns-rizky.pdf', 200, 'application/pdf');
        $this->actingAs($operator)
            ->post(
                route('kepegawaian.pegawai.dokumen.store', $pegawai),
                KepegawaianE2eHelper::payloadUploadDokumen([
                    'jenis_dokumen' => 'SK CPNS',
                    'nomor_dokumen' => 'SK-CPNS/2020/11',
                    'file' => $dokumenFile,
                ]),
            )
            ->assertSessionHasNoErrors();

        $dokumen = DokumenPegawai::query()->where('pegawai_id', $pegawai->id)->firstOrFail();
        Storage::disk('google')->assertExists((string) $dokumen->file_path);

        $admin = Pegawai::factory()->admin()->create();
        $fotoFile = UploadedFile::fake()->image('foto-rizky.jpg', 300, 300);
        $this->actingAs($admin)
            ->post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $fotoFile])
            ->assertSessionHasNoErrors();

        $pegawai->refresh();
        Storage::disk('local')->assertExists((string) $pegawai->foto);
        Storage::disk('public')->assertMissing((string) $pegawai->foto);

        // Langkah 5: Pegawai menerima notifikasi ULID, membacanya, dan dokumen/foto diunduh terotorisasi
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
                    'title' => 'Pembaruan Data & Dokumen',
                    'body' => 'Data keluarga dan dokumen SK CPNS Anda telah diperbarui.',
                ];
            }
        };

        $pegawai->notify($notifikasi);
        $notifDb = DatabaseNotification::query()
            ->where('notifiable_type', Pegawai::class)
            ->where('notifiable_id', $pegawai->id)
            ->firstOrFail();

        $this->actingAs($pegawai)
            ->getJson(route('notifications.index'))
            ->assertSuccessful()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($pegawai)
            ->postJson(route('notifications.read', $notifDb->id))
            ->assertSuccessful()
            ->assertJsonPath('success', true);

        $downloadDokumenUrl = KepegawaianE2eHelper::urlDownloadDokumen($pegawai, $dokumen);
        $downloadFotoUrl = KepegawaianE2eHelper::urlDownloadFoto($pegawai);
        expect($downloadDokumenUrl)->not->toBeNull()
            ->and($downloadFotoUrl)->not->toBeNull();

        $pemeriksa = KepegawaianE2eHelper::buatPegawaiDenganPermission(['pegawai.view']);

        $this->actingAs($pemeriksa)->get((string) $downloadDokumenUrl)->assertSuccessful();
        $this->actingAs($pemeriksa)->get((string) $downloadFotoUrl)->assertSuccessful();

        $tanpaIzin = Pegawai::factory()->viewer()->create();
        $this->actingAs($tanpaIzin)->get((string) $downloadDokumenUrl)->assertForbidden();
        $this->actingAs($tanpaIzin)->get((string) $downloadFotoUrl)->assertForbidden();
    } finally {
        KepegawaianE2eHelper::hapusFileSementara($xlsxPath);
    }
});

test('skenario end-to-end 2: siklus monitoring kgb dan kp skala besar (>105 pegawai), export penuh, kgb:notify idempoten, serta gerbang higienitas rilis', function (): void {
    Carbon::setTestNow('2026-01-15');
    $this->seed(PermissionSikepP1Seeder::class);
    KepegawaianE2eHelper::seedReferensiDasar();

    $pangkat = RefPangkat::query()->where('kode', 'III/c')->firstOrFail();

    // Populasi 106 pegawai aktif dengan TMT jatuh tempo KGB & eligible KP pada 2026
    foreach (range(1, 106) as $urutan) {
        KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
            '2022-02-01',
            [
                'nama_lengkap' => sprintf('ASN Skala Besar %03d', $urutan),
                'email' => sprintf('asn.skala.%03d@contoh.test', $urutan),
            ],
            $pangkat,
        );
    }

    // Tambahkan 1 pegawai yang riwayat pangkat aktifnya di-soft-delete (tidak boleh menyebabkan HTTP 500)
    $pegawaiRiwayatTerhapus = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => StatusPegawai::Aktif->value,
        'email' => 'riwayat.terhapus@contoh.test',
    ]);
    $riwayatTerhapus = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiRiwayatTerhapus->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => '2022-02-01',
        'is_aktif' => true,
    ]);
    $riwayatTerhapus->delete();

    $userMonitoring = KepegawaianE2eHelper::buatPegawaiDenganPermission([
        'monitoring.view',
        'kenaikan-pangkat.monitoring.view',
    ]);

    // Monitoring KGB & KP bebas 500 dan menampilkan statistik akurat 106 pegawai
    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kgb.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kgbStats.total', 106),
        );

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kenaikan-pangkat.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.tahun', 2026)
            ->where('kpStats.total', 106),
        );

    // Export KGB & KP mengekspor seluruh 106 baris tanpa terpotong limit 100
    Excel::fake();

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kgb.export'))
        ->assertSuccessful();

    Excel::assertDownloaded('kgb-monitoring.xlsx', function (KgbMonitoringExport $export): bool {
        return $export->collection()->count() === 106;
    });

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kenaikan-pangkat.export'))
        ->assertSuccessful();

    Excel::assertDownloaded('kp-monitoring.xlsx', function ($export): bool {
        return $export->collection()->count() === 106;
    });

    // Jalankan kgb:notify dua kali: hanya terkirim 1x per pegawai aktif dan 0x untuk riwayat soft-deleted
    $this->artisan('kgb:notify')
        ->expectsOutput('Notifikasi KGB terkirim ke 106 pegawai.')
        ->assertSuccessful();

    $this->artisan('kgb:notify')
        ->expectsOutput('Notifikasi KGB terkirim ke 0 pegawai.')
        ->assertSuccessful();

    // Verifikasi gerbang higienitas nol-PII & kesiapan DevSecOps rilis
    expect(file_exists(base_path('docs/data_pegawai.json')))->toBeFalse()
        ->and(file_exists(base_path('docs/data-pegawai.html')))->toBeFalse()
        ->and(file_exists(base_path('docs/org_structure.json')))->toBeFalse()
        ->and(KepegawaianE2eHelper::pelanggaranPiiTemplateImport())->toBeEmpty()
        ->and(is_dir(public_path('css/filament')))->toBeFalse()
        ->and(is_dir(public_path('js/filament')))->toBeFalse()
        ->and(is_dir(public_path('vendor/swagger-api')))->toBeFalse();
});
