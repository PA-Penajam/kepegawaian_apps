<?php

namespace App\Console\Commands\Cuti;

use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanStateHistory;
use App\Services\Cuti\EventDispatcherService;
use App\States\Cuti\DibatalkanState;
use App\States\Cuti\DraftState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOverdueDraftsCommand extends Command
{
    protected $signature = 'cuti:expire-drafts {--days=7 : Jumlah hari batas kadaluarsa draft}';

    protected $description = 'Expire draft pengajuan cuti yang sudah lebih dari N hari';

    public function handle(EventDispatcherService $eventDispatcher): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $draftIds = CutiPengajuan::query()
            ->where('state', DraftState::$name)
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        $count = 0;

        foreach ($draftIds as $draftId) {
            try {
                $expiredPengajuan = DB::transaction(function () use ($draftId, $days, $eventDispatcher): ?CutiPengajuan {
                    $pengajuan = CutiPengajuan::query()
                        ->where('id', $draftId)
                        ->lockForUpdate()
                        ->first();

                    if ($pengajuan === null || $pengajuan->state->name() !== DraftState::$name) {
                        return null;
                    }

                    $fromState = $pengajuan->state->name();
                    $pengajuan->state->transitionTo(DibatalkanState::class);
                    $pengajuan->cancelled_at = now();
                    $pengajuan->save();

                    CutiPengajuanStateHistory::create([
                        'pengajuan_id' => $pengajuan->id,
                        'state_from' => $fromState,
                        'state_to' => $pengajuan->state->name(),
                        'aktor_pegawai_nip' => $pengajuan->pegawai_nip,
                        'catatan' => "Draft kadaluarsa otomatis setelah {$days} hari.",
                    ]);

                    $eventDispatcher->dispatch('cuti.dibatalkan', $pengajuan);

                    return $pengajuan;
                });

                if ($expiredPengajuan !== null) {
                    $count++;
                    $this->info("Expired: {$expiredPengajuan->nomor_pengajuan}");
                }
            } catch (\Throwable $e) {
                $this->error("Gagal expire {$draftId}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Selesai: {$count} draft diexpire dari {$draftIds->count()} total.");

        return self::SUCCESS;
    }
}
