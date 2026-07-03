<?php

namespace App\Console\Commands\Keycloak;

use App\Keycloak\Contracts\KeycloakSyncServiceInterface;
use App\Keycloak\DataTransferObjects\SyncResult;
use App\Models\Pegawai;
use Illuminate\Console\Command;

/**
 * Artisan command untuk menjalankan sinkronisasi data Pegawai ke Keycloak.
 *
 * Mendukung full sync, incremental sync, dan sync per NIP individual.
 */
class KeycloakSyncCommand extends Command
{
    protected $signature = 'keycloak:sync
                            {--type=incremental : Tipe sync (full|incremental)}
                            {--nip= : Sync pegawai tertentu berdasarkan NIP (18 digit)}';

    protected $description = 'Sinkronisasi data Pegawai ke Keycloak';

    public function handle(KeycloakSyncServiceInterface $syncService): int
    {
        $nip = $this->option('nip');
        $type = $this->option('type');

        // Jika --nip diberikan, sync single pegawai
        if ($nip !== null) {
            return $this->syncByNip($syncService, $nip);
        }

        // Validasi tipe sync
        if (! in_array($type, ['full', 'incremental'], true)) {
            $this->error("Tipe sync tidak valid: {$type}. Gunakan 'full' atau 'incremental'.");

            return self::FAILURE;
        }

        return $this->runSync($syncService, $type);
    }

    /**
     * Sync single Pegawai berdasarkan NIP.
     */
    private function syncByNip(KeycloakSyncServiceInterface $syncService, string $nip): int
    {
        // Validasi format NIP (18 digit)
        if (! preg_match('/^\d{18}$/', $nip)) {
            $this->error("Format NIP tidak valid: {$nip}. NIP harus 18 digit angka.");

            return self::FAILURE;
        }

        $this->info("Memulai sync untuk NIP: {$nip}...");

        $pegawai = Pegawai::where('nip', $nip)->first();

        if ($pegawai === null) {
            $this->error("Pegawai dengan NIP {$nip} tidak ditemukan.");

            return self::FAILURE;
        }

        $result = $syncService->syncPegawai($pegawai);

        $this->displayResult($result);

        return $result->success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Jalankan full atau incremental sync.
     */
    private function runSync(KeycloakSyncServiceInterface $syncService, string $type): int
    {
        $label = $type === 'full' ? 'Full Sync' : 'Incremental Sync';
        $this->info("Memulai {$label}...");

        $result = $type === 'full'
            ? $syncService->fullSync()
            : $syncService->incrementalSync();

        $this->displayResult($result);

        return $result->success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Tampilkan hasil sync ke console dengan format tabel berwarna.
     */
    private function displayResult(SyncResult $result): void
    {
        $this->newLine();

        if ($result->success) {
            $this->info('✅ Sync berhasil diselesaikan.');
        } else {
            $this->error('❌ Sync selesai dengan error.');
        }

        $this->newLine();
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Created', "<fg=green>{$result->created}</>"],
                ['Updated', "<fg=yellow>{$result->updated}</>"],
                ['Skipped', "<fg=gray>{$result->skipped}</>"],
                ['Conflicts', "<fg=magenta>{$result->conflicts}</>"],
                ['Errors', $result->errors > 0 ? "<fg=red>{$result->errors}</>" : "<fg=green>{$result->errors}</>"],
            ],
        );

        if ($result->errorDetails !== []) {
            $this->newLine();
            $this->warn('Detail Error:');
            foreach ($result->errorDetails as $detail) {
                $this->line("  • NIP {$detail['nip']}: {$detail['error']}");
            }
        }
    }
}
