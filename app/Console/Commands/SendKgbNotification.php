<?php

namespace App\Console\Commands;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Notifications\KgbJatuhTempoNotification;
use App\Services\KgbMonitoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
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
            ->select('pegawai.*')
            ->join('riwayat_pangkat as rp_kgb', function ($join) {
                $join->on('rp_kgb.pegawai_id', '=', 'pegawai.id')
                    ->where('rp_kgb.is_aktif', true)
                    ->whereNull('rp_kgb.deleted_at');
            })
            ->with(['riwayatPangkat' => fn ($q) => $q->aktif()->latest('tmt')])
            ->whereIn('status_pegawai', [
                StatusPegawai::Aktif->value,
                StatusPegawai::MutasiKeluar->value,
            ])
            ->whereNotNull('pegawai.email')
            ->whereRaw("{$kgbDateExpr} <= ?", [$batasNotif])
            ->get();

        // Preload notifikasi database existing secara chunked untuk mencegah duplikasi pengiriman
        $existingKgbDatesByPegawai = [];
        foreach ($pegawaiList->pluck('id')->chunk(500) as $chunkIds) {
            $notifications = DatabaseNotification::query()
                ->where('notifiable_type', Pegawai::class)
                ->where('type', KgbJatuhTempoNotification::class)
                ->whereIn('notifiable_id', $chunkIds->all())
                ->get(['notifiable_id', 'data']);

            foreach ($notifications as $notification) {
                $kgbDate = $notification->data['kgb_date'] ?? null;
                if ($kgbDate !== null) {
                    $existingKgbDatesByPegawai[$notification->notifiable_id][$kgbDate] = true;
                }
            }
        }

        $count = 0;
        foreach ($pegawaiList as $pegawai) {
            try {
                $kgbStatus = $service->getKgbStatus($pegawai);
                $kgbDateString = $kgbStatus['tanggal_kgb_berikutnya']->toDateString();

                if (isset($existingKgbDatesByPegawai[$pegawai->id][$kgbDateString])) {
                    continue;
                }

                $pegawai->notify(new KgbJatuhTempoNotification(
                    kgbDate: $kgbStatus['tanggal_kgb_berikutnya'],
                    sisaHari: $kgbStatus['sisa_hari'],
                    status: $kgbStatus['status'],
                ));

                $existingKgbDatesByPegawai[$pegawai->id][$kgbDateString] = true;
                $count++;
            } catch (\Exception $e) {
                $this->error("Gagal kirim notifikasi ke pegawai ID {$pegawai->id}: {$e->getMessage()}");
            }
        }

        $this->info("Notifikasi KGB terkirim ke {$count} pegawai.");

        return self::SUCCESS;
    }
}
