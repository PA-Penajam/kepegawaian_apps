<?php

/**
 * Feature tests untuk sync actions pada Filament Sync Dashboard.
 *
 * Menguji trigger full sync, incremental sync, dan single NIP sync.
 * Validates: Requirement 15.2
 */

use App\Filament\Pages\SyncDashboard;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\KeycloakSyncServiceInterface;
use App\Keycloak\DataTransferObjects\SyncResult;
use App\Models\Pegawai;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = Pegawai::factory()->create();
    $this->actingAs($this->user);

    // Mock circuit breaker default agar dashboard bisa render
    $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
    $circuitBreaker->shouldReceive('getState')->andReturn('closed');
    $circuitBreaker->shouldReceive('getFailureCount')->andReturn(0);
    $circuitBreaker->shouldReceive('isOpen')->andReturn(false);
    $this->app->instance(CircuitBreakerInterface::class, $circuitBreaker);
});

describe('SyncDashboard - Full Sync Action', function () {
    test('action fullSync tersedia di dashboard', function () {
        Livewire::test(SyncDashboard::class)
            ->assertActionExists('fullSync');
    });

    test('full sync memanggil SyncService fullSync dan menampilkan notifikasi sukses', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('fullSync')
            ->once()
            ->andReturn(new SyncResult(
                success: true,
                created: 5,
                updated: 3,
                skipped: 10,
                errors: 0,
                conflicts: 1,
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        Livewire::test(SyncDashboard::class)
            ->callAction('fullSync')
            ->assertNotified();
    });

    test('full sync menampilkan error notification saat circuit breaker OPEN', function () {
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
    test('action incrementalSync tersedia di dashboard', function () {
        Livewire::test(SyncDashboard::class)
            ->assertActionExists('incrementalSync');
    });

    test('incremental sync memanggil SyncService incrementalSync dan menampilkan notifikasi', function () {
        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('incrementalSync')
            ->once()
            ->andReturn(new SyncResult(
                success: true,
                created: 2,
                updated: 1,
                skipped: 5,
                errors: 0,
                conflicts: 0,
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        Livewire::test(SyncDashboard::class)
            ->callAction('incrementalSync')
            ->assertNotified();
    });
});

describe('SyncDashboard - Single NIP Sync Action', function () {
    test('action syncByNip tersedia di dashboard', function () {
        Livewire::test(SyncDashboard::class)
            ->assertActionExists('syncByNip');
    });

    test('single NIP sync memanggil SyncService syncPegawai dengan NIP valid', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => '123456789012345678',
            'status_pegawai' => 'aktif',
        ]);

        $syncService = Mockery::mock(KeycloakSyncServiceInterface::class);
        $syncService->shouldReceive('syncPegawai')
            ->once()
            ->andReturn(new SyncResult(
                success: true,
                created: 1,
                updated: 0,
                skipped: 0,
                conflicts: 0,
                errors: 0,
            ));

        $this->app->instance(KeycloakSyncServiceInterface::class, $syncService);

        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => '123456789012345678'])
            ->assertNotified();
    });

    test('single NIP sync menolak NIP yang tidak valid (bukan 18 digit)', function () {
        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => '12345'])
            ->assertHasActionErrors(['nip']);
    });

    test('single NIP sync menolak NIP yang berisi karakter non-numerik', function () {
        Livewire::test(SyncDashboard::class)
            ->callAction('syncByNip', data: ['nip' => 'abcdefghijklmnopqr'])
            ->assertHasActionErrors(['nip']);
    });
});
