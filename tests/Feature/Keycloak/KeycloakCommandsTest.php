<?php

/**
 * Feature tests untuk Keycloak Artisan commands.
 *
 * Menguji keycloak:sync, keycloak:health, dan keycloak:circuit-reset commands.
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\KeycloakSyncServiceInterface;
use App\Keycloak\DataTransferObjects\HealthStatus;
use App\Keycloak\DataTransferObjects\SyncResult;
use App\Models\Pegawai;
use Carbon\Carbon;

// === keycloak:sync ===

describe('keycloak:sync', function () {
    test('menjalankan incremental sync secara default', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('incrementalSync')
            ->once()
            ->andReturn(new SyncResult(
                success: true,
                created: 2,
                updated: 1,
                skipped: 5,
                conflicts: 1,
                syncType: 'incremental',
                completedAt: now(),
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        $this->artisan('keycloak:sync')
            ->expectsOutputToContain('Incremental Sync')
            ->expectsOutputToContain('Sync berhasil')
            ->assertExitCode(0);
    });

    test('menjalankan full sync dengan option --type=full', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('fullSync')
            ->once()
            ->andReturn(new SyncResult(
                success: true,
                created: 10,
                updated: 3,
                skipped: 0,
                conflicts: 2,
                syncType: 'full',
                completedAt: now(),
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        $this->artisan('keycloak:sync --type=full')
            ->expectsOutputToContain('Full Sync')
            ->expectsOutputToContain('Sync berhasil')
            ->assertExitCode(0);
    });

    test('menolak tipe sync yang tidak valid', function () {
        $this->artisan('keycloak:sync --type=invalid')
            ->expectsOutputToContain('Tipe sync tidak valid')
            ->assertExitCode(1);
    });

    test('sync single pegawai berdasarkan NIP', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('syncPegawai')
            ->once()
            ->with(Mockery::on(fn ($p) => $p->nip === $pegawai->nip))
            ->andReturn(new SyncResult(
                success: true,
                created: 1,
                syncType: 'single',
                completedAt: now(),
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        $this->artisan("keycloak:sync --nip={$pegawai->nip}")
            ->expectsOutputToContain("NIP: {$pegawai->nip}")
            ->expectsOutputToContain('Sync berhasil')
            ->assertExitCode(0);
    });

    test('menolak NIP yang bukan 18 digit', function () {
        $this->artisan('keycloak:sync --nip=12345')
            ->expectsOutputToContain('Format NIP tidak valid')
            ->assertExitCode(1);
    });

    test('menolak NIP yang mengandung huruf', function () {
        $this->artisan('keycloak:sync --nip=12345678901234567a')
            ->expectsOutputToContain('Format NIP tidak valid')
            ->assertExitCode(1);
    });

    test('menampilkan error ketika NIP tidak ditemukan di database', function () {
        $this->artisan('keycloak:sync --nip=198501152010011099')
            ->expectsOutputToContain('tidak ditemukan')
            ->assertExitCode(1);
    });

    test('menampilkan detail error ketika sync gagal', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('syncPegawai')
            ->once()
            ->andReturn(new SyncResult(
                success: false,
                errors: 1,
                errorDetails: [['nip' => $pegawai->nip, 'error' => 'Connection timeout']],
                syncType: 'single',
                completedAt: now(),
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        $this->artisan("keycloak:sync --nip={$pegawai->nip}")
            ->expectsOutputToContain('Sync selesai dengan error')
            ->expectsOutputToContain('Connection timeout')
            ->assertExitCode(1);
    });
});

// === keycloak:health ===

describe('keycloak:health', function () {
    test('menampilkan status healthy ketika circuit closed', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('healthCheck')
            ->once()
            ->andReturn(new HealthStatus(
                isHealthy: true,
                circuitState: 'closed',
                failureCount: 0,
                lastSuccessAt: Carbon::now(),
                lastFailureAt: null,
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        $this->artisan('keycloak:health')
            ->expectsOutputToContain('HEALTHY')
            ->expectsOutputToContain('CLOSED')
            ->assertExitCode(0);
    });

    test('menampilkan status unhealthy ketika circuit open', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('healthCheck')
            ->once()
            ->andReturn(new HealthStatus(
                isHealthy: false,
                circuitState: 'open',
                failureCount: 5,
                lastSuccessAt: Carbon::now()->subMinutes(10),
                lastFailureAt: Carbon::now()->subSeconds(15),
                lastError: 'Connection refused',
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        $this->artisan('keycloak:health')
            ->expectsOutputToContain('UNHEALTHY')
            ->expectsOutputToContain('OPEN')
            ->expectsOutputToContain('5')
            ->expectsOutputToContain('Connection refused')
            ->assertExitCode(1);
    });

    test('menampilkan status half_open dengan informasi failure', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('healthCheck')
            ->once()
            ->andReturn(new HealthStatus(
                isHealthy: false,
                circuitState: 'half_open',
                failureCount: 3,
                lastSuccessAt: null,
                lastFailureAt: Carbon::now()->subSeconds(30),
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        $this->artisan('keycloak:health')
            ->expectsOutputToContain('UNHEALTHY')
            ->expectsOutputToContain('HALF_OPEN')
            ->assertExitCode(1);
    });
});

// === keycloak:circuit-reset ===

describe('keycloak:circuit-reset', function () {
    test('mereset circuit breaker ke state CLOSED', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->once()->andReturn('open');
        $circuitBreaker->shouldReceive('getFailureCount')->once()->andReturn(5);
        $circuitBreaker->shouldReceive('reset')->once();

        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        $this->artisan('keycloak:circuit-reset')
            ->expectsOutputToContain('open')
            ->expectsOutputToContain('berhasil di-reset')
            ->assertExitCode(0);
    });

    test('menampilkan state saat ini sebelum reset', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->once()->andReturn('half_open');
        $circuitBreaker->shouldReceive('getFailureCount')->once()->andReturn(3);
        $circuitBreaker->shouldReceive('reset')->once();

        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        $this->artisan('keycloak:circuit-reset')
            ->expectsOutputToContain('half_open')
            ->expectsOutputToContain('berhasil di-reset')
            ->assertExitCode(0);
    });
});
