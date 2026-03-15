<?php

namespace App\Services;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class KenaikanPangkatMonitoringService
{
    public function getUpcomingKenaikanPangkat(?string $periode = null): Collection
    {
        $normalizedPeriode = $periode !== null ? strtolower($periode) : null;

        return Pegawai::query()
            ->whereNotIn('status_pegawai', [
                StatusPegawai::Pensiun->value,
                StatusPegawai::Meninggal->value,
                StatusPegawai::Diberhentikan->value,
            ])
            ->with([
                'pangkat',
                'riwayatPangkat' => fn ($query) => $query
                    ->aktif()
                    ->with('pangkat')
                    ->orderByDesc('tmt'),
            ])
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function (Pegawai $pegawai) use ($normalizedPeriode): ?array {
                $riwayatPangkatAktif = $pegawai->riwayatPangkat->first();

                if ($riwayatPangkatAktif === null) {
                    return null;
                }

                $status = $this->getKpStatus($pegawai);
                $periodeKey = str_starts_with($status['periode_usul'], 'April') ? 'april' : 'oktober';

                if ($normalizedPeriode !== null && in_array($normalizedPeriode, ['april', 'oktober'], true) && $periodeKey !== $normalizedPeriode) {
                    return null;
                }

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
            })
            ->filter()
            ->values();
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
