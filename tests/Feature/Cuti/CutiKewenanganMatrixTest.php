<?php

use App\Enums\JenisJabatan;
use App\Models\Cuti\CutiKewenangan;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Services\Cuti\KewenanganResolverService;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

test('matriks kewenangan eksternal dimulai kosong dan mengembalikan null untuk kewenangan internal', function () {
    $jabatan = RefJabatan::factory()->create(['jenis_jabatan' => JenisJabatan::Fungsional]);
    $pegawai = Pegawai::factory()->create(['ref_jabatan_id' => $jabatan->id]);

    expect(CutiKewenangan::query()->count())->toBe(0)
        ->and(app(KewenanganResolverService::class)->resolve($pegawai, 'CB'))->toBeNull();
});

test('halaman admin matriks kewenangan dapat diakses admin dan ditolak untuk non-admin', function () {
    $admin = Pegawai::factory()->admin()->create();
    $biasa = Pegawai::factory()->create();

    $this->actingAs($biasa)->get(route('admin.cuti.kewenangan.index'))->assertForbidden();
    $this->actingAs($biasa)->post(route('admin.cuti.kewenangan.store'), [
        'jenis_cuti_kode' => 'CB',
        'jenis_jabatan' => JenisJabatan::Fungsional->value,
        'pemberi_izin' => 'pta',
        'instansi_nama' => 'Pengadilan Tinggi Agama Samarinda',
    ])->assertForbidden();

    $this->actingAs($admin)->get(route('admin.cuti.kewenangan.index'))->assertOk();
    $this->actingAs($admin)->post(route('admin.cuti.kewenangan.store'), [
        'jenis_cuti_kode' => 'CB',
        'jenis_jabatan' => JenisJabatan::Fungsional->value,
        'pemberi_izin' => 'pta',
        'instansi_nama' => 'Pengadilan Tinggi Agama Samarinda',
        'nama_aplikasi' => 'SIKEP PTA',
        'url_aplikasi' => 'https://pta-samarinda.example.test',
        'aktif' => true,
    ])->assertRedirect();

    $rule = CutiKewenangan::query()->first();
    expect($rule)->not->toBeNull()
        ->and($rule->pemberi_izin)->toBe('pta')
        ->and($rule->aktif)->toBeTrue();

    $this->actingAs($admin)->patch(route('admin.cuti.kewenangan.update', $rule->id), [
        'jenis_cuti_kode' => 'CB',
        'jenis_jabatan' => JenisJabatan::Fungsional->value,
        'pemberi_izin' => 'ma',
        'instansi_nama' => 'Mahkamah Agung RI',
        'aktif' => false,
    ])->assertRedirect();

    expect($rule->fresh()->pemberi_izin)->toBe('ma')
        ->and($rule->fresh()->aktif)->toBeFalse();

    $this->actingAs($admin)->delete(route('admin.cuti.kewenangan.destroy', $rule->id))
        ->assertRedirect();

    expect(CutiKewenangan::query()->count())->toBe(0);
});

test('KewenanganResolverService hanya mencocokkan aturan aktif sesuai jenis cuti dan jenis jabatan', function () {
    $jabFungsional = RefJabatan::factory()->create(['jenis_jabatan' => JenisJabatan::Fungsional]);
    $jabPelaksana = RefJabatan::factory()->create(['jenis_jabatan' => JenisJabatan::Pelaksana]);

    $hakim = Pegawai::factory()->create(['ref_jabatan_id' => $jabFungsional->id]);
    $staff = Pegawai::factory()->create(['ref_jabatan_id' => $jabPelaksana->id]);

    $aktifRule = CutiKewenangan::create([
        'jenis_cuti_kode' => 'CB',
        'jenis_jabatan' => JenisJabatan::Fungsional->value,
        'pemberi_izin' => 'pta',
        'instansi_nama' => 'Pengadilan Tinggi Agama Samarinda',
        'aktif' => true,
    ]);

    CutiKewenangan::create([
        'jenis_cuti_kode' => 'CLTN',
        'jenis_jabatan' => JenisJabatan::Fungsional->value,
        'pemberi_izin' => 'ma',
        'instansi_nama' => 'Mahkamah Agung RI',
        'aktif' => false,
    ]);

    $resolver = app(KewenanganResolverService::class);

    expect($resolver->resolve($hakim, 'CB')?->id)->toBe($aktifRule->id)
        ->and($resolver->resolve($staff, 'CB'))->toBeNull()
        ->and($resolver->resolve($hakim, 'CLTN'))->toBeNull();
});
