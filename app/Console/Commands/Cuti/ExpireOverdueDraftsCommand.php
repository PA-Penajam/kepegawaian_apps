<?php

namespace App\Console\Commands\Cuti;

use App\Models\Cuti\CutiPengajuan;
use App\States\Cuti\DibatalkanState;
use App\States\Cuti\DraftState;
use Illuminate\Console\Command;

class ExpireOverdueDraftsCommand extends Command
{
    protected $signature = 'cuti:expire-drafts {--days=7 : Jumlah hari batas kadaluarsa draft}';

    protected $description = 'Expire draft pengajuan cuti yang sudah lebih dari N hari';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $drafts = CutiPengajuan::query()
            ->where('state', DraftState::$name)
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = 0;

        foreach ($drafts as $pengajuan) {
            try {
                $pengajuan->state->transitionTo(DibatalkanState::class);
                $pengajuan->cancelled_at = now();
                $pengajuan->save();
                $count++;
                $this->info("Expired: {$pengajuan->nomor_pengajuan}");
            } catch (\Throwable $e) {
                $this->error("Gagal expire {$pengajuan->nomor_pengajuan}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Selesai: {$count} draft diexpire dari {$drafts->count()} total.");

        return self::SUCCESS;
    }
}
