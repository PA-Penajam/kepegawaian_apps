<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\States\Cuti\DibatalkanState;
use Database\Seeders\CutiJenisMasterSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
});

test('expire transitions old drafts to dibatalkan', function () {
    $pegawai = Pegawai::factory()->create();

    // Draft lama (8 hari lalu)
    $oldDraft = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'state' => 'DRAFT',
        'created_at' => now()->subDays(8),
    ]);

    $this->artisan('cuti:expire-drafts')
        ->assertSuccessful();

    $oldDraft->refresh();
    expect($oldDraft->state->name())->toBe('DIBATALKAN');
    expect($oldDraft->cancelled_at)->not->toBeNull();
});

test('expire does not touch recent drafts', function () {
    $pegawai = Pegawai::factory()->create();

    // Draft baru (3 hari lalu)
    $recentDraft = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'state' => 'DRAFT',
        'created_at' => now()->subDays(3),
    ]);

    $this->artisan('cuti:expire-drafts')
        ->assertSuccessful();

    $recentDraft->refresh();
    expect($recentDraft->state->name())->toBe('DRAFT');
    expect($recentDraft->cancelled_at)->toBeNull();
});

test('expire does not touch non draft states', function () {
    $pegawai = Pegawai::factory()->create();

    // Pengajuan lama tapi sudah DIAJUKAN
    $submitted = CutiPengajuan::factory()->submitted()->create([
        'pegawai_nip' => $pegawai->nip,
        'created_at' => now()->subDays(10),
    ]);

    $this->artisan('cuti:expire-drafts')
        ->assertSuccessful();

    $submitted->refresh();
    expect($submitted->state->name())->toBe('DIAJUKAN');
});

test('activity log records state changes on cuti pengajuan', function () {
    $pegawai = Pegawai::factory()->create();

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'state' => 'DRAFT',
    ]);

    // Ubah state
    $pengajuan->state->transitionTo(DibatalkanState::class);
    $pengajuan->cancelled_at = now();
    $pengajuan->save();

    $activities = Activity::query()
        ->where('subject_type', CutiPengajuan::class)
        ->where('subject_id', $pengajuan->id)
        ->get();

    expect($activities)->not->toBeEmpty();
});
