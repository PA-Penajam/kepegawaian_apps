<?php

use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Services\Cuti\Rules\CutiBesarRule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-06-01');
    CutiJenisMaster::firstOrCreate(
        ['kode' => 'CB'],
        ['nama' => 'Cuti Besar', 'saldo_driven' => false, 'aktif' => true]
    );
});

test('CutiBesarRule menerima pegawai dengan TMT CPNS CarbonImmutable >= 5 tahun', function () {
    $pegawai = Pegawai::factory()->create([
        'tmt_cpns' => CarbonImmutable::parse('2021-06-01'),
    ]);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CB',
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-09-10',
        'jumlah_hari_kerja' => 90,
    ]);

    expect($pegawai->tmt_cpns)->toBeInstanceOf(CarbonImmutable::class);

    app(CutiBesarRule::class)->validate($pengajuan);

    expect(true)->toBeTrue();
});

test('CutiBesarRule menolak pegawai dengan masa kerja kurang dari 5 tahun', function () {
    $pegawai = Pegawai::factory()->create([
        'tmt_cpns' => CarbonImmutable::parse('2021-07-01'),
    ]);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CB',
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-09-10',
        'jumlah_hari_kerja' => 90,
    ]);

    app(CutiBesarRule::class)->validate($pengajuan);
})->throws(DomainException::class, 'Memerlukan minimal 5 tahun masa kerja');

test('CutiBesarRule menolak pegawai tanpa TMT CPNS', function () {
    $pegawai = Pegawai::factory()->create([
        'tmt_cpns' => null,
    ]);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CB',
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-09-10',
        'jumlah_hari_kerja' => 90,
    ]);

    app(CutiBesarRule::class)->validate($pengajuan);
})->throws(DomainException::class, 'Data TMT CPNS pegawai tidak ditemukan');

test('CutiBesarRule menolak TMT CPNS di masa depan', function () {
    $pegawai = Pegawai::factory()->create([
        'tmt_cpns' => CarbonImmutable::parse('2035-01-01'),
    ]);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CB',
        'tanggal_mulai' => '2026-06-10',
        'tanggal_selesai' => '2026-09-10',
        'jumlah_hari_kerja' => 90,
    ]);

    app(CutiBesarRule::class)->validate($pengajuan);
})->throws(DomainException::class, 'masa depan');
