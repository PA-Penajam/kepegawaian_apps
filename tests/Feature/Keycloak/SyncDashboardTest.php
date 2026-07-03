<?php

/**
 * Feature tests untuk halaman Filament Sync Dashboard.
 *
 * Menguji tampilan sync state, circuit breaker state,
 * dan fungsionalitas reset circuit breaker.
 */

use App\Filament\Pages\SyncDashboard;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Models\KeycloakSyncState;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = Pegawai::factory()->create();
    $this->actingAs($this->user);
});

describe('SyncDashboard - Tampilan Sync State', function () {
    test('menampilkan halaman dashboard dengan sync state kosong', function () {
        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertSee('Belum pernah sync')
            ->assertSee('Total Disinkronkan')
            ->assertSee('Total Konflik');
    });

    test('menampilkan data sync state terakhir dari database', function () {
        KeycloakSyncState::create([
            'last_sync_at' => '2025-01-15 10:30:00',
            'last_sync_type' => 'full',
            'total_synced' => 42,
            'total_conflicts' => 3,
        ]);

        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertSee('15 Jan 2025 10:30:00')
            ->assertSee('Full')
            ->assertSee('42')
            ->assertSee('3');
    });

    test('menampilkan sync state terbaru ketika ada beberapa record', function () {
        KeycloakSyncState::create([
            'last_sync_at' => '2025-01-10 08:00:00',
            'last_sync_type' => 'full',
            'total_synced' => 10,
            'total_conflicts' => 1,
        ]);

        KeycloakSyncState::create([
            'last_sync_at' => '2025-01-15 14:00:00',
            'last_sync_type' => 'incremental',
            'total_synced' => 5,
            'total_conflicts' => 0,
        ]);

        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertSee('15 Jan 2025 14:00:00')
            ->assertSee('Incremental')
            ->assertSee('5');
    });
});

describe('SyncDashboard - Circuit Breaker State', function () {
    test('menampilkan circuit breaker state CLOSED', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('closed');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(0);

        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertSee('CLOSED');
    });

    test('menampilkan circuit breaker state OPEN dengan failure count', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('open');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(5);

        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertSee('OPEN')
            ->assertSee('5');
    });

    test('menampilkan circuit breaker state HALF_OPEN', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('half_open');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(3);

        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertSee('HALF_OPEN')
            ->assertSee('3');
    });

    test('menampilkan last failure timestamp dari KeycloakCircuitBreaker', function () {
        // Gunakan implementasi riil dengan cache
        Cache::put('keycloak_circuit_state', 'open');
        Cache::put('keycloak_circuit_failures', 5);
        Cache::put('keycloak_circuit_last_failure_at', strtotime('2025-01-15 09:45:00'));

        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertSee('15 Jan 2025 09:45:00');
    });

    test('menampilkan pesan tidak ada failure jika belum pernah gagal', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('closed');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(0);

        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertSee('Tidak ada failure');
    });
});

describe('SyncDashboard - Reset Circuit Breaker', function () {
    test('tombol reset circuit breaker tersedia', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('closed');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(0);

        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->assertSuccessful()
            ->assertActionExists('resetCircuitBreaker');
    });

    test('reset circuit breaker memanggil reset method dan menampilkan notifikasi', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('open');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(5);
        $circuitBreaker->shouldReceive('reset')->once();

        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->callAction('resetCircuitBreaker')
            ->assertNotified('Circuit Breaker Direset');
    });
});
