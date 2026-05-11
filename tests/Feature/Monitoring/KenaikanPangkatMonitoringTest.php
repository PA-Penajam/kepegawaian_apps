<?php

use App\Enums\StatusPegawai;
use App\Models\HukumanDisiplin;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Models\RiwayatPangkat;
use App\Services\KenaikanPangkatMonitoringService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function createPegawaiDenganPangkatAktif(string $tmt, array $pegawaiAttributes = []): Pegawai
{
    $pangkat = RefPangkat::factory()->create();

    $pegawai = Pegawai::factory()->create(array_merge([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $pegawaiAttributes));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => $tmt,
        'is_aktif' => true,
        'masa_kerja_tahun' => 0,
        'masa_kerja_bulan' => 0,
    ]);

    return $pegawai;
}

test('it calculates next regular promotion date from active rank tmt', function () {
    Carbon::setTestNow('2026-01-15');

    $service = app(KenaikanPangkatMonitoringService::class);
    $pegawai = createPegawaiDenganPangkatAktif('2022-04-01');

    $status = $service->getKpStatus($pegawai->fresh(['riwayatPangkat' => fn ($query) => $query->aktif()]));

    expect($status['tmt_kp_berikutnya']->toDateString())->toBe('2026-04-01');

    Carbon::setTestNow();
});

test('it calculates proposal period and deadline for monthly promotion period', function () {
    Carbon::setTestNow('2026-01-15');

    $service = app(KenaikanPangkatMonitoringService::class);
    $pegawai = createPegawaiDenganPangkatAktif('2023-04-01');

    $status = $service->getKpStatus($pegawai->fresh(['riwayatPangkat' => fn ($query) => $query->aktif()]));

    expect($status['periode_usul'])->toBe('April 2027')
        ->and($status['batas_usul']->toDateString())->toBe('2027-03-01');

    Carbon::setTestNow();
});

test('it skips employees without active rank history from monitoring list', function () {
    $service = app(KenaikanPangkatMonitoringService::class);

    $pangkat = RefPangkat::factory()->create();
    Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
        'ref_pangkat_id' => $pangkat->id,
    ]);

    $result = $service->getUpcomingKenaikanPangkat();

    expect($result)->toHaveCount(0);
});

test('it excludes retired employees from monitoring list', function () {
    $service = app(KenaikanPangkatMonitoringService::class);

    createPegawaiDenganPangkatAktif('2022-04-01', [
        'status_pegawai' => StatusPegawai::Pensiun->value,
    ]);

    $result = $service->getUpcomingKenaikanPangkat();

    expect($result)->toHaveCount(0);
});

test('it filters monitoring list by monthly promotion period', function () {
    Carbon::setTestNow('2026-01-15');

    $service = app(KenaikanPangkatMonitoringService::class);

    createPegawaiDenganPangkatAktif('2023-04-01');
    createPegawaiDenganPangkatAktif('2022-10-01');

    $result = $service->getUpcomingKenaikanPangkat(4, periodeTahun: 2027);

    expect($result)
        ->toHaveCount(1)
        ->and($result->first()['periode_usul'])->toBe('April 2027');

    Carbon::setTestNow();
});

test('controller mengembalikan data kp dalam format paginasi', function () {
    Carbon::setTestNow('2026-01-01');

    $user = Pegawai::factory()->operator()->create();

    // Buat 20 pegawai yang eligible KP (TMT 4+ tahun lalu)
    foreach (range(1, 20) as $i) {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif->value,
            'nama_lengkap' => "Pegawai KP {$i}",
        ]);
        RiwayatPangkat::factory()->create([
            'pegawai_id' => $pegawai->id,
            'tmt' => Carbon::now()->subYears(5)->toDateString(),
            'is_aktif' => true,
        ]);
    }

    actingAs($user);

    get(route('monitoring.kenaikan-pangkat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kenaikan-pangkat/index')
            ->has('pegawaiList.data', 15)
            ->where('pegawaiList.total', 20)
            ->where('pegawaiList.last_page', 2)
            ->has('kpStats')
            ->where('kpStats.sudahEligible', 20),
        );

    Carbon::setTestNow();
});

test('monitoring index returns inertia response', function () {
    $user = Pegawai::factory()->admin()->create();
    createPegawaiDenganPangkatAktif('2022-04-01');

    actingAs($user);

    get(route('monitoring.kenaikan-pangkat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kenaikan-pangkat/index')
            ->has('pegawaiList.data', 1)
            ->has('kpStats.total')
            ->has('kpStats.sudahEligible')
            ->has('kpStats.mendekatiEligible')
            ->has('kpStats.belumEligible'),
        );
});

test('filter unit_kerja_id hanya menampilkan pegawai KP dari unit kerja tersebut', function () {
    $admin = Pegawai::factory()->admin()->create();
    actingAs($admin);

    $unitKerja1 = RefUnitKerja::factory()->create();
    $unitKerja2 = RefUnitKerja::factory()->create();

    $pangkat = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);

    $pegawai1 = Pegawai::factory()->create([
        'ref_unit_kerja_id' => $unitKerja1->id,
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai1->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(5),
        'is_aktif' => true,
    ]);

    $pegawai2 = Pegawai::factory()->create([
        'ref_unit_kerja_id' => $unitKerja2->id,
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai2->id,
        'ref_pangkat_id' => $pangkat->id,
        'tmt' => now()->subYears(5),
        'is_aktif' => true,
    ]);

    $service = app(KenaikanPangkatMonitoringService::class);
    $result = $service->getUpcomingKenaikanPangkat(null, 15, $unitKerja1->id);

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawai1->id)
        ->and($ids)->not->toContain($pegawai2->id);
});

test('filter golongan hanya menampilkan pegawai KP dengan golongan tersebut', function () {
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
        'tmt' => now()->subYears(5),
        'is_aktif' => true,
    ]);

    $pegawaiIV = Pegawai::factory()->create([
        'ref_pangkat_id' => $pangkatIV->id,
        'status_pegawai' => 'aktif',
    ]);
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawaiIV->id,
        'ref_pangkat_id' => $pangkatIV->id,
        'tmt' => now()->subYears(5),
        'is_aktif' => true,
    ]);

    $service = app(KenaikanPangkatMonitoringService::class);
    $result = $service->getUpcomingKenaikanPangkat(null, 15, null, 'III');

    $ids = collect($result->items())->pluck('id')->toArray();

    expect($ids)->toContain($pegawaiIII->id)
        ->and($ids)->not->toContain($pegawaiIV->id);
});

it('returns twelve monthly period statistics for selected year', function (): void {
    Carbon::setTestNow('2026-01-15');

    foreach (range(1, 12) as $month) {
        createPegawaiDenganPangkatAktif(Carbon::create(2022, $month, 1)->toDateString());
    }

    $periods = app(KenaikanPangkatMonitoringService::class)->getAllPeriodeBulanan(2026);

    expect($periods)->toHaveCount(12)
        ->and($periods[0]['bulan'])->toBe(1)
        ->and($periods[0]['periode_usul'])->toBe('Januari 2026')
        ->and($periods[11]['bulan'])->toBe(12)
        ->and($periods[11]['periode_usul'])->toBe('Desember 2026');

    foreach ($periods as $period) {
        expect($period['stats']['total'])->toBe(1);
    }

    Carbon::setTestNow();
});

it('calculates stats for each monthly period without exception', function (): void {
    Carbon::setTestNow('2026-01-15');

    foreach (range(1, 12) as $month) {
        createPegawaiDenganPangkatAktif(Carbon::create(2022, $month, 15)->toDateString());
    }

    foreach (range(1, 12) as $month) {
        $stats = app(KenaikanPangkatMonitoringService::class)->getKpStats($month, 2026);

        expect($stats['total'])->toBe(1);
    }

    Carbon::setTestNow();
});

it('calculates december period and previous month deadline', function (): void {
    $pegawai = createPegawaiDenganPangkatAktif('2022-12-01');

    $status = app(KenaikanPangkatMonitoringService::class)
        ->getKpStatus($pegawai->fresh(['riwayatPangkat' => fn ($query) => $query->aktif()]));

    expect($status['periode_usul'])->toBe('Desember 2026')
        ->and($status['batas_usul']->toDateString())->toBe('2026-11-01');
});

it('keeps leap year promotion in february period', function (): void {
    $pegawai = createPegawaiDenganPangkatAktif('2020-02-29');

    $status = app(KenaikanPangkatMonitoringService::class)
        ->getKpStatus($pegawai->fresh(['riwayatPangkat' => fn ($query) => $query->aktif()]));

    expect($status['periode_usul'])->toBe('Februari 2024');
});

it('excludes pppk employees from monitoring list', function (): void {
    $pns = createPegawaiDenganPangkatAktif('2022-05-01', ['nama_lengkap' => 'PNS KP']);
    createPegawaiDenganPangkatAktif('2022-05-01', [
        'nama_lengkap' => 'PPPK KP',
        'status_kepegawaian' => 'pppk',
    ]);

    $result = app(KenaikanPangkatMonitoringService::class)->getUpcomingKenaikanPangkat(5, periodeTahun: 2026);

    expect(collect($result->items())->pluck('id')->all())->toBe([$pns->id]);
});

it('excludes employees with active disciplinary punishment from monitoring list', function (): void {
    $eligible = createPegawaiDenganPangkatAktif('2022-06-01', ['nama_lengkap' => 'Bersih KP']);
    $blocked = createPegawaiDenganPangkatAktif('2022-06-01', ['nama_lengkap' => 'Hukdis KP']);

    HukumanDisiplin::factory()->create([
        'pegawai_id' => $blocked->id,
        'tmt_berlaku' => now()->subMonth()->toDateString(),
        'tmt_selesai' => now()->addMonth()->toDateString(),
    ]);

    $result = app(KenaikanPangkatMonitoringService::class)->getUpcomingKenaikanPangkat(6, periodeTahun: 2026);

    expect(collect($result->items())->pluck('id')->all())->toBe([$eligible->id]);
});

test('controller kp meneruskan filter dan filterOptions ke view', function () {
    $admin = Pegawai::factory()->admin()->create();
    $unitKerja = RefUnitKerja::factory()->create();
    actingAs($admin);

    get(route('monitoring.kenaikan-pangkat.index', ['unit_kerja' => $unitKerja->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kenaikan-pangkat/index')
            ->has('filters', fn (Assert $f) => $f
                ->where('unit_kerja', $unitKerja->id)
                ->etc()
            )
            ->has('filterOptions', fn (Assert $f) => $f
                ->has('unitKerja')
                ->has('golongan')
                ->etc()
            )
        );
});
