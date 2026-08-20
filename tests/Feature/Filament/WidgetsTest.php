<?php

use App\Filament\Widgets\LatestSyncAuditWidget;
use App\Filament\Widgets\SystemOverviewWidget;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Models\KeycloakEmergencyLoginLog;
use App\Keycloak\Models\KeycloakSyncAudit;
use App\Keycloak\Models\KeycloakSyncState;
use App\Models\Pegawai;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = Pegawai::factory()->create([
        'status_pegawai' => 'aktif',
    ]);
    $this->actingAs($this->user);

    $this->circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
    $this->circuitBreaker->shouldReceive('getState')->andReturn('closed');
    $this->circuitBreaker->shouldReceive('getFailureCount')->andReturn(0);
    $this->circuitBreaker->shouldReceive('isOpen')->andReturn(false);
    $this->app->instance(CircuitBreakerInterface::class, $this->circuitBreaker);
});

it('merender SystemOverviewWidget dengan benar', function () {
    Pegawai::factory()->create([
        'status_pegawai' => 'aktif',
    ]);

    KeycloakSyncState::query()->create([
        'last_sync_at' => now(),
        'last_sync_type' => 'full',
        'total_synced' => 15,
        'total_conflicts' => 0,
        'status' => 'success',
    ]);

    KeycloakEmergencyLoginLog::query()->create([
        'username' => 'admin_darurat',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit Test',
        'logged_in_at' => now(),
    ]);

    Livewire::test(SystemOverviewWidget::class)
        ->assertSuccessful();
});

it('merender LatestSyncAuditWidget dengan data audit', function () {
    $audit = KeycloakSyncAudit::query()->create([
        'event_type' => 'create',
        'nip' => '199001012015011001',
        'pegawai_snapshot' => ['nama' => 'Budi'],
        'keycloak_snapshot' => ['username' => '199001012015011001'],
        'resolved_by' => 'system',
    ]);

    Livewire::test(LatestSyncAuditWidget::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$audit]);
});
