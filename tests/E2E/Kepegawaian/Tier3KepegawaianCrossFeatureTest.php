<?php

use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use App\Enums\StatusPengajuanPerubahanData;
use App\Imports\PegawaiImport;
use App\Models\DokumenPegawai;
use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use App\Models\RefPangkat;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use App\Models\SyncConsumer;
use App\Services\KenaikanPangkatMonitoringService;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Database\Seeders\IamSeeder;
use Illuminate\Database\Eloquent\Model;
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
    ->group('tier3');

test('kombinasi import xlsx pegawai, restore soft-deleted, proteksi tmt riwayat aktif, dan kemunculan langsung di monitoring kgb serta kp', function (): void {
    Carbon::setTestNow('2026-02-01');
    KepegawaianE2eHelper::seedReferensiDasar();

    $xlsxPath = KepegawaianE2eHelper::buatFileXlsxImport([
        [
            '199004122018031007',
            'Hendro Wijaya, S.H.',
            'Sekretaris',
            'Sekretaris',
            'III/b',
            'hendro.reimport@contoh.test',
            '081300000001',
        ],
    ], 'cross_import');

    try {
        Excel::import(new PegawaiImport, $xlsxPath);

        $pegawai = Pegawai::query()->where('nip', '199004122018031007')->firstOrFail();
        $riwayatPangkat = RiwayatPangkat::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('is_aktif', true)
            ->firstOrFail();

        expect(RiwayatJabatan::query()->where('pegawai_id', $pegawai->id)->where('is_aktif', true)->exists())->toBeTrue();

        // Set TMT riwayat aktif agar jatuh tempo KGB (2024-03-01) dan eligible KP di masa depan
        $riwayatPangkat->update([
            'tmt' => '2022-03-01',
            'tanggal_sk' => '2022-02-15',
        ]);

        // Soft-delete pegawai lalu re-import ulang file yang sama
        $pegawai->delete();
        expect(Pegawai::query()->find($pegawai->id))->toBeNull();

        Excel::import(new PegawaiImport, $xlsxPath);

        $pulih = Pegawai::query()->findOrFail($pegawai->id);
        $riwayatSetelahReimport = RiwayatPangkat::query()
            ->where('pegawai_id', $pulih->id)
            ->where('is_aktif', true)
            ->firstOrFail();

        expect($pulih->trashed())->toBeFalse()
            ->and($riwayatSetelahReimport->tmt?->toDateString())->toBe('2022-03-01')
            ->and($riwayatSetelahReimport->tanggal_sk?->toDateString())->toBe('2022-02-15');

        $userMonitoring = KepegawaianE2eHelper::buatPegawaiDenganPermission([
            'monitoring.view',
            'kenaikan-pangkat.monitoring.view',
        ]);

        $this->actingAs($userMonitoring)
            ->get(route('monitoring.kgb.index'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pegawaiList.data.0.id', $pulih->id),
            );

        $this->actingAs($userMonitoring)
            ->get(route('monitoring.kenaikan-pangkat.index'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pegawaiList.data.0.id', $pulih->id),
            );
    } finally {
        KepegawaianE2eHelper::hapusFileSementara($xlsxPath);
    }
});

test('kombinasi upload dokumen google drive, referensi jenis dokumen, policy akses unduhan, dan notifikasi ulid ke pegawai', function (): void {
    KepegawaianE2eHelper::seedReferensiDasar();
    Storage::fake('google');

    $operator = Pegawai::factory()->operator()->create();
    $pegawai = Pegawai::factory()->viewer()->create();

    $filePdf = UploadedFile::fake()->create('sk-pangkat-terbaru.pdf', 300, 'application/pdf');

    $this->actingAs($operator)
        ->post(
            route('kepegawaian.pegawai.dokumen.store', $pegawai),
            KepegawaianE2eHelper::payloadUploadDokumen([
                'jenis_dokumen' => 'SK Pangkat',
                'nomor_dokumen' => 'SK-999/2026',
                'file' => $filePdf,
            ]),
        )
        ->assertSessionHasNoErrors();

    $dokumen = DokumenPegawai::query()->where('pegawai_id', $pegawai->id)->firstOrFail();
    Storage::disk('google')->assertExists((string) $dokumen->file_path);

    $notifikasi = new class($dokumen->nomor_dokumen ?? '') extends Notification
    {
        public function __construct(private readonly string $nomor) {}

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
                'title' => 'Dokumen Diunggah',
                'body' => "Dokumen {$this->nomor} tersedia di arsip.",
            ];
        }
    };

    $pegawai->notify($notifikasi);

    $notifRecord = DatabaseNotification::query()
        ->where('notifiable_type', Pegawai::class)
        ->where('notifiable_id', $pegawai->id)
        ->first();

    expect($notifRecord)->not->toBeNull()
        ->and($notifRecord?->notifiable_id)->toBe($pegawai->id);

    $downloadUrl = KepegawaianE2eHelper::urlDownloadDokumen($pegawai, $dokumen);
    expect($downloadUrl)->not->toBeNull();

    // Tanpa permission pegawai.view harus ditolak 403
    $this->actingAs($pegawai)
        ->get((string) $downloadUrl)
        ->assertForbidden();

    // Operator berizin pegawai.view berhasil mengunduh
    $this->actingAs($operator)
        ->get((string) $downloadUrl)
        ->assertSuccessful();
});

test('kombinasi self-service di luar 15 baris pertama cocok dengan monitoring service serta alur pengajuan perubahan keluarga hingga approval validator', function (): void {
    Carbon::setTestNow('2026-01-15');
    KepegawaianE2eHelper::seedReferensiDasar();

    $pangkat = RefPangkat::query()->where('kode', 'III/a')->firstOrFail();

    foreach (range(1, 16) as $index) {
        KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
            '2022-01-20',
            ['nama_lengkap' => sprintf('AAA Pegawai Urutan %02d', $index)],
            $pangkat,
        );
    }

    $targetPegawai = KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
        '2024-03-01',
        ['nama_lengkap' => 'ZZZ Pegawai Target'],
        $pangkat,
    );

    $kgbExpected = app(KgbMonitoringService::class)->getKgbStatus($targetPegawai->fresh('riwayatPangkat'));
    $kpExpected = app(KenaikanPangkatMonitoringService::class)->getKpStatus($targetPegawai->fresh('riwayatPangkat'));

    $this->actingAs($targetPegawai)
        ->get(route('self-service.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kgbInfo.tanggal_kgb_berikutnya', $kgbExpected['tanggal_kgb_berikutnya']->toDateString())
            ->where('kgbInfo.status', $kgbExpected['status'])
            ->where('kpInfo.tmt_kp_berikutnya', $kpExpected['tmt_kp_berikutnya']->toDateString())
            ->where('kpInfo.periode_usul', $kpExpected['periode_usul']),
        );

    $operator = Pegawai::factory()->operator()->create();
    $validator = Pegawai::factory()->validator()->create();

    $this->actingAs($operator)
        ->post(route('kepegawaian.pegawai.keluarga.store', $targetPegawai), [
            'hubungan' => HubunganKeluarga::AyahKandung->value,
            'nama' => 'Ayah Kandung Self Service',
            'jenis_kelamin' => JenisKelamin::LakiLaki->value,
        ])
        ->assertSessionHasNoErrors();

    $pengajuan = PengajuanPerubahanData::query()
        ->where('subject_pegawai_id', $targetPegawai->id)
        ->where('domain', 'orang_tua')
        ->firstOrFail();

    $this->actingAs($validator)
        ->post(route('kepegawaian.pengajuan.approve', $pengajuan))
        ->assertRedirect(route('kepegawaian.pengajuan.show', $pengajuan));

    expect($pengajuan->fresh()?->status)->toBe(StatusPengajuanPerubahanData::Approved)
        ->and(Keluarga::query()
            ->where('pegawai_id', $targetPegawai->id)
            ->where('hubungan', HubunganKeluarga::AyahKandung->value)
            ->exists())->toBeTrue();

    // Percobaan approve kedua setelah tidak Pending ditolak
    $this->actingAs($validator)
        ->post(route('kepegawaian.pengajuan.approve', $pengajuan))
        ->assertForbidden();
});

test('kombinasi hapus riwayat pangkat aktif, resync denormalisasi pegawai, monitoring kgb, dan kgb:notify bebas 500 serta bebas duplikasi', function (): void {
    Carbon::setTestNow('2026-01-15');
    KepegawaianE2eHelper::seedReferensiDasar();

    $operator = Pegawai::factory()->operator()->create();
    $userMonitoring = KepegawaianE2eHelper::buatPegawaiDenganPermission([
        'monitoring.view',
        'kenaikan-pangkat.monitoring.view',
    ]);

    $pangkatLama = RefPangkat::query()->where('kode', 'III/a')->firstOrFail();
    $pangkatBaru = RefPangkat::query()->where('kode', 'III/b')->firstOrFail();

    $pegawaiSatu = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkatBaru->id,
        'email' => 'resync.satu@contoh.test',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiSatu->id,
        'ref_pangkat_id' => $pangkatLama->id,
        'tmt' => '2020-01-01',
        'is_aktif' => false,
    ]);
    $riwayatAktifSatu = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiSatu->id,
        'ref_pangkat_id' => $pangkatBaru->id,
        'tmt' => '2024-02-01',
        'is_aktif' => true,
    ]);

    $pegawaiDua = KepegawaianE2eHelper::buatPegawaiDenganPangkatAktif(
        '2024-02-10',
        ['email' => 'tetap.dua@contoh.test'],
        $pangkatBaru,
    );

    // Hapus riwayat aktif pegawaiSatu -> pegawai.ref_pangkat_id tersinkron ulang
    $this->actingAs($operator)
        ->delete(route('kepegawaian.pegawai.riwayat-pangkat.destroy', [$pegawaiSatu, $riwayatAktifSatu]))
        ->assertSessionHasNoErrors();

    expect($pegawaiSatu->fresh()?->ref_pangkat_id)->toBeNull();

    $this->actingAs($userMonitoring)
        ->get(route('monitoring.kgb.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pegawaiList.data', 1)
            ->where('pegawaiList.data.0.id', $pegawaiDua->id),
        );

    $this->artisan('kgb:notify')
        ->expectsOutput('Notifikasi KGB terkirim ke 1 pegawai.')
        ->assertSuccessful();

    $this->artisan('kgb:notify')
        ->expectsOutput('Notifikasi KGB terkirim ke 0 pegawai.')
        ->assertSuccessful();
});

test('kombinasi higienitas repo, ci/cd workflows, environment produksi, dan bebas PII PA Penajam', function (): void {
    $envExample = (string) file_get_contents(base_path('.env.example'));
    expect($envExample)
        ->toContain('APP_LOCALE=id')
        ->toContain('GOOGLE_DRIVE_CLIENT_ID')
        ->toContain('SSO_SCOPES');

    $deployYml = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));
    $lintYml = (string) file_get_contents(base_path('.github/workflows/lint.yml'));
    $testsYml = (string) file_get_contents(base_path('.github/workflows/tests.yml'));
    $packageJson = json_decode((string) file_get_contents(base_path('package.json')), true);
    $eslintConfig = (string) file_get_contents(base_path('eslint.config.js'));

    expect($deployYml)->toContain('/XJ')->toContain('php artisan down')->toContain('php artisan queue:restart')
        ->and($lintYml)->toContain('lint:check')->toContain('types:check')->toContain('npm ci')
        ->and($testsYml)->toContain('npm ci')->toContain('--fail-on-risky')
        ->and($packageJson['dependencies'] ?? [])->toHaveKey('puppeteer')
        ->and($eslintConfig)->toContain('.worktrees/**')
        ->and(file_exists(base_path('docs/data_pegawai.json')))->toBeFalse()
        ->and(file_exists(base_path('docs/data-pegawai.html')))->toBeFalse()
        ->and(file_exists(base_path('docs/org_structure.json')))->toBeFalse()
        ->and(KepegawaianE2eHelper::pelanggaranPiiTemplateImport())->toBeEmpty()
        ->and(is_dir(public_path('vendor/swagger-api')))->toBeFalse();
});

test('kombinasi foto disk local, preventLazyLoading, SyncConsumer scopeActive, dan pencarian escaped', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    expect(Model::preventsLazyLoading())->toBeTrue();

    $admin = Pegawai::factory()->admin()->create(['nama_lengkap' => 'Admin Uji Kombinasi']);
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Pegawai_100% Uji']);
    Pegawai::factory()->create(['nama_lengkap' => 'Pegawai 1000 Biasa']);

    $fileFoto = UploadedFile::fake()->image('profil.png', 300, 300);

    $this->actingAs($admin)
        ->post(route('kepegawaian.pegawai.foto.update', $pegawai), ['foto' => $fileFoto])
        ->assertSessionHasNoErrors();

    $pegawai->refresh();
    Storage::disk('local')->assertExists((string) $pegawai->foto);
    Storage::disk('public')->assertMissing((string) $pegawai->foto);

    // Halaman detail pegawai & self-service detail tidak boleh memicu LazyLoadingViolationException
    $this->actingAs($admin)
        ->get(route('kepegawaian.pegawai.show', $pegawai))
        ->assertSuccessful();

    $this->actingAs($pegawai)
        ->get(route('self-service.detail'))
        ->assertSuccessful();

    // Pencarian wildcard literal
    $this->actingAs($admin)
        ->get(route('kepegawaian.pegawai.index', ['search' => '100%']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawai.total', 1)
            ->where('pegawai.data.0.id', $pegawai->id),
        );

    $consumerAktif = SyncConsumer::factory()->create(['is_active' => true]);
    SyncConsumer::factory()->create(['is_active' => false]);

    expect(SyncConsumer::query()->active()->pluck('id')->all())->toBe([$consumerAktif->id]);
});
