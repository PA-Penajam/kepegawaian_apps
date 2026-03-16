<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function createPegawaiWithAktifPangkat(string $nextKgbDate, array $pegawaiOverrides = []): Pegawai
{
    $pegawai = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $pegawaiOverrides));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'tmt' => Carbon::parse($nextKgbDate)->subYears(2)->toDateString(),
        'is_aktif' => true,
    ]);

    return $pegawai;
}

test('service menghitung tanggal kgb berikutnya dari tmt aktif', function () {
    Carbon::setTestNow('2026-01-01');

    $pegawai = createPegawaiWithAktifPangkat('2026-04-01');
    $service = app(KgbMonitoringService::class);

    $status = $service->getKgbStatus($pegawai);

    expect($status['tanggal_kgb_berikutnya']->toDateString())->toBe('2026-04-01')
        ->and($status['sisa_hari'])->toBe(90)
        ->and($status['status'])->toBe('Mendekati');

    Carbon::setTestNow();
});

test('service melewati pegawai tanpa riwayat pangkat aktif dan status pegawai yang dikecualikan', function () {
    Carbon::setTestNow('2026-01-01');

    $service = app(KgbMonitoringService::class);

    createPegawaiWithAktifPangkat('2025-12-22', [
        'nama_lengkap' => 'Jatuh Tempo',
    ]);
    createPegawaiWithAktifPangkat('2026-01-31', [
        'nama_lengkap' => 'Segera',
    ]);
    createPegawaiWithAktifPangkat('2026-03-22', [
        'nama_lengkap' => 'Mendekati',
    ]);
    createPegawaiWithAktifPangkat('2026-05-01', [
        'nama_lengkap' => 'Aman',
    ]);

    $pegawaiTanpaAktif = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiTanpaAktif->id,
        'is_aktif' => false,
    ]);

    createPegawaiWithAktifPangkat('2026-01-31', [
        'status_pegawai' => StatusPegawai::Pensiun->value,
        'nama_lengkap' => 'Pegawai Pensiun',
    ]);

    $upcoming = $service->getUpcomingKgb();

    expect($upcoming)->toHaveCount(3)
        ->and($upcoming->pluck('nama_lengkap')->all())
        ->toBe(['Jatuh Tempo', 'Segera', 'Mendekati'])
        ->and($upcoming->pluck('kgb.status', 'nama_lengkap')->all())
        ->toBe([
            'Jatuh Tempo' => 'Sudah Jatuh Tempo',
            'Segera' => 'Segera',
            'Mendekati' => 'Mendekati',
        ]);

    $statusAman = $service->getKgbStatus(Pegawai::query()->where('nama_lengkap', 'Aman')->firstOrFail());

    expect($statusAman['sisa_hari'])->toBe(120)
        ->and($statusAman['status'])->toBe('Aman');

    Carbon::setTestNow();
});

test('controller index menampilkan inertia monitoring kgb', function () {
    Carbon::setTestNow('2026-01-01');

    $user = Pegawai::factory()->operator()->create();
    createPegawaiWithAktifPangkat('2026-01-31', [
        'nama_lengkap' => 'Operator Monitor',
    ]);

    actingAs($user);

    get(route('monitoring.kgb.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->has('pegawaiList', 1)
            ->where('pegawaiList.0.nama_lengkap', 'Operator Monitor')
            ->where('pegawaiList.0.status', 'Segera')
            ->where('kgbStats.total', 1)
            ->where('kgbStats.jatuhTempo', 0)
            ->where('kgbStats.segera', 1)
            ->where('kgbStats.mendekati', 0)
            ->where('kgbStats.aman', 0),
        );

    Carbon::setTestNow();
});
