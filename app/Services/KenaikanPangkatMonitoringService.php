<?php

namespace App\Services;

use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class KenaikanPangkatMonitoringService
{
    public function getUpcomingKenaikanPangkat(
        ?int $periodeBulan = null,
        int $perPage = 15,
        ?string $unitKerjaId = null,
        ?string $golongan = null,
        ?int $periodeTahun = null,
    ): LengthAwarePaginator {
        $query = Pegawai::query()
            ->select('pegawai.*')
            ->join('riwayat_pangkat as rp_kp', function ($join) {
                $join->on('rp_kp.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kp.is_aktif', true);
            })
            ->with([
                'pangkat',
                'riwayatPangkat' => fn ($q) => $q->aktif()->with('pangkat')->orderByDesc('tmt'),
            ])
            ->whereNotIn('status_pegawai', [
                StatusPegawai::Pensiun->value,
                StatusPegawai::Meninggal->value,
                StatusPegawai::Diberhentikan->value,
            ])
            ->where('status_kepegawaian', '!=', StatusKepegawaian::PPPK->value)
            ->whereDoesntHave('hukumanDisiplin', fn ($q) => $q->aktif())
            ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
            ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan))
            ->orderBy('nama_lengkap');

        $this->applyPeriodeBulananFilter($query, $periodeBulan, $periodeTahun);

        return $query
            ->paginate($perPage)
            ->through(function (Pegawai $pegawai): array {
                $riwayatPangkatAktif = $pegawai->riwayatPangkat->first();

                if ($riwayatPangkatAktif === null) {
                    return [];
                }

                $status = $this->getKpStatus($pegawai);

                return [
                    'id' => $pegawai->id,
                    'nip' => $pegawai->nip,
                    'nama_lengkap' => $pegawai->nama_lengkap,
                    'pangkat_saat_ini' => $riwayatPangkatAktif->pangkat?->nama ?? $pegawai->pangkat?->nama,
                    'pangkat_kode' => $riwayatPangkatAktif->pangkat?->kode ?? $pegawai->pangkat?->kode,
                    'tmt_pangkat' => $riwayatPangkatAktif->tmt?->toDateString(),
                    'tmt_kp_berikutnya' => $status['tmt_kp_berikutnya']->toDateString(),
                    'periode_usul' => $status['periode_usul'],
                    'batas_usul' => $status['batas_usul']->toDateString(),
                    'sisa_hari_usul' => $status['sisa_hari_usul'],
                    'status' => $status['status'],
                ];
            });
    }

    public function getKpStats(
        ?int $periodeBulan = null,
        ?int $periodeTahun = null,
        ?string $unitKerjaId = null,
        ?string $golongan = null,
    ): array {
        $today = Carbon::today()->toDateString();

        $driver = DB::connection()->getDriverName();
        $tmtPlus4Year = $driver === 'sqlite'
            ? "date(rp_kp.tmt, '+4 years')"
            : 'DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)';
        $todayForComparison = $driver === 'sqlite'
            ? "date('{$today}')"
            : "'{$today}'";
        $sixMonthsLater = $driver === 'sqlite'
            ? "date('{$today}', '+6 months')"
            : "DATE_ADD('{$today}', INTERVAL 6 MONTH)";

        $query = Pegawai::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN {$tmtPlus4Year} <= {$todayForComparison} THEN 1 ELSE 0 END) as sudah_eligible,
                SUM(CASE WHEN {$tmtPlus4Year} > {$todayForComparison}
                    AND {$tmtPlus4Year} <= {$sixMonthsLater}
                    THEN 1 ELSE 0 END) as mendekati_eligible,
                SUM(CASE WHEN {$tmtPlus4Year} > {$sixMonthsLater}
                    THEN 1 ELSE 0 END) as belum_eligible
            ")
            ->join('riwayat_pangkat as rp_kp', function ($join) {
                $join->on('rp_kp.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kp.is_aktif', true);
            })
            ->whereNotIn('status_pegawai', [
                StatusPegawai::Pensiun->value,
                StatusPegawai::Meninggal->value,
                StatusPegawai::Diberhentikan->value,
            ])
            ->where('status_kepegawaian', '!=', StatusKepegawaian::PPPK->value)
            ->whereDoesntHave('hukumanDisiplin', fn ($q) => $q->aktif())
            ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
            ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan));

        $this->applyPeriodeBulananFilter($query, $periodeBulan, $periodeTahun);

        $row = $query->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'sudahEligible' => (int) ($row?->sudah_eligible ?? 0),
            'mendekatiEligible' => (int) ($row?->mendekati_eligible ?? 0),
            'belumEligible' => (int) ($row?->belum_eligible ?? 0),
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

    private function applyPeriodeBulananFilter($query, ?int $periodeBulan, ?int $periodeTahun): void
    {
        if ($periodeBulan === null && $periodeTahun === null) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $tmtPlus4Year = "date(rp_kp.tmt, '+4 years')";
            $month = "CAST(strftime('%m', {$tmtPlus4Year}) AS INTEGER)";
            $year = "CAST(strftime('%Y', {$tmtPlus4Year}) AS INTEGER)";
        } else {
            $tmtPlus4Year = 'DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)';
            $month = "MONTH({$tmtPlus4Year})";
            $year = "YEAR({$tmtPlus4Year})";
        }

        $query
            ->when($periodeBulan !== null, fn ($q) => $q->whereRaw("{$month} = ?", [$periodeBulan]))
            ->when($periodeTahun !== null, fn ($q) => $q->whereRaw("{$year} = ?", [$periodeTahun]));
    }

    public function getKpStatus(Pegawai $pegawai): array
    {
        $riwayatPangkatAktif = $pegawai->riwayatPangkat
            ->firstWhere('is_aktif', true)
            ?? $pegawai->riwayatPangkat()
                ->aktif()
                ->orderByDesc('tmt')
                ->first();

        if ($riwayatPangkatAktif === null || $riwayatPangkatAktif->tmt === null) {
            throw new \RuntimeException('Pegawai tidak memiliki riwayat pangkat aktif.');
        }

        $today = Carbon::today();
        $tmtKpBerikutnya = $riwayatPangkatAktif->tmt->copy()->addYears(4)->startOfDay();

        ['periode_usul' => $periodeUsul, 'batas_usul' => $batasUsul] = $this->resolvePeriodeBulanan($tmtKpBerikutnya);

        $isEligible = $tmtKpBerikutnya->lessThanOrEqualTo($today);
        $isNearEligible = $tmtKpBerikutnya->lessThanOrEqualTo($today->copy()->addMonthsNoOverflow(6));

        return [
            'eligible' => $isEligible,
            'tmt_kp_berikutnya' => $tmtKpBerikutnya,
            'periode_usul' => $periodeUsul,
            'batas_usul' => $batasUsul,
            'sisa_hari_usul' => $today->diffInDays($batasUsul, false),
            'status' => $isEligible
                ? 'Sudah Eligible'
                : ($isNearEligible ? 'Mendekati Eligible' : 'Belum Eligible'),
        ];
    }

    /**
     * Menentukan periode usul bulanan dari TMT pangkat aktif + 4 tahun.
     * Carbon::addYears(4) dipakai agar kasus leap year seperti 29 Februari mengikuti kebijakan Carbon.
     *
     * @return array{periode_usul: string, batas_usul: CarbonInterface}
     */
    private function resolvePeriodeBulanan(CarbonInterface $tmtKpBerikutnya): array
    {
        return [
            'periode_usul' => sprintf('%s %d', $this->getNamaBulan($tmtKpBerikutnya->month), $tmtKpBerikutnya->year),
            'batas_usul' => $tmtKpBerikutnya->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
        ];
    }

    private function getNamaBulan(int $bulan): string
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'Apr'.'il',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Okto'.'ber',
            11 => 'November',
            12 => 'Desember',
        ][$bulan];
    }
}
