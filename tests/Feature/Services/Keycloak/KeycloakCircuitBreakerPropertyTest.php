<?php

/**
 * Property-Based Test: Circuit Breaker State Machine
 *
 * Memvalidasi properti universal state machine circuit breaker
 * menggunakan sequence operasi acak yang di-generate secara random.
 *
 * **Validates: Requirements 5.1, 5.2, 5.3, 5.4, 5.5, 5.6**
 */

use App\Keycloak\Enums\CircuitState;
use App\Keycloak\Exceptions\KeycloakCircuitOpenException;
use App\Keycloak\Services\KeycloakCircuitBreaker;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Konfigurasi circuit breaker untuk testing
    config()->set('keycloak.circuit_breaker.failure_threshold', 5);
    config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', 30);
    config()->set('keycloak.circuit_breaker.success_threshold', 2);
    config()->set('keycloak.circuit_breaker.cache_driver', 'array');
});

/**
 * Helper: Membuat sequence operasi acak (success/failure).
 *
 * @return array<int, bool> Array dari boolean, true = success, false = failure
 */
function generateRandomSequence(int $length = 50): array
{
    $sequence = [];
    for ($i = 0; $i < $length; $i++) {
        $sequence[] = (bool) random_int(0, 1);
    }

    return $sequence;
}

/**
 * Helper: Menjalankan operasi pada circuit breaker.
 * Mengembalikan true jika operasi berhasil dijalankan, false jika circuit open.
 */
function executeOperation(KeycloakCircuitBreaker $breaker, bool $shouldSucceed): string
{
    try {
        $breaker->call(function () use ($shouldSucceed) {
            if (! $shouldSucceed) {
                throw new RuntimeException('operasi gagal');
            }

            return 'berhasil';
        });

        return 'success';
    } catch (KeycloakCircuitOpenException) {
        return 'circuit_open';
    } catch (RuntimeException) {
        return 'operation_failed';
    }
}

// ============================================================================
// Property 1: N consecutive failures (N >= failure_threshold) di CLOSED
//             SELALU menghasilkan state OPEN (Req 5.2)
// ============================================================================

test('PROPERTY: N consecutive failures >= threshold di CLOSED selalu menghasilkan OPEN state', function () {
    // Jalankan 50 iterasi dengan threshold berbeda-beda
    for ($iteration = 0; $iteration < 50; $iteration++) {
        // Variasikan failure threshold antara 3-8
        $threshold = random_int(3, 8);
        config()->set('keycloak.circuit_breaker.failure_threshold', $threshold);

        $breaker = new KeycloakCircuitBreaker;

        // Hasilkan N failures di mana N >= threshold
        $extraFailures = random_int(0, 3);
        $totalFailures = $threshold + $extraFailures;

        for ($i = 0; $i < $totalFailures; $i++) {
            executeOperation($breaker, false);
        }

        // PROPERTI: State harus OPEN setelah >= threshold failures
        expect($breaker->getState())
            ->toBe(CircuitState::Open->value, "Iterasi {$iteration}: Setelah {$totalFailures} failures (threshold={$threshold}), state harus OPEN");
    }
});

// ============================================================================
// Property 2: Di OPEN state (sebelum recovery timeout),
//             SEMUA panggilan SELALU throw KeycloakCircuitOpenException (Req 5.3)
// ============================================================================

test('PROPERTY: Di OPEN state sebelum recovery timeout, semua panggilan ditolak', function () {
    for ($iteration = 0; $iteration < 50; $iteration++) {
        config()->set('keycloak.circuit_breaker.failure_threshold', 5);
        $breaker = new KeycloakCircuitBreaker;

        // Paksa ke OPEN state
        for ($i = 0; $i < 5; $i++) {
            executeOperation($breaker, false);
        }

        expect($breaker->getState())->toBe(CircuitState::Open->value);

        // Coba sejumlah panggilan acak (1-10), semuanya harus ditolak
        $attempts = random_int(1, 10);
        for ($i = 0; $i < $attempts; $i++) {
            $shouldSucceed = (bool) random_int(0, 1);
            $result = executeOperation($breaker, $shouldSucceed);

            // PROPERTI: Semua panggilan harus ditolak dengan circuit_open
            expect($result)
                ->toBe('circuit_open', "Iterasi {$iteration}, attempt {$i}: Panggilan harus ditolak saat circuit OPEN");
        }

        // State tetap OPEN
        expect($breaker->getState())->toBe(CircuitState::Open->value);
    }
});

// ============================================================================
// Property 3: Setelah recovery timeout di OPEN state,
//             SELALU transisi ke HALF_OPEN (Req 5.4)
// ============================================================================

test('PROPERTY: Setelah recovery timeout di OPEN, selalu transisi ke HALF_OPEN', function () {
    for ($iteration = 0; $iteration < 50; $iteration++) {
        // Variasikan recovery timeout
        $recoveryTimeout = random_int(10, 60);
        config()->set('keycloak.circuit_breaker.failure_threshold', 5);
        config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', $recoveryTimeout);

        $breaker = new KeycloakCircuitBreaker;

        // Paksa ke OPEN state
        for ($i = 0; $i < 5; $i++) {
            executeOperation($breaker, false);
        }

        expect($breaker->getState())->toBe(CircuitState::Open->value);

        // Simulasikan waktu berlalu melebihi recovery timeout
        $elapsedExtra = random_int(1, 30);
        Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - $recoveryTimeout - $elapsedExtra);

        // Panggil operasi yang berhasil → harus transisi ke HALF_OPEN
        $result = executeOperation($breaker, true);

        // PROPERTI: State harus HALF_OPEN setelah recovery timeout
        expect($breaker->getState())
            ->toBe(CircuitState::HalfOpen->value, "Iterasi {$iteration}: Setelah recovery timeout ({$recoveryTimeout}s + {$elapsedExtra}s), state harus HALF_OPEN");
        expect($result)->toBe('success');
    }
});

// ============================================================================
// Property 4: Di HALF_OPEN dengan M consecutive successes (M >= success_threshold),
//             SELALU transisi ke CLOSED (Req 5.6)
// ============================================================================

test('PROPERTY: Di HALF_OPEN dengan consecutive successes >= threshold, selalu transisi ke CLOSED', function () {
    for ($iteration = 0; $iteration < 50; $iteration++) {
        // Variasikan success threshold antara 2-5
        $successThreshold = random_int(2, 5);
        config()->set('keycloak.circuit_breaker.failure_threshold', 5);
        config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', 30);
        config()->set('keycloak.circuit_breaker.success_threshold', $successThreshold);

        $breaker = new KeycloakCircuitBreaker;

        // Setup: paksa ke OPEN → HALF_OPEN
        for ($i = 0; $i < 5; $i++) {
            executeOperation($breaker, false);
        }
        Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);

        // Panggilan pertama → transisi ke HALF_OPEN
        executeOperation($breaker, true);
        expect($breaker->getState())->toBe(CircuitState::HalfOpen->value);

        // Berikan consecutive successes sebanyak threshold - 1 (karena sudah 1 dari transisi)
        for ($i = 1; $i < $successThreshold; $i++) {
            executeOperation($breaker, true);
        }

        // PROPERTI: State harus CLOSED setelah cukup consecutive successes
        expect($breaker->getState())
            ->toBe(CircuitState::Closed->value, "Iterasi {$iteration}: Setelah {$successThreshold} successes di HALF_OPEN (threshold={$successThreshold}), state harus CLOSED");
    }
});

// ============================================================================
// Property 5: Di HALF_OPEN dengan 1 failure,
//             SELALU transisi kembali ke OPEN (Req 5.7 via 5.5)
// ============================================================================

test('PROPERTY: Di HALF_OPEN dengan 1 failure, selalu transisi kembali ke OPEN', function () {
    for ($iteration = 0; $iteration < 50; $iteration++) {
        config()->set('keycloak.circuit_breaker.failure_threshold', 5);
        config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', 30);
        config()->set('keycloak.circuit_breaker.success_threshold', 2);

        $breaker = new KeycloakCircuitBreaker;

        // Setup: paksa ke OPEN → HALF_OPEN
        for ($i = 0; $i < 5; $i++) {
            executeOperation($breaker, false);
        }
        Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);

        // Panggilan sukses pertama → transisi ke HALF_OPEN
        executeOperation($breaker, true);
        expect($breaker->getState())->toBe(CircuitState::HalfOpen->value);

        // Opsional: berikan 0 atau 1 success tambahan (tapi di bawah threshold)
        $extraSuccesses = random_int(0, 0); // Di HALF_OPEN, hanya butuh 1 success sebelum fail
        for ($i = 0; $i < $extraSuccesses; $i++) {
            executeOperation($breaker, true);
        }

        // 1 failure → harus kembali ke OPEN
        $result = executeOperation($breaker, false);

        // PROPERTI: State harus OPEN setelah 1 failure di HALF_OPEN
        expect($breaker->getState())
            ->toBe(CircuitState::Open->value, "Iterasi {$iteration}: Setelah 1 failure di HALF_OPEN, state harus OPEN");
        expect($result)->toBe('operation_failed');
    }
});

// ============================================================================
// Property 6: State selalu salah satu dari: closed, open, half_open (Req 5.1)
// ============================================================================

test('PROPERTY: State selalu merupakan salah satu dari closed, open, atau half_open', function () {
    $validStates = [
        CircuitState::Closed->value,
        CircuitState::Open->value,
        CircuitState::HalfOpen->value,
    ];

    for ($iteration = 0; $iteration < 50; $iteration++) {
        config()->set('keycloak.circuit_breaker.failure_threshold', 5);
        config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', 30);
        config()->set('keycloak.circuit_breaker.success_threshold', 2);

        $breaker = new KeycloakCircuitBreaker;

        // Generate sequence acak 20-40 operasi
        $sequenceLength = random_int(20, 40);
        $sequence = generateRandomSequence($sequenceLength);

        // PROPERTI: State awal harus valid
        expect($breaker->getState())->toBeIn($validStates);

        foreach ($sequence as $index => $shouldSucceed) {
            // Kadang simulasi recovery timeout untuk memungkinkan transisi
            if ($breaker->getState() === CircuitState::Open->value && random_int(0, 3) === 0) {
                Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);
            }

            executeOperation($breaker, $shouldSucceed);

            // PROPERTI: Setelah setiap operasi, state HARUS valid
            expect($breaker->getState())
                ->toBeIn($validStates, "Iterasi {$iteration}, step {$index}: State '{$breaker->getState()}' bukan state yang valid");
        }
    }
});

// ============================================================================
// Property 7: Setelah transisi state, counter yang tidak relevan di-reset (Req 5.11)
// ============================================================================

test('PROPERTY: Transisi ke CLOSED mereset failure count ke 0', function () {
    for ($iteration = 0; $iteration < 50; $iteration++) {
        $successThreshold = random_int(2, 4);
        config()->set('keycloak.circuit_breaker.failure_threshold', 5);
        config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', 30);
        config()->set('keycloak.circuit_breaker.success_threshold', $successThreshold);

        $breaker = new KeycloakCircuitBreaker;

        // Setup: CLOSED → OPEN → HALF_OPEN → CLOSED
        for ($i = 0; $i < 5; $i++) {
            executeOperation($breaker, false);
        }
        Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);

        // Transisi ke HALF_OPEN dan berikan successes untuk kembali ke CLOSED
        for ($i = 0; $i < $successThreshold; $i++) {
            executeOperation($breaker, true);
        }

        // PROPERTI: Setelah transisi ke CLOSED, failure count HARUS 0
        expect($breaker->getState())->toBe(CircuitState::Closed->value);
        expect($breaker->getFailureCount())
            ->toBe(0, "Iterasi {$iteration}: Failure count harus 0 setelah transisi ke CLOSED");
    }
});

test('PROPERTY: Transisi ke OPEN mereset success count ke 0', function () {
    for ($iteration = 0; $iteration < 50; $iteration++) {
        config()->set('keycloak.circuit_breaker.failure_threshold', 5);
        config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', 30);
        config()->set('keycloak.circuit_breaker.success_threshold', 2);

        $breaker = new KeycloakCircuitBreaker;

        // Berikan beberapa successes dulu agar success count > 0
        $initialSuccesses = random_int(1, 5);
        for ($i = 0; $i < $initialSuccesses; $i++) {
            executeOperation($breaker, true);
        }

        // Lalu berikan 5 failures → transisi ke OPEN
        for ($i = 0; $i < 5; $i++) {
            executeOperation($breaker, false);
        }

        // PROPERTI: Setelah transisi ke OPEN, success count HARUS 0
        expect($breaker->getState())->toBe(CircuitState::Open->value);
        expect($breaker->getSuccessCount())
            ->toBe(0, "Iterasi {$iteration}: Success count harus 0 setelah transisi ke OPEN");
    }
});

// ============================================================================
// Property 8: Total count invariant - failures reset saat transisi ke CLOSED,
//             successes reset saat transisi ke OPEN (Req 5.11)
// ============================================================================

test('PROPERTY: Full state machine cycle mempertahankan counter invariants', function () {
    for ($iteration = 0; $iteration < 50; $iteration++) {
        $failureThreshold = random_int(3, 7);
        $successThreshold = random_int(2, 4);
        $recoveryTimeout = random_int(15, 45);

        config()->set('keycloak.circuit_breaker.failure_threshold', $failureThreshold);
        config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', $recoveryTimeout);
        config()->set('keycloak.circuit_breaker.success_threshold', $successThreshold);

        $breaker = new KeycloakCircuitBreaker;

        // State 1: CLOSED - berikan failures sampai threshold
        expect($breaker->getState())->toBe(CircuitState::Closed->value);

        for ($i = 0; $i < $failureThreshold; $i++) {
            executeOperation($breaker, false);
        }

        // Invariant: Saat transisi ke OPEN, success count = 0
        expect($breaker->getState())->toBe(CircuitState::Open->value);
        expect($breaker->getSuccessCount())->toBe(0);

        // State 2: OPEN → HALF_OPEN via recovery timeout
        Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - $recoveryTimeout - 1);

        // State 3: HALF_OPEN - berikan successes sampai threshold
        for ($i = 0; $i < $successThreshold; $i++) {
            executeOperation($breaker, true);
        }

        // Invariant: Saat transisi ke CLOSED, failure count = 0
        expect($breaker->getState())->toBe(CircuitState::Closed->value);
        expect($breaker->getFailureCount())
            ->toBe(0, "Iterasi {$iteration}: Failure count harus 0 di CLOSED state");
        expect($breaker->getSuccessCount())
            ->toBeGreaterThanOrEqual(0, "Iterasi {$iteration}: Success count harus >= 0 di CLOSED state");
    }
});
