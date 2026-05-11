<?php

use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\DokumenPegawai;
use App\Models\Pegawai;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('creates submission with all template items', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();

    $submission = $service->createSubmission($template, $subject, $pegawai);

    expect($submission)->toBeInstanceOf(BerkasChecklistSubmission::class)
        ->and($submission->items)->toHaveCount(7)
        ->and($submission->items->pluck('status')->unique()->values()->all())->toBe(['belum_ada'])
        ->and($submission->subject->is($subject))->toBeTrue();
});

it('filters active templates by domain', function () {
    checklistContext();

    BerkasChecklistTemplate::factory()->create([
        'jenis' => 'kenaikan_pangkat',
        'aktif' => false,
    ]);

    $templates = BerkasChecklistTemplate::query()->aktif()->byDomain('kenaikan_pangkat')->get();

    expect($templates)->toHaveCount(1)
        ->and($templates->first()->kode)->toBe('checklist-kp-reguler');
});

it('orders required template items', function () {
    [, $template] = checklistContext();

    $items = $template->items()->wajib()->ordered()->pluck('kode')->all();

    expect($items)->toBe([
        'sk_cpns',
        'sk_pns',
        'sk_pangkat_terakhir',
        'sk_jabatan_terakhir',
        'skp_2_tahun',
    ]);
});

it('updates item status with note', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    $item = $service->createSubmission($template, $subject, $pegawai)->items()->firstOrFail();

    $service->updateItemStatus($item, 'ada', 'Berkas tersedia');

    expect($item->fresh())
        ->status->toBe('ada')
        ->catatan->toBe('Berkas tersedia');
});

it('rejects invalid item status updates', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    $item = $service->createSubmission($template, $subject, $pegawai)->items()->firstOrFail();

    $service->updateItemStatus($item, 'rusak');
})->throws(InvalidArgumentException::class, 'Status rusak tidak valid.');

it('stores uploaded file and updates metadata', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    Storage::fake('local');
    $item = $service->createSubmission($template, $subject, $pegawai)->items()->firstOrFail();
    $file = UploadedFile::fake()->create('sk-cpns.pdf', 128, 'application/pdf');

    $service->uploadFile($item, $file);

    $storedItem = $item->fresh();
    expect(Storage::disk('local')->exists($storedItem->file_path))->toBeTrue();

    expect($storedItem)
        ->status->toBe('ada')
        ->file_original_name->toBe('sk-cpns.pdf')
        ->file_mime->toBe('application/pdf')
        ->file_size->toBe($file->getSize());
});

it('records validator and timestamp when validating item', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    $item = $service->createSubmission($template, $subject, $pegawai)->items()->firstOrFail();
    $validator = Pegawai::factory()->create();

    $service->validateItem($item, $validator, 'valid', 'Sesuai');

    expect($item->fresh())
        ->status->toBe('valid')
        ->catatan->toBe('Sesuai')
        ->validated_by->toBe($validator->id)
        ->validated_at->not->toBeNull();
});

it('resolves validator relation', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    $item = $service->createSubmission($template, $subject, $pegawai)->items()->firstOrFail();
    $validator = Pegawai::factory()->create();

    $service->validateItem($item, $validator, 'valid');

    expect($item->fresh()->validator->is($validator))->toBeTrue();
});

it('rejects invalid validation status', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    $item = $service->createSubmission($template, $subject, $pegawai)->items()->firstOrFail();
    $validator = Pegawai::factory()->create();

    $service->validateItem($item, $validator, 'ditolak');
})->throws(InvalidArgumentException::class, 'Status ditolak tidak valid.');

it('recalculates percentage at zero sixty and one hundred percent', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    $submission = $service->createSubmission($template, $subject, $pegawai);

    $service->recalculatePersentase($submission);
    expect($submission->fresh())
        ->persentase->toBe(0)
        ->status_kelengkapan->toBe('belum_lengkap');

    markRequiredItemsValid($submission, 3);
    $service->recalculatePersentase($submission->fresh());
    expect($submission->fresh())
        ->persentase->toBe(60)
        ->status_kelengkapan->toBe('belum_lengkap');

    markRequiredItemsValid($submission, 5);
    $service->recalculatePersentase($submission->fresh());
    expect($submission->fresh())
        ->persentase->toBe(100)
        ->status_kelengkapan->toBe('lengkap');
});

it('is complete only when every required item is valid', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    $submission = $service->createSubmission($template, $subject, $pegawai);

    expect($service->isComplete($submission))->toBeFalse();

    markRequiredItemsValid($submission, 5);

    expect($service->isComplete($submission->fresh()))->toBeTrue();
});

it('keeps incomplete when optional items are valid but required items are missing', function () {
    [$service, $template, $pegawai, $subject] = checklistContext();
    $submission = $service->createSubmission($template, $subject, $pegawai);
    $optionalItemIds = $template->items()->where('wajib', false)->pluck('id');

    $submission->items()
        ->whereIn('berkas_checklist_item_id', $optionalItemIds)
        ->update(['status' => 'valid']);

    $service->recalculatePersentase($submission->fresh());

    expect($submission->fresh())
        ->persentase->toBe(0)
        ->status_kelengkapan->toBe('belum_lengkap')
        ->and($service->isComplete($submission->fresh()))->toBeFalse();
});

function checklistContext(): array
{
    Artisan::call('db:seed', [
        '--class' => ChecklistKenaikanPangkatSeeder::class,
    ]);

    $service = app(ChecklistBerkasService::class);
    $template = BerkasChecklistTemplate::query()
        ->where('kode', 'checklist-kp-reguler')
        ->firstOrFail();
    $pegawai = Pegawai::factory()->create();
    $subject = DokumenPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
    ]);

    return [$service, $template, $pegawai, $subject];
}

function markRequiredItemsValid(BerkasChecklistSubmission $submission, int $count): void
{
    $requiredItemIds = BerkasChecklistItem::query()
        ->where('berkas_checklist_template_id', $submission->berkas_checklist_template_id)
        ->wajib()
        ->ordered()
        ->limit($count)
        ->pluck('id');

    $submission->items()
        ->whereIn('berkas_checklist_item_id', $requiredItemIds)
        ->update(['status' => 'valid']);
}
