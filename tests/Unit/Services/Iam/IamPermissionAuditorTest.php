<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Services\Iam\IamPermissionAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->auditor = app(IamPermissionAuditor::class);
});

test('isValidSlug menerima slug canonical', function () {
    expect($this->auditor->isValidSlug('pegawai.view'))->toBeTrue();
    expect($this->auditor->isValidSlug('cuti.pengajuan.approve-langsung'))->toBeTrue();
});

test('isValidSlug menolak slug non-canonical', function () {
    expect($this->auditor->isValidSlug('iam-manage'))->toBeFalse();
    expect($this->auditor->isValidSlug('Pegawai.View'))->toBeFalse();
    expect($this->auditor->isValidSlug('pegawai_view'))->toBeFalse();
});

test('findNonCanonical mengembalikan hanya slug yang melanggar', function () {
    $app = IamApplication::factory()->create();
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'pegawai.view']);
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'cuti.create']);
    IamPermission::factory()->for($app, 'application')->legacy('iam-manage')->create();

    $result = $this->auditor->findNonCanonical();

    expect($result)->toHaveCount(1)
        ->and($result->first()['slug'])->toBe('iam-manage')
        ->and($result->first()['app'])->toBe($app->slug)
        ->and($result->first()['suggested'])->toBe('iam.manage');
});

test('findNonCanonical mengembalikan kosong jika semua canonical', function () {
    $app = IamApplication::factory()->create();
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'a.view']);
    IamPermission::factory()->for($app, 'application')->create(['slug' => 'b.create']);

    expect($this->auditor->findNonCanonical())->toBeEmpty();
});

test('suggestCanonical mengkonversi strip-tunggal trailing menjadi titik', function () {
    expect($this->auditor->suggestCanonical('iam-manage'))->toBe('iam.manage');
    expect($this->auditor->suggestCanonical('audit-view'))->toBe('audit.view');
});

test('suggestCanonical kembalikan null untuk underscore', function () {
    expect($this->auditor->suggestCanonical('barang_masuk'))->toBeNull();
});

test('suggestCanonical kembalikan null untuk slug yang sudah punya titik', function () {
    expect($this->auditor->suggestCanonical('pegawai.view'))->toBeNull();
    expect($this->auditor->suggestCanonical('cuti.pengajuan.approve-langsung'))->toBeNull();
});

test('violationReason memberikan alasan spesifik', function () {
    $app = IamApplication::factory()->create();
    IamPermission::factory()->for($app, 'application')->legacy('iam-manage')->create();
    IamPermission::factory()->for($app, 'application')->legacy('pegawai_view')->create();
    IamPermission::factory()->for($app, 'application')->legacy('Pegawai.View')->create();

    $result = $this->auditor->findNonCanonical();

    $reasons = $result->pluck('reason', 'slug');
    expect($reasons['iam-manage'])->toBe('Tidak ada titik pemisah');
    expect($reasons['pegawai_view'])->toBe('Tidak ada titik pemisah'); // tidak ada titik pemisah dulu
    expect($reasons['Pegawai.View'])->toBe('Mengandung uppercase');
});
