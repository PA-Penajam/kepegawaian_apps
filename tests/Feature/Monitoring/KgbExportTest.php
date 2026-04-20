<?php

use App\Enums\StatusPegawai;
use App\Exports\KgbMonitoringExport;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function () {
    Carbon::setTestNow();
});

function createKgbPegawai(string $nextKgbDate, array $overrides = []): Pegawai
{
    $pegawai = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $overrides));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => Carbon::parse($nextKgbDate)->subYears(2)->toDateString(),
        'is_aktif' => true,
    ]);

    return $pegawai;
}

test('KgbMonitoringExport bisa di-download sebagai xlsx', function () {
    Excel::fake();

    $user = Pegawai::factory()->admin()->create();
    actingAs($user);

    createKgbPegawai('2026-05-01');

    $export = new KgbMonitoringExport;

    Excel::download($export, 'kgb-monitoring.xlsx');

    Excel::assertDownloaded('kgb-monitoring.xlsx');
});

test('KgbMonitoringExport memiliki heading yang benar', function () {
    $export = new KgbMonitoringExport;

    expect($export->headings())->toBe([
        'NIP',
        'Nama Lengkap',
        'Pangkat/Golongan',
        'TMT Pangkat',
        'KGB Berikutnya',
        'Sisa Hari',
        'Status',
    ]);
});

test('endpoint export kgb mengembalikan file xlsx', function () {
    Excel::fake();

    $user = Pegawai::factory()->admin()->create();
    actingAs($user);

    createKgbPegawai('2026-05-01');

    $this->get(route('monitoring.kgb.export'))
        ->assertStatus(200);

    Excel::assertDownloaded('kgb-monitoring.xlsx');
});

test('endpoint export kgb dengan filter golongan', function () {
    Excel::fake();

    $user = Pegawai::factory()->admin()->create();
    actingAs($user);

    createKgbPegawai('2026-05-01');

    $this->get(route('monitoring.kgb.export', ['golongan' => 'III']))
        ->assertStatus(200);

    Excel::assertDownloaded('kgb-monitoring.xlsx');
});

test('endpoint export kgb tetap bisa di-download walau data kosong', function () {
    Excel::fake();

    $user = Pegawai::factory()->admin()->create();
    actingAs($user);

    $this->get(route('monitoring.kgb.export'))
        ->assertSuccessful();

    Excel::assertDownloaded('kgb-monitoring.xlsx');
});
