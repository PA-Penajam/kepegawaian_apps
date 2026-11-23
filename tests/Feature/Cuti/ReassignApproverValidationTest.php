<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Services\Cuti\WorkflowService;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;

test('reassignApprover menolak role yang tidak valid', function () {
    $aktor = Pegawai::factory()->admin()->create();

    $service = app(WorkflowService::class);

    expect(fn () => $service->reassignApprover(
        'fake-id',
        'role_tidak_valid',
        '123456789',
        $aktor,
        'Test reassign'
    ))->toThrow(InvalidArgumentException::class, 'Role tidak valid untuk reassignment: role_tidak_valid');
});

test('reassignApprover mengizinkan role petugas_kepegawaian', function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);

    $aktor = Pegawai::factory()->admin()->create();
    $pemohon = Pegawai::factory()->create();
    $petugasLama = Pegawai::factory()->create();
    $petugasBaru = Pegawai::factory()->create();

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DIAJUKAN',
        'petugas_kepegawaian_current_nip' => $petugasLama->nip,
    ]);

    app(WorkflowService::class)->reassignApprover(
        $pengajuan->id,
        'petugas_kepegawaian',
        $petugasBaru->nip,
        $aktor,
        'Rotasi petugas kepegawaian'
    );

    expect($pengajuan->fresh()->petugas_kepegawaian_current_nip)->toBe($petugasBaru->nip);

    $this->assertDatabaseHas('cuti_pengajuan_approver_history', [
        'pengajuan_id' => $pengajuan->id,
        'role' => 'petugas_kepegawaian',
        'from_pegawai_nip' => $petugasLama->nip,
        'to_pegawai_nip' => $petugasBaru->nip,
    ]);
});

test('reassignApprover menolak role kosong', function () {
    $aktor = Pegawai::factory()->admin()->create();

    $service = app(WorkflowService::class);

    expect(fn () => $service->reassignApprover(
        'fake-id',
        '',
        '123456789',
        $aktor,
        'Test reassign'
    ))->toThrow(InvalidArgumentException::class, 'Role tidak valid untuk reassignment:');
});
