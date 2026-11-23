<?php

declare(strict_types=1);

use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\RiwayatPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\KenaikanPangkatMonitoringService;
use App\Services\Sikep\SikepAdapter;
use App\Services\Sikep\UsulanKenaikanPangkatDto;
use Carbon\Carbon;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Database\Seeders\IamSeeder;
use Database\Seeders\RefPangkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\E2E\KenaikanPangkat\Support\KpE2eHelper;
use Tests\TestCase;

require_once __DIR__.'/Support/KpE2eHelper.php';

uses(TestCase::class, RefreshDatabase::class)->beforeEach(function (): void {
    $this->withoutVite();
    $this->seed([IamSeeder::class, RefPangkatSeeder::class, ChecklistKenaikanPangkatSeeder::class]);
})->afterEach(function (): void {
    Carbon::setTestNow();
})->group('tier4');

test('siklus penuh usulan KP dari eligible hingga SK final terbit, riwayat pangkat aktif baru, dan monitoring terupdate', function (): void {
    Carbon::setTestNow('2026-06-01');
    Storage::fake('local');
    $pdfTracker = KpE2eHelper::pasangFakePdfDenganRenderBlade();

    $pushedDto = null;
    $mockAdapter = Mockery::mock(SikepAdapter::class);
    $mockAdapter->shouldReceive('pushUsulan')
        ->once()
        ->withArgs(function (?object $dto) use (&$pushedDto): bool {
            $pushedDto = $dto;

            return $dto instanceof UsulanKenaikanPangkatDto;
        })
        ->andReturn(['external_id' => 'SIKEP-E2E-01']);
    $mockAdapter->shouldReceive('isConfigured')->byDefault()->andReturn(true);
    app()->instance(SikepAdapter::class, $mockAdapter);

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $pemohon = $aktor['pemohon'];
    $kasubbag = $aktor['kasubbag'];
    $sekretaris = $aktor['sekretaris'];
    $ketua = $aktor['ketua'];
    $biro = $aktor['biro'];
    $adminSk = $aktor['adminSk'];
    $tahunUsul = 2027;

    // Langkah 1: Pemohon memeriksa daftar pegawai eligible dan melihat status Sudah Eligible
    $this->actingAs($pemohon)
        ->get('/kenaikan-pangkat/eligible')
        ->assertSuccessful()
        ->assertInertia(function (Assert $page) use ($pemohon): void {
            $page->component('kenaikan-pangkat/eligible/index')
                ->has('pegawaiList.data');

            $pegawaiRow = collect($page->toArray()['props']['pegawaiList']['data'])
                ->firstWhere('id', $pemohon->id);

            expect($pegawaiRow)->not->toBeNull()
                ->and($pegawaiRow['status'])->toBe('Sudah Eligible');
        });

    // Langkah 2: Buka form create dengan pegawai_id dan simpan draft usulan KP
    $this->actingAs($pemohon)
        ->get("/kenaikan-pangkat/usulan/create?pegawai_id={$pemohon->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/form')
            ->where('pegawai.id', $pemohon->id)
            ->where('pangkatAsal.id', $aktor['pangkatAsal']->id));

    $this->actingAs($pemohon)
        ->post('/kenaikan-pangkat/usulan', [
            'pegawai_id' => $pemohon->id,
            'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
            'periode_usul_bulan' => 4,
            'periode_usul_tahun' => $tahunUsul,
            'catatan_pengusul' => 'Pengajuan KP reguler siklus penuh E2E',
        ])
        ->assertRedirect();

    $usulan = UsulanKenaikanPangkat::query()->where('pegawai_id', $pemohon->id)->firstOrFail();

    // Langkah 3: Upload seluruh item checklist wajib via HTTP + lampiran tambahan + validasi menjadi 100% VALID
    $checklistService = app(ChecklistBerkasService::class);
    $submission = $usulan->checklistSubmission()->with(['template.items', 'items.item'])->firstOrFail();
    $requiredIds = $submission->template->items()->wajib()->pluck('id');

    $submission->items()
        ->whereIn('berkas_checklist_item_id', $requiredIds)
        ->get()
        ->each(function (BerkasChecklistSubmissionItem $item) use ($pemohon, $kasubbag, $usulan, $checklistService): void {
            $this->actingAs($pemohon)
                ->post("/kenaikan-pangkat/usulan/{$usulan->id}/checklist/{$item->id}/upload", [
                    'file' => UploadedFile::fake()->create("{$item->id}.pdf", 128, 'application/pdf'),
                ])
                ->assertRedirect();

            $checklistService->validateItem($item->fresh(), $kasubbag, BerkasChecklistSubmissionItem::STATUS_VALID, 'Terverifikasi');
        });

    $checklistService->recalculatePersentase($submission->fresh());
    expect($checklistService->isComplete($submission->fresh(['template.items', 'items'])))->toBeTrue();

    $this->actingAs($pemohon)
        ->post("/kenaikan-pangkat/usulan/{$usulan->id}/lampiran", [
            'file' => UploadedFile::fake()->create('lampiran-pendukung.pdf', 64, 'application/pdf'),
        ])
        ->assertRedirect();

    // Langkah 4: Submit usulan -> Verifikasi Kasubbag -> Verifikasi Sekretaris
    $this->actingAs($pemohon)->post("/kenaikan-pangkat/usulan/{$usulan->id}/submit")->assertRedirect();
    expect($usulan->refresh()->nomor_usulan)->not->toBeNull();

    $this->actingAs($kasubbag)
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", [
            'setuju' => true,
            'catatan' => 'Berkas lengkap dan sesuai',
        ])
        ->assertRedirect();

    $this->actingAs($sekretaris)
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-sekretaris", [
            'setuju' => true,
            'catatan' => 'Disetujui untuk penandatanganan ketua',
        ])
        ->assertRedirect();

    // Langkah 5: Tanda Tangan Ketua -> terbit Surat Pengantar PDF di luar transaksi DB -> dapat diunduh
    $baselineTx = DB::transactionLevel();
    $this->actingAs($ketua)
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/tanda-tangan-ketua")
        ->assertRedirect();

    $pdf = UsulanKpPdf::query()->where('usulan_kenaikan_pangkat_id', $usulan->id)->firstOrFail();
    expect($pdfTracker->transactionLevels[0])->toBe($baselineTx)
        ->and($pdfTracker->renderedHtml)->toContain('SK CPNS')
        ->and($pdfTracker->renderedHtml)->toContain($pemohon->nama_lengkap);

    $this->actingAs($pemohon)
        ->get("/kenaikan-pangkat/admin-sk/pdf/{$pdf->id}/download")
        ->assertSuccessful();

    // Langkah 6: Kirim ke Biro meneruskan DTO dengan nomor_usulan
    $this->actingAs($biro)
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/kirim-biro", ['catatan' => 'Dikirim via SIKEP'])
        ->assertRedirect();

    expect($pushedDto)->toBeInstanceOf(UsulanKenaikanPangkatDto::class)
        ->and($pushedDto->nomor_usulan)->toBe($usulan->nomor_usulan)
        ->and((string) $usulan->refresh()->state)->toBe('MENUNGGU_SK');

    // Langkah 7: Admin SK mengunggah SK Final dengan tmt_pangkat_baru & MKG resmi -> download SK berhasil
    $this->actingAs($adminSk)
        ->post("/kenaikan-pangkat/admin-sk/{$usulan->id}/upload-sk", [
            'sk_file' => UploadedFile::fake()->create('sk-final-e2e.pdf', 256, 'application/pdf'),
            'nomor_sk' => 'SK-MA-E2E-2026',
            'tanggal_sk' => '2026-05-25',
            'tmt_pangkat_baru' => '2026-04-01',
            'masa_kerja_tahun' => 11,
            'masa_kerja_bulan' => 4,
        ])
        ->assertRedirect();

    expect((string) $usulan->refresh()->state)->toBe('SELESAI_SK_TERBIT');

    $this->actingAs($pemohon)
        ->get("/kenaikan-pangkat/admin-sk/{$usulan->id}/download-sk")
        ->assertSuccessful();

    // Langkah 8: Riwayat pangkat baru aktif dengan TMT & MKG akurat, dan monitoring KP terupdate
    $riwayatAktif = RiwayatPangkat::query()
        ->where('pegawai_id', $pemohon->id)
        ->where('is_aktif', true)
        ->firstOrFail();

    expect($riwayatAktif->ref_pangkat_id)->toBe($aktor['pangkatTujuan']->id)
        ->and($riwayatAktif->tmt->toDateString())->toBe('2026-04-01')
        ->and($riwayatAktif->masa_kerja_tahun)->toBe(11)
        ->and($riwayatAktif->masa_kerja_bulan)->toBe(4)
        ->and($aktor['riwayatAsal']->fresh()->is_aktif)->toBeFalse()
        ->and($pemohon->fresh()->ref_pangkat_id)->toBe($aktor['pangkatTujuan']->id);

    $kpStatus = app(KenaikanPangkatMonitoringService::class)->getKpStatus($pemohon->fresh('riwayatPangkat'));
    expect($kpStatus['tmt_kp_berikutnya']->toDateString())->toBe('2030-04-01')
        ->and($kpStatus['status'])->toBe('Belum Eligible');
});

test('siklus penolakan, perbaikan berjenjang, proteksi IDOR pemilik, dan pembatalan wajib alasan', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $pemohon = $aktor['pemohon'];
    $kasubbag = $aktor['kasubbag'];
    $sekretaris = $aktor['sekretaris'];
    $tahunUsul = (int) now()->addYear()->format('Y');

    // Pemohon membuat draft, melengkapi checklist, dan submit
    $usulan = KpE2eHelper::buatDraftUsulanSiap($pemohon, $aktor['pangkatAsal'], $aktor['pangkatTujuan'], '2021-04-01', 4, $tahunUsul);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $kasubbag);
    $this->actingAs($pemohon)->post("/kenaikan-pangkat/usulan/{$usulan->id}/submit")->assertRedirect();

    // Pemilik mencoba approve / mintaPerbaikan / tolak usulannya sendiri → wajib ditolak 403 (F-04)
    $this->actingAs($pemohon)->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", ['setuju' => true])->assertForbidden();
    $this->actingAs($pemohon)->post("/kenaikan-pangkat/approval/{$usulan->id}/minta-perbaikan", ['catatan' => 'Bypass'])->assertForbidden();
    $this->actingAs($pemohon)->post("/kenaikan-pangkat/approval/{$usulan->id}/tolak", ['alasan' => 'Bypass'])->assertForbidden();

    // Kasubbag meminta perbaikan
    $this->actingAs($kasubbag)
        ->post("/kenaikan-pangkat/approval/{$usulan->id}/minta-perbaikan", [
            'catatan' => 'Perbaiki catatan dan lampiran SKP.',
        ])
        ->assertRedirect();

    expect((string) $usulan->refresh()->state)->toBe('PERLU_PERBAIKAN');

    // Pemohon mencoba batalkan tanpa alasan → ditolak 422
    $this->actingAs($pemohon)
        ->postJson("/kenaikan-pangkat/usulan/{$usulan->id}/batalkan", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('alasan');

    // Pemohon memperbaiki data usulan lalu resubmit → approval_steps di-reset menunggu
    $this->actingAs($pemohon)->put("/kenaikan-pangkat/usulan/{$usulan->id}", [
        'pegawai_id' => $pemohon->id,
        'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => $tahunUsul,
        'catatan_pengusul' => 'Dokumen telah diperbaiki.',
    ])->assertRedirect();

    $this->actingAs($pemohon)->post("/kenaikan-pangkat/usulan/{$usulan->id}/submit")->assertRedirect();

    expect((string) $usulan->refresh()->state)->toBe('DIAJUKAN')
        ->and($usulan->approvalSteps()->where('status', 'menunggu')->count())->toBe(3);

    // Kasubbag menyetujui, lalu Sekretaris menolak secara final
    $this->actingAs($kasubbag)->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", ['setuju' => true, 'catatan' => 'OK'])->assertRedirect();
    $this->actingAs($sekretaris)->post("/kenaikan-pangkat/approval/{$usulan->id}/tolak", ['alasan' => 'Belum memenuhi syarat administratif.'])->assertRedirect();

    expect((string) $usulan->refresh()->state)->toBe('DITOLAK')
        ->and($usulan->catatan_penolakan)->toBe('Belum memenuhi syarat administratif.');

    // Setelah ditolak (terminal), pegawai dapat membuat draft baru pada periode yang sama lalu membatalkannya dengan alasan
    $this->actingAs($pemohon)
        ->post('/kenaikan-pangkat/usulan', [
            'pegawai_id' => $pemohon->id,
            'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
            'periode_usul_bulan' => 4,
            'periode_usul_tahun' => $tahunUsul,
            'catatan_pengusul' => 'Usulan baru pasca penolakan',
        ])
        ->assertRedirect();

    $usulanBaru = UsulanKenaikanPangkat::query()
        ->where('pegawai_id', $pemohon->id)
        ->where('id', '!=', $usulan->id)
        ->firstOrFail();

    $this->actingAs($pemohon)
        ->post("/kenaikan-pangkat/usulan/{$usulanBaru->id}/batalkan", [
            'alasan' => 'Dibatalkan oleh pengusul atas permintaan pegawai.',
        ])
        ->assertRedirect();

    expect((string) $usulanBaru->refresh()->state)->toBe('DIBATALKAN');
});

test('siklus admin SK, timeline aktivitas JSON, dan verifikasi akses detail usulan lintas state', function (): void {
    Storage::fake('local');
    KpE2eHelper::pasangFakePdfDenganRenderBlade();

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);

    $this->actingAs($aktor['pemohon'])->post("/kenaikan-pangkat/usulan/{$usulan->id}/submit")->assertRedirect();
    $this->actingAs($aktor['kasubbag'])->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", ['setuju' => true])->assertRedirect();
    $this->actingAs($aktor['sekretaris'])->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-sekretaris", ['setuju' => true])->assertRedirect();
    $this->actingAs($aktor['ketua'])->post("/kenaikan-pangkat/approval/{$usulan->id}/tanda-tangan-ketua")->assertRedirect();
    $this->actingAs($aktor['biro'])->post("/kenaikan-pangkat/approval/{$usulan->id}/kirim-biro")->assertRedirect();

    // Admin SK melihat usulan pada daftar MENUNGGU_SK
    $this->actingAs($aktor['adminSk'])
        ->get('/kenaikan-pangkat/admin-sk')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/admin-sk/index')
            ->has('usulan.data', 1)
            ->where('usulan.data.0.id', $usulan->id)
            ->where('usulan.data.0.state', 'MENUNGGU_SK'));

    // Sebelum SK diunggah, download SK mengembalikan 404 (bukan 500)
    $this->actingAs($aktor['adminSk'])
        ->get("/kenaikan-pangkat/admin-sk/{$usulan->id}/download-sk")
        ->assertNotFound();

    // Unggah SK final lalu periksa timeline aktivitas JSON
    $this->actingAs($aktor['adminSk'])
        ->post("/kenaikan-pangkat/admin-sk/{$usulan->id}/upload-sk", [
            'sk_file' => UploadedFile::fake()->create('sk-arsip.pdf', 128, 'application/pdf'),
            'nomor_sk' => 'SK-ARSIP-2026',
            'tanggal_sk' => '2026-05-30',
            'tmt_pangkat_baru' => '2026-04-01',
            'masa_kerja_tahun' => 7,
            'masa_kerja_bulan' => 9,
        ])
        ->assertRedirect();

    $this->actingAs($aktor['pemohon'])
        ->getJson("/kenaikan-pangkat/usulan/{$usulan->id}/activity")
        ->assertSuccessful()
        ->assertJsonStructure(['data'])
        ->assertJsonFragment(['event' => 'state_transition']);
});
