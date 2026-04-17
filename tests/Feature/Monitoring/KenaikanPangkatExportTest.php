<?php

use App\Enums\StatusPegawai;
use App\Exports\KenaikanPangkatMonitoringExport;
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

function createKpPegawai(string $tmtPangkat, array $overrides = []): Pegawai
{
    $pegawai = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $overrides));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => $tmtPangkat,
        'is_aktif' => true,
    ]);

    return $pegawai;
}

test('KenaikanPangkatMonitoringExport bisa di-download sebagai xlsx', function () {
    Excel::fake();

    $user = \App\Models\Pegawai::factory()->admin()->create();
    actingAs($user);

    createKpPegawai('2022-04-01');

    $export = new KenaikanPangkatMonitoringExport();

    Excel::download($export, 'kp-monitoring.xlsx');

    Excel::assertDownloaded('kp-monitoring.xlsx');
});

test('KenaikanPangkatMonitoringExport memiliki heading yang benar', function () {
    $export = new KenaikanPangkatMonitoringExport();

    expect($export->headings())->toBe([
        'NIP',
        'Nama Lengkap',
        'Pangkat Saat Ini',
        'TMT Pangkat',
        'TMT KP Berikutnya',
        'Periode Usul',
        'Batas Usul',
        'Sisa Hari Usul',
        'Status',
    ]);
});

test('endpoint export kp mengembalikan file xlsx', function () {
    Excel::fake();

    $user = \App\Models\Pegawai::factory()->admin()->create();
    actingAs($user);

    createKpPegawai('2022-04-01');

    $response = $this->get('/kepegawaian/monitoring/kenaikan-pangkat/export');

    $response->assertStatus(200);
    Excel::assertDownloaded('kp-monitoring.xlsx');
});