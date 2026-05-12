<?php

use App\Models\Pegawai;
use App\Services\Cuti\WorkflowService;

test('reassignApprover menolak role yang tidak valid', function () {
    $aktor = Pegawai::factory()->admin()->create();

    $service = app(WorkflowService::class);

    expect(fn () => $service->reassignApprover(
        'fake-id',
        'role_tidak_valid',
        '123456789',
        $aktor,
        'Test reassign'
    ))->toThrow(\InvalidArgumentException::class, 'Role tidak valid untuk reassignment: role_tidak_valid');
});

test('reassignApprover menolak role petugas_kepegawaian', function () {
    $aktor = Pegawai::factory()->admin()->create();

    $service = app(WorkflowService::class);

    expect(fn () => $service->reassignApprover(
        'fake-id',
        'petugas_kepegawaian',
        '123456789',
        $aktor,
        'Test reassign'
    ))->toThrow(\InvalidArgumentException::class, 'Role tidak valid untuk reassignment: petugas_kepegawaian');
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
    ))->toThrow(\InvalidArgumentException::class, 'Role tidak valid untuk reassignment:');
});
