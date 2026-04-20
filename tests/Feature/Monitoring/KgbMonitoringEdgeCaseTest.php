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

beforeEach(function (): void {
    Carbon::setTestNow('2026-04-17');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeKgbPegawai(string $nextKgbDate, array $pegawai = []): Pegawai
{
    $record = Pegawai::factory()->create(array_merge([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ], $pegawai));

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $record->id,
        'ref_pangkat_id' => $record->ref_pangkat_id,
        'tmt' => Carbon::parse($nextKgbDate)->subYears(2)->toDateString(),
        'is_aktif' => true,
    ]);

    return $record;
}

it('menampilkan empty state inertia ketika tidak ada data monitoring kgb', function (): void {
    $user = Pegawai::factory()->operator()->create();

    actingAs($user)
        ->get(route('monitoring.kgb.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/monitoring/kgb/index')
            ->where('pegawaiList.total', 0)
            ->has('pegawaiList.data', 0)
            ->where('kgbStats.total', 0)
            ->where('kgbStats.jatuhTempo', 0)
            ->where('kgbStats.segera', 0)
            ->where('kgbStats.mendekati', 0)
            ->where('kgbStats.aman', 0)
        );
});

it('melempar exception ketika status kgb dihitung untuk pegawai tanpa riwayat aktif', function (): void {
    $pegawai = Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
    ]);

    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'is_aktif' => false,
    ]);

    expect(fn () => app(KgbMonitoringService::class)->getKgbStatus($pegawai))
        ->toThrow(InvalidArgumentException::class);
});

it('mempertahankan total data dan halaman kedua untuk dataset besar', function (): void {
    $user = Pegawai::factory()->operator()->create();

    foreach (range(1, 31) as $index) {
        makeKgbPegawai('2026-05-01', ['nama_lengkap' => "Pegawai KGB {$index}"]);
    }

    actingAs($user)
        ->get(route('monitoring.kgb.index', ['page' => 2, 'per_page' => 15]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pegawaiList.current_page', 2)
            ->where('pegawaiList.total', 31)
            ->where('pegawaiList.last_page', 3)
            ->has('pegawaiList.data', 15)
        );
});

it('menerapkan kombinasi filter unit kerja, golongan, dan status', function (): void {
    $admin = Pegawai::factory()->admin()->create();
    $unitCocok = RefUnitKerja::factory()->create();
    $unitLain = RefUnitKerja::factory()->create();
    $pangkatCocok = RefPangkat::factory()->create(['kode' => 'III/a', 'golongan' => 'III']);
    $pangkatLain = RefPangkat::factory()->create(['kode' => 'IV/a', 'golongan' => 'IV']);

    makeKgbPegawai('2026-05-01', [
        'ref_unit_kerja_id' => $unitCocok->id,
        'ref_pangkat_id' => $pangkatCocok->id,
        'nama_lengkap' => 'Target KGB',
    ]);

    makeKgbPegawai('2026-05-01', [
        'ref_unit_kerja_id' => $unitLain->id,
        'ref_pangkat_id' => $pangkatCocok->id,
        'nama_lengkap' => 'Salah Unit',
    ]);

    makeKgbPegawai('2026-09-30', [
        'ref_unit_kerja_id' => $unitCocok->id,
        'ref_pangkat_id' => $pangkatLain->id,
        'nama_lengkap' => 'Salah Golongan',
    ]);

    actingAs($admin);

    $result = app(KgbMonitoringService::class)->getUpcomingKgb(6, 15, $unitCocok->id, 'III', 'segera');

    expect(collect($result->items())->pluck('nama_lengkap')->all())->toBe(['Target KGB']);
});
