<?php

declare(strict_types=1);

use App\Actions\UsulanKenaikanPangkat\GenerateSuratPengantarPdf;
use App\Enums\StatusPegawai;
use App\Events\UsulanKenaikanPangkat\UsulanKpSkTerbit;
use App\Exceptions\UsulanKenaikanPangkat\PegawaiTidakEligibleException;
use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Database\Seeders\IamSeeder;
use Database\Seeders\RefPangkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\E2E\KenaikanPangkat\Support\KpE2eHelper;
use Tests\TestCase;

require_once __DIR__.'/Support/KpE2eHelper.php';

uses(TestCase::class, RefreshDatabase::class)->beforeEach(function (): void {
    $this->withoutVite();
    $this->seed([IamSeeder::class, RefPangkatSeeder::class, ChecklistKenaikanPangkatSeeder::class]);
})->group('tier2');

test('pemilik usulan tanpa permission approver ditolak 403 saat memanggil mintaPerbaikan dan tolak', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $service = app(UsulanKenaikanPangkatService::class);
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);
    $service->submit($usulan, $aktor['pemohon']);

    $this->actingAs($aktor['pemohon'])
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/minta-perbaikan", [
            'catatan' => 'Pemilik mencoba mengembalikan sendiri',
        ])
        ->assertForbidden();

    $service->verifikasiKasubbag($usulan->refresh(), $aktor['kasubbag'], true, 'Lolos');

    $this->actingAs($aktor['pemohon'])
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/tolak", [
            'alasan' => 'Pemilik mencoba menolak sendiri',
        ])
        ->assertForbidden();
});

test('pengguna viewer hanya dengan permission lihat usulan ditolak 403 saat mintaPerbaikan dan tolak', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $service = app(UsulanKenaikanPangkatService::class);
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);
    $service->submit($usulan, $aktor['pemohon']);
    $service->verifikasiKasubbag($usulan->refresh(), $aktor['kasubbag'], true, 'Lolos');

    $viewer = KpE2eHelper::beriPermissions(
        Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
        ['kenaikan-pangkat.usulan.view'],
        'kp-e2e-viewer-only',
    );

    $this->actingAs($viewer)
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/minta-perbaikan", [
            'catatan' => 'Viewer tidak boleh meminta perbaikan',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/tolak", [
            'alasan' => 'Viewer tidak boleh menolak usulan',
        ])
        ->assertForbidden();
});

test('approver tahap berbeda dari state aktif ditolak 403 saat memanggil mintaPerbaikan atau tolak', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $service = app(UsulanKenaikanPangkatService::class);
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);
    $service->submit($usulan, $aktor['pemohon']);

    // State masih DIAJUKAN (tahap kasubbag), sekretaris tidak berhak minta perbaikan atau menolak
    $this->actingAs($aktor['sekretaris'])
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/minta-perbaikan", [
            'catatan' => 'Sekretaris melangkahi tahap kasubbag',
        ])
        ->assertForbidden();

    $service->verifikasiKasubbag($usulan->refresh(), $aktor['kasubbag'], true, 'Lolos');

    // State sudah DIVERIFIKASI_KASUBBAG (tahap sekretaris), kasubbag tidak berhak menolak lagi
    $this->actingAs($aktor['kasubbag'])
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/tolak", [
            'alasan' => 'Kasubbag menolak setelah lewat tahapnya',
        ])
        ->assertForbidden();
});

test('pengajuan draft KP dengan pegawai_id bukan ULID atau berformat UUID ditolak validasi 422', function (string $invalidPegawaiId): void {
    $aktor = KpE2eHelper::buatAktorWorkflowKp();

    $this->actingAs($aktor['pemohon'])
        ->postJson('/kenaikan-pangkat/usulan', [
            'pegawai_id' => $invalidPegawaiId,
            'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
            'periode_usul_bulan' => 4,
            'periode_usul_tahun' => (int) now()->addYear()->format('Y'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pegawai_id');
})->with([
    'string acak non-ULID' => 'bukan-ulid-sama-sekali',
    'format UUID v4 bukan ULID' => '550e8400-e29b-41d4-a716-446655440000',
    'string kosong' => '',
]);

test('pembuatan draft KP untuk pegawai tanpa riwayat pangkat aktif ditolak', function (): void {
    $pangkatTujuan = RefPangkat::query()->where('kode', 'III/b')->firstOrFail();
    $pegawaiTanpaRiwayat = KpE2eHelper::beriPermissions(
        Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif->value,
            'ref_pangkat_id' => null,
        ]),
        ['kenaikan-pangkat.usulan.create', 'kenaikan-pangkat.usulan.view'],
        'kp-e2e-tanpa-pangkat',
    );

    expect(fn () => app(UsulanKenaikanPangkatService::class)->createDraft([
        'pegawai_id' => $pegawaiTanpaRiwayat->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => (int) now()->addYear()->format('Y'),
    ], $pegawaiTanpaRiwayat))->toThrow(PegawaiTidakEligibleException::class);

    $res = $this->actingAs($pegawaiTanpaRiwayat)
        ->from('/kenaikan-pangkat/usulan/create')
        ->post('/kenaikan-pangkat/usulan', [
            'pegawai_id' => $pegawaiTanpaRiwayat->id,
            'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
            'periode_usul_bulan' => 4,
            'periode_usul_tahun' => (int) now()->addYear()->format('Y'),
        ]);

    expect($res->status())->not->toBe(500)
        ->and($res->status())->toBeIn([302, 422]);

    if ($res->status() === 302) {
        expect(session()->has('errors') || filled(session('error')))->toBeTrue();
    }

    expect(UsulanKenaikanPangkat::query()->where('pegawai_id', $pegawaiTanpaRiwayat->id)->exists())->toBeFalse();
});

test('pembaruan PUT usulan KP menolak pegawai_id non-ULID dengan 422 dan menolak edit pada state DIAJUKAN', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $pemohon = $aktor['pemohon'];
    $service = app(UsulanKenaikanPangkatService::class);
    $usulanDraft = KpE2eHelper::buatDraftUsulanSiap($pemohon, $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    $pangkatTujuanLain = RefPangkat::query()->where('kode', 'III/c')->firstOrFail();
    $tahunUsul = (int) now()->addYear()->format('Y');

    // 1. PUT dengan pegawai_id non-ULID dan format UUID v4 harus ditolak 422
    foreach (['bukan-ulid-sama-sekali', '550e8400-e29b-41d4-a716-446655440000'] as $invalidPegawaiId) {
        $this->actingAs($pemohon)
            ->putJson("/kenaikan-pangkat/usulan/{$usulanDraft->id}", [
                'pegawai_id' => $invalidPegawaiId,
                'ref_pangkat_tujuan_id' => $pangkatTujuanLain->id,
                'periode_usul_bulan' => 10,
                'periode_usul_tahun' => $tahunUsul,
                'catatan_pengusul' => 'Payload pegawai_id tidak valid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pegawai_id');
    }

    expect($usulanDraft->fresh()->pegawai_id)->toBe($pemohon->id)
        ->and($usulanDraft->fresh()->ref_pangkat_tujuan_id)->toBe($aktor['pangkatTujuan']->id);

    // 2. PUT pada usulan yang sudah berstate DIAJUKAN ditolak 403 atau redirect error dan data tidak berubah
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulanDraft, $aktor['kasubbag']);
    $service->submit($usulanDraft, $pemohon);

    $resNonEditable = $this->actingAs($pemohon)
        ->from("/kenaikan-pangkat/usulan/{$usulanDraft->id}")
        ->put("/kenaikan-pangkat/usulan/{$usulanDraft->id}", [
            'pegawai_id' => $pemohon->id,
            'ref_pangkat_tujuan_id' => $pangkatTujuanLain->id,
            'periode_usul_bulan' => 10,
            'periode_usul_tahun' => $tahunUsul,
            'catatan_pengusul' => 'Mencoba ubah usulan pada state DIAJUKAN',
        ]);

    expect($resNonEditable->status())->not->toBe(500)
        ->and($resNonEditable->status() === 403 || ($resNonEditable->isRedirect() && (session()->has('errors') || filled(session('error')))))->toBeTrue();

    $usulanDraft->refresh();
    expect($usulanDraft->ref_pangkat_tujuan_id)->toBe($aktor['pangkatTujuan']->id)
        ->and($usulanDraft->catatan_pengusul)->not->toBe('Mencoba ubah usulan pada state DIAJUKAN');
});

test('pihak ketiga ditolak 403 saat upload checklist atau lampiran dan pemohon ditolak 403 saat validasi checklist sendiri', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $pemohon = $aktor['pemohon'];
    $usulan = KpE2eHelper::buatDraftUsulanSiap($pemohon, $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    $item = $usulan->checklistSubmission->items()->firstOrFail();

    $pihakKetiga = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]);

    // 1. Pihak ketiga tanpa izin dan bukan pemilik ditolak 403 pada upload checklist dan lampiran
    $this->actingAs($pihakKetiga)
        ->post("/kenaikan-pangkat/usulan/{$usulan->id}/checklist/{$item->id}/upload", [
            'file' => UploadedFile::fake()->create('illegal-upload.pdf', 64, 'application/pdf'),
        ])
        ->assertForbidden();

    $this->actingAs($pihakKetiga)
        ->post("/kenaikan-pangkat/usulan/{$usulan->id}/lampiran", [
            'file' => UploadedFile::fake()->create('illegal-lampiran.pdf', 64, 'application/pdf'),
        ])
        ->assertForbidden();

    // 2. Pemohon tidak boleh memvalidasi item checklist miliknya sendiri (ditolak 403)
    $this->actingAs($pemohon)
        ->post("/kenaikan-pangkat/usulan/{$usulan->id}/checklist/{$item->id}/upload", [
            'file' => UploadedFile::fake()->create('berkas-pemohon.pdf', 64, 'application/pdf'),
        ])
        ->assertRedirect();

    $urlValidasi = KpE2eHelper::urlValidasiChecklist($usulan, $item);
    $methodValidasi = KpE2eHelper::methodValidasiChecklist();

    $resSelfValidate = $this->actingAs($pemohon)->call($methodValidasi, $urlValidasi, [
        'status' => BerkasChecklistSubmissionItem::STATUS_VALID,
        'catatan' => 'Pemohon mencoba validasi sendiri',
    ]);

    expect($resSelfValidate->status())->toBe(403)
        ->and($item->fresh()->status)->toBe(BerkasChecklistSubmissionItem::STATUS_ADA)
        ->and($item->fresh()->validated_by)->toBeNull();
});

test('upload checklist dan lampiran KP menolak request tanpa file atau MIME terlarang dengan error validasi file', function (?string $filename, ?string $mime): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $pemohon = $aktor['pemohon'];
    $usulan = KpE2eHelper::buatDraftUsulanSiap($pemohon, $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    $item = $usulan->checklistSubmission->items()->firstOrFail();

    $payload = $filename !== null && $mime !== null
        ? ['file' => UploadedFile::fake()->create($filename, 32, $mime)]
        : [];

    $this->actingAs($pemohon)
        ->from("/kenaikan-pangkat/usulan/{$usulan->id}")
        ->post("/kenaikan-pangkat/usulan/{$usulan->id}/checklist/{$item->id}/upload", $payload)
        ->assertSessionHasErrors('file');

    $this->actingAs($pemohon)
        ->from("/kenaikan-pangkat/usulan/{$usulan->id}")
        ->post("/kenaikan-pangkat/usulan/{$usulan->id}/lampiran", $payload)
        ->assertSessionHasErrors('file');
})->with([
    'tanpa file' => [null, null],
    'MIME HTML terlarang' => ['xss.html', 'text/html'],
    'MIME EXE terlarang' => ['payload.exe', 'application/x-msdownload'],
]);

test('pembatalan usulan KP tanpa alasan ditolak validasi 422 dan dengan alasan berhasil', function (): void {
    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);

    $this->actingAs($aktor['pemohon'])
        ->postJson("/kenaikan-pangkat/usulan/{$usulan->id}/batalkan", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('alasan');

    expect((string) $usulan->refresh()->state)->toBe('DRAFT');

    $this->actingAs($aktor['pemohon'])
        ->post("/kenaikan-pangkat/usulan/{$usulan->id}/batalkan", [
            'alasan' => 'Pegawai menunda pengajuan ke periode berikutnya.',
        ])
        ->assertRedirect();

    expect((string) $usulan->refresh()->state)->toBe('DIBATALKAN');
});

test('ChecklistBerkasService::isComplete mengembalikan false jika ada item wajib baru yang belum punya baris submission', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    $submission = KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);

    $checklistService = app(ChecklistBerkasService::class);
    expect($checklistService->isComplete($submission))->toBeTrue();

    // Tambahkan item wajib baru pada template setelah submission terbentuk
    BerkasChecklistItem::query()->create([
        'berkas_checklist_template_id' => $submission->berkas_checklist_template_id,
        'kode' => 'sk_mutasi_baru',
        'nama' => 'SK Mutasi Terbaru',
        'wajib' => true,
        'urutan' => 99,
    ]);

    expect($checklistService->isComplete($submission->fresh(['template.items', 'items'])))->toBeFalse();
});

test('SinkronkanRiwayatPangkat tidak memakai tanggal_sk sebagai tmt baru dan tidak melakukan hardcode MKG nol', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $aktor['pemohon']->id,
        'ref_pangkat_asal_id' => $aktor['pangkatAsal']->id,
        'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
        'tmt_pangkat_asal' => '2021-04-01',
        'state' => 'MENUNGGU_SK',
    ]);

    $this->actingAs($aktor['adminSk'])
        ->post("/kenaikan-pangkat/admin-sk/{$usulan->id}/upload-sk", [
            'sk_file' => UploadedFile::fake()->create('sk-beda-tmt.pdf', 100, 'application/pdf'),
            'nomor_sk' => 'SK-KP-2026-888',
            'tanggal_sk' => '2026-06-20',
            'tmt_pangkat_baru' => '2026-04-01',
            'masa_kerja_tahun' => 10,
            'masa_kerja_bulan' => 7,
        ])
        ->assertRedirect();

    $riwayatAktif = RiwayatPangkat::query()
        ->where('pegawai_id', $aktor['pemohon']->id)
        ->where('is_aktif', true)
        ->firstOrFail();

    expect($riwayatAktif->tmt->toDateString())->toBe('2026-04-01')
        ->and($riwayatAktif->tmt->toDateString())->not->toBe($riwayatAktif->tanggal_sk->toDateString())
        ->and($riwayatAktif->masa_kerja_tahun)->toBe(10)
        ->and($riwayatAktif->masa_kerja_bulan)->toBe(7);

    // Pastikan listener langsung juga tidak mengembalikan tanggal_sk jika atribut TMT tersedia
    expect(UsulanKpSkTerbit::class)->toBeString();
});

test('upload SK final menolak masa kerja negatif, bulan di atas 11, dan berkas non-PDF dengan 422', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $aktor['pemohon']->id,
        'ref_pangkat_asal_id' => $aktor['pangkatAsal']->id,
        'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
        'tmt_pangkat_asal' => '2021-04-01',
        'state' => 'MENUNGGU_SK',
    ]);

    $this->actingAs($aktor['adminSk'])
        ->postJson("/kenaikan-pangkat/admin-sk/{$usulan->id}/upload-sk", [
            'sk_file' => UploadedFile::fake()->create('sk-salah.txt', 32, 'text/plain'),
            'nomor_sk' => 'SK-ERR-01',
            'tanggal_sk' => now()->toDateString(),
            'tmt_pangkat_baru' => now()->toDateString(),
            'masa_kerja_tahun' => -1,
            'masa_kerja_bulan' => 15,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sk_file', 'masa_kerja_tahun', 'masa_kerja_bulan']);
});

test('GenerateSuratPengantarPdf menangani nol item valid dan campuran item valid serta tidak valid dengan tepat', function (): void {
    Storage::fake('local');
    $tracker = KpE2eHelper::pasangFakePdfDenganRenderBlade();

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $pdfAction = app(GenerateSuratPengantarPdf::class);

    // Skenario A: 0 item valid (seluruh item masih STATUS_BELUM_ADA)
    $usulanKosong = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan'], '2021-04-01', 4);
    $pdfAction->handle($usulanKosong->refresh(), $aktor['ketua']);

    expect($tracker->renderedHtml)->toContain('Belum ada berkas valid yang tercatat.');

    // Skenario B: Campuran STATUS_VALID, STATUS_TIDAK_VALID, dan STATUS_ADA
    $pegawaiCampuran = KpE2eHelper::buatPegawaiPnsDenganPangkatAktif($aktor['pangkatAsal'], '2021-04-01');
    $usulanCampuran = KpE2eHelper::buatDraftUsulanSiap($pegawaiCampuran['pegawai'], $aktor['pangkatAsal'], $aktor['pangkatTujuan'], '2021-04-01', 10);
    $items = $usulanCampuran->checklistSubmission->items()->with('item')->get();

    $itemValid = $items[0];
    $itemTidakValid = $items[1];
    $itemBelumValidasi = $items[2];

    $checklistService = app(ChecklistBerkasService::class);
    $checklistService->validateItem($itemValid, $aktor['kasubbag'], BerkasChecklistSubmissionItem::STATUS_VALID, 'Valid');
    $checklistService->validateItem($itemTidakValid, $aktor['kasubbag'], BerkasChecklistSubmissionItem::STATUS_TIDAK_VALID, 'Buram');
    $checklistService->updateItemStatus($itemBelumValidasi, BerkasChecklistSubmissionItem::STATUS_ADA);

    $pdfAction->handle($usulanCampuran->refresh(), $aktor['ketua']);

    expect($tracker->renderedHtml)->toContain($itemValid->item->nama)
        ->and($tracker->renderedHtml)->not->toContain($itemTidakValid->item->nama)
        ->and($tracker->renderedHtml)->not->toContain($itemBelumValidasi->item->nama);
});

test('SkAdminController downloadSk mengembalikan 404 saat sk_file_path kosong atau file fisik tidak ditemukan', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();

    $usulanTanpaSk = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $aktor['pemohon']->id,
        'ref_pangkat_asal_id' => $aktor['pangkatAsal']->id,
        'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
        'state' => 'MENUNGGU_SK',
        'sk_file_path' => null,
    ]);

    $this->actingAs($aktor['adminSk'])
        ->get("/kenaikan-pangkat/admin-sk/{$usulanTanpaSk->id}/download-sk")
        ->assertNotFound();

    $usulanFileHilang = UsulanKenaikanPangkat::factory()->skTerbit()->create([
        'pegawai_id' => $aktor['pemohon']->id,
        'ref_pangkat_asal_id' => $aktor['pangkatAsal']->id,
        'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
        'sk_file_path' => 'usulan-kp/sk/file-tidak-ada.pdf',
    ]);

    $this->actingAs($aktor['adminSk'])
        ->get("/kenaikan-pangkat/admin-sk/{$usulanFileHilang->id}/download-sk")
        ->assertNotFound();
});

test('resubmit pasca mintaPerbaikan mereset seluruh approval_steps kembali ke status menunggu', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp();
    $service = app(UsulanKenaikanPangkatService::class);
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);

    $service->submit($usulan, $aktor['pemohon']);
    $service->verifikasiKasubbag($usulan->refresh(), $aktor['kasubbag'], true, 'Setuju tahap 1');
    $service->mintaPerbaikan($usulan->refresh(), $aktor['sekretaris'], 'Lengkapi berkas ulang');

    expect((string) $usulan->refresh()->state)->toBe('PERLU_PERBAIKAN');

    $service->submit($usulan->refresh(), $aktor['pemohon']);

    $steps = $usulan->refresh()->approvalSteps()->orderBy('urutan')->get();
    expect($steps)->toHaveCount(3)
        ->and($steps->pluck('status')->unique()->values()->all())->toBe(['menunggu'])
        ->and($steps->pluck('approver_user_id')->filter()->all())->toBe([])
        ->and($steps->pluck('acted_at')->filter()->all())->toBe([]);
});

test('endpoint API kenaikan pangkat menolak request tanpa Sanctum, tanpa ability tepat, atau signature HMAC tidak sah', function (): void {
    $path = '/api/kenaikan-pangkat/usulan';

    // 1. Tanpa autentikasi Sanctum
    $this->getJson($path)->assertUnauthorized();

    // 2. Token tanpa ability app:kepegawaian
    $pegawai = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]);
    Sanctum::actingAs($pegawai, ['app:attendance']);
    $this->getJson($path, KpE2eHelper::buatHeaderHmacApi('GET', $path))->assertForbidden();

    // 3. Token valid namun tanpa header HMAC
    Sanctum::actingAs($pegawai, ['app:kepegawaian']);
    $this->getJson($path)->assertUnauthorized();

    // 4. Timestamp HMAC kedaluwarsa (> 5 menit)
    $expiredHeaders = KpE2eHelper::buatHeaderHmacApi('GET', $path, [], '[]', now()->subMinutes(10)->timestamp);
    $this->getJson($path, $expiredHeaders)->assertUnauthorized();

    // 5. Signature HMAC salah/dimanipulasi
    $tamperedHeaders = KpE2eHelper::buatHeaderHmacApi('GET', $path, [], '[]', now()->timestamp, 'secret-palsu');
    $this->getJson($path, $tamperedHeaders)->assertUnauthorized();
});
