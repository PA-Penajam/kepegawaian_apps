<?php

use App\Enums\StatusSuratDinas;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanPdf;
use App\Models\Pegawai;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\FakePenomoranGateway;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

test('endpoint unduh surat izin final menegakkan policy akses, menolak 404 sebelum final, dan men-stream PDF dari gateway', function () {
    $gateway = new FakePenomoranGateway;
    $this->app->instance(PenomoranSuratGateway::class, $gateway);

    $pemilik = Pegawai::factory()->admin()->create();
    $orangLain = Pegawai::factory()->create();

    $pengajuan = CutiPengajuan::factory()->approved()->create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    // Sebelum ada SuratDinasRequest -> 404 bagi pemilik
    $this->actingAs($pemilik)
        ->get(route('cuti.pengajuan.surat-izin.final', $pengajuan->id))
        ->assertNotFound();

    // Status masih MenungguNomor -> 404
    $surat = SuratDinasRequest::factory()->menungguNomor()->create([
        'suratable_type' => $pengajuan->getMorphClass(),
        'suratable_id' => $pengajuan->id,
    ]);
    $this->actingAs($pemilik)
        ->get(route('cuti.pengajuan.surat-izin.final', $pengajuan->id))
        ->assertNotFound();

    // Ubah ke FinalTersedia -> orang lain tanpa hak ditolak 403, pemilik mendapat stream PDF
    $surat->update([
        'status' => StatusSuratDinas::FinalTersedia,
        'nomor_surat' => '21/KPA.W17-A8/KP5.3/XI/2026',
    ]);

    $this->actingAs($orangLain)
        ->get(route('cuti.pengajuan.surat-izin.final', $pengajuan->id))
        ->assertForbidden();

    $response = $this->actingAs($pemilik)
        ->get(route('cuti.pengajuan.surat-izin.final', $pengajuan->id));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('endpoint unduh draf bernomor mengembalikan PDF lokal jika sudah digenerate', function () {
    Storage::fake('local');

    $pemilik = Pegawai::factory()->admin()->create();
    $pengajuan = CutiPengajuan::factory()->approved()->create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $this->actingAs($pemilik)
        ->get(route('cuti.pengajuan.surat-izin.draft', $pengajuan->id))
        ->assertNotFound();

    $path = "cuti/surat-izin/{$pengajuan->id}.pdf";
    Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF\n");
    CutiPengajuanPdf::create([
        'pengajuan_id' => $pengajuan->id,
        'path_file' => $path,
        'checksum_sha256' => hash('sha256', "%PDF-1.4\n%%EOF\n"),
        'size_bytes' => 16,
        'generated_at' => now(),
    ]);

    $this->actingAs($pemilik)
        ->get(route('cuti.pengajuan.surat-izin.draft', $pengajuan->id))
        ->assertOk();
});
