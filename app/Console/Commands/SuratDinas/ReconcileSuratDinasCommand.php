<?php

namespace App\Console\Commands\SuratDinas;

use App\Enums\StatusSuratDinas;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\SuratDinas\SuratDinasRequestService;
use Illuminate\Console\Command;

class ReconcileSuratDinasCommand extends Command
{
    protected $signature = 'surat-dinas:reconcile {--limit=50}';

    protected $description = 'Menyelaraskan permintaan surat dinas yang tertunda atau kehilangan webhook dengan SiSukma';

    public function handle(SuratDinasRequestService $service): int
    {
        if (! config('sisukma.enabled')) {
            $this->info('Integrasi SiSukma nonaktif; rekonsiliasi dilewati.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $pendingStates = [
            StatusSuratDinas::BelumTerkirim->value,
            StatusSuratDinas::MenungguNomor->value,
            StatusSuratDinas::PerluPerbaikan->value,
            StatusSuratDinas::NomorTerbit->value,
            StatusSuratDinas::FinalTersedia->value,
        ];

        $requests = SuratDinasRequest::query()
            ->whereIn('status', $pendingStates)
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $ok = 0;
        $fail = 0;

        foreach ($requests as $request) {
            try {
                $service->reconcile($request);
                $ok++;
            } catch (\Throwable $e) {
                report($e);
                $fail++;
            }
        }

        $this->info("Rekonsiliasi surat dinas selesai: {$ok} sukses, {$fail} gagal.");

        return self::SUCCESS;
    }
}
