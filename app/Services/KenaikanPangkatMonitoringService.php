<?php

namespace App\Services;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class KenaikanPangkatMonitoringService
{
    public function getUpcomingKenaikanPangkat(
        ?string $periode = null,
        int $perPage = 15,
        ?string $unitKerjaId = null,
        ?string $golongan = null,
    ): LengthAwarePaginator {
        $normalizedPeriode = $periode !== null ? strtolower($periode) : null;

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
            ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
            ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan))
            ->orderBy('nama_lengkap');

        // Filter periode di level query (April = bulan 1-4, Oktober = bulan 5-10)
        if ($normalizedPeriode === 'april') {
            $query->whereRaw($this->getPeriodeFilterSql('april'), [4]);
        } elseif ($normalizedPeriode === 'oktober') {
            $query->whereRaw($this->getPeriodeFilterSql('oktober'));
        }

        return $query
            ->paginate($perPage)
            ->through(function (Pegawai $pegawai): array {
                $riwayatPangkatAktif = $pegawai->riwayatPangkat->first();

                if ($riwayatPangkatAktif === null) {
                    return [];
                }

                $status = $this->getKpStatus($pegawai);

                return [
                    'id'               => $pegawai->id,
                    'nip'              => $pegawai->nip,
                    'nama_lengkap'     => $pegawai->nama_lengkap,
                    'pangkat_saat_ini' => $riwayatPangkatAktif->pangkat?->nama ?? $pegawai->pangkat?->nama,
                    'pangkat_kode'     => $riwayatPangkatAktif->pangkat?->kode ?? $pegawai->pangkat?->kode,
                    'tmt_pangkat'      => $riwayatPangkatAktif->tmt?->toDateString(),
                    'tmt_kp_berikutnya'=> $status['tmt_kp_berikutnya']->toDateString(),
                    'periode_usul'     => $status['periode_usul'],
                    'batas_usul'       => $status['batas_usul']->toDateString(),
                    'sisa_hari_usul'   => $status['sisa_hari_usul'],
                    'status'           => $status['status'],
                ];
            });
    }

    public function getKpStats(
        ?string $periode = null,
        ?string $unitKerjaId = null,
        ?string $golongan = null,
    ): array {
        $normalizedPeriode = $periode !== null ? strtolower($periode) : null;
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
            ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
            ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan));

        if ($normalizedPeriode === 'april') {
            $query->whereRaw($this->getPeriodeFilterSql('april'), [4]);
        } elseif ($normalizedPeriode === 'oktober') {
            $query->whereRaw($this->getPeriodeFilterSql('oktober'));
        }

        $row = $query->first();

        return [
            'total'             => (int) ($row?->total ?? 0),
            'sudahEligible'     => (int) ($row?->sudah_eligible ?? 0),
            'mendekatiEligible' => (int) ($row?->mendekati_eligible ?? 0),
            'belumEligible'     => (int) ($row?->belum_eligible ?? 0),
        ];
    }

    private function getPeriodeFilterSql(string $periode): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $tmtPlus4Year = "date(rp_kp.tmt, '+4 years')";
            $month = "CAST(strftime('%m', {$tmtPlus4Year}) AS INTEGER)";
        } else {
            $tmtPlus4Year = 'DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)';
            $month = "MONTH({$tmtPlus4Year})";
        }

        if ($periode === 'april') {
            return "{$month} <= ?";
        }

        // oktober
        return "{$month} BETWEEN 5 AND 10";
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

        ['periode_usul' => $periodeUsul, 'batas_usul' => $batasUsul] = $this->resolvePeriodeUsulDanBatas($tmtKpBerikutnya);

        $isEligible = $tmtKpBerikutnya->lessThanOrEqualTo($today);
        $isNearEligible = $tmtKpBerikutnya->lessThanOrEqualTo($today->copy()->addMonthsNoOverflow(6));

        return [
            'eligible'        => $isEligible,
            'tmt_kp_berikutnya'=> $tmtKpBerikutnya,
            'periode_usul'    => $periodeUsul,
            'batas_usul'      => $batasUsul,
            'sisa_hari_usul'  => $today->diffInDays($batasUsul, false),
            'status'          => $isEligible
                ? 'Sudah Eligible'
                : ($isNearEligible ? 'Mendekati Eligible' : 'Belum Eligible'),
        ];
    }

    private function resolvePeriodeUsulDanBatas(CarbonInterface $tmtKpBerikutnya): array
    {
        $year = $tmtKpBerikutnya->year;
        $month = $tmtKpBerikutnya->month;

        if ($month <= 4) {
            return [
                'periode_usul' => sprintf('April %d', $year),
                'batas_usul' => Carbon::create($year - 1, 10, 1)->startOfDay(),
            ];
        }

        if ($month <= 10) {
            return [
                'periode_usul' => sprintf('Oktober %d', $year),
                'batas_usul' => Carbon::create($year, 4, 1)->startOfDay(),
            ];
        }

        return [
            'periode_usul' => sprintf('April %d', $year + 1),
            'batas_usul' => Carbon::create($year, 10, 1)->startOfDay(),
        ];
    }
}
