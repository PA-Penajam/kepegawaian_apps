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
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

test('show menghasilkan PDF untuk pengguna yang berhak', function () {
    Pdf::fake();

    $pegawai = Pegawai::factory()->create();

    // Tambahkan permission cuti.pengajuan.view-own
    grantCutiViewOwnPermission($pegawai);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $response = $this->actingAs($pegawai)
        ->get(route('cuti.pengajuan.pdf', $pengajuan->id));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        return $pdf->viewName === 'pdf.cuti.pengajuan';
    });
});

test('show menolak pengguna yang tidak berhak', function () {
    Pdf::fake();

    $pemilik = Pegawai::factory()->create();
    $orangLain = Pegawai::factory()->create();

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $response = $this->actingAs($orangLain)
        ->get(route('cuti.pengajuan.pdf', $pengajuan->id));

    $response->assertForbidden();
});

test('show mengizinkan viewTeam dan viewAll serta mensanitasi karakter slash pada filename PDF', function () {
    Pdf::fake();

    $pemilik = Pegawai::factory()->create();
    $atasan = Pegawai::factory()->create();
    $admin = Pegawai::factory()->admin()->create();

    $app = IamApplication::where('slug', 'kepegawaian')->first();
    if ($app) {
        $role = IamRole::firstOrCreate(
            ['iam_application_id' => $app->id, 'slug' => 'cuti-atasan-pdf'],
            ['nama' => 'Cuti Atasan PDF']
        );
        $permission = IamPermission::firstOrCreate(
            ['iam_application_id' => $app->id, 'slug' => 'cuti.pengajuan.view-team'],
            ['nama' => 'Lihat Pengajuan Tim']
        );
        IamRolePermission::firstOrCreate([
            'iam_role_id' => $role->id,
            'iam_permission_id' => $permission->id,
        ]);
        IamUserRole::firstOrCreate(
            ['user_id' => $atasan->id, 'iam_role_id' => $role->id],
            ['assigned_at' => now()]
        );
    }

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemilik->nip,
        'atasan_langsung_current_nip' => $atasan->nip,
        'nomor_pengajuan' => 'CUTI/2026/00000001/0001',
        'jenis_cuti_kode' => 'CT',
    ]);

    $this->actingAs($atasan)
        ->get(route('cuti.pengajuan.pdf', $pengajuan->id))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('cuti.pengajuan.pdf', $pengajuan->id))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
        return str_contains($pdf->downloadName, 'CUTI-2026-00000001-0001')
            && ! str_contains($pdf->downloadName, '/');
    });
});

/**
 * Memberikan permission cuti.pengajuan.view-own kepada pegawai.
 */
function grantCutiViewOwnPermission(Pegawai $pegawai): void
{
    $app = IamApplication::where('slug', 'kepegawaian')->first();
    if (! $app) {
        return;
    }

    // Buat role khusus cuti jika belum ada
    $role = IamRole::firstOrCreate(
        ['iam_application_id' => $app->id, 'slug' => 'cuti-pegawai'],
        ['nama' => 'Cuti Pegawai']
    );

    // Buat permission jika belum ada
    $permission = IamPermission::firstOrCreate(
        ['iam_application_id' => $app->id, 'slug' => 'cuti.pengajuan.view-own'],
        ['nama' => 'Lihat Pengajuan Cuti Sendiri']
    );

    // Hubungkan role ke permission
    IamRolePermission::firstOrCreate([
        'iam_role_id' => $role->id,
        'iam_permission_id' => $permission->id,
    ]);

    // Assign role ke pegawai
    IamUserRole::firstOrCreate(
        ['user_id' => $pegawai->id, 'iam_role_id' => $role->id],
        ['assigned_at' => now()]
    );
}
