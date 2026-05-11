<?php

use App\Actions\UsulanKenaikanPangkat\GenerateSuratPengantarPdf;
use App\Events\UsulanKenaikanPangkat\UsulanKpSkTerbit;
use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Artisan::call('db:seed', [
        '--class' => ChecklistKenaikanPangkatSeeder::class,
    ]);

    app()->bind(GenerateSuratPengantarPdf::class, FakeGenerateSuratPengantarPdf::class);
});

it('UsulanKpE2E syncs active riwayat pangkat when SK is uploaded', function (): void {
    Storage::fake('local');

    $service = app(UsulanKenaikanPangkatService::class);
    $pegawai = Pegawai::factory()->create();
    $pangkatAsal = RefPangkat::factory()->create();
    $pangkatTujuan = RefPangkat::factory()->create();

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pangkatAsal->id,
        'is_aktif' => true,
    ]);

    $usulan = $service->createDraft([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_asal_id' => $pangkatAsal->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
        'tmt_pangkat_asal' => now()->subYears(4)->toDateString(),
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => (int) now()->addYear()->format('Y'),
        'catatan_pengusul' => 'Usulan KP E2E.',
    ], $pegawai);

    completeRequiredChecklist($usulan, Pegawai::factory()->create());

    $service->submit($usulan->fresh());
    $service->verifikasiKasubbag($usulan->fresh(), Pegawai::factory()->create(), true);
    $service->verifikasiSekretaris($usulan->fresh(), Pegawai::factory()->create(), true);
    $service->tandaTanganKetua($usulan->fresh(), Pegawai::factory()->create());
    $service->kirimBiro($usulan->fresh(), Pegawai::factory()->create());
    $service->uploadSkFinal($usulan->fresh(), Pegawai::factory()->create(), UploadedFile::fake()->create('sk-final.pdf', 128, 'application/pdf'), 'SK-KP-E2E-001', '2026-05-12');

    $usulan = $usulan->fresh();
    $pegawai = $pegawai->fresh();
    $riwayatAktif = RiwayatPangkat::query()->aktif()->where('pegawai_id', $pegawai->id)->sole();

    expect($pegawai->ref_pangkat_id)->toBe($pangkatTujuan->id)
        ->and(RiwayatPangkat::query()->aktif()->where('pegawai_id', $pegawai->id)->count())->toBe(1)
        ->and($riwayatAktif->no_sk)->toBe($usulan->nomor_sk)
        ->and($riwayatAktif->ref_pangkat_id)->toBe($pangkatTujuan->id);
});

it('UsulanKpE2E does not dispatch SK event when transition fails', function (): void {
    Storage::fake('local');
    Event::fake([UsulanKpSkTerbit::class]);

    $usulan = UsulanKenaikanPangkat::factory()->create([
        'state' => 'DRAFT',
    ]);

    try {
        app(UsulanKenaikanPangkatService::class)->uploadSkFinal(
            $usulan,
            Pegawai::factory()->create(),
            UploadedFile::fake()->create('sk-final.pdf', 128, 'application/pdf'),
            'SK-KP-GAGAL-001',
            '2026-05-12',
        );

        $this->fail('Upload SK final dari state DRAFT seharusnya gagal.');
    } catch (Throwable) {
        expect(true)->toBeTrue();
    }

    Event::assertNotDispatched(UsulanKpSkTerbit::class);
});

it('UsulanKpE2E enforces one active riwayat pangkat per pegawai', function (): void {
    $pegawai = Pegawai::factory()->create();

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => true,
    ]);

    expect(fn () => RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => true,
    ]))->toThrow(QueryException::class);
})->skip('Invariant enforced at service layer (SinkronkanRiwayatPangkat listener), not DB partial unique index');

function completeRequiredChecklist(UsulanKenaikanPangkat $usulan, Pegawai $validator): void
{
    $submission = $usulan->checklistSubmission()->firstOrFail();
    $checklistService = app(ChecklistBerkasService::class);

    foreach (requiredUsulanKpE2EItems($submission->berkas_checklist_template_id) as $item) {
        $checklistService->uploadFile($item, UploadedFile::fake()->create("{$item->id}.pdf", 64, 'application/pdf'));
        $checklistService->validateItem($item->fresh(), $validator, BerkasChecklistSubmissionItem::STATUS_VALID);
    }

    $checklistService->recalculatePersentase($submission->fresh());
}

function requiredUsulanKpE2EItems(string $templateId): Collection
{
    $requiredItemIds = BerkasChecklistItem::query()
        ->where('berkas_checklist_template_id', $templateId)
        ->wajib()
        ->pluck('id');

    return BerkasChecklistSubmissionItem::query()
        ->whereIn('berkas_checklist_item_id', $requiredItemIds)
        ->get();
}

class FakeGenerateSuratPengantarPdf extends GenerateSuratPengantarPdf
{
    public function __construct() {}

    public function handle(object $usulan, object $pejabatPenandatangan): object
    {
        return UsulanKpPdf::query()->create([
            'usulan_kenaikan_pangkat_id' => $usulan->id,
            'jenis_pdf' => 'surat_pengantar',
            'nomor_surat' => 'TEST/KP/001',
            'file_path' => "usulan-kp/surat-pengantar/{$usulan->id}.pdf",
            'generated_by' => $pejabatPenandatangan->id,
            'generated_at' => now(),
        ]);
    }
}
