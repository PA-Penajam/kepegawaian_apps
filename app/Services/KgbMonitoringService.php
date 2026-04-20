<?php

namespace App\Services;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class KgbMonitoringService
{
    public function getUpcomingKgb(
        int $months = 3,
        int $perPage = 15,
        ?string $unitKerjaId = null,
        ?string $golongan = null,
        ?string $status = null,
    ): LengthAwarePaginator
    {
        $maxSisaHari = $months * 30;
        $driver = DB::connection()->getDriverName();
        $isMySQL = $driver === 'mysql';

        // Hitung tanggal KGB maksimum di PHP (tanggal hari ini + X bulan)
        // Ini diperlukan karena SQLite date('now') tidak mengenal Carbon::setTestNow()
        $maxKgbDate = Carbon::today()->addDays($maxSisaHari)->toDateString();

        $query = Pegawai::query()
            ->select('pegawai.*')
            ->join('riwayat_pangkat as rp_kgb', function ($join) {
                $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kgb.is_aktif', true);
            })
            ->with([
                'pangkat',
                'unitKerja',
                'riwayatPangkat' => fn ($q) => $q->aktif()->latest('tmt'),
            ])
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
            ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan));

        // Build KGB date expression untuk filter status
        $kgbDateExpr = $isMySQL
            ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR)'
            : "date(rp_kgb.tmt, '+2 years')";

        // Filter KGB berdasarkan status
        if ($status === null) {
            // Default: KGB yang akan jatuh tempo dalam X bulan ke depan
            $query->whereRaw("{$kgbDateExpr} <= ?", [$maxKgbDate]);
        } else {
            $today = Carbon::today()->toDateString();

            match ($status) {
                'jatuh-tempo' => $query->whereRaw("{$kgbDateExpr} <= ?", [$today]),
                'segera'      => $query->whereRaw("{$kgbDateExpr} > ? AND {$kgbDateExpr} <= ?", [
                                    $today,
                                    Carbon::today()->addDays(60)->toDateString(),
                                 ]),
                'mendekati'   => $query->whereRaw("{$kgbDateExpr} > ? AND {$kgbDateExpr} <= ?", [
                                    Carbon::today()->addDays(60)->toDateString(),
                                    Carbon::today()->addDays(90)->toDateString(),
                                 ]),
                'aman'        => $query->whereRaw("{$kgbDateExpr} > ?", [
                                    Carbon::today()->addDays(90)->toDateString(),
                                 ]),
                default       => $query->whereRaw("{$kgbDateExpr} <= ?", [$maxKgbDate]),
            };
        }

        // Order berdasarkan sisa hari (kurang hari = lebih mendesak)
        if ($isMySQL) {
            $query->orderByRaw('DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR) ASC');
        } else {
            $query->orderByRaw("date(rp_kgb.tmt, '+2 years') ASC");
        }

        return $query->paginate($perPage)
            ->through(function (Pegawai $pegawai) use ($isMySQL): array {
                $riwayatPangkatAktif = $this->getRiwayatPangkatAktif($pegawai);
                $statusKgb = $this->getKgbStatus($pegawai);

                return [
                    'id' => $pegawai->id,
                    'nip' => $pegawai->nip,
                    'nama_lengkap' => $pegawai->nama_lengkap,
                    'unit_kerja' => $pegawai->unitKerja?->nama ?? '-',
                    'pangkat_gol' => $pegawai->nama_pangkat_lengkap,
                    'tmt_pangkat' => $riwayatPangkatAktif?->tmt?->toDateString(),
                    'tanggal_kgb_berikutnya' => $statusKgb['tanggal_kgb_berikutnya']->toDateString(),
                    'sisa_hari' => $statusKgb['sisa_hari'],
                    'status' => $statusKgb['status'],
                ];
            });
    }

    public function getKgbStats(
        int $months = 3,
        ?string $unitKerjaId = null,
        ?string $golongan = null,
    ): array
    {
        $maxSisaHari = $months * 30;
        $driver = DB::connection()->getDriverName();
        $isMySQL = $driver === 'mysql';

        // Hitung tanggal KGB maksimum di PHP
        $maxKgbDate = Carbon::today()->addDays($maxSisaHari)->toDateString();
        $today = Carbon::today()->toDateString();

        // Build KGB date expression untuk MySQL dan SQLite
        $kgbDateExpr = $isMySQL
            ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR)'
            : "date(rp_kgb.tmt, '+2 years')";

        // Hitung sisa hari di SQL (KGB date - today)
        $sisaHariExpr = $isMySQL
            ? "DATEDIFF({$kgbDateExpr}, CURDATE())"
            : "CAST(julianday({$kgbDateExpr}) - julianday('{$today}') AS INTEGER)";

        $row = Pegawai::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN {$sisaHariExpr} <= 0 THEN 1 ELSE 0 END) as jatuh_tempo,
                SUM(CASE WHEN {$sisaHariExpr} BETWEEN 1 AND 60 THEN 1 ELSE 0 END) as segera,
                SUM(CASE WHEN {$sisaHariExpr} BETWEEN 61 AND 90 THEN 1 ELSE 0 END) as mendekati,
                SUM(CASE WHEN {$sisaHariExpr} > 90 THEN 1 ELSE 0 END) as aman
            ")
            ->join('riwayat_pangkat as rp_kgb', function ($join) {
                $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kgb.is_aktif', true);
            })
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->when($unitKerjaId !== null, fn ($q) => $q->where('pegawai.ref_unit_kerja_id', $unitKerjaId))
            ->when($golongan !== null, fn ($q) => $q->byGolongan($golongan))
            ->whereRaw(
                $isMySQL
                    ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR) <= ?'
                    : "date(rp_kgb.tmt, '+2 years') <= ?",
                [$maxKgbDate]
            )
            ->first();

        return [
            'total'      => (int) ($row?->total ?? 0),
            'jatuhTempo' => (int) ($row?->jatuh_tempo ?? 0),
            'segera'     => (int) ($row?->segera ?? 0),
            'mendekati'  => (int) ($row?->mendekati ?? 0),
            'aman'       => (int) ($row?->aman ?? 0),
        ];
    }

    public function getKgbStatus(Pegawai $pegawai): array
    {
        $riwayatPangkatAktif = $this->getRiwayatPangkatAktif($pegawai);

        if ($riwayatPangkatAktif === null || $riwayatPangkatAktif->tmt === null) {
            throw new InvalidArgumentException('Pegawai tidak memiliki riwayat pangkat aktif.');
        }

        $tanggalKgbBerikutnya = Carbon::parse($riwayatPangkatAktif->tmt)->addYears(2)->startOfDay();
        $sisaHari = (int) Carbon::today()->diffInDays($tanggalKgbBerikutnya, false);

        return [
            'tanggal_kgb_berikutnya' => $tanggalKgbBerikutnya,
            'sisa_hari'              => $sisaHari,
            'status'                 => $this->resolveStatusLabel($sisaHari),
        ];
    }

    protected function getRiwayatPangkatAktif(Pegawai $pegawai): ?RiwayatPangkat
    {
        if (! $pegawai->relationLoaded('riwayatPangkat')) {
            $pegawai->load([
                'riwayatPangkat' => fn ($query) => $query->aktif()->latest('tmt'),
            ]);
        }

        return $pegawai->riwayatPangkat->first();
    }

    protected function resolveStatusLabel(int $sisaHari): string
    {
        if ($sisaHari <= 0) {
            return 'Sudah Jatuh Tempo';
        }

        if ($sisaHari <= 60) {
            return 'Segera';
        }

        if ($sisaHari <= 90) {
            return 'Mendekati';
        }

        return 'Aman';
    }
}
