<?php

use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanLampiran;
use App\Models\Pegawai;
use App\Services\Cuti\Rules\CutiLtnRule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-06-01');
    CutiJenisMaster::firstOrCreate(
        ['kode' => 'CLTN'],
        ['nama' => 'Cuti di Luar Tanggungan Negara', 'saldo_driven' => false, 'butuh_lampiran' => true, 'aktif' => true]
    );
});

test('CutiLtnRule menerima pegawai dengan TMT CPNS >= 5 tahun dan lampiran', function () {
    $pegawai = Pegawai::factory()->create([
        'tmt_cpns' => CarbonImmutable::parse('2020-01-01'),
    ]);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CLTN',
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-07-10',
        'jumlah_hari_kerja' => 20,
    ]);

    CutiPengajuanLampiran::create([
        'pengajuan_id' => $pengajuan->id,
        'jenis_lampiran' => 'pendukung',
        'nama_file_asli' => 'surat.pdf',
        'path_file' => 'cuti/lampiran/surat.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'checksum_sha256' => hash('sha256', 'dummy'),
        'uploaded_by_nip' => $pegawai->nip,
    ]);

    app(CutiLtnRule::class)->validate($pengajuan);

    expect(true)->toBeTrue();
});

test('CutiLtnRule menolak masa kerja < 5 tahun (4 tahun 11 bulan)', function () {
    $pegawai = Pegawai::factory()->create([
        'tmt_cpns' => CarbonImmutable::parse('2021-07-15'),
    ]);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CLTN',
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-07-10',
        'jumlah_hari_kerja' => 20,
    ]);

    app(CutiLtnRule::class)->validate($pengajuan);
})->throws(DomainException::class, 'Memerlukan minimal 5 tahun masa kerja');

test('CutiLtnRule menolak jika tanpa lampiran', function () {
    $pegawai = Pegawai::factory()->create([
        'tmt_cpns' => CarbonImmutable::parse('2019-01-01'),
    ]);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CLTN',
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-07-10',
        'jumlah_hari_kerja' => 20,
    ]);

    app(CutiLtnRule::class)->validate($pengajuan);
})->throws(DomainException::class, 'lampiran pendukung');

test('CutiLtnRule menolak TMT CPNS di masa depan', function () {
    $pegawai = Pegawai::factory()->create([
        'tmt_cpns' => CarbonImmutable::parse('2032-01-01'),
    ]);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CLTN',
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-07-10',
        'jumlah_hari_kerja' => 20,
    ]);

    app(CutiLtnRule::class)->validate($pengajuan);
})->throws(DomainException::class, 'masa depan');
