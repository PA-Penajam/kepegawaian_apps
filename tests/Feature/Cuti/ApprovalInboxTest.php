<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
    $this->withoutVite();
});

/**
 * Helper lokal: buat pegawai dengan permission spesifik.
 */
function buatApproverInbox(array $permissionSlugs): Pegawai
{
    $pegawai = Pegawai::factory()->create();
    $app = IamApplication::where('slug', 'kepegawaian')->first();

    $role = IamRole::create([
        'iam_application_id' => $app->id,
        'slug' => 'test-role-'.$pegawai->nip,
        'nama' => 'Test Role '.$pegawai->nip,
    ]);

    $permissionIds = IamPermission::where('iam_application_id', $app->id)
        ->whereIn('slug', $permissionSlugs)
        ->pluck('id');

    foreach ($permissionIds as $permId) {
        IamRolePermission::create([
            'iam_role_id' => $role->id,
            'iam_permission_id' => $permId,
        ]);
    }

    IamUserRole::create([
        'user_id' => $pegawai->id,
        'iam_role_id' => $role->id,
        'assigned_at' => now(),
    ]);

    return $pegawai;
}

test('inbox menampilkan pengajuan sesuai state dan peran user', function () {
    $petugas = buatApproverInbox(['cuti.pengajuan.verify']);
    $pemilik = Pegawai::factory()->create();

    $menungguVerifikasi = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DIAJUKAN',
        'submitted_at' => now(),
        'petugas_kepegawaian_current_nip' => $petugas->nip,
    ]);

    // Pengajuan di state lain tidak boleh masuk inbox petugas
    CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DIVERIFIKASI',
        'submitted_at' => now(),
        'petugas_kepegawaian_current_nip' => $petugas->nip,
    ]);

    $this->actingAs($petugas)
        ->get(route('cuti.inbox'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/approval/inbox')
            ->has('pengajuanList.data', 1)
            ->where('pengajuanList.data.0.id', $menungguVerifikasi->id)
        );
});

test('inbox user dengan dua permission melihat gabungan kedua antrian', function () {
    // User merangkap petugas kepegawaian dan atasan langsung
    $user = buatApproverInbox([
        'cuti.pengajuan.verify',
        'cuti.pengajuan.approve-langsung',
    ]);
    $pemilik = Pegawai::factory()->create();

    // submitted_at dibedakan agar urutan (latest) deterministik
    $dariAntrianVerifikasi = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DIAJUKAN',
        'submitted_at' => now()->subDay(),
        'petugas_kepegawaian_current_nip' => $user->nip,
    ]);

    $dariAntrianAtasan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DIVERIFIKASI',
        'submitted_at' => now(),
        'atasan_langsung_current_nip' => $user->nip,
    ]);

    $this->actingAs($user)
        ->get(route('cuti.inbox'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/approval/inbox')
            ->has('pengajuanList.data', 2)
            ->where('pengajuanList.data.0.id', $dariAntrianAtasan->id)
            ->where('pengajuanList.data.1.id', $dariAntrianVerifikasi->id)
        );
});

test('inbox user tanpa permission approval menampilkan daftar kosong', function () {
    $user = Pegawai::factory()->create();

    $this->actingAs($user)
        ->get(route('cuti.inbox'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/approval/inbox')
            ->has('pengajuanList.data', 0)
        );
});
