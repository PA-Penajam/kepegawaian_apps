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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatService
{
    public function getStats(): array
    {
        return array_merge($this->getFastStats(), $this->getHeavyStats());
    }

    public function getFastStats(): array
    {
        return Cache::remember('dashboard_stats_fast', 300, fn () => [
            'total_pegawai_aktif' => $this->getTotalPegawaiAktif(),
            'kgb_segera_count' => $this->getKgbSegeraCount(),
            'kp_eligible_count' => $this->getKpEligibleCount(),
            'pegawai_baru_bulan_ini' => $this->getPegawaiBaruBulanIni(),
        ]);
    }

    public function getHeavyStats(): array
    {
        return Cache::remember('dashboard_stats_heavy', 300, fn () => [
            'distribusi_golongan' => $this->getDistribusiGolongan(),
            'distribusi_unit_kerja' => $this->getDistribusiUnitKerja(),
            'distribusi_jenis_kelamin' => $this->getDistribusiJenisKelamin(),
            'distribusi_jabatan' => $this->getDistribusiJabatan(),
            'distribusi_pendidikan' => $this->getDistribusiPendidikan(),
        ]);
    }

    public function getTotalPegawaiAktif(): int
    {
        return $this->pegawaiAktifQuery()->count();
    }

    public function getDistribusiGolongan(): array
    {
        $driver = DB::connection()->getDriverName();
        $isMySQL = $driver === 'mysql';

        // SUBSTRING_INDEX untuk MySQL, SUBSTR+INSTR untuk SQLite
        $golonganExpr = $isMySQL
            ? "SUBSTRING_INDEX(ref_pangkat.kode, '/', 1)"
            : "CASE WHEN INSTR(ref_pangkat.kode, '/') > 0 THEN SUBSTR(ref_pangkat.kode, 1, INSTR(ref_pangkat.kode, '/') - 1) ELSE ref_pangkat.kode END";

        $rows = $this->pegawaiAktifQuery()
            ->join('ref_pangkat', 'pegawai.ref_pangkat_id', '=', 'ref_pangkat.id')
            ->selectRaw("{$golonganExpr} as golongan, COUNT(*) as total")
            ->groupByRaw($golonganExpr)
            ->pluck('total', 'golongan');

        return [
            'I' => (int) ($rows['I'] ?? 0),
            'II' => (int) ($rows['II'] ?? 0),
            'III' => (int) ($rows['III'] ?? 0),
            'IV' => (int) ($rows['IV'] ?? 0),
        ];
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
        return Container::getInstance()->make(KgbMonitoringService::class)->getKgbStats(2)['total'];
    }

    public function getKpEligibleCount(): int
    {
        return Container::getInstance()->make(KenaikanPangkatMonitoringService::class)
            ->getKpStats()['sudahEligible'];
    }

    public function getDistribusiJabatan(): Collection
    {
        return $this->pegawaiAktifQuery()
            ->join('ref_jabatan', 'pegawai.ref_jabatan_id', '=', 'ref_jabatan.id')
            ->selectRaw('ref_jabatan.nama, COUNT(*) as pegawai_count')
            ->groupBy('pegawai.ref_jabatan_id', 'ref_jabatan.nama')
            ->orderByDesc('pegawai_count')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'nama' => $row->nama,
                'pegawai_count' => (int) $row->pegawai_count,
            ]);
    }

    public function getDistribusiPendidikan(): Collection
    {
        return $this->pegawaiAktifQuery()
            ->whereNotNull('pendidikan_terakhir')
            ->selectRaw('pendidikan_terakhir, COUNT(*) as pegawai_count')
            ->groupBy('pendidikan_terakhir')
            ->orderByDesc('pegawai_count')
            ->get()
            ->map(function ($row) {
                $label = JenjangPendidikan::tryFrom($row->pendidikan_terakhir)?->label()
                    ?? strtoupper($row->pendidikan_terakhir);

                return [
                    'pendidikan' => $label,
                    'pegawai_count' => (int) $row->pegawai_count,
                ];
            });
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
