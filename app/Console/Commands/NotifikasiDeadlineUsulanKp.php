<?php

namespace App\Console\Commands;

use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Notifications\KenaikanPangkatDeadlineNotification;
use App\Services\KenaikanPangkatMonitoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifikasiDeadlineUsulanKp extends Command
{
    protected $signature = 'sikep:notifikasi-deadline-kp {--threshold-days=14}';

    protected $description = 'Kirim notifikasi deadline usulan kenaikan pangkat yang mendekati batas usul';

    public function handle(KenaikanPangkatMonitoringService $monitoringService): int
    {
        $thresholdDays = (int) $this->option('threshold-days');
        $count = 0;

        UsulanKenaikanPangkat::query()
            ->with(['pegawai.riwayatPangkat' => fn ($query) => $query->aktif()->orderByDesc('tmt')])
            ->whereIn('state', ['DRAFT', 'PERLU_PERBAIKAN'])
            ->chunkById(100, function ($usulanList) use ($monitoringService, $thresholdDays, &$count): void {
                foreach ($usulanList as $usulan) {
                    $pegawai = $usulan->pegawai;

                    if ($pegawai === null) {
                        continue;
                    }

                    try {
                        $status = $monitoringService->getKpStatus($pegawai);
                        $batasUsul = Carbon::parse($status['batas_usul'])->startOfDay();
                        $sisaHari = Carbon::today()->diffInDays($batasUsul, false);

                        if ($sisaHari > $thresholdDays) {
                            continue;
                        }

                        if ($this->hasRecentNotification($pegawai, $usulan->id)) {
                            continue;
                        }

                        $pegawai->notify(new KenaikanPangkatDeadlineNotification(
                            usulanId: $usulan->id,
                            batasUsul: $batasUsul,
                            sisaHari: $sisaHari,
                            url: route('kenaikan-pangkat.usulan.show', $usulan),
                        ));

                        $count++;
                    } catch (\Exception $e) {
                        $this->error("Gagal kirim notifikasi deadline KP untuk usulan ID {$usulan->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Notifikasi deadline KP terkirim ke {$count} pegawai.");

        return self::SUCCESS;
    }

    private function hasRecentNotification(object $pegawai, string $usulanId): bool
    {
        return $pegawai->notifications()
            ->where('type', KenaikanPangkatDeadlineNotification::class)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereJsonContains('data->usulan_id', $usulanId)
            ->exists();
    }
}
