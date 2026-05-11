<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use App\Notifications\KenaikanPangkatEligibleNotification;
use App\Services\KenaikanPangkatMonitoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendKenaikanPangkatNotification extends Command
{
    protected $signature = 'sikep:notifikasi-kp {--bulan=} {--tahun=}';

    protected $description = 'Kirim notifikasi KP ke pegawai mendekati eligible untuk periode bulanan';

    public function handle(KenaikanPangkatMonitoringService $service): int
    {
        $bulan = (int) ($this->option('bulan') ?? Carbon::today()->month);
        $tahun = (int) ($this->option('tahun') ?? Carbon::today()->year);
        $lookahead = (int) config('sikep.kp.lookahead_months', 6);

        $count = 0;

        for ($i = 0; $i < $lookahead; $i++) {
            $targetBulan = (($bulan + $i - 1) % 12) + 1;
            $targetTahun = $tahun + intdiv($bulan + $i - 1, 12);

            $data = $service->getUpcomingKenaikanPangkat($targetBulan, 1000, null, null, $targetTahun);

            foreach ($data->items() as $row) {
                if ($row['status'] !== 'Mendekati Eligible') {
                    continue;
                }

                $pegawai = Pegawai::find($row['id']);
                if ($pegawai === null) {
                    continue;
                }

                $sudahAda = $pegawai->notifications()
                    ->where('type', KenaikanPangkatEligibleNotification::class)
                    ->whereJsonContains('data->periode_bulan', $targetBulan)
                    ->whereJsonContains('data->periode_tahun', $targetTahun)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                try {
                    $batasUsulFormatted = Carbon::parse($row['batas_usul'])->translatedFormat('d F Y');
                    $pegawai->notify(new KenaikanPangkatEligibleNotification(
                        periodeBulan: $targetBulan,
                        periodeTahun: $targetTahun,
                        batasUsul: $batasUsulFormatted,
                    ));
                    $count++;
                } catch (\Exception $e) {
                    $this->error("Gagal kirim ke pegawai ID {$pegawai->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Notifikasi KP terkirim ke {$count} pegawai.");

        return self::SUCCESS;
    }
}
