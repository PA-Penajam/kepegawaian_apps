<?php

declare(strict_types=1);

use App\Enums\StatusPegawai;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\KenaikanPangkatMonitoringService;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Carbon\Carbon;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Database\Seeders\IamSeeder;
use Database\Seeders\RefPangkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\E2E\KenaikanPangkat\Support\KpE2eHelper;
use Tests\TestCase;

require_once __DIR__.'/Support/KpE2eHelper.php';

uses(TestCase::class, RefreshDatabase::class)->beforeEach(function (): void {
    $this->withoutVite();
    $this->seed([IamSeeder::class, RefPangkatSeeder::class, ChecklistKenaikanPangkatSeeder::class]);
})->afterEach(function (): void {
    Carbon::setTestNow();
})->group('tier3');

test('kombinasi upload dan validasi checklist hingga submit, verifikasi berjenjang, dan tandaTanganKetua menghasilkan PDF di luar transaksi DB', function (): void {
    Storage::fake('local');
    $tracker = KpE2eHelper::pasangFakePdfDenganRenderBlade();

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $pemohon = $aktor['pemohon'];
    $kasubbag = $aktor['kasubbag'];
    $sekretaris = $aktor['sekretaris'];
    $ketua = $aktor['ketua'];

    // 1. Buat draft via HTTP
    $this->actingAs($pemohon)->post('/kenaikan-pangkat/usulan', [
        'pegawai_id' => $pemohon->id,
        'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => (int) now()->addYear()->format('Y'),
        'catatan_pengusul' => 'Kombinasi checklist ke PDF',
    ])->assertRedirect();

    $usulan = UsulanKenaikanPangkat::query()->where('pegawai_id', $pemohon->id)->firstOrFail();
    $submission = $usulan->checklistSubmission()->with(['template.items', 'items.item'])->firstOrFail();
    $checklistService = app(ChecklistBerkasService::class);
    $requiredIds = $submission->template->items()->wajib()->pluck('id');

    // 2. Upload semua item wajib via HTTP, lalu validasi oleh Kasubbag dan Sekretaris lewat HTTP
    $urlMethod = KpE2eHelper::methodValidasiChecklist();
    $submission->items()
        ->whereIn('berkas_checklist_item_id', $requiredIds)
        ->get()
        ->values()
        ->each(function (BerkasChecklistSubmissionItem $item, int $index) use ($pemohon, $kasubbag, $sekretaris, $usulan, $urlMethod): void {
            $this->actingAs($pemohon)
                ->post("/kenaikan-pangkat/usulan/{$usulan->id}/checklist/{$item->id}/upload", [
                    'file' => UploadedFile::fake()->create("{$item->id}.pdf", 64, 'application/pdf'),
                ])
                ->assertRedirect();

            $validator = $index % 2 === 0 ? $kasubbag : $sekretaris;
            $urlValidasi = KpE2eHelper::urlValidasiChecklist($usulan, $item);

            $validateResponse = $this->actingAs($validator)->call($urlMethod, $urlValidasi, [
                'status' => BerkasChecklistSubmissionItem::STATUS_VALID,
                'catatan' => 'Sesuai',
            ]);

            expect($validateResponse->status())->not->toBe(500)
                ->and($validateResponse->isSuccessful() || $validateResponse->isRedirection())->toBeTrue();
        });

    $checklistService->recalculatePersentase($submission->fresh());
    expect($checklistService->isComplete($submission->fresh(['template.items', 'items'])))->toBeTrue();

    // 3. Submit via HTTP → Verifikasi Kasubbag & Sekretaris → Tanda Tangan Ketua
    $this->actingAs($pemohon)->post("/kenaikan-pangkat/usulan/{$usulan->id}/submit")->assertRedirect();
    $this->actingAs($kasubbag)->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", ['setuju' => true, 'catatan' => 'Lengkap'])->assertRedirect();
    $this->actingAs($sekretaris)->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-sekretaris", ['setuju' => true, 'catatan' => 'Disetujui'])->assertRedirect();

    $baselineLevel = DB::transactionLevel();
    $this->actingAs($ketua)->post("/kenaikan-pangkat/approval/{$usulan->id}/tanda-tangan-ketua")->assertRedirect();

    $usulan->refresh();
    expect((string) $usulan->state)->toBe('DITANDATANGANI_KETUA')
        ->and($usulan->nomor_usulan)->not->toBeNull()
        ->and($usulan->pdfs()->where('jenis_pdf', 'surat_pengantar')->exists())->toBeTrue()
        ->and($tracker->transactionLevels)->not->toBeEmpty()
        ->and($tracker->transactionLevels[0])->toBe($baselineLevel)
        ->and($tracker->renderedHtml)->toContain('SK CPNS')
        ->and($tracker->renderedHtml)->toContain('SKP 2 Tahun Terakhir');
});

test('kombinasi alur perbaikan lintas aktor: mintaPerbaikan reset steps -> edit & resubmit -> verifikasi ulang berhasil', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $usulan = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan']);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan, $aktor['kasubbag']);

    // Submit awal dan verifikasi kasubbag
    $this->actingAs($aktor['pemohon'])->post("/kenaikan-pangkat/usulan/{$usulan->id}/submit")->assertRedirect();
    $this->actingAs($aktor['kasubbag'])->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", [
        'setuju' => true,
        'catatan' => 'Teruskan ke sekretaris',
    ])->assertRedirect();

    // Sekretaris meminta perbaikan (harus sah pada state DIVERIFIKASI_KASUBBAG)
    $this->actingAs($aktor['sekretaris'])->post("/kenaikan-pangkat/approval/{$usulan->id}/minta-perbaikan", [
        'catatan' => 'Sesuaikan catatan pengusul',
    ])->assertRedirect();

    expect((string) $usulan->refresh()->state)->toBe('PERLU_PERBAIKAN');

    // Pemohon memperbarui catatan usulan lalu resubmit
    $this->actingAs($aktor['pemohon'])->put("/kenaikan-pangkat/usulan/{$usulan->id}", [
        'pegawai_id' => $aktor['pemohon']->id,
        'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
        'periode_usul_bulan' => $usulan->periode_usul_bulan,
        'periode_usul_tahun' => $usulan->periode_usul_tahun,
        'catatan_pengusul' => 'Sudah diperbaiki sesuai arahan sekretaris',
    ])->assertRedirect();

    $this->actingAs($aktor['pemohon'])->post("/kenaikan-pangkat/usulan/{$usulan->id}/submit")->assertRedirect();

    $usulan->refresh();
    expect((string) $usulan->state)->toBe('DIAJUKAN')
        ->and($usulan->approvalSteps()->where('status', 'menunggu')->count())->toBe(3);

    // Verifikasi ulang kasubbag dan sekretaris berjalan mulus
    $this->actingAs($aktor['kasubbag'])->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", [
        'setuju' => true,
        'catatan' => 'Verifikasi ulang OK',
    ])->assertRedirect();

    $this->actingAs($aktor['sekretaris'])->post("/kenaikan-pangkat/approval/{$usulan->id}/verifikasi-sekretaris", [
        'setuju' => true,
        'catatan' => 'Verifikasi ulang Sekretaris OK',
    ])->assertRedirect();

    expect((string) $usulan->refresh()->state)->toBe('DIVERIFIKASI_SEKRETARIS');
});

test('kombinasi upload SK final dan SinkronkanRiwayatPangkat memperbarui status pegawai pada monitoring KP dan halaman eligible', function (): void {
    Carbon::setTestNow('2026-06-01');
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $pemohon = $aktor['pemohon'];
    $monitoringService = app(KenaikanPangkatMonitoringService::class);

    // Sebelum SK final terbit, TMT 2021-04-01 sudah melewati 4 tahun pada 2026-06-01
    $statusAwal = $monitoringService->getKpStatus($pemohon->fresh('riwayatPangkat'));
    expect($statusAwal['eligible'])->toBeTrue()
        ->and($statusAwal['status'])->toBe('Sudah Eligible');

    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $pemohon->id,
        'ref_pangkat_asal_id' => $aktor['pangkatAsal']->id,
        'ref_pangkat_tujuan_id' => $aktor['pangkatTujuan']->id,
        'tmt_pangkat_asal' => '2021-04-01',
        'state' => 'MENUNGGU_SK',
    ]);

    $this->actingAs($aktor['adminSk'])
        ->post("/kenaikan-pangkat/admin-sk/{$usulan->id}/upload-sk", [
            'sk_file' => UploadedFile::fake()->create('sk-final.pdf', 128, 'application/pdf'),
            'nomor_sk' => 'SK-KP-2026-777',
            'tanggal_sk' => '2026-05-20',
            'tmt_pangkat_baru' => '2026-04-01',
            'masa_kerja_tahun' => 9,
            'masa_kerja_bulan' => 2,
        ])
        ->assertRedirect();

    $statusBaru = $monitoringService->getKpStatus($pemohon->fresh(['riwayatPangkat', 'pangkat']));
    expect($statusBaru['eligible'])->toBeFalse()
        ->and($statusBaru['tmt_kp_berikutnya']->toDateString())->toBe('2030-04-01')
        ->and($statusBaru['periode_usul'])->toBe('April 2030')
        ->and($statusBaru['status'])->toBe('Belum Eligible');

    // Verifikasi halaman /kenaikan-pangkat/eligible menampilkan data pangkat & TMT terbaru
    $this->actingAs($aktor['pemohon'])
        ->get('/kenaikan-pangkat/eligible')
        ->assertSuccessful()
        ->assertInertia(function (Assert $page) use ($pemohon): void {
            $page->component('kenaikan-pangkat/eligible/index')
                ->has('pegawaiList.data');

            $row = collect($page->toArray()['props']['pegawaiList']['data'])
                ->firstWhere('id', $pemohon->id);

            expect($row)->not->toBeNull()
                ->and($row['pangkat_kode'])->toBe('III/b')
                ->and($row['tmt_pangkat'])->toBe('2026-04-01')
                ->and($row['tmt_kp_berikutnya'])->toBe('2030-04-01')
                ->and($row['status'])->toBe('Belum Eligible');
        });
});

test('kontrak integrasi Inertia props usulan index, form, show beserta paginator root dan stateBadgeVariants', function (): void {
    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $pemohon = $aktor['pemohon'];
    $pangkatTujuan = $aktor['pangkatTujuan'];

    $usulanSample = null;
    foreach (range(1, 12) as $i) {
        $peg = $i === 1 ? $pemohon : KpE2eHelper::buatPegawaiPnsDenganPangkatAktif($aktor['pangkatAsal'], '2021-04-01')['pegawai'];
        $created = UsulanKenaikanPangkat::factory()->create([
            'pegawai_id' => $peg->id,
            'ref_pangkat_asal_id' => $aktor['pangkatAsal']->id,
            'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
            'state' => $i % 2 === 0 ? 'DIAJUKAN' : 'DRAFT',
            'created_by' => $pemohon->id,
        ]);

        if ($i === 1) {
            $usulanSample = $created;
        }
    }

    $this->actingAs($pemohon)
        ->get('/kenaikan-pangkat/usulan')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/index')
            ->has('usulanList.data', 10)
            ->where('usulanList.last_page', 2)
            ->has('usulanList.links')
            ->has('stateLabels.DRAFT')
            ->has('stateLabels.DIAJUKAN')
            ->has('bulanOptions', 12)
            ->has('tahunOptions'));

    $this->actingAs($pemohon)
        ->get("/kenaikan-pangkat/usulan/{$usulanSample->id}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/show')
            ->where('usulan.id', $usulanSample->id)
            ->has('stateBadgeVariants.DRAFT')
            ->has('stateBadgeVariants.SELESAI_SK_TERBIT')
            ->has('stateBadgeVariants.DITOLAK')
            ->has('policy.update')
            ->has('policy.batalkan'));
});

test('kombinasi multi-role inbox approval dan API statistik KP sinkron dengan transisi state serta nomor_usulan', function (): void {
    Storage::fake('local');

    $aktor = KpE2eHelper::buatAktorWorkflowKp('2021-04-01');
    $service = app(UsulanKenaikanPangkatService::class);
    $tahunUsul = (int) now()->addYear()->format('Y');

    $usulan1 = KpE2eHelper::buatDraftUsulanSiap($aktor['pemohon'], $aktor['pangkatAsal'], $aktor['pangkatTujuan'], '2021-04-01', 4, $tahunUsul);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan1, $aktor['kasubbag']);
    $service->submit($usulan1, $aktor['pemohon']);

    $pegawai2 = KpE2eHelper::buatPegawaiPnsDenganPangkatAktif($aktor['pangkatAsal'], '2021-04-01')['pegawai'];
    $usulan2 = KpE2eHelper::buatDraftUsulanSiap($pegawai2, $aktor['pangkatAsal'], $aktor['pangkatTujuan'], '2021-04-01', 4, $tahunUsul);
    KpE2eHelper::lengkapiDanValidasiChecklistWajib($usulan2, $aktor['kasubbag']);
    $service->submit($usulan2, $pegawai2);
    $service->verifikasiKasubbag($usulan2->refresh(), $aktor['kasubbag'], true, 'Lolos');

    $superApprover = KpE2eHelper::beriPermissions(
        Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif->value]),
        KpE2eHelper::semuaPermissionKp(),
        'kp-e2e-super-approver',
    );

    $this->actingAs($superApprover)
        ->get('/kenaikan-pangkat/approval/inbox')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/approval/inbox')
            ->has('usulan.data', 2));

    Sanctum::actingAs($superApprover, ['app:kepegawaian']);

    $statsPath = '/api/kenaikan-pangkat/stats';
    $statsQuery = ['periode_usul_bulan' => 4, 'periode_usul_tahun' => $tahunUsul];
    $this->getJson($statsPath.'?'.http_build_query($statsQuery), KpE2eHelper::buatHeaderHmacApi('GET', $statsPath, $statsQuery))
        ->assertSuccessful()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('per_state.DIAJUKAN', 1)
        ->assertJsonPath('per_state.DIVERIFIKASI_KASUBBAG', 1);

    $detailPath = "/api/kenaikan-pangkat/usulan/{$usulan1->id}";
    $this->getJson($detailPath, KpE2eHelper::buatHeaderHmacApi('GET', $detailPath))
        ->assertSuccessful()
        ->assertJsonPath('data.nomor_usulan', $usulan1->refresh()->nomor_usulan);
});
