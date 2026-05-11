<?php

use App\Enums\JenisKelamin;
use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\DashboardStatService;
use App\Services\KenaikanPangkatMonitoringService;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('getStats returns dashboard statistics with expected structure and values', function () {
    Carbon::setTestNow('2026-03-16');

    $unitUmum = RefUnitKerja::factory()->create(['nama' => 'Bagian Umum']);
    $unitPerkara = RefUnitKerja::factory()->create(['nama' => 'Bagian Perkara']);

    $jabatanPanitera = RefJabatan::factory()->create(['nama' => 'Panitera']);
    $jabatanSekretaris = RefJabatan::factory()->create(['nama' => 'Sekretaris']);

    $pangkatTiga = RefPangkat::factory()->create(['kode' => 'III/a']);
    $pangkatEmpat = RefPangkat::factory()->create(['kode' => 'IV/b']);

    Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
        'ref_unit_kerja_id' => $unitUmum->id,
        'ref_jabatan_id' => $jabatanPanitera->id,
        'ref_pangkat_id' => $pangkatTiga->id,
        'jenis_kelamin' => JenisKelamin::LakiLaki->value,
        'pendidikan_terakhir' => 's1',
        'tanggal_masuk' => '2026-03-10',
    ]);

    Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
        'ref_unit_kerja_id' => $unitUmum->id,
        'ref_jabatan_id' => $jabatanPanitera->id,
        'ref_pangkat_id' => $pangkatEmpat->id,
        'jenis_kelamin' => JenisKelamin::Perempuan->value,
        'pendidikan_terakhir' => 's2',
        'tanggal_masuk' => '2026-02-12',
    ]);

    Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif->value,
        'ref_unit_kerja_id' => $unitPerkara->id,
        'ref_jabatan_id' => $jabatanSekretaris->id,
        'ref_pangkat_id' => null,
        'jenis_kelamin' => JenisKelamin::LakiLaki->value,
        'pendidikan_terakhir' => 's1',
        'tanggal_masuk' => '2026-01-01',
    ]);

    Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Pensiun->value,
        'ref_unit_kerja_id' => $unitPerkara->id,
        'ref_jabatan_id' => $jabatanSekretaris->id,
        'ref_pangkat_id' => $pangkatEmpat->id,
        'jenis_kelamin' => JenisKelamin::Perempuan->value,
        'pendidikan_terakhir' => 's3',
        'tanggal_masuk' => '2026-03-05',
    ]);

    app()->instance(KgbMonitoringService::class, new class extends KgbMonitoringService
    {
        public function getUpcomingKgb(
            int $months = 3,
            int $perPage = 15,
            ?string $unitKerjaId = null,
            ?string $golongan = null,
            ?string $status = null,
        ): LengthAwarePaginator {
            return new LengthAwarePaginator(
                items: collect([
                    ['id' => 'kgb-1'],
                    ['id' => 'kgb-2'],
                ]),
                total: 2,
                perPage: $perPage,
                currentPage: 1
            );
        }

        public function getKgbStats(
            int $months = 3,
            ?string $unitKerjaId = null,
            ?string $golongan = null,
        ): array {
            return [
                'total' => 2,
                'jatuhTempo' => 0,
                'segera' => 2,
                'mendekati' => 0,
                'aman' => 0,
            ];
        }
    });

    app()->instance(KenaikanPangkatMonitoringService::class, new class extends KenaikanPangkatMonitoringService
    {
        public function getUpcomingKenaikanPangkat(
            ?int $periodeBulan = null,
            int $perPage = 15,
            ?string $unitKerjaId = null,
            ?string $golongan = null,
            ?int $periodeTahun = null,
        ): LengthAwarePaginator {
            return new LengthAwarePaginator(
                items: collect([
                    ['id' => 'kp-1', 'status' => 'Sudah Eligible'],
                    ['id' => 'kp-2', 'status' => 'Belum Eligible'],
                ]),
                total: 2,
                perPage: $perPage,
                currentPage: 1
            );
        }

        public function getKpStats(
            ?int $periodeBulan = null,
            ?int $periodeTahun = null,
            ?string $unitKerjaId = null,
            ?string $golongan = null,
        ): array {
            return [
                'total' => 2,
                'sudahEligible' => 1,
                'mendekatiEligible' => 0,
                'belumEligible' => 1,
            ];
        }

        public function getAllPeriodeBulanan(int $tahun): array
        {
            return array_map(fn (int $bulan): array => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'periode_usul' => sprintf('%s %d', $this->getNamaBulan($bulan), $tahun),
                'stats' => $this->getKpStats($bulan, $tahun),
            ], range(1, 12));
        }
    });

    $stats = app(DashboardStatService::class)->getStats();

    expect($stats)->toHaveKeys([
        'total_pegawai_aktif',
        'distribusi_golongan',
        'distribusi_unit_kerja',
        'distribusi_jenis_kelamin',
        'kgb_segera_count',
        'kp_eligible_count',
        'distribusi_jabatan',
        'distribusi_pendidikan',
        'pegawai_baru_bulan_ini',
    ]);

    expect($stats['total_pegawai_aktif'])->toBe(3)
        ->and($stats['distribusi_golongan'])->toBe([
            'I' => 0,
            'II' => 0,
            'III' => 1,
            'IV' => 1,
        ])
        ->and($stats['kgb_segera_count'])->toBe(2)
        ->and($stats['kp_eligible_count'])->toBe(1)
        ->and($stats['pegawai_baru_bulan_ini'])->toBe(1)
        ->and(collect($stats['distribusi_unit_kerja'])->firstWhere('nama', 'Bagian Umum')['pegawai_count'])->toBe(2)
        ->and(collect($stats['distribusi_unit_kerja'])->firstWhere('nama', 'Bagian Perkara')['pegawai_count'])->toBe(1)
        ->and(collect($stats['distribusi_jenis_kelamin'])->firstWhere('jenis_kelamin', 'laki_laki')['total'])->toBe(2)
        ->and(collect($stats['distribusi_jenis_kelamin'])->firstWhere('jenis_kelamin', 'perempuan')['total'])->toBe(1)
        ->and(collect($stats['distribusi_jabatan'])->firstWhere('nama', 'Panitera')['pegawai_count'])->toBe(2)
        ->and(collect($stats['distribusi_jabatan'])->firstWhere('nama', 'Sekretaris')['pegawai_count'])->toBe(1)
        ->and(collect($stats['distribusi_pendidikan'])->firstWhere('pendidikan', 'S1')['pegawai_count'])->toBe(2)
        ->and(collect($stats['distribusi_pendidikan'])->firstWhere('pendidikan', 'S2')['pegawai_count'])->toBe(1);

    Carbon::setTestNow();
});
