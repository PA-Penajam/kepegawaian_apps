<?php

/**
 * Feature tests untuk sync actions di Filament Sync Dashboard.
 *
 * Menguji fungsionalitas full sync, incremental sync,
 * dan sync by NIP termasuk validasi dan error handling.
 */

use App\Filament\Pages\SyncDashboard;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\KeycloakSyncServiceInterface;
use App\Keycloak\DataTransferObjects\SyncResult;
use App\Models\Pegawai;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = Pegawai::factory()->create([
        'status_pegawai' => 'aktif',
    ]);
    $this->actingAs($this->user);

    // Default: circuit breaker closed
    $this->circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
    $this->circuitBreaker->shouldReceive('getState')->andReturn('closed');
    $this->circuitBreaker->shouldReceive('getFailureCount')->andReturn(0);
    $this->circuitBreaker->shouldReceive('isOpen')->andReturn(false);
    $this->app->instance(CircuitBreakerInterface::class, $this->circuitBreaker);
});

describe('SyncDashboard - Full Sync Action', function () {
    test('full sync action tersedia di header', function () {
        Livewire::test(SyncDashboard::class)
            ->assertActionExists('fullSync');
    });

    test('full sync berhasil menampilkan notification dengan result counts', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('fullSync')->once()->andReturn(
            new SyncResult(
                success: true,
                created: 5,
                updated: 3,
                skipped: 10,
                conflicts: 2,
                errors: 0,
            )
        );
        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        Livewire::test(SyncDashboard::class)
            ->callAction('fullSync')
            ->assertNotified('Full Sync Berhasil');
    });

    test('full sync gagal menampilkan warning notification', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('fullSync')->once()->andReturn(
            new SyncResult(
                success: false,
                created: 2,
                updated: 1,
                skipped: 5,
                conflicts: 1,
                errors: 3,
            )
        );
        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        Livewire::test(SyncDashboard::class)
            ->callAction('fullSync')
            ->assertNotified();
    });

    test('full sync menampilkan error notification jika circuit breaker OPEN', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('open');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(5);
        $circuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->callAction('fullSync')
            ->assertNotified('Sync Gagal');
    });
});

describe('SyncDashboard - Incremental Sync Action', function () {
    test('incremental sync action tersedia di header', function () {
        Livewire::test(SyncDashboard::class)
            ->assertActionExists('incrementalSync');
    });

    test('incremental sync berhasil menampilkan notification', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('incrementalSync')->once()->andReturn(
            new SyncResult(
                success: true,
                created: 1,
                updated: 2,
                skipped: 0,
                conflicts: 0,
                errors: 0,
            )
        );
        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        Livewire::test(SyncDashboard::class)
            ->callAction('incrementalSync')
            ->assertNotified('Incremental Sync Berhasil');
    });

    test('incremental sync menampilkan error notification jika circuit breaker OPEN', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('open');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(5);
        $circuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->callAction('incrementalSync')
            ->assertNotified('Sync Gagal');
    });
});

describe('SyncDashboard - Sync by NIP Action', function () {
    test('sync by NIP action tersedia di header', function () {
        Livewire::test(SyncDashboard::class)
            ->assertActionExists('syncByNip');
    });

    test('sync by NIP berhasil dengan NIP valid', function () {
        $targetPegawai = Pegawai::factory()->create([
            'nip' => '198501152010011001',
            'status_pegawai' => 'aktif',
        ]);

        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('syncPegawai')
            ->once()
            ->withArgs(fn (Pegawai $p) => $p->nip === '198501152010011001')
            ->andReturn(
                new SyncResult(
                    success: true,
                    created: 1,
                    updated: 0,
                    skipped: 0,
                    conflicts: 0,
                    errors: 0,
                )
            );
        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => '198501152010011001'])
            ->assertNotified('Single Sync Berhasil');
    });

    test('sync by NIP gagal validasi jika NIP bukan 18 digit', function () {
        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => '12345'])
            ->assertHasActionErrors(['nip']);
    });

    test('sync by NIP gagal validasi jika NIP mengandung huruf', function () {
        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => '12345678901234567a'])
            ->assertHasActionErrors(['nip']);
    });

    test('sync by NIP menampilkan error jika pegawai tidak ditemukan', function () {
        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => '999999999999999999'])
            ->assertNotified('Sync Gagal');
    });

    test('sync by NIP menampilkan error jika pegawai tidak aktif', function () {
        Pegawai::factory()->create([
            'nip' => '198501152010011002',
            'status_pegawai' => 'pensiun',
        ]);

        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => '198501152010011002'])
            ->assertNotified('Sync Gagal');
    });

    test('sync by NIP menampilkan error notification jika circuit breaker OPEN', function () {
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('getState')->andReturn('open');
        $circuitBreaker->shouldReceive('getFailureCount')->andReturn(5);
        $circuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);

        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => '198501152010011001'])
            ->assertNotified('Sync Gagal');
    });
});
