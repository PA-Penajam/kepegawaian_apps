<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);

    // Berikan semua permission cuti ke admin role
    $app = IamApplication::where('slug', 'kepegawaian')->first();
    $adminRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'admin')->first();
    $cutiPermissions = IamPermission::where('iam_application_id', $app->id)
        ->where('group', 'cuti')
        ->pluck('id');
    $adminRole->permissions()->syncWithoutDetaching($cutiPermissions);
});

function buatPengajuanUntukApproval(string $state, Pegawai $approver, ?Pegawai $pemohon = null): CutiPengajuan
{
    $pemohon ??= Pegawai::factory()->create();

    return CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => $state,
        'submitted_at' => now(),
        'petugas_kepegawaian_current_nip' => $approver->nip,
        'atasan_langsung_current_nip' => $approver->nip,
        'pejabat_berwenang_current_nip' => $approver->nip,
    ]);
}

test('verify transitions state to DIVERIFIKASI', function () {
    $petugas = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanUntukApproval('DIAJUKAN', $petugas);

    actingAs($petugas);

    $response = post(route('cuti.pengajuan.verify', $pengajuan->id), [
        'catatan' => 'Verifikasi OK',
    ]);

    $response->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    expect($pengajuan->fresh()->state->name())->toBe('DIVERIFIKASI');

    $this->assertDatabaseHas('cuti_pengajuan_approval_steps', [
        'pengajuan_id' => $pengajuan->id,
        'role' => 'petugas_kepegawaian',
        'action' => 'verify',
    ]);
});

test('reject requires alasan with minimum length', function () {
    $petugas = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanUntukApproval('DIAJUKAN', $petugas);

    actingAs($petugas);

    // Tanpa alasan
    $response = post(route('cuti.pengajuan.reject', $pengajuan->id), []);
    $response->assertSessionHasErrors('alasan');

    // Alasan terlalu pendek
    $response = post(route('cuti.pengajuan.reject', $pengajuan->id), [
        'alasan' => 'pendek',
    ]);
    $response->assertSessionHasErrors('alasan');
});

test('reject with valid alasan transitions state', function () {
    $petugas = Pegawai::factory()->admin()->create();
    // State DISETUJUI_ATASAN karena admin punya approve-pejabat → role pejabat_berwenang
    $pengajuan = buatPengajuanUntukApproval('DISETUJUI_ATASAN', $petugas);

    actingAs($petugas);

    $response = post(route('cuti.pengajuan.reject', $pengajuan->id), [
        'alasan' => 'Alasan penolakan yang cukup panjang untuk memenuhi validasi minimum karakter',
    ]);

    $response->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    $fresh = $pengajuan->fresh();
    expect($fresh->state->name())->toContain('DITOLAK');
});

test('cancel draft transitions to DIBATALKAN', function () {
    $user = Pegawai::factory()->admin()->create();
    $pengajuan = buatPengajuanUntukApproval('DRAFT', $user, $user);

    actingAs($user);

    $response = post(route('cuti.pengajuan.cancel', $pengajuan->id));

    $response->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    expect($pengajuan->fresh()->state->name())->toBe('DIBATALKAN');
});

test('guests cannot access approval routes', function () {
    post(route('cuti.pengajuan.verify', 'fake-id'))
        ->assertRedirectContains('login');

    post(route('cuti.pengajuan.reject', 'fake-id'))
        ->assertRedirectContains('login');
});
