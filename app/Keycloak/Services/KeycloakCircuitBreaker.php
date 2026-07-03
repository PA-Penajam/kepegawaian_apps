<?php

namespace App\Keycloak\Services;

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Enums\CircuitState;
use App\Keycloak\Exceptions\KeycloakCircuitOpenException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Implementasi circuit breaker pattern untuk koneksi ke Keycloak.
 *
 * Melindungi aplikasi dari cascading failures dengan mengelola
 * state machine: CLOSED → OPEN → HALF_OPEN → CLOSED.
 * State disimpan di cache dengan driver yang dapat dikonfigurasi.
 */
class KeycloakCircuitBreaker implements CircuitBreakerInterface
{
    /** Cache key untuk state circuit breaker */
    private const string CACHE_KEY_STATE = 'keycloak_circuit_state';

    /** Cache key untuk jumlah failure berturut-turut */
    private const string CACHE_KEY_FAILURES = 'keycloak_circuit_failures';

    /** Cache key untuk jumlah success berturut-turut */
    private const string CACHE_KEY_SUCCESSES = 'keycloak_circuit_successes';

    /** Cache key untuk timestamp failure terakhir */
    private const string CACHE_KEY_LAST_FAILURE_AT = 'keycloak_circuit_last_failure_at';

    /** Cache key untuk timestamp success terakhir */
    private const string CACHE_KEY_LAST_SUCCESS_AT = 'keycloak_circuit_last_success_at';

    /** Jumlah failure berturut-turut sebelum OPEN */
    private readonly int $failureThreshold;

    /** Detik recovery timeout sebelum transisi OPEN → HALF_OPEN */
    private readonly int $recoveryTimeoutSeconds;

    /** Jumlah success berturut-turut untuk HALF_OPEN → CLOSED */
    private readonly int $successThreshold;

    /** Cache store yang digunakan untuk menyimpan state */
    private readonly CacheRepository $cache;

    public function __construct()
    {
        $this->failureThreshold = config('keycloak.circuit_breaker.failure_threshold', 5);
        $this->recoveryTimeoutSeconds = config('keycloak.circuit_breaker.recovery_timeout_seconds', 30);
        $this->successThreshold = config('keycloak.circuit_breaker.success_threshold', 2);

        $driver = config('keycloak.circuit_breaker.cache_driver');
        $this->cache = $driver ? Cache::store($driver) : Cache::store();
    }

    /**
     * Eksekusi operasi dengan circuit breaker protection.
     *
     * Jika circuit CLOSED: operasi dijalankan normal.
     * Jika circuit OPEN dan sudah recovery timeout: transisi ke HALF_OPEN, coba operasi.
     * Jika circuit OPEN dan belum recovery timeout: throw exception.
     * Jika circuit HALF_OPEN: operasi dijalankan sebagai probe.
     */
    public function call(callable $operation): mixed
    {
        $state = $this->resolveCurrentState();

        if ($state === CircuitState::Open) {
            if ($this->shouldTryRecovery()) {
                $this->transitionTo(CircuitState::HalfOpen);
            } else {
                throw new KeycloakCircuitOpenException(
                    'Keycloak tidak tersedia. Circuit breaker dalam state OPEN. '
                    ."Failures: {$this->getFailureCount()}/{$this->failureThreshold}"
                );
            }
        }

        try {
            $result = $operation();
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Cek apakah circuit sedang open (blocking requests).
     *
     * Mengembalikan true jika state OPEN dan belum melewati recovery timeout.
     */
    public function isOpen(): bool
    {
        $state = $this->resolveCurrentState();

        if ($state !== CircuitState::Open) {
            return false;
        }

        // Jika sudah waktunya recovery, bukan lagi "open" secara efektif
        return ! $this->shouldTryRecovery();
    }

    /**
     * Dapatkan state saat ini (closed, open, half_open).
     */
    public function getState(): string
    {
        return $this->resolveCurrentState()->value;
    }

    /**
     * Reset circuit breaker ke closed state.
     *
     * Manual reset yang mereset semua counter dan state ke CLOSED.
     */
    public function reset(): void
    {
        $this->cache->put(self::CACHE_KEY_STATE, CircuitState::Closed->value);
        $this->cache->put(self::CACHE_KEY_FAILURES, 0);
        $this->cache->put(self::CACHE_KEY_SUCCESSES, 0);
    }

    /**
     * Dapatkan jumlah failure berturut-turut.
     */
    public function getFailureCount(): int
    {
        return (int) $this->cache->get(self::CACHE_KEY_FAILURES, 0);
    }

    /**
     * Dapatkan jumlah success berturut-turut.
     */
    public function getSuccessCount(): int
    {
        return (int) $this->cache->get(self::CACHE_KEY_SUCCESSES, 0);
    }

    /**
     * Dapatkan timestamp failure terakhir.
     */
    public function getLastFailureAt(): ?int
    {
        return $this->cache->get(self::CACHE_KEY_LAST_FAILURE_AT);
    }

    /**
     * Dapatkan timestamp success terakhir.
     */
    public function getLastSuccessAt(): ?int
    {
        return $this->cache->get(self::CACHE_KEY_LAST_SUCCESS_AT);
    }

    /**
     * Ambil state saat ini dari cache.
     */
    private function resolveCurrentState(): CircuitState
    {
        $stateValue = $this->cache->get(self::CACHE_KEY_STATE);

        if ($stateValue === null) {
            return CircuitState::Closed;
        }

        return CircuitState::from($stateValue);
    }

    /**
     * Cek apakah sudah waktunya mencoba recovery (OPEN → HALF_OPEN).
     *
     * Recovery dicoba jika sudah lewat recovery_timeout_seconds sejak failure terakhir.
     */
    private function shouldTryRecovery(): bool
    {
        $lastFailureAt = $this->cache->get(self::CACHE_KEY_LAST_FAILURE_AT);

        if ($lastFailureAt === null) {
            return true;
        }

        $elapsedSeconds = time() - $lastFailureAt;

        return $elapsedSeconds >= $this->recoveryTimeoutSeconds;
    }

    /**
     * Catat success dan lakukan transisi state jika diperlukan.
     *
     * Di state HALF_OPEN: jika success mencapai threshold, transisi ke CLOSED.
     * Di state CLOSED: reset failure count.
     */
    private function recordSuccess(): void
    {
        $currentSuccesses = $this->getSuccessCount() + 1;
        $this->cache->put(self::CACHE_KEY_SUCCESSES, $currentSuccesses);
        $this->cache->put(self::CACHE_KEY_FAILURES, 0);
        $this->cache->put(self::CACHE_KEY_LAST_SUCCESS_AT, time());

        $state = $this->resolveCurrentState();

        if ($state === CircuitState::HalfOpen && $currentSuccesses >= $this->successThreshold) {
            $this->transitionTo(CircuitState::Closed);
        }
    }

    /**
     * Catat failure dan lakukan transisi state jika diperlukan.
     *
     * Di state HALF_OPEN: langsung transisi ke OPEN.
     * Di state CLOSED: jika failure mencapai threshold, transisi ke OPEN.
     */
    private function recordFailure(): void
    {
        $currentFailures = $this->getFailureCount() + 1;
        $this->cache->put(self::CACHE_KEY_FAILURES, $currentFailures);
        $this->cache->put(self::CACHE_KEY_SUCCESSES, 0);
        $this->cache->put(self::CACHE_KEY_LAST_FAILURE_AT, time());

        $state = $this->resolveCurrentState();

        if ($state === CircuitState::HalfOpen) {
            $this->transitionTo(CircuitState::Open);
        } elseif ($currentFailures >= $this->failureThreshold) {
            $this->transitionTo(CircuitState::Open);
        }
    }

    /**
     * Transisi ke state baru dan reset counter yang tidak relevan.
     *
     * Saat transisi ke CLOSED: reset failure count.
     * Saat transisi ke OPEN: reset success count.
     * Saat transisi ke HALF_OPEN: reset success count untuk mulai tracking probe.
     */
    private function transitionTo(CircuitState $newState): void
    {
        $this->cache->put(self::CACHE_KEY_STATE, $newState->value);

        match ($newState) {
            CircuitState::Closed => $this->cache->put(self::CACHE_KEY_FAILURES, 0),
            CircuitState::Open => $this->cache->put(self::CACHE_KEY_SUCCESSES, 0),
            CircuitState::HalfOpen => $this->cache->put(self::CACHE_KEY_SUCCESSES, 0),
        };
    }
}
