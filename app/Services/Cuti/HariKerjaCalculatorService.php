<?php

namespace App\Services\Cuti;

use App\Models\Cuti\CutiLiburMaster;
use Carbon\Carbon;

class HariKerjaCalculatorService
{
    /**
     * Menghitung jumlah hari kerja antara dua tanggal (inklusif).
     * Skip hari Sabtu, Minggu, dan libur nasional dari cuti_libur_master.
     */
    public function hitung(Carbon $from, Carbon $to): int
    {
        // Ambil daftar tanggal libur dalam rentang
        $libur = CutiLiburMaster::query()
            ->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
            ->pluck('tanggal')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $count = 0;
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            // Skip weekend dan libur nasional
            if (! $cursor->isWeekend() && ! in_array($cursor->toDateString(), $libur, true)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
