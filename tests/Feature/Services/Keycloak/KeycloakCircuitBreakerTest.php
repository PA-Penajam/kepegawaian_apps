<?php

use App\Keycloak\Enums\CircuitState;
use App\Keycloak\Exceptions\KeycloakCircuitOpenException;
use App\Keycloak\Services\KeycloakCircuitBreaker;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Gunakan array cache driver untuk isolasi test
    config()->set('keycloak.circuit_breaker.failure_threshold', 5);
    config()->set('keycloak.circuit_breaker.recovery_timeout_seconds', 30);
    config()->set('keycloak.circuit_breaker.success_threshold', 2);
    config()->set('keycloak.circuit_breaker.cache_driver', 'array');

    $this->breaker = new KeycloakCircuitBreaker;
});

test('state awal adalah CLOSED', function () {
    expect($this->breaker->getState())->toBe(CircuitState::Closed->value);
});

test('failure count awal adalah 0', function () {
    expect($this->breaker->getFailureCount())->toBe(0);
});

test('isOpen mengembalikan false saat state CLOSED', function () {
    expect($this->breaker->isOpen())->toBeFalse();
});

test('call menjalankan operasi dan mengembalikan hasilnya saat CLOSED', function () {
    $result = $this->breaker->call(fn () => 'sukses');

    expect($result)->toBe('sukses');
});

test('call melempar exception dari operasi yang gagal', function () {
    expect(fn () => $this->breaker->call(function () {
        throw new RuntimeException('koneksi gagal');
    }))->toThrow(RuntimeException::class, 'koneksi gagal');
});

test('failure count bertambah setelah operasi gagal', function () {
    try {
        $this->breaker->call(function () {
            throw new RuntimeException('gagal');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    expect($this->breaker->getFailureCount())->toBe(1);
});

test('success count di-reset setelah failure', function () {
    // 1 success dulu
    $this->breaker->call(fn () => 'ok');
    expect($this->breaker->getSuccessCount())->toBe(1);

    // Lalu 1 failure → success count harus reset ke 0
    try {
        $this->breaker->call(function () {
            throw new RuntimeException('gagal');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    expect($this->breaker->getSuccessCount())->toBe(0);
});

test('transisi CLOSED ke OPEN setelah 5 consecutive failures', function () {
    for ($i = 0; $i < 5; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('failure');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    expect($this->breaker->getState())->toBe(CircuitState::Open->value);
    expect($this->breaker->isOpen())->toBeTrue();
});

test('OPEN state menolak semua request dengan KeycloakCircuitOpenException', function () {
    // Paksa ke OPEN state
    for ($i = 0; $i < 5; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    expect(fn () => $this->breaker->call(fn () => 'test'))
        ->toThrow(KeycloakCircuitOpenException::class);
});

test('transisi OPEN ke HALF_OPEN setelah recovery timeout berlalu', function () {
    // Paksa ke OPEN state
    for ($i = 0; $i < 5; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    expect($this->breaker->getState())->toBe(CircuitState::Open->value);

    // Manipulasi last_failure_at agar seolah sudah 31 detik lalu
    Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);

    // Panggil call → harus transisi ke HALF_OPEN dan menjalankan operasi
    $result = $this->breaker->call(fn () => 'recovery berhasil');

    expect($result)->toBe('recovery berhasil');
    expect($this->breaker->getState())->toBe(CircuitState::HalfOpen->value);
});

test('transisi HALF_OPEN ke CLOSED setelah 2 consecutive successes', function () {
    // Setup: paksa ke OPEN, lalu ke HALF_OPEN via recovery timeout
    for ($i = 0; $i < 5; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);

    // Success pertama → transisi OPEN→HALF_OPEN, lalu success dicatat
    $this->breaker->call(fn () => 'probe 1');
    expect($this->breaker->getState())->toBe(CircuitState::HalfOpen->value);

    // Success kedua → transisi HALF_OPEN→CLOSED
    $this->breaker->call(fn () => 'probe 2');
    expect($this->breaker->getState())->toBe(CircuitState::Closed->value);
});

test('transisi HALF_OPEN ke OPEN setelah 1 failure', function () {
    // Setup: paksa ke HALF_OPEN
    for ($i = 0; $i < 5; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);

    // Transisi ke HALF_OPEN dengan call pertama
    $this->breaker->call(fn () => 'probe berhasil');
    expect($this->breaker->getState())->toBe(CircuitState::HalfOpen->value);

    // Failure di HALF_OPEN → kembali ke OPEN
    try {
        $this->breaker->call(function () {
            throw new RuntimeException('probe gagal');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    expect($this->breaker->getState())->toBe(CircuitState::Open->value);
});

test('reset mengembalikan state ke CLOSED dan mereset semua counter', function () {
    // Paksa ke OPEN state
    for ($i = 0; $i < 5; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    expect($this->breaker->getState())->toBe(CircuitState::Open->value);
    expect($this->breaker->getFailureCount())->toBe(5);

    // Reset
    $this->breaker->reset();

    expect($this->breaker->getState())->toBe(CircuitState::Closed->value);
    expect($this->breaker->getFailureCount())->toBe(0);
    expect($this->breaker->getSuccessCount())->toBe(0);
});

test('failure count reset ke 0 setelah success', function () {
    // 3 failures
    for ($i = 0; $i < 3; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    expect($this->breaker->getFailureCount())->toBe(3);

    // 1 success → failure count harus 0
    $this->breaker->call(fn () => 'berhasil');

    expect($this->breaker->getFailureCount())->toBe(0);
});

test('last failure timestamp dicatat saat operasi gagal', function () {
    $before = time();

    try {
        $this->breaker->call(function () {
            throw new RuntimeException('gagal');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    $lastFailureAt = $this->breaker->getLastFailureAt();

    expect($lastFailureAt)->toBeGreaterThanOrEqual($before);
    expect($lastFailureAt)->toBeLessThanOrEqual(time());
});

test('last success timestamp dicatat saat operasi berhasil', function () {
    $before = time();

    $this->breaker->call(fn () => 'ok');

    $lastSuccessAt = $this->breaker->getLastSuccessAt();

    expect($lastSuccessAt)->toBeGreaterThanOrEqual($before);
    expect($lastSuccessAt)->toBeLessThanOrEqual(time());
});

test('transisi ke CLOSED mereset failure count', function () {
    // Setup: paksa ke HALF_OPEN
    for ($i = 0; $i < 5; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);

    // 2 successes → transisi ke CLOSED
    $this->breaker->call(fn () => 'probe 1');
    $this->breaker->call(fn () => 'probe 2');

    expect($this->breaker->getState())->toBe(CircuitState::Closed->value);
    expect($this->breaker->getFailureCount())->toBe(0);
});

test('transisi ke OPEN mereset success count', function () {
    // Setup: paksa ke HALF_OPEN
    for ($i = 0; $i < 5; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    Cache::store('array')->put('keycloak_circuit_last_failure_at', time() - 31);

    // 1 success → sekarang HALF_OPEN
    $this->breaker->call(fn () => 'probe');
    expect($this->breaker->getSuccessCount())->toBe(1);

    // 1 failure → kembali ke OPEN, success count harus 0
    try {
        $this->breaker->call(function () {
            throw new RuntimeException('gagal lagi');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    expect($this->breaker->getState())->toBe(CircuitState::Open->value);
    expect($this->breaker->getSuccessCount())->toBe(0);
});

test('4 consecutive failures tidak membuka circuit', function () {
    for ($i = 0; $i < 4; $i++) {
        try {
            $this->breaker->call(function () {
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
            // diharapkan
        }
    }

    expect($this->breaker->getState())->toBe(CircuitState::Closed->value);
    expect($this->breaker->getFailureCount())->toBe(4);
});
