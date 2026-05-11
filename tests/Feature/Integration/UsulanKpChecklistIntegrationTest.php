<?php

use App\Events\ChecklistKelengkapanBerubah;
use App\Exceptions\UsulanKenaikanPangkat\BerkasBelumLengkapException;
use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use App\States\UsulanKenaikanPangkat\DiajukanState;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Artisan::call('db:seed', [
        '--class' => ChecklistKenaikanPangkatSeeder::class,
    ]);
});

it('createDraft auto-attaches default checklist and submission items', function (): void {
    $pegawai = Pegawai::factory()->create();
    $pangkatAsal = RefPangkat::factory()->create();
    $pangkatTujuan = RefPangkat::factory()->create();

    $usulan = app(UsulanKenaikanPangkatService::class)->createDraft([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_asal_id' => $pangkatAsal->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
        'tmt_pangkat_asal' => now()->subYears(4)->toDateString(),
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => (int) now()->addYear()->format('Y'),
        'catatan_pengusul' => 'Usulan KP reguler.',
    ], $pegawai);

    $template = BerkasChecklistTemplate::query()
        ->where('kode', config('sikep.kp.checklist_template_kode'))
        ->firstOrFail();
    $submission = $usulan->fresh()->checklistSubmission;

    expect($submission)->not->toBeNull()
        ->and($submission->berkas_checklist_template_id)->toBe($template->id)
        ->and($submission->pegawai_id)->toBe($pegawai->id)
        ->and($submission->items)->toHaveCount($template->items()->count())
        ->and($submission->items->pluck('status')->unique()->values()->all())->toBe(['belum_ada']);
});

it('blocks submit when checklist is incomplete', function (): void {
    $usulan = usulanKpDraftWithChecklist();

    app(UsulanKenaikanPangkatService::class)->submit($usulan);
})->throws(BerkasBelumLengkapException::class, 'Checklist berkas usulan kenaikan pangkat belum lengkap.');

it('submits after every required checklist item is uploaded and valid', function (): void {
    Storage::fake('local');

    $validator = Pegawai::factory()->create();
    $usulan = usulanKpDraftWithChecklist();
    $submission = $usulan->checklistSubmission()->with('items')->firstOrFail();
    $checklistService = app(ChecklistBerkasService::class);

    foreach (requiredSubmissionItems($submission->berkas_checklist_template_id) as $item) {
        $checklistService->uploadFile($item, UploadedFile::fake()->create("{$item->id}.pdf", 64, 'application/pdf'));
        $checklistService->validateItem($item->fresh(), $validator, BerkasChecklistSubmissionItem::STATUS_VALID);
    }

    $checklistService->recalculatePersentase($submission->fresh());

    app(UsulanKenaikanPangkatService::class)->submit($usulan->fresh());
    $submitted = $usulan->fresh();

    expect($submission->fresh())
        ->persentase->toBe(100)
        ->status_kelengkapan->toBe('lengkap')
        ->and($submitted->state)->toBeInstanceOf(DiajukanState::class)
        ->and($submitted->submitted_at)->not->toBeNull();
});

it('fires ChecklistKelengkapanBerubah when completeness status transitions', function (): void {
    Event::fake([ChecklistKelengkapanBerubah::class]);

    $validator = Pegawai::factory()->create();
    $usulan = usulanKpDraftWithChecklist();
    $submission = $usulan->checklistSubmission()->firstOrFail();
    $checklistService = app(ChecklistBerkasService::class);

    foreach (requiredSubmissionItems($submission->berkas_checklist_template_id) as $item) {
        $checklistService->validateItem($item, $validator, BerkasChecklistSubmissionItem::STATUS_VALID);
    }

    $checklistService->recalculatePersentase($submission->fresh());

    Event::assertDispatched(ChecklistKelengkapanBerubah::class, function (ChecklistKelengkapanBerubah $event) use ($submission): bool {
        return $event->submission->is($submission);
    });
});

function usulanKpDraftWithChecklist(): UsulanKenaikanPangkat
{
    $pegawai = Pegawai::factory()->create();

    return app(UsulanKenaikanPangkatService::class)->createDraft([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_asal_id' => RefPangkat::factory()->create()->id,
        'ref_pangkat_tujuan_id' => RefPangkat::factory()->create()->id,
        'tmt_pangkat_asal' => now()->subYears(4)->toDateString(),
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => (int) now()->addYear()->format('Y'),
    ], $pegawai);
}

function requiredSubmissionItems(string $templateId): Collection
{
    $requiredItemIds = BerkasChecklistItem::query()
        ->where('berkas_checklist_template_id', $templateId)
        ->wajib()
        ->pluck('id');

    return BerkasChecklistSubmissionItem::query()
        ->whereIn('berkas_checklist_item_id', $requiredItemIds)
        ->get();
}
