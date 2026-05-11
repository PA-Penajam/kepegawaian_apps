<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Models\RiwayatPangkat;
use App\Services\KenaikanPangkatMonitoringService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeKpPegawai(string $tmt, array $pegawai = []): Pegawai
{
    $pangkat = $pegawai['ref_pangkat_id'] ?? RefPangkat::factory()->create()->id;

    $record = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
        'ref_pangkat_id' => $pangkat,
    ], $pegawai));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $record->id,
        'ref_pangkat_id' => $pangkat,
        'tmt' => $tmt,
        'is_aktif' => true,
    ]);

    return $record;
}

it('menampilkan empty state inertia ketika monitoring kp kosong', function (): void {
    $user = Pegawai::factory()->operator()->create();

    actingAs($user)
        ->get(route('monitoring.kenaikan-pangkat.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kenaikan-pangkat/index')
            ->where('pegawaiList.total', 0)
            ->has('pegawaiList.data', 0)
            ->where('kpStats.total', 0)
            ->where('kpStats.sudahEligible', 0)
            ->where('kpStats.mendekatiEligible', 0)
            ->where('kpStats.belumEligible', 0)
        );
});

it('melempar exception ketika status kp dihitung untuk pegawai tanpa riwayat aktif', function (): void {
    $pegawai = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);

    expect(fn () => app(KenaikanPangkatMonitoringService::class)->getKpStatus($pegawai->fresh(['riwayatPangkat'])))
        ->toThrow(RuntimeException::class);
});

it('mempertahankan metadata pagination untuk dataset kp besar', function (): void {
    $user = Pegawai::factory()->operator()->create();

    foreach (range(1, 31) as $index) {
        makeKpPegawai('2022-04-01', ['nama_lengkap' => "Pegawai KP {$index}"]);
    }

    actingAs($user)
        ->get(route('monitoring.kenaikan-pangkat.index', ['page' => 3, 'per_page' => 10]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawaiList.current_page', 3)
            ->where('pegawaiList.total', 31)
            ->where('pegawaiList.last_page', 4)
            ->has('pegawaiList.data', 10)
        );
});

it('menerapkan kombinasi filter periode, unit kerja, dan golongan pada kp', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $unitCocok = RefUnitKerja::factory()->create();
    $unitLain = RefUnitKerja::factory()->create();
    $pangkatIII = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);
    $pangkatIV = RefPangkat::factory()->create(['kode' => 'IV/a', 'golongan' => 'IV']);

    makeKpPegawai('2023-01-01', [
        'ref_unit_kerja_id' => $unitCocok->id,
        'ref_pangkat_id' => $pangkatIII->id,
        'nama_lengkap' => 'Target KP',
    ]);

    makeKpPegawai('2023-01-01', [
        'ref_unit_kerja_id' => $unitLain->id,
        'ref_pangkat_id' => $pangkatIII->id,
        'nama_lengkap' => 'Salah Unit KP',
    ]);

    makeKpPegawai('2023-07-01', [
        'ref_unit_kerja_id' => $unitCocok->id,
        'ref_pangkat_id' => $pangkatIV->id,
        'nama_lengkap' => 'Salah Golongan KP',
    ]);

    actingAs($admin);

    $result = app(KenaikanPangkatMonitoringService::class)
        ->getUpcomingKenaikanPangkat(1, 15, $unitCocok->id, 'III', 2027);

    expect(collect($result->items())->pluck('nama_lengkap')->all())->toBe(['Target KP']);
});
