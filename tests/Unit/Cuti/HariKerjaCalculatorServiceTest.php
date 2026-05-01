<?php

use App\Models\Cuti\CutiLiburMaster;
use App\Services\Cuti\HariKerjaCalculatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('hitung 5 hari kerja Senin sampai Jumat tanpa libur', function () {
    $svc = app(HariKerjaCalculatorService::class);
    // 2026-06-01 adalah Senin, 2026-06-05 adalah Jumat
    $hasil = $svc->hitung(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-05'));
    expect($hasil)->toBe(5);
});

test('skip weekend dalam rentang cross-week', function () {
    $svc = app(HariKerjaCalculatorService::class);
    // 2026-06-01 (Senin) sampai 2026-06-08 (Senin): 6 hari kerja
    $hasil = $svc->hitung(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-08'));
    expect($hasil)->toBe(6);
});

test('skip libur nasional dari cuti_libur_master', function () {
    // Masukkan data libur nasional
    CutiLiburMaster::create([
        'tanggal' => '2026-06-03',
        'keterangan' => 'Libur nasional test',
        'is_cuti_bersama' => false,
        'tahun' => 2026,
    ]);

    $svc = app(HariKerjaCalculatorService::class);
    $hasil = $svc->hitung(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-05'));
    // 5 hari kerja - 1 libur = 4
    expect($hasil)->toBe(4);
});

test('satu hari kerja (tanggal sama)', function () {
    $svc = app(HariKerjaCalculatorService::class);
    expect($svc->hitung(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-01')))->toBe(1);
});

test('mulai setelah selesai return 0', function () {
    $svc = app(HariKerjaCalculatorService::class);
    expect($svc->hitung(Carbon::parse('2026-06-05'), Carbon::parse('2026-06-01')))->toBe(0);
});

test('tanggal mulai di weekend return 0 hari untuk hari itu', function () {
    $svc = app(HariKerjaCalculatorService::class);
    // 2026-06-06 adalah Sabtu, 2026-06-07 adalah Minggu
    expect($svc->hitung(Carbon::parse('2026-06-06'), Carbon::parse('2026-06-07')))->toBe(0);
});
