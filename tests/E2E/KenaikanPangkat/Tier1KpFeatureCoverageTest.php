<?php

declare(strict_types=1);

use App\Enums\StatusPegawai;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\Sikep\SikepAdapter;
use App\Services\Sikep\UsulanKenaikanPangkatDto;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Database\Seeders\IamSeeder;
use Database\Seeders\RefPangkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\E2E\KenaikanPangkat\Support\KpE2eHelper;
use Tests\TestCase;

require_once __DIR__.'/Support/KpE2eHelper.php';

uses(TestCase::class, RefreshDatabase::class)->beforeEach(function (): void {
    $this->withoutVite();
    $this->seed([IamSeeder::class, RefPangkatSeeder::class, ChecklistKenaikanPangkatSeeder::class]);
})->group('tier1');

test('membuat draft usulan KP via HTTP dengan ULID menyelesaikan pangkat asal dan TMT aktif otomatis', function (): void {
    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $pemohon = $aktor['pemohon'];
    $pangkatAsal = $aktor['pangkatAsal'];
    $pangkatTujuan = $aktor['pangkatTujuan'];
    $tahunUsul = (int) now()->addYear()->format('Y');

    $response = $this->actingAs($pemohon)->post('/kenaikan-pangkat/usulan', [
        'pegawai_id' => $pemohon->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => $tahunUsul,
        'catatan_pengusul' => 'Usulan kenaikan pangkat reguler via HTTP',
    ]);

    $usulan = UsulanKenaikanPangkat::query()->where('pegawai_id', $pemohon->id)->first();

    expect($usulan)->not->toBeNull()
        ->and($usulan->ref_pangkat_asal_id)->toBe($pangkatAsal->id)
        ->and($usulan->tmt_pangkat_asal->toDateString())->toBe('2021-04-01')
        ->and((string) $usulan->state)->toBe('DRAFT')
        ->and($usulan->approvalSteps)->toHaveCount(3)
        ->and($usulan->checklistSubmission)->not->toBeNull();

    $response->assertRedirect(route('kenaikan-pangkat.usulan.show', $usulan));
});

test('memperbarui draft usulan KP via HTTP PUT dengan payload valid', function (): void {
    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $pemohon = $aktor['pemohon'];
    $usulan = KpE2eHelper::buatDraftUsulanSiap($pemohon, $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    $pangkatTujuanBaru = RefPangkat::query()->where('kode', 'III/c')->firstOrFail();
    $tahunBaru = (int) now()->addYear()->format('Y');

    $response = $this->actingAs($pemohon)->put("/kenaikan-pangkat/usulan/{$usulan->id}", [
        'pegawai_id' => $pemohon->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuanBaru->id,
        'periode_usul_bulan' => 10,
        'periode_usul_tahun' => $tahunBaru,
        'catatan_pengusul' => 'Pembaruan pangkat tujuan dan periode usul',
    ]);

    $response->assertRedirect(route('kenaikan-pangkat.usulan.show', $usulan));

    $usulan->refresh();
    expect($usulan->ref_pangkat_tujuan_id)->toBe($pangkatTujuanBaru->id)
        ->and($usulan->periode_usul_bulan)->toBe(10)
        ->and($usulan->periode_usul_tahun)->toBe($tahunBaru)
        ->and($usulan->catatan_pengusul)->toBe('Pembaruan pangkat tujuan dan periode usul');
});

test('halaman Inertia usulan index, form create/edit, dan show memenuhi kontrak props dan nama route', function (): void {
    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $pemohon = $aktor['pemohon'];
    $usulan = KpE2eHelper::buatDraftUsulanSiap($pemohon, $aktor['pangkatAsal'], $aktor['pangkatTujuan']);

    expect(Route::has('kenaikan-pangkat.usulan.index'))->toBeTrue()
        ->and(Route::has('kenaikan-pangkat.usulan.create'))->toBeTrue()
        ->and(Route::has('kenaikan-pangkat.usulan.show'))->toBeTrue()
        ->and(Route::has('kenaikan-pangkat.usulan.edit'))->toBeTrue();

    $this->actingAs($pemohon)
        ->get('/kenaikan-pangkat/usulan')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/index')
            ->has('usulanList.data', 1)
            ->has('stateLabels')
            ->has('bulanOptions', 12)
            ->has('tahunOptions'));

    $this->actingAs($pemohon)
        ->get("/kenaikan-pangkat/usulan/create?pegawai_id={$pemohon->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/form')
            ->where('pegawai.id', $pemohon->id)
            ->has('pangkatAsal')
            ->has('pangkatOptions')
            ->has('bulanOptions', 12)
            ->has('tahunOptions'));

    $this->actingAs($pemohon)
        ->get("/kenaikan-pangkat/usulan/{$usulan->id}/edit")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/form')
            ->where('usulan.id', $usulan->id)
            ->has('pegawai')
            ->has('pangkatAsal')
            ->has('pangkatOptions')
            ->has('bulanOptions', 12)
            ->has('tahunOptions'));

    $this->actingAs($pemohon)
        ->get("/kenaikan-pangkat/usulan/{$usulan->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/show')
            ->where('usulan.id', $usulan->id)
            ->has('usulan.pdfs')
            ->has('policy')
            ->has('stateLabels')
            ->has('stateBadgeVariants'));
});

test('mengunggah berkas item checklist via HTTP dan kasubbag memvalidasi item lewat HTTP', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $pemohon = $aktor['pemohon'];
    $kasubbag = $aktor['kasubbag'];
    $usulan = KpE2eHelper::buatDraftUsulanSiap($pemohon, $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    $item = $usulan->checklistSubmission->items()->firstOrFail();

    $uploadResponse = $this->actingAs($pemohon)->post(
        "/kenaikan-pangkat/usulan/{$usulan->id}/checklist/{$item->id}/upload",
        [
            'file' => UploadedFile::fake()->create('sk-cpns.pdf', 128, 'application/pdf'),
        ],
    );

    $uploadResponse->assertRedirect();

    $item->refresh();
    expect($item->status)->toBe(BerkasChecklistSubmissionItem::STATUS_ADA)
        ->and($item->file_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($item->file_path))->toBeTrue();

    $urlValidasi = KpE2eHelper::urlValidasiChecklist($usulan, $item);
    $methodValidasi = KpE2eHelper::methodValidasiChecklist();

    $validateResponse = $this->actingAs($kasubbag)->call($methodValidasi, $urlValidasi, [
        'status' => BerkasChecklistSubmissionItem::STATUS_VALID,
        'catatan' => 'Berkas sesuai',
    ]);

    expect($validateResponse->status())->not->toBe(500)
        ->and($validateResponse->isSuccessful() || $validateResponse->isRedirection())->toBeTrue();

    expect($item->fresh()->status)->toBe(BerkasChecklistSubmissionItem::STATUS_VALID)
        ->and($item->fresh()->validated_by)->toBe($kasubbag->id);
});

test('ChecklistBerkasService::isComplete mengembalikan true saat semua item wajib berstatus STATUS_VALID', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    $submission = KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);

    $checklistService = app(ChecklistBerkasService::class);

    expect($checklistService->isComplete($submission))->toBeTrue()
        ->and($submission->persentase)->toBe(100)
        ->and($submission->status_kelengkapan)->toBe('lengkap');
});

test('mengunggah lampiran tambahan usulan KP via endpoint HTTP lampiran', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $pemohon = $aktor['pemohon'];
    $usulan = KpE2eHelper::buatDraftUsulanSiap($pemohon, $aktor['pangkatAsal'], $aktor['pangkatTujuan']);

    $response = $this->actingAs($pemohon)->post("/kenaikan-pangkat/usulan/{$usulan->id}/lampiran", [
        'file' => UploadedFile::fake()->create('surat-tambahan.pdf', 96, 'application/pdf'),
    ]);

    $response->assertRedirect();

    $lampiran = $usulan->lampiran()->first();
    expect($lampiran)->not->toBeNull()
        ->and($lampiran->file_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($lampiran->file_path))->toBeTrue();
});

test('approver berizin dapat melakukan mintaPerbaikan dan tolak sesuai state aktif usulan', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $service = app(UsulanKenaikanPangkatService::class);

    $usulanPerbaikan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulanPerbaikan, $aktor['kasubbag']);
    $service->submit($usulanPerbaikan, $aktor['pemohon']);

    $this->actingAs($aktor['kasubbag'])
        ->post("/kenaikan-pangkat/approval/{$usulanPerbaikan->id}/minta-perbaikan", [
            'catatan' => 'Mohon perbarui dokumen SKP tahun terakhir.',
        ])
        ->assertRedirect();

    expect((string) $usulanPerbaikan->refresh()->state)->toBe('PERLU_PERBAIKAN');

    $pegawaiKedua = KpE2eHelper::buatPegawaiPnsDenganPangkatAktif($aktor['pangkatAsal'], '2021-04-01');
    $usulanTolak = KpE2eHelper::buatDraftUsulanSiap($pegawaiKedua['pegawai'], $aktor['pangkatAsal'], $aktor['pangkatTujuan'], '2021-04-01', 10);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulanTolak, $aktor['kasubbag']);
    $service->submit($usulanTolak, $pegawaiKedua['pegawai']);
    $service->verifikasiKasubbag($usulanTolak->refresh(), $aktor['kasubbag'], true, 'Lolos kasubbag');

    $this->actingAs($aktor['sekretaris'])
        ->post("/kenaikan-pangkat/approval/{$usulanTolak->id}/tolak", [
            'alasan' => 'Tidak memenuhi syarat masa kerja golongan.',
        ])
        ->assertRedirect();

    $usulanTolak->refresh();
    expect((string) $usulanTolak->state)->toBe('DITOLAK')
        ->and($usulanTolak->catatan_penolakan)->toBe('Tidak memenuhi syarat masa kerja golongan.');
});

test('submit usulan mengisi nomor_usulan dan kirimBiro meneruskan DTO berisi nomor_usulan ke SikepAdapter', function (): void {
    Storage::fake('local');
    KpE2eHelper::pasangFakePdfDenganRenderBlade();

    $capturedDto = null;
    $mockAdapter = Mockery::mock(SikepAdapter::class);
    $mockAdapter->shouldReceive('pushUsulan')
        ->once()
        ->withArgs(function (?object $dto) use (&$capturedDto): bool {
            $capturedDto = $dto;

            return $dto instanceof UsulanKenaikanPangkatDto;
        })
        ->andReturn(['external_id' => 'SIKEP-KP-100']);
    $mockAdapter->shouldReceive('isConfigured')->byDefault()->andReturn(true);
    app()->instance(SikepAdapter::class, $mockAdapter);

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $service = app(UsulanKenaikanPangkatService::class);
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);

    $service->submit($usulan, $aktor['pemohon']);
    $usulan->refresh();

    expect($usulan->nomor_usulan)->not->toBeNull()
        ->and($usulan->nomor_usulan)->not->toBe('');

    $service->verifikasiKasubbag($usulan, $aktor['kasubbag'], true, 'OK');
    $service->verifikasiSekretaris($usulan->refresh(), $aktor['sekretaris'], true, 'OK');
    $service->tandaTanganKetua($usulan->refresh(), $aktor['ketua']);
    $service->kirimBiro($usulan->refresh(), $aktor['biro'], 'Kirim ke Biro');

    expect($capturedDto)->toBeInstanceOf(UsulanKenaikanPangkatDto::class)
        ->and($capturedDto->nomor_usulan)->toBe($usulan->nomor_usulan)
        ->and($capturedDto->nip)->toBe($aktor['pemohon']->nip)
        ->and((string) $usulan->refresh()->state)->toBe('MENUNGGU_SK');
});

test('tandaTanganKetua merender surat pengantar PDF berisi nama item checklist valid tanpa membuka transaksi DB', function (): void {
    Storage::fake('local');
    $tracker = KpE2eHelper::pasangFakePdfDenganRenderBlade();

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $service = app(UsulanKenaikanPangkatService::class);
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    $submission = KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);

    $expectedNames = $submission->items()
        ->with('item')
        ->where('status', BerkasChecklistSubmissionItem::STATUS_VALID)
        ->get()
        ->map(fn (BerkasChecklistSubmissionItem $subItem): ?string => $subItem->item?->nama)
        ->filter()
        ->values()
        ->all();

    $service->submit($usulan, $aktor['pemohon']);
    $service->verifikasiKasubbag($usulan->refresh(), $aktor['kasubbag'], true, 'OK');
    $service->verifikasiSekretaris($usulan->refresh(), $aktor['sekretaris'], true, 'OK');

    $baselineLevel = DB::transactionLevel();
    $pdf = $service->tandaTanganKetua($usulan->refresh(), $aktor['ketua']);

    expect($tracker->viewName)->toBe('pdf.kenaikan-pangkat.surat-pengantar')
        ->and($tracker->transactionLevels)->not->toBeEmpty()
        ->and($tracker->transactionLevels[0])->toBe($baselineLevel)
        ->and(Storage::disk('local')->exists($pdf->file_path))->toBeTrue();

    foreach ($expectedNames as $namaBerkas) {
        expect($tracker->renderedHtml)->toContain($namaBerkas);
    }
});

test('uploadSkFinal dan SinkronkanRiwayatPangkat memakai TMT baru dan MKG resmi serta SK final dapat diunduh', function (): void {
    Storage::fake('local');
    KpE2eHelper::pasangFakePdfDenganRenderBlade();

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $service = app(UsulanKenaikanPangkatService::class);
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);

    $service->submit($usulan, $aktor['pemohon']);
    $service->verifikasiKasubbag($usulan->refresh(), $aktor['kasubbag'], true, 'OK');
    $service->verifikasiSekretaris($usulan->refresh(), $aktor['sekretaris'], true, 'OK');
    $service->tandaTanganKetua($usulan->refresh(), $aktor['ketua']);
    $service->kirimBiro($usulan->refresh(), $aktor['biro'], 'Kirim');

    $this->actingAs($aktor['adminSk'])
        ->post("/kenaikan-pangkat/admin-sk/{$usulan->id}/upload-sk", [
            'sk_file' => UploadedFile::fake()->create('sk-kp-resmi.pdf', 256, 'application/pdf'),
            'nomor_sk' => 'SK-MA-2026-099',
            'tanggal_sk' => '2026-05-15',
            'tmt_pangkat_baru' => '2026-04-01',
            'masa_kerja_tahun' => 8,
            'masa_kerja_bulan' => 5,
        ])
        ->assertRedirect();

    $riwayatBaru = RiwayatPangkat::query()
        ->where('pegawai_id', $aktor['pemohon']->id)
        ->where('is_aktif', true)
        ->first();

    expect($riwayatBaru)->not->toBeNull()
        ->and($riwayatBaru->ref_pangkat_id)->toBe($aktor['pangkatTujuan']->id)
        ->and($riwayatBaru->no_sk)->toBe('SK-MA-2026-099')
        ->and($riwayatBaru->tanggal_sk->toDateString())->toBe('2026-05-15')
        ->and($riwayatBaru->tmt->toDateString())->toBe('2026-04-01')
        ->and($riwayatBaru->masa_kerja_tahun)->toBe(8)
        ->and($riwayatBaru->masa_kerja_bulan)->toBe(5)
        ->and($aktor['riwayatAsal']->fresh()->is_aktif)->toBeFalse()
        ->and($aktor['pemohon']->fresh()->ref_pangkat_id)->toBe($aktor['pangkatTujuan']->id);

    $this->actingAs($aktor['pemohon'])
        ->get("/kenaikan-pangkat/admin-sk/{$usulan->id}/download-sk")
        ->assertSuccessful();
});

test('halaman eligible kenaikan pangkat menampilkan daftar pegawai memenuhi syarat beserta props kontrak', function (): void {
    $aktor = KpE2eHelper::buatAktorWorkflowKp(now()->subYears(5)->toDateString());
    $viewer = KpE2eHelper::beriPermissions(
        Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
        ['kenaikan-pangkat.usulan.view', 'kenaikan-pangkat.usulan.create'],
        'kp-e2e-eligible-viewer',
    );

    $this->actingAs($viewer)
        ->get('/kenaikan-pangkat/eligible')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/eligible/index')
            ->has('pegawaiList.data')
            ->has('stats.total')
            ->has('stats.sudahEligible')
            ->has('stats.mendekatiEligible')
            ->has('stats.belumEligible')
            ->has('bulanOptions', 12)
            ->has('tahunOptions')
            ->has('filters'));
});

test('inbox approval multi-role menampilkan semua usulan sesuai izin gabungan dan UsulanKenaikanPangkatServiceTest valid PHP', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $service = app(UsulanKenaikanPangkatService::class);

    $usulanDiajukan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan'], '2021-04-01', 4);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulanDiajukan, $aktor['kasubbag']);
    $service->submit($usulanDiajukan, $aktor['pemohon']);

    $pegawaiKedua = KpE2eHelper::buatPegawaiPnsDenganPangkatAktif($aktor['pangkatAsal'], '2021-04-01');
    $usulanKasubbag = KpE2eHelper::buatDraftUsulanSiap($pegawaiKedua['pegawai'], $aktor['pangkatAsal'], $aktor['pangkatTujuan'], '2021-04-01', 10);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulanKasubbag, $aktor['kasubbag']);
    $service->submit($usulanKasubbag, $pegawaiKedua['pegawai']);
    $service->verifikasiKasubbag($usulanKasubbag->refresh(), $aktor['kasubbag'], true, 'Lolos');

    $multiRoleApprover = KpE2eHelper::beriPermissions(
        Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
        [
            'kenaikan-pangkat.usulan.view',
            'kenaikan-pangkat.usulan.verifikasi-kasubbag',
            'kenaikan-pangkat.usulan.verifikasi-sekretaris',
        ],
        'kp-e2e-multi-approver',
    );

    $this->actingAs($multiRoleApprover)
        ->get('/kenaikan-pangkat/approval/inbox')
        ->assertSuccessful()
        ->assertInertia(function (Assert $page) use ($usulanDiajukan, $usulanKasubbag): void {
            $page->component('kenaikan-pangkat/approval/inbox')
                ->has('usulan.data', 2);

            $ids = collect($page->toArray()['props']['usulan']['data'])->pluck('id')->all();
            expect($ids)->toContain($usulanDiajukan->id)
                ->and($ids)->toContain($usulanKasubbag->id);
        });

    $serviceTestPath = base_path('tests/Feature/Services/UsulanKenaikanPangkat/UsulanKenaikanPangkatServiceTest.php');
    $contents = File::get($serviceTestPath);

    expect(str_starts_with(ltrim($contents), '<?php'))->toBeTrue()
        ->and($contents)->toContain('RefreshDatabase')
        ->and(substr_count($contents, 'it('))->toBeGreaterThanOrEqual(21)
        ->and(substr_count($contents, 'use App\\Services\\UsulanKenaikanPangkat\\UsulanKenaikanPangkatService;'))->toBe(1);
});
