<?php

namespace App\Services;

use App\Enums\JenjangPendidikan;
use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RefUnitKerja;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardStatService
{
    public function getStats(): array
    {
        return [
            'total_pegawai_aktif' => $this->getTotalPegawaiAktif(),
            'distribusi_golongan' => $this->getDistribusiGolongan(),
            'distribusi_unit_kerja' => $this->getDistribusiUnitKerja(),
            'distribusi_jenis_kelamin' => $this->getDistribusiJenisKelamin(),
            'kgb_segera_count' => $this->getKgbSegeraCount(),
            'kp_eligible_count' => $this->getKpEligibleCount(),
            'distribusi_jabatan' => $this->getDistribusiJabatan(),
            'distribusi_pendidikan' => $this->getDistribusiPendidikan(),
            'pegawai_baru_bulan_ini' => $this->getPegawaiBaruBulanIni(),
        ];
    }

    public function getTotalPegawaiAktif(): int
    {
        return $this->pegawaiAktifQuery()->count();
    }

    public function getDistribusiGolongan(): array
    {
        $pegawaiDenganPangkat = $this->pegawaiAktifQuery()
            ->with('pangkat')
            ->get();

        $distribusiGolongan = [
            'I' => 0,
            'II' => 0,
            'III' => 0,
            'IV' => 0,
        ];

        foreach ($pegawaiDenganPangkat as $pegawai) {
            if ($pegawai->pangkat && $pegawai->pangkat->kode) {
                $golongan = explode('/', $pegawai->pangkat->kode)[0];

                if (array_key_exists($golongan, $distribusiGolongan)) {
                    $distribusiGolongan[$golongan]++;
                }
            }
        }

        return $distribusiGolongan;
    }

    public function getDistribusiUnitKerja(): Collection
    {
        return RefUnitKerja::query()
            ->withCount(['pegawai' => fn ($query) => $query->where('status_pegawai', StatusPegawai::Aktif->value)])
            ->orderByDesc('pegawai_count')
            ->take(6)
            ->get()
            ->map(fn ($unit) => [
                'nama' => $unit->nama,
                'pegawai_count' => $unit->pegawai_count,
            ]);
    }

    public function getDistribusiJenisKelamin(): Collection
    {
        return $this->pegawaiAktifQuery()
            ->selectRaw('jenis_kelamin, count(*) as total')
            ->groupBy('jenis_kelamin')
            ->get()
            ->map(fn ($item) => [
                'jenis_kelamin' => $item->jenis_kelamin->value ?? $item->jenis_kelamin,
                'total' => $item->total,
            ]);
    }

    public function getKgbSegeraCount(): int
    {
        return Container::getInstance()->make(KgbMonitoringService::class)->getUpcomingKgb(2)->count();
    }

    public function getKpEligibleCount(): int
    {
        return Container::getInstance()->make(KenaikanPangkatMonitoringService::class)
            ->getUpcomingKenaikanPangkat()
            ->filter(fn ($kp) => $kp['status'] === 'Sudah Eligible')
            ->count();
    }

    public function getDistribusiJabatan(): Collection
    {
        return $this->pegawaiAktifQuery()
            ->with('jabatan')
            ->get()
            ->groupBy('ref_jabatan_id')
            ->map(fn ($pegawaiGroup) => [
                'nama' => $pegawaiGroup->first()->jabatan?->nama ?? 'Tidak Ada Jabatan',
                'pegawai_count' => $pegawaiGroup->count(),
            ])
            ->sortByDesc('pegawai_count')
            ->take(6)
            ->values();
    }

    public function getDistribusiPendidikan(): Collection
    {
        return $this->pegawaiAktifQuery()
            ->whereNotNull('pendidikan_terakhir')
            ->get()
            ->groupBy('pendidikan_terakhir')
            ->map(function ($pegawaiGroup, $pendidikan) {
                $label = JenjangPendidikan::tryFrom($pendidikan)?->label() ?? strtoupper($pendidikan);

                return [
                    'pendidikan' => $label,
                    'pegawai_count' => $pegawaiGroup->count(),
                ];
            })
            ->sortByDesc('pegawai_count')
            ->values();
    }

    public function getPegawaiBaruBulanIni(): int
    {
        $today = Carbon::today();

        return $this->pegawaiAktifQuery()
            ->whereMonth('tanggal_masuk', $today->month)
            ->whereYear('tanggal_masuk', $today->year)
            ->count();
    }

    protected function pegawaiAktifQuery(): Builder
    {
        return Pegawai::query()->where('status_pegawai', StatusPegawai::Aktif->value);
    }
}
