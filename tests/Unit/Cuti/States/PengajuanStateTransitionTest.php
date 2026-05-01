<?php

use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\States\Cuti\DiajukanState;
use App\States\Cuti\DibatalkanState;
use App\States\Cuti\DicabutSetelahDisetujuiState;
use App\States\Cuti\DisetujuiAtasanState;
use App\States\Cuti\DisetujuiState;
use App\States\Cuti\DitolakAtasanState;
use App\States\Cuti\DitolakKepegawaianState;
use App\States\Cuti\DitolakPejabatState;
use App\States\Cuti\DiverifikasiState;
use App\States\Cuti\DraftState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Helper untuk membuat pengajuan cuti dengan state tertentu.
 */
function createPengajuan(string $state = 'DRAFT'): CutiPengajuan
{
    $pegawai = Pegawai::factory()->create();
    CutiJenisMaster::firstOrCreate(
        ['kode' => 'CT'],
        [
            'nama' => 'Cuti Tahunan',
            'saldo_driven' => true,
            'hak_default_per_tahun' => 12,
            'durasi_min_kalender' => 1,
            'durasi_max_kalender' => 90,
            'butuh_lampiran' => false,
            'boleh_dicabut_setelah_disetujui' => true,
            'aktif' => true,
        ],
    );

    return CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => $state,
    ]);
}

// ============================================================
// Task 5.3: Happy path transitions
// ============================================================

test('full happy path transitions DRAFT → DIAJUKAN → DIVERIFIKASI → DISETUJUI_ATASAN → DISETUJUI', function () {
    $p = createPengajuan();
    expect($p->state)->toBeInstanceOf(DraftState::class);

    $p->state->transitionTo(DiajukanState::class);
    expect($p->fresh()->state)->toBeInstanceOf(DiajukanState::class);

    $p->state->transitionTo(DiverifikasiState::class);
    expect($p->fresh()->state)->toBeInstanceOf(DiverifikasiState::class);

    $p->state->transitionTo(DisetujuiAtasanState::class);
    expect($p->fresh()->state)->toBeInstanceOf(DisetujuiAtasanState::class);

    $p->state->transitionTo(DisetujuiState::class);
    expect($p->fresh()->state->name())->toBe('DISETUJUI');
});

test('DRAFT dapat dibatalkan langsung', function () {
    $p = createPengajuan();
    $p->state->transitionTo(DibatalkanState::class);
    expect($p->fresh()->state)->toBeInstanceOf(DibatalkanState::class);
});

test('DISETUJUI dapat dicabut setelah disetujui', function () {
    $p = createPengajuan('DISETUJUI');
    $p->state->transitionTo(DicabutSetelahDisetujuiState::class);
    expect($p->fresh()->state)->toBeInstanceOf(DicabutSetelahDisetujuiState::class);
});

// ============================================================
// Task 5.4: Invalid transitions
// ============================================================

test('skip state throws TransitionNotFound', function () {
    $p = createPengajuan();
    // DRAFT → DISETUJUI tidak diizinkan (harus lewat DIAJUKAN → DIVERIFIKASI → ...)
    $p->state->transitionTo(DisetujuiState::class);
})->throws(TransitionNotFound::class);

test('terminal state tidak bisa transition', function () {
    $p = createPengajuan('DIBATALKAN');
    $p->state->transitionTo(DiajukanState::class);
})->throws(TransitionNotFound::class);

// ============================================================
// Task 5.5: Terminal states
// ============================================================

test('semua terminal states mengembalikan isTerminal true', function () {
    $terminals = [
        'DITOLAK_KEPEGAWAIAN',
        'DITOLAK_ATASAN',
        'DITOLAK_PEJABAT',
        'DIBATALKAN',
        'DICABUT_SETELAH_DISETUJUI',
    ];
    foreach ($terminals as $stateName) {
        $p = createPengajuan($stateName);
        expect($p->state->isTerminal())->toBeTrue("State {$stateName} harus terminal");
    }
});

test('non-terminal states mengembalikan isTerminal false', function () {
    $nonTerminals = ['DRAFT', 'DIAJUKAN', 'DIVERIFIKASI', 'DISETUJUI_ATASAN', 'DISETUJUI'];
    foreach ($nonTerminals as $stateName) {
        $p = createPengajuan($stateName);
        expect($p->state->isTerminal())->toBeFalse("State {$stateName} harus non-terminal");
    }
});

// ============================================================
// Rejection path transitions
// ============================================================

test('DIAJUKAN dapat ditolak oleh kepegawaian', function () {
    $p = createPengajuan('DIAJUKAN');
    $p->state->transitionTo(DitolakKepegawaianState::class);
    expect($p->fresh()->state)->toBeInstanceOf(DitolakKepegawaianState::class);
});

test('DIVERIFIKASI dapat ditolak oleh atasan', function () {
    $p = createPengajuan('DIVERIFIKASI');
    $p->state->transitionTo(DitolakAtasanState::class);
    expect($p->fresh()->state)->toBeInstanceOf(DitolakAtasanState::class);
});

test('DISETUJUI_ATASAN dapat ditolak oleh pejabat', function () {
    $p = createPengajuan('DISETUJUI_ATASAN');
    $p->state->transitionTo(DitolakPejabatState::class);
    expect($p->fresh()->state)->toBeInstanceOf(DitolakPejabatState::class);
});
