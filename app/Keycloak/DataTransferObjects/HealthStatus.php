<?php

namespace App\Keycloak\DataTransferObjects;

use Carbon\CarbonInterface;

/**
 * DTO untuk status kesehatan koneksi ke Keycloak.
 *
 * Menyimpan informasi circuit breaker state, failure count,
 * dan timestamps success/failure terakhir.
 */
readonly class HealthStatus
{
    public function __construct(
        public bool $isHealthy,
        public string $circuitState,
        public int $failureCount,
        public ?CarbonInterface $lastSuccessAt,
        public ?CarbonInterface $lastFailureAt,
        public ?string $lastError = null,
    ) {}
}
