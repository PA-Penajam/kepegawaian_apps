<?php

namespace App\Console\Commands;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Notifications\KgbJatuhTempoNotification;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendKgbNotification extends Command
{
    protected $signature = 'kgb:notify';

    protected $description = 'Kirim notifikasi email ke pegawai yang KGB-nya sudah/mendekati jatuh tempo';

    public function handle(KgbMonitoringService $service): int
    {
        $driver = DB::connection()->getDriverName();
        $kgbDateExpr = $driver === 'mysql'
            ? 'DATE_ADD(rp_kgb.tmt, INTERVAL 2 YEAR)'
            : "date(rp_kgb.tmt, '+2 years')";

        $batasNotif = Carbon::today()->addDays(90)->toDateString();

        $pegawaiList = Pegawai::query()
            ->join('riwayat_pangkat as rp_kgb', function ($join) {
                $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kgb.is_aktif', true);
            })
            ->with(['riwayatPangkat' => fn ($q) => $q->aktif()->latest('tmt')])
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->whereNotNull('pegawai.email')
            ->whereRaw("{$kgbDateExpr} <= ?", [$batasNotif])
            ->get();

        $count = 0;
        foreach ($pegawaiList as $pegawai) {
            try {
                $kgbStatus = $service->getKgbStatus($pegawai);
                $pegawai->notify(new KgbJatuhTempoNotification(
                    kgbDate: $kgbStatus['tanggal_kgb_berikutnya'],
                    sisaHari: $kgbStatus['sisa_hari'],
                    status: $kgbStatus['status'],
                ));
                $count++;
            } catch (\Exception $e) {
                $this->error("Gagal kirim notifikasi ke pegawai ID {$pegawai->id}: {$e->getMessage()}");
            }
        }

        $this->info("Notifikasi KGB terkirim ke {$count} pegawai.");

        return self::SUCCESS;
    }
}
