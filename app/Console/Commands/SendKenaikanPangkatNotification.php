<?php

namespace App\Console\Commands;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Notifications\KenaikanPangkatEligibleNotification;
use App\Services\KenaikanPangkatMonitoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendKenaikanPangkatNotification extends Command
{
    protected $signature = 'kp:notify';
    protected $description = 'Kirim notifikasi email ke pegawai yang kenaikan pangkatnya sudah/mendekati eligible';

    public function handle(KenaikanPangkatMonitoringService $service): int
    {
        $driver = DB::connection()->getDriverName();
        $tmtPlus4Year = $driver === 'mysql'
            ? 'DATE_ADD(rp_kp.tmt, INTERVAL 4 YEAR)'
            : "date(rp_kp.tmt, '+4 years')";

        $today = Carbon::today()->toDateString();
        $sixMonthsLater = Carbon::today()->addMonths(6)->toDateString();

        $pegawaiList = Pegawai::query()
            ->join('riwayat_pangkat as rp_kp', function ($join) {
                $join->on('rp_kp.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kp.is_aktif', true);
            })
            ->with(['riwayatPangkat' => fn ($q) => $q->aktif()->with('pangkat')->latest('tmt')])
            ->whereNotIn('status_pegawai', [
                StatusPegawai::Pensiun->value,
                StatusPegawai::Meninggal->value,
                StatusPegawai::Diberhentikan->value,
            ])
            ->whereNotNull('pegawai.email')
            ->whereRaw("{$tmtPlus4Year} <= ?", [$sixMonthsLater])
            ->get();

        $count = 0;
        foreach ($pegawaiList as $pegawai) {
            try {
                $kpStatus = $service->getKpStatus($pegawai);
                $pegawai->notify(new KenaikanPangkatEligibleNotification(
                    tmtKpBerikutnya: $kpStatus['tmt_kp_berikutnya'],
                    periodeUsul: $kpStatus['periode_usul'],
                    batasUsul: $kpStatus['batas_usul'],
                    sisaHariUsul: $kpStatus['sisa_hari_usul'],
                    status: $kpStatus['status'],
                ));
                $count++;
            } catch (\Exception $e) {
                $this->error("Gagal kirim notifikasi ke pegawai ID {$pegawai->id}: {$e->getMessage()}");
            }
        }

        $this->info("Notifikasi KP terkirim ke {$count} pegawai.");

        return self::SUCCESS;
    }
}
