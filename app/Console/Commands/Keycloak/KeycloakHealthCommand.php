<?php

namespace App\Console\Commands\Keycloak;

use App\Keycloak\Contracts\KeycloakSyncServiceInterface;
use Illuminate\Console\Command;

/**
 * Artisan command untuk memeriksa konektivitas Keycloak dan status circuit breaker.
 *
 * Menampilkan informasi kesehatan koneksi termasuk circuit state,
 * failure count, dan timestamps success/failure terakhir.
 */
class KeycloakHealthCommand extends Command
{
    protected $signature = 'keycloak:health';

    protected $description = 'Periksa konektivitas Keycloak dan status circuit breaker';

    public function handle(KeycloakSyncServiceInterface $syncService): int
    {
        $this->info('Memeriksa konektivitas Keycloak...');
        $this->newLine();

        $health = $syncService->healthCheck();

        // Tampilkan status kesehatan
        $statusLabel = $health->isHealthy
            ? '<fg=green>✅ HEALTHY</>'
            : '<fg=red>❌ UNHEALTHY</>';

        $this->line("Status       : {$statusLabel}");

        // Tampilkan circuit state dengan warna
        $circuitLabel = match ($health->circuitState) {
            'closed' => '<fg=green>CLOSED</>',
            'open' => '<fg=red>OPEN</>',
            'half_open' => '<fg=yellow>HALF_OPEN</>',
            default => $health->circuitState,
        };

        $this->line("Circuit State: {$circuitLabel}");
        $this->line("Failure Count: {$health->failureCount}");

        // Timestamps
        $lastSuccess = $health->lastSuccessAt?->format('Y-m-d H:i:s') ?? '-';
        $lastFailure = $health->lastFailureAt?->format('Y-m-d H:i:s') ?? '-';

        $this->line("Last Success : {$lastSuccess}");
        $this->line("Last Failure : {$lastFailure}");

        if ($health->lastError !== null) {
            $this->newLine();
            $this->warn("Last Error: {$health->lastError}");
        }

        return $health->isHealthy ? self::SUCCESS : self::FAILURE;
    }
}
