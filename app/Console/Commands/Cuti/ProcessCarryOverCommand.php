<?php

namespace App\Console\Commands\Cuti;

use App\Models\Pegawai;
use App\Services\Cuti\CarryOverProcessorService;
use Illuminate\Console\Command;

class ProcessCarryOverCommand extends Command
{
    protected $signature = 'cuti:carry-over {--nip= : Proses untuk single NIP saja}';

    protected $description = 'Proses carry-over saldo CT tiap awal tahun';

    public function handle(CarryOverProcessorService $svc): int
    {
        $query = Pegawai::aktif();

        if ($nip = $this->option('nip')) {
            $query->where('nip', $nip);
        }

        $count = 0;
        $query->chunk(100, function ($pegawais) use ($svc, &$count) {
            foreach ($pegawais as $p) {
                try {
                    $svc->process($p);
                    $count++;
                } catch (\Throwable $e) {
                    $this->error("Gagal {$p->nip}: {$e->getMessage()}");
                    report($e);
                }
            }
        });

        $this->info("Carry-over selesai untuk {$count} pegawai");

        return self::SUCCESS;
    }
}
