<?php

namespace App\Console\Commands\Cuti;

use App\Models\Cuti\CutiEvent;
use App\Models\Cuti\CutiEventDelivery;
use App\Models\Cuti\CutiPengajuan;
use App\Services\Cuti\ConsumerRegistry;
use App\Services\Cuti\EventDispatcherService;
use Illuminate\Console\Command;

class ReplayEventsCommand extends Command
{
    protected $signature = 'cuti:replay-events
        {--consumer=sisukma : ID consumer tujuan di config cuti.consumers}
        {--state=DISETUJUI : State pengajuan cuti yang akan di-replay}
        {--aktif-sejak= : Hanya sertakan cuti dengan tanggal_selesai >= tanggal ini (YYYY-MM-DD)}';

    protected $description = 'Mengantrekan ulang event cuti untuk consumer tertentu sebagai backfill atau pemulihan';

    /**
     * Peta state pengajuan cuti ke tipe event outbox.
     *
     * @var array<string, string>
     */
    private const STATE_EVENT_MAP = [
        'DIVERIFIKASI' => 'cuti.diverifikasi',
        'DISETUJUI_ATASAN' => 'cuti.disetujui_atasan',
        'DISETUJUI' => 'cuti.disetujui',
        'DITOLAK_KEPEGAWAIAN' => 'cuti.ditolak',
        'DITOLAK_ATASAN' => 'cuti.ditolak',
        'DITOLAK_PEJABAT' => 'cuti.ditolak',
        'DIBATALKAN' => 'cuti.dibatalkan',
        'DICABUT_SETELAH_DISETUJUI' => 'cuti.dicabut',
    ];

    /**
     * Menjalankan antre ulang event cuti khusus untuk consumer target.
     */
    public function handle(ConsumerRegistry $registry, EventDispatcherService $dispatcher): int
    {
        $consumer = trim((string) $this->option('consumer'));
        $state = strtoupper(trim((string) $this->option('state')));
        $aktifSejak = $this->option('aktif-sejak');

        try {
            $registry->get($consumer);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $eventType = self::STATE_EVENT_MAP[$state] ?? null;
        if ($eventType === null) {
            $this->error("State [{$state}] tidak memiliki pemetaan event replay.");

            return self::FAILURE;
        }

        $query = CutiPengajuan::query()
            ->with('pegawai')
            ->where('state', $state);

        if (is_string($aktifSejak) && trim($aktifSejak) !== '') {
            $query->whereDate('tanggal_selesai', '>=', trim($aktifSejak));
        }

        $total = 0;

        $query->chunkById(100, function ($pengajuanList) use ($consumer, $eventType, $dispatcher, &$total): void {
            foreach ($pengajuanList as $pengajuan) {
                $existingEvent = CutiEvent::query()
                    ->where('aggregate_type', 'PengajuanCuti')
                    ->where('aggregate_id', $pengajuan->id)
                    ->where('event_type', $eventType)
                    ->latest('occurred_at')
                    ->first();

                if ($existingEvent !== null) {
                    CutiEventDelivery::query()->updateOrCreate(
                        [
                            'event_id' => $existingEvent->id,
                            'consumer_id' => $consumer,
                        ],
                        [
                            'status' => 'pending',
                            'attempts' => 0,
                            'last_attempt_at' => null,
                            'delivered_at' => null,
                            'last_error' => null,
                            'next_retry_at' => null,
                        ],
                    );
                } else {
                    $dispatcher->dispatchForConsumer($eventType, $pengajuan, $consumer);
                }

                $total++;
            }
        });

        $this->info("Replay selesai: {$total} pengajuan diantrekan ulang untuk consumer [{$consumer}].");

        return self::SUCCESS;
    }
}
