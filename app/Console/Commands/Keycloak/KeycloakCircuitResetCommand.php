<?php

namespace App\Console\Commands\Keycloak;

use App\Keycloak\Contracts\CircuitBreakerInterface;
use Illuminate\Console\Command;

/**
 * Artisan command untuk manual reset circuit breaker ke state CLOSED.
 *
 * Mereset semua counter (failure, success) dan transisi state ke CLOSED.
 */
class KeycloakCircuitResetCommand extends Command
{
    protected $signature = 'keycloak:circuit-reset';

    protected $description = 'Reset circuit breaker Keycloak ke state CLOSED';

    public function handle(CircuitBreakerInterface $circuitBreaker): int
    {
        $currentState = $circuitBreaker->getState();
        $failureCount = $circuitBreaker->getFailureCount();

        $this->info("State saat ini: {$currentState} (failures: {$failureCount})");

        $circuitBreaker->reset();

        $this->newLine();
        $this->info('✅ Circuit breaker berhasil di-reset ke state CLOSED.');
        $this->line('   Failure count: 0');
        $this->line('   Success count: 0');

        return self::SUCCESS;
    }
}
