<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
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
        ->and($upcoming->pluck('status', 'nama_lengkap')->all())
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
            ->has('pegawaiList.data', 1)
            ->where('pegawaiList.data.0.nama_lengkap', 'Operator Monitor')
            ->where('pegawaiList.data.0.status', 'Segera')
            ->where('kgbStats.total', 1)
            ->where('kgbStats.jatuhTempo', 0)
            ->where('kgbStats.segera', 1)
            ->where('kgbStats.mendekati', 0)
            ->where('kgbStats.aman', 0),
        );

    Carbon::setTestNow();
});

test('controller mengembalikan data pegawai dalam format paginasi', function () {
    Carbon::setTestNow('2026-01-01');

    $user = Pegawai::factory()->operator()->create();

    // Buat 20 pegawai dengan KGB segera agar melebihi default per_page 15
    foreach (range(1, 20) as $i) {
        createPegawaiWithAktifPangkat('2026-01-31', [
            'nama_lengkap' => "Pegawai {$i}",
        ]);
    }

    actingAs($user);

    get(route('monitoring.kgb.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->has('pegawaiList.data', 15) // default per_page 15
            ->where('pegawaiList.total', 20)
            ->where('pegawaiList.last_page', 2)
            ->has('kgbStats')
            ->where('kgbStats.total', 20),
        );

    Carbon::setTestNow();
});

test('filter unit_kerja_id hanya menampilkan pegawai dari unit kerja tersebut', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $unitKerja1 = RefUnitKerja::factory()->create();
    $unitKerja2 = RefUnitKerja::factory()->create();

    $pangkat = RefPangkat::factory()->create(['kode' => 'III/a']);

    // Pegawai unit kerja 1 dengan KGB jatuh tempo
    $pegawai1 = Pegawai::factory()->create([
        'ref_unit_kerja_id' => $unitKerja1->id,
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai1->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(2)->subMonth(),
        'is_aktif' => true,
    ]);

    // Pegawai unit kerja 2 dengan KGB jatuh tempo
    $pegawai2 = Pegawai::factory()->create([
        'ref_unit_kerja_id' => $unitKerja2->id,
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai2->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(2)->subMonth(),
        'is_aktif' => true,
    ]);

    $service = app(KgbMonitoringService::class);
    $result = $service->getUpcomingKgb(3, 15, $unitKerja1->id);

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawai1->id)
        ->and($ids)->not->toContain($pegawai2->id);
});

test('filter status jatuh-tempo hanya menampilkan KGB yang sudah lewat', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $pangkat = RefPangkat::factory()->create(['kode' => 'III/a']);

    // KGB sudah lewat (tmt 2 tahun + 1 hari yang lalu)
    $pegawaiJatuhTempo = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiJatuhTempo->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(2)->subDay(),
        'is_aktif' => true,
    ]);

    // KGB masih segera (tmt 2 tahun dikurangi 30 hari ke depan)
    $pegawaiSegera = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiSegera->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(2)->addDays(30),
        'is_aktif' => true,
    ]);

    $service = app(KgbMonitoringService::class);
    $result = $service->getUpcomingKgb(3, 15, null, null, 'jatuh-tempo');

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawaiJatuhTempo->id)
        ->and($ids)->not->toContain($pegawaiSegera->id);
});

test('filter golongan hanya menampilkan pegawai dengan golongan tersebut', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $pangkatIII = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);
    $pangkatIV = RefPangkat::factory()->create(['kode' => 'IV/a', 'golongan' => 'IV']);

    $pegawaiIII = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkatIII->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiIII->id,
        'ref_pangkat_id' => $pangkatIII->id,
        'tmt' => now()->subYears(2)->subMonth(),
        'is_aktif' => true,
    ]);

    $pegawaiIV = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkatIV->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiIV->id,
        'ref_pangkat_id' => $pangkatIV->id,
        'tmt' => now()->subYears(2)->subMonth(),
        'is_aktif' => true,
    ]);

    $service = app(KgbMonitoringService::class);
    $result = $service->getUpcomingKgb(3, 15, null, 'III');

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawaiIII->id)
        ->and($ids)->not->toContain($pegawaiIV->id);
});

test('controller kgb meneruskan filter ke service dan kembali ke view', function () {
    $admin = Pegawai::factory()->admin()->create();
    $unitKerja = RefUnitKerja::factory()->create();
    actingAs($admin);

    get(route('monitoring.kgb.index', ['unit_kerja' => $unitKerja->id, 'status' => 'segera']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->has('filters', fn (Assert $f) => $f
                ->where('unit_kerja', $unitKerja->id)
                ->where('status', 'segera')
                ->etc()
            )
            ->has('filterOptions', fn (Assert $f) => $f
                ->has('unitKerja')
                ->has('golongan')
                ->etc()
            )
        );
});
