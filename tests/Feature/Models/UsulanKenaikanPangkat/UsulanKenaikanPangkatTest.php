<?php

use App\Models\BerkasChecklistSubmission;
use App\Models\BerkasChecklistTemplate;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpApprovalStep;
use App\Models\UsulanKenaikanPangkat\UsulanKpApproverHistory;
use App\Models\UsulanKenaikanPangkat\UsulanKpLampiran;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Models\UsulanKenaikanPangkat\UsulanKpStateHistory;
use App\States\UsulanKenaikanPangkat\DiajukanState;
use App\States\UsulanKenaikanPangkat\DibatalkanState;
use App\States\UsulanKenaikanPangkat\DikirimBiroState;
use App\States\UsulanKenaikanPangkat\DitandatanganiKetuaState;
use App\States\UsulanKenaikanPangkat\DitolakState;
use App\States\UsulanKenaikanPangkat\DiverifikasiKasubbagState;
use App\States\UsulanKenaikanPangkat\DiverifikasiSekretarisState;
use App\States\UsulanKenaikanPangkat\DraftState;
use App\States\UsulanKenaikanPangkat\MenungguSkState;
use App\States\UsulanKenaikanPangkat\PerluPerbaikanState;
use App\States\UsulanKenaikanPangkat\SelesaiSkTerbitState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

uses(RefreshDatabase::class);

function createUsulanKenaikanPangkat(): UsulanKenaikanPangkat
{
    return UsulanKenaikanPangkat::factory()->create();
}

it('membuat usulan via factory dengan default state draft', function () {
    $usulan = createUsulanKenaikanPangkat();

    expect($usulan->state)->toBeInstanceOf(DraftState::class)
        ->and($usulan->state->label())->toBe('Draft');
});

it('factory state diajukan mengisi state dan submitted_at', function () {
    $usulan = UsulanKenaikanPangkat::factory()->diajukan()->create();

    expect($usulan->state)->toBeInstanceOf(DiajukanState::class)
        ->and($usulan->submitted_at)->not->toBeNull();
});

it('factory state sk_terbit mengisi state final dan metadata sk', function () {
    $usulan = UsulanKenaikanPangkat::factory()->skTerbit()->create();

    expect($usulan->state)->toBeInstanceOf(SelesaiSkTerbitState::class)
        ->and($usulan->nomor_sk)->not->toBeNull()
        ->and($usulan->finalized_at)->not->toBeNull();
});

it('mengizinkan transisi happy path hingga sk terbit', function () {
    $usulan = createUsulanKenaikanPangkat();

    $usulan->state->transitionTo(DiajukanState::class);
    $usulan->state->transitionTo(DiverifikasiKasubbagState::class);
    $usulan->state->transitionTo(DiverifikasiSekretarisState::class);
    $usulan->state->transitionTo(DitandatanganiKetuaState::class);
    $usulan->state->transitionTo(DikirimBiroState::class);
    $usulan->state->transitionTo(MenungguSkState::class);
    $usulan->state->transitionTo(SelesaiSkTerbitState::class);

    expect($usulan->refresh()->state)->toBeInstanceOf(SelesaiSkTerbitState::class);
});

it('menolak transisi draft langsung ke sk terbit', function () {
    $usulan = createUsulanKenaikanPangkat();

    expect(fn () => $usulan->state->transitionTo(SelesaiSkTerbitState::class))
        ->toThrow(CouldNotPerformTransition::class);
});

it('mencatat activity log saat usulan diupdate', function () {
    $usulan = createUsulanKenaikanPangkat();

    $usulan->update(['catatan_pengusul' => 'Catatan baru']);

    expect(Activity::query()
        ->where('subject_type', UsulanKenaikanPangkat::class)
        ->where('subject_id', $usulan->id)
        ->where('log_name', 'usulan_kenaikan_pangkat')
        ->exists())->toBeTrue();
});

it('memuat relasi pegawai dan pangkat', function () {
    $usulan = createUsulanKenaikanPangkat();

    expect($usulan->pegawai)->toBeInstanceOf(Pegawai::class)
        ->and($usulan->pangkatAsal)->toBeInstanceOf(RefPangkat::class)
        ->and($usulan->pangkatTujuan)->toBeInstanceOf(RefPangkat::class);
});

it('memuat relasi approval steps', function () {
    $usulan = createUsulanKenaikanPangkat();
    $step = UsulanKpApprovalStep::query()->create([
        'usulan_kenaikan_pangkat_id' => $usulan->id,
        'urutan' => 1,
        'role_required' => 'kasubbag_kepegawaian',
    ]);

    expect($usulan->approvalSteps()->first())->toBeInstanceOf(UsulanKpApprovalStep::class)
        ->and($step->usulan->is($usulan))->toBeTrue();
});

it('memuat relasi approver history', function () {
    $usulan = createUsulanKenaikanPangkat();
    $user = Pegawai::factory()->create();
    $history = UsulanKpApproverHistory::query()->create([
        'usulan_kenaikan_pangkat_id' => $usulan->id,
        'step_urutan' => 1,
        'user_id' => $user->id,
        'action' => 'approve',
    ]);

    expect($usulan->approverHistory()->first())->toBeInstanceOf(UsulanKpApproverHistory::class)
        ->and($history->user->is($user))->toBeTrue();
});

it('memuat relasi state history', function () {
    $usulan = createUsulanKenaikanPangkat();
    $history = UsulanKpStateHistory::query()->create([
        'usulan_kenaikan_pangkat_id' => $usulan->id,
        'from_state' => 'DRAFT',
        'to_state' => 'DIAJUKAN',
    ]);

    expect($usulan->stateHistory()->first())->toBeInstanceOf(UsulanKpStateHistory::class)
        ->and($history->usulan->is($usulan))->toBeTrue();
});

it('memuat relasi lampiran', function () {
    $usulan = createUsulanKenaikanPangkat();
    $user = Pegawai::factory()->create();
    $lampiran = UsulanKpLampiran::query()->create([
        'usulan_kenaikan_pangkat_id' => $usulan->id,
        'jenis' => 'sk_cpns',
        'nama_file' => 'SK CPNS',
        'file_path' => 'kp/sk-cpns.pdf',
        'file_original_name' => 'sk-cpns.pdf',
        'file_mime' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $user->id,
    ]);

    expect($usulan->lampiran()->first())->toBeInstanceOf(UsulanKpLampiran::class)
        ->and($lampiran->uploadedBy->is($user))->toBeTrue();
});

it('memuat relasi pdf', function () {
    $usulan = createUsulanKenaikanPangkat();
    $user = Pegawai::factory()->create();
    $pdf = UsulanKpPdf::query()->create([
        'usulan_kenaikan_pangkat_id' => $usulan->id,
        'jenis_pdf' => 'surat_pengantar',
        'nomor_surat' => 'KP/001/2026',
        'file_path' => 'kp/surat.pdf',
        'generated_by' => $user->id,
    ]);

    expect($usulan->pdfs()->first())->toBeInstanceOf(UsulanKpPdf::class)
        ->and($pdf->generatedBy->is($user))->toBeTrue();
});

it('me-resolve checklistSubmission morphOne', function () {
    $usulan = createUsulanKenaikanPangkat();
    $template = BerkasChecklistTemplate::query()->create([
        'jenis' => 'kenaikan_pangkat',
        'kode' => 'checklist-kp-reguler',
        'nama' => 'Checklist KP Reguler',
    ]);

    $submission = BerkasChecklistSubmission::query()->create([
        'berkas_checklist_template_id' => $template->id,
        'subject_type' => UsulanKenaikanPangkat::class,
        'subject_id' => $usulan->id,
        'pegawai_id' => $usulan->pegawai_id,
    ]);

    expect($usulan->checklistSubmission)->toBeInstanceOf(BerkasChecklistSubmission::class)
        ->and($submission->subject->is($usulan))->toBeTrue();
});

it('state terminal selesai tidak mengizinkan transisi lanjutan', function () {
    $usulan = UsulanKenaikanPangkat::factory()->skTerbit()->create();

    expect(fn () => $usulan->state->transitionTo(PerluPerbaikanState::class))
        ->toThrow(CouldNotPerformTransition::class);
});

it('state perbaikan dapat kembali ke draft lalu dibatalkan', function () {
    $usulan = UsulanKenaikanPangkat::factory()->diajukan()->create();

    $usulan->state->transitionTo(PerluPerbaikanState::class);
    $usulan->state->transitionTo(DraftState::class);
    $usulan->state->transitionTo(DibatalkanState::class);

    expect($usulan->refresh()->state)->toBeInstanceOf(DibatalkanState::class);
});

it('state diverifikasi dapat ditolak', function () {
    $usulan = UsulanKenaikanPangkat::factory()->diajukan()->create();

    $usulan->state->transitionTo(DiverifikasiKasubbagState::class);
    $usulan->state->transitionTo(DitolakState::class);

    expect($usulan->refresh()->state)->toBeInstanceOf(DitolakState::class);
});
