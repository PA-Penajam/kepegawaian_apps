<?php

use App\Actions\UsulanKenaikanPangkat\GenerateSuratPengantarPdf;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpApproverHistory;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Models\UsulanKenaikanPangkat\UsulanKpStateHistory;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\Sikep\SikepAdapter;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Artisan::call('db:seed', ['--class' => ChecklistKenaikanPangkatSeeder::class, '--force' => true]);
});

function makeAuditTrailKpPegawai(array $attributes = []): Pegawai
{
    $pangkat = RefPangkat::factory()->create();

    return Pegawai::factory()->create(array_merge([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => StatusPegawai::Aktif->value,
        'status_kepegawaian' => StatusKepegawaian::PNS->value,
    ], $attributes));
}

function makeAuditTrailKpData(Pegawai $pegawai, array $attributes = []): array
{
    return array_merge([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_asal_id' => $pegawai->ref_pangkat_id,
        'ref_pangkat_tujuan_id' => RefPangkat::factory()->create()->id,
        'tmt_pangkat_asal' => now()->subYears(4)->toDateString(),
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
        'catatan_pengusul' => 'Usulan reguler',
    ], $attributes);
}

function makeAuditTrailKpService(?GenerateSuratPengantarPdf $pdfAction = null, ?SikepAdapter $adapter = null): UsulanKenaikanPangkatService
{
    $pdfAction ??= Mockery::mock(GenerateSuratPengantarPdf::class, function (MockInterface $mock): void {
        $mock->shouldIgnoreMissing();
    });

    $adapter ??= Mockery::mock(SikepAdapter::class, function (MockInterface $mock): void {
        $mock->shouldReceive('pushUsulan')->byDefault()->andReturn(null);
        $mock->shouldReceive('isConfigured')->byDefault()->andReturn(false);
    });

    return new UsulanKenaikanPangkatService(
        app(ChecklistBerkasService::class),
        $pdfAction,
        $adapter,
    );
}

function makeAuditTrailKpCompleteChecklist(UsulanKenaikanPangkat $usulan, Pegawai $pegawai): BerkasChecklistSubmission
{
    $template = BerkasChecklistTemplate::factory()->create(['jenis' => 'kenaikan_pangkat']);
    $item = BerkasChecklistItem::factory()->create([
        'berkas_checklist_template_id' => $template->id,
        'wajib' => true,
    ]);

    $submission = BerkasChecklistSubmission::factory()->create([
        'berkas_checklist_template_id' => $template->id,
        'subject_type' => $usulan->getMorphClass(),
        'subject_id' => $usulan->id,
        'pegawai_id' => $pegawai->id,
        'status_kelengkapan' => 'lengkap',
        'persentase' => 100,
    ]);

    BerkasChecklistSubmissionItem::factory()->create([
        'berkas_checklist_submission_id' => $submission->id,
        'berkas_checklist_item_id' => $item->id,
        'status' => BerkasChecklistSubmissionItem::STATUS_VALID,
    ]);

    return $submission->load('template.items', 'items');
}

function makeAuditTrailKpSubmittedUsulan(): array
{
    $actor = makeAuditTrailKpPegawai();
    $service = makeAuditTrailKpService();
    $usulan = $service->createDraft(makeAuditTrailKpData($actor), $actor);

    $service->submit($usulan, $actor, makeAuditTrailKpCompleteChecklist($usulan, $actor));

    return [$service, $usulan->refresh(), $actor];
}

function makeAuditTrailKpFullFlow(): UsulanKenaikanPangkat
{
    Storage::fake('local');

    $actor = makeAuditTrailKpPegawai();
    $service = makeAuditTrailKpService();
    $usulan = $service->createDraft(makeAuditTrailKpData($actor), $actor);

    $service->submit($usulan, $actor, makeAuditTrailKpCompleteChecklist($usulan, $actor));
    $service->verifikasiKasubbag($usulan->refresh(), makeAuditTrailKpPegawai(), true, 'Setuju kasubbag');
    $service->verifikasiSekretaris($usulan->refresh(), makeAuditTrailKpPegawai(), true, 'Setuju sekretaris');

    $pdfAction = Mockery::mock(GenerateSuratPengantarPdf::class);
    $pdfAction->shouldReceive('handle')->once()->andReturnUsing(
        fn (UsulanKenaikanPangkat $signedUsulan, Pegawai $pejabat): UsulanKpPdf => UsulanKpPdf::query()->create([
            'usulan_kenaikan_pangkat_id' => $signedUsulan->id,
            'jenis_pdf' => 'surat_pengantar',
            'nomor_surat' => 'KP/001',
            'file_path' => 'usulan-kp/surat-pengantar/'.$signedUsulan->id.'.pdf',
            'generated_by' => $pejabat->id,
            'generated_at' => now(),
        ])
    );

    $service = makeAuditTrailKpService($pdfAction);
    $service->tandaTanganKetua($usulan->refresh(), makeAuditTrailKpPegawai());
    $service->kirimBiro($usulan->refresh(), makeAuditTrailKpPegawai(), 'Dikirim');
    $service->uploadSkFinal(
        $usulan->refresh(),
        $actor,
        UploadedFile::fake()->create('sk.pdf', 10, 'application/pdf'),
        'SK-001',
        '2026-05-12'
    );

    return $usulan->refresh();
}

it('AuditTrailKp mencatat activity log saat usulan dibuat', function (): void {
    $actor = makeAuditTrailKpPegawai();
    $usulan = makeAuditTrailKpService()->createDraft(makeAuditTrailKpData($actor), $actor);

    expect(DB::table('activity_log')
        ->where('subject_type', UsulanKenaikanPangkat::class)
        ->where('subject_id', $usulan->id)
        ->where('event', 'created')
        ->count())->toBe(1);
});

it('AuditTrailKp mencatat attribute_changes saat usulan diubah', function (): void {
    $actor = makeAuditTrailKpPegawai();
    $usulan = makeAuditTrailKpService()->createDraft(makeAuditTrailKpData($actor), $actor);

    $usulan->update(['catatan_pengusul' => 'Catatan berubah']);

    $changes = DB::table('activity_log')
        ->where('subject_type', UsulanKenaikanPangkat::class)
        ->where('subject_id', $usulan->id)
        ->where('event', 'updated')
        ->value('attribute_changes');

    expect($changes)->not->toBeNull()
        ->and(json_decode($changes, true))->toHaveKey('attributes.catatan_pengusul', 'Catatan berubah');
});

it('AuditTrailKp mencatat state transition pada activity log dan state_history', function (): void {
    [, $usulan] = makeAuditTrailKpSubmittedUsulan();

    expect($usulan->stateHistory()->where('to_state', 'DIAJUKAN')->count())->toBe(1)
        ->and(DB::table('activity_log')->where('subject_type', UsulanKpStateHistory::class)->count())->toBe(1)
        ->and(DB::table('activity_log')->where('subject_type', UsulanKpApproverHistory::class)->count())->toBe(1);
});

it('AuditTrailKp flow lengkap menghasilkan minimal sepuluh audit entries', function (): void {
    $usulan = makeAuditTrailKpFullFlow();

    $entries = DB::table('activity_log')
        ->where(function ($query) use ($usulan): void {
            $query->where(function ($query) use ($usulan): void {
                $query->where('subject_type', UsulanKenaikanPangkat::class)
                    ->where('subject_id', $usulan->id);
            })
                ->orWhereIn('subject_type', [UsulanKpStateHistory::class, UsulanKpApproverHistory::class]);
        })
        ->count();

    expect($entries)->toBeGreaterThanOrEqual(10);
});

it('AuditTrailKp endpoint activity menggabungkan tiga source timeline', function (): void {
    [, $usulan, $actor] = makeAuditTrailKpSubmittedUsulan();

    actingAs($actor)
        ->getJson(route('kenaikan-pangkat.usulan.activity', $usulan))
        ->assertSuccessful()
        ->assertJsonPath('data.0.source', fn (string $source): bool => in_array($source, ['activity_log', 'state_history', 'approver_history'], true))
        ->assertJsonFragment(['source' => 'activity_log'])
        ->assertJsonFragment(['source' => 'state_history'])
        ->assertJsonFragment(['source' => 'approver_history']);
});

it('AuditTrailKp endpoint activity menolak pegawai lain', function (): void {
    [, $usulan] = makeAuditTrailKpSubmittedUsulan();
    $other = makeAuditTrailKpPegawai();

    actingAs($other)
        ->getJson(route('kenaikan-pangkat.usulan.activity', $usulan))
        ->assertForbidden();
});
