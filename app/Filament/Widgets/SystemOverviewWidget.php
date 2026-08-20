<?php

namespace App\Filament\Widgets;

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Enums\CircuitState;
use App\Keycloak\Models\KeycloakEmergencyLoginLog;
use App\Keycloak\Models\KeycloakSyncState;
use App\Models\Pegawai;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Widget ringkasan status operasional sistem SIMPEG PA Penajam.
 *
 * Menampilkan ringkasan metrik real-time mencakup pegawai aktif,
 * status circuit breaker Keycloak, rekaman sync terakhir, dan log darurat.
 */
class SystemOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Pegawai Aktif
        $totalPegawaiAktif = Pegawai::query()
            ->where('status_pegawai', 'aktif')
            ->count();

        // 2. Status Circuit Breaker Keycloak
        $circuitBreaker = app(CircuitBreakerInterface::class);
        $cbState = $circuitBreaker->getState();
        $cbFailures = $circuitBreaker->getFailureCount();

        $cbLabel = match ($cbState) {
            CircuitState::Closed->value => 'TERHUBUNG (CLOSED)',
            CircuitState::Open->value => 'TERPUTUS (OPEN)',
            CircuitState::HalfOpen->value => 'PEMULIHAN (HALF-OPEN)',
            default => strtoupper($cbState),
        };

        $cbColor = match ($cbState) {
            CircuitState::Closed->value => 'success',
            CircuitState::Open->value => 'danger',
            CircuitState::HalfOpen->value => 'warning',
            default => 'gray',
        };

        $cbIcon = match ($cbState) {
            CircuitState::Closed->value => 'heroicon-m-check-circle',
            CircuitState::Open->value => 'heroicon-m-x-circle',
            CircuitState::HalfOpen->value => 'heroicon-m-arrow-path',
            default => 'heroicon-m-question-mark-circle',
        };

        $cbDescription = $cbFailures > 0
            ? "{$cbFailures} kegagalan tercatat"
            : 'Koneksi IAM stabil';

        // 3. Sync Terakhir
        $lastSync = KeycloakSyncState::query()->latest('id')->first();
        $syncTime = $lastSync?->last_sync_at
            ? Carbon::parse($lastSync->last_sync_at)->diffForHumans()
            : 'Belum ada data';
        $syncType = $lastSync?->last_sync_type
            ? ucfirst($lastSync->last_sync_type)
            : '-';

        // 4. Emergency Login 24 Jam Terakhir
        $emergencyCount = KeycloakEmergencyLoginLog::query()
            ->where('logged_in_at', '>=', now()->subDay())
            ->count();

        return [
            Stat::make('Pegawai Aktif', number_format($totalPegawaiAktif))
                ->description('Total pegawai berstatus aktif di SIMPEG')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Integrasi IAM & SSO', $cbLabel)
                ->description($cbDescription)
                ->descriptionIcon($cbIcon)
                ->color($cbColor),

            Stat::make('Sync Terakhir', $syncTime)
                ->description("Tipe: {$syncType} | {$lastSync?->total_synced} disinkronkan")
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('Login Darurat (24 Jam)', number_format($emergencyCount))
                ->description($emergencyCount > 0 ? 'Perlu tinjauan audit' : 'Tidak ada login darurat')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($emergencyCount > 0 ? 'warning' : 'success'),
        ];
    }
}
