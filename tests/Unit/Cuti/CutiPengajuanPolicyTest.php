<?php

use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Policies\Cuti\CutiPengajuanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Helper: memberikan permission ke pegawai melalui IAM role.
 */
function grantCutiPolicyPermission(Pegawai $pegawai, string $slug): void
{
    $app = IamApplication::firstOrCreate(
        ['slug' => 'test-cuti-policy'],
        ['nama' => 'Test Cuti Policy App', 'url' => 'https://test.local']
    );

    $role = IamRole::firstOrCreate(
        ['slug' => 'test-cuti-role', 'iam_application_id' => $app->id],
        ['nama' => 'Test Cuti Role']
    );

    $perm = IamPermission::firstOrCreate(
        ['slug' => $slug, 'iam_application_id' => $app->id],
        ['nama' => $slug]
    );

    $role->permissions()->syncWithoutDetaching([$perm->id]);
    $pegawai->iamRoles()->syncWithoutDetaching([$role->id]);
}

beforeEach(function () {
    // Buat jenis cuti sebagai dependency FK
    CutiJenisMaster::firstOrCreate(
        ['kode' => 'CT'],
        ['nama' => 'Cuti Tahunan', 'saldo_driven' => true, 'aktif' => true]
    );

    $this->policy = new CutiPengajuanPolicy;
});

test('viewOwn mengizinkan pegawai melihat pengajuan milik sendiri', function () {
    $pegawai = Pegawai::factory()->create();
    grantCutiPolicyPermission($pegawai, 'cuti.pengajuan.view-own');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    expect($this->policy->viewOwn($pegawai, $pengajuan))->toBeTrue();
});

test('viewOwn menolak pegawai melihat pengajuan orang lain', function () {
    $pegawai = Pegawai::factory()->create();
    $other = Pegawai::factory()->create();
    grantCutiPolicyPermission($pegawai, 'cuti.pengajuan.view-own');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $other->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    expect($this->policy->viewOwn($pegawai, $pengajuan))->toBeFalse();
});

test('viewTeam mengizinkan atasan langsung melihat pengajuan tim', function () {
    $atasan = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    grantCutiPolicyPermission($atasan, 'cuti.pengajuan.view-team');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'atasan_langsung_current_nip' => $atasan->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    expect($this->policy->viewTeam($atasan, $pengajuan))->toBeTrue();
});

test('viewAll mengizinkan admin melihat semua pengajuan', function () {
    $admin = Pegawai::factory()->create();
    grantCutiPolicyPermission($admin, 'cuti.pengajuan.view-all');

    expect($this->policy->viewAll($admin))->toBeTrue();
});

test('verify mengizinkan petugas kepegawaian memverifikasi', function () {
    $petugas = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    grantCutiPolicyPermission($petugas, 'cuti.pengajuan.verify');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    expect($this->policy->verify($petugas, $pengajuan))->toBeTrue();
});

test('approveLangsung mengizinkan jika NIP cocok dan punya permission', function () {
    $atasan = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    grantCutiPolicyPermission($atasan, 'cuti.pengajuan.approve-langsung');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'atasan_langsung_current_nip' => $atasan->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    expect($this->policy->approveLangsung($atasan, $pengajuan))->toBeTrue();
});

test('approveLangsung menolak jika NIP tidak cocok', function () {
    $atasan = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    $otherAtasan = Pegawai::factory()->create();
    grantCutiPolicyPermission($atasan, 'cuti.pengajuan.approve-langsung');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'atasan_langsung_current_nip' => $otherAtasan->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    expect($this->policy->approveLangsung($atasan, $pengajuan))->toBeFalse();
});

test('approvePejabat mengizinkan pejabat berwenang', function () {
    $pejabat = Pegawai::factory()->create();
    grantCutiPolicyPermission($pejabat, 'cuti.pengajuan.approve-pejabat');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => Pegawai::factory()->create()->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    expect($this->policy->approvePejabat($pejabat, $pengajuan))->toBeTrue();
});

test('cancelOwn mengizinkan pegawai membatalkan pengajuan sendiri', function () {
    $pegawai = Pegawai::factory()->create();
    grantCutiPolicyPermission($pegawai, 'cuti.pengajuan.cancel-own');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    expect($this->policy->cancelOwn($pegawai, $pengajuan))->toBeTrue();
});

test('cancelAny mengizinkan admin membatalkan pengajuan apapun', function () {
    $admin = Pegawai::factory()->create();
    grantCutiPolicyPermission($admin, 'cuti.pengajuan.cancel-any');

    expect($this->policy->cancelAny($admin))->toBeTrue();
});

test('reassign mengizinkan admin melakukan reassign approver', function () {
    $admin = Pegawai::factory()->create();
    grantCutiPolicyPermission($admin, 'cuti.pengajuan.reassign');

    expect($this->policy->reassign($admin))->toBeTrue();
});
