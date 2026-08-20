<?php

namespace App\Filament\Pages;

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\KeycloakSyncServiceInterface;
use App\Keycloak\DataTransferObjects\SyncResult;
use App\Keycloak\Enums\CircuitState;
use App\Keycloak\Models\KeycloakSyncState;
use App\Keycloak\Services\KeycloakCircuitBreaker;
use App\Models\Pegawai;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Halaman dashboard sinkronisasi Keycloak.
 *
 * Menampilkan informasi sync state terakhir dan status circuit breaker,
 * termasuk tombol manual reset circuit breaker dan sync actions.
 */
class SyncDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Dashboard Sinkronisasi';

    protected static ?string $title = 'Dashboard Sinkronisasi Keycloak';

    protected static ?string $navigationGroup = 'Integrasi Keycloak & SSO';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.sync-dashboard';

    /**
     * Dapatkan data sync state terakhir dari database.
     *
     * @return array<string, mixed>
     */
    public function getSyncStateData(): array
    {
        $syncState = KeycloakSyncState::query()->latest('id')->first();

        if (! $syncState) {
            return [
                'last_sync_at' => null,
                'last_sync_type' => null,
                'total_synced' => 0,
                'total_conflicts' => 0,
            ];
        }

        return [
            'last_sync_at' => $syncState->last_sync_at
                ? Carbon::parse($syncState->last_sync_at)->format('d M Y H:i:s')
                : null,
            'last_sync_type' => $syncState->last_sync_type,
            'total_synced' => $syncState->total_synced ?? 0,
            'total_conflicts' => $syncState->total_conflicts ?? 0,
        ];
    }

    /**
     * Dapatkan data circuit breaker dari cache.
     *
     * @return array<string, mixed>
     */
    public function getCircuitBreakerData(): array
    {
        $circuitBreaker = app(CircuitBreakerInterface::class);

        $state = $circuitBreaker->getState();
        $failureCount = $circuitBreaker->getFailureCount();

        // Dapatkan last failure timestamp jika implementasi mendukungnya
        $lastFailureAt = null;
        if ($circuitBreaker instanceof KeycloakCircuitBreaker) {
            $lastFailureTimestamp = $circuitBreaker->getLastFailureAt();
            $lastFailureAt = $lastFailureTimestamp
                ? Carbon::createFromTimestamp($lastFailureTimestamp)->format('d M Y H:i:s')
                : null;
        }

        return [
            'state' => strtoupper($state),
            'state_color' => match ($state) {
                CircuitState::Closed->value => 'success',
                CircuitState::Open->value => 'danger',
                CircuitState::HalfOpen->value => 'warning',
                default => 'gray',
            },
            'failure_count' => $failureCount,
            'last_failure_at' => $lastFailureAt,
        ];
    }

    /**
     * Definisikan header actions termasuk sync operations dan reset circuit breaker.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getFullSyncAction(),
            $this->getIncrementalSyncAction(),
            $this->getSyncByNipAction(),
            $this->getResetCircuitBreakerAction(),
        ];
    }

    /**
     * Action untuk trigger full sync seluruh Pegawai aktif.
     */
    private function getFullSyncAction(): Action
    {
        return Action::make('fullSync')
            ->label('Full Sync')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Full Sync')
            ->modalDescription('Sinkronisasi seluruh Pegawai aktif ke Keycloak. Proses ini mungkin membutuhkan waktu beberapa saat.')
            ->modalSubmitActionLabel('Mulai Full Sync')
            ->action(function (): void {
                $circuitBreaker = app(CircuitBreakerInterface::class);

                // Req 15.4: Error notification jika circuit breaker OPEN
                if ($circuitBreaker->isOpen()) {
                    Notification::make()
                        ->title('Sync Gagal')
                        ->body('Circuit breaker dalam state OPEN. Keycloak tidak tersedia.')
                        ->danger()
                        ->duration(5000)
                        ->send();

                    return;
                }

                $syncService = app(KeycloakSyncServiceInterface::class);
                $result = $syncService->fullSync();

                $this->sendSyncResultNotification($result, 'Full Sync');
            });
    }

    /**
     * Action untuk trigger incremental sync (Pegawai berubah 24 jam terakhir).
     */
    private function getIncrementalSyncAction(): Action
    {
        return Action::make('incrementalSync')
            ->label('Incremental Sync')
            ->icon('heroicon-o-clock')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Incremental Sync')
            ->modalDescription('Sinkronisasi Pegawai yang berubah dalam 24 jam terakhir ke Keycloak.')
            ->modalSubmitActionLabel('Mulai Incremental Sync')
            ->action(function (): void {
                $circuitBreaker = app(CircuitBreakerInterface::class);

                // Req 15.4: Error notification jika circuit breaker OPEN
                if ($circuitBreaker->isOpen()) {
                    Notification::make()
                        ->title('Sync Gagal')
                        ->body('Circuit breaker dalam state OPEN. Keycloak tidak tersedia.')
                        ->danger()
                        ->duration(5000)
                        ->send();

                    return;
                }

                $syncService = app(KeycloakSyncServiceInterface::class);
                $result = $syncService->incrementalSync();

                $this->sendSyncResultNotification($result, 'Incremental Sync');
            });
    }

    /**
     * Action untuk sync single Pegawai berdasarkan NIP (18 digit).
     */
    private function getSyncByNipAction(): Action
    {
        return Action::make('syncByNip')
            ->label('Sync by NIP')
            ->icon('heroicon-o-user')
            ->color('success')
            ->form([
                TextInput::make('nip')
                    ->label('NIP Pegawai')
                    ->placeholder('Masukkan 18 digit NIP')
                    ->required()
                    ->minLength(18)
                    ->maxLength(18)
                    ->regex('/^\d{18}$/')
                    ->validationMessages([
                        'regex' => 'NIP harus berupa 18 digit angka.',
                        'min_length' => 'NIP harus tepat 18 digit.',
                        'max_length' => 'NIP harus tepat 18 digit.',
                    ]),
            ])
            ->modalHeading('Sync Pegawai by NIP')
            ->modalDescription('Sinkronisasi satu Pegawai ke Keycloak berdasarkan NIP.')
            ->modalSubmitActionLabel('Sync Pegawai')
            ->action(function (array $data): void {
                $circuitBreaker = app(CircuitBreakerInterface::class);

                // Req 15.4: Error notification jika circuit breaker OPEN
                if ($circuitBreaker->isOpen()) {
                    Notification::make()
                        ->title('Sync Gagal')
                        ->body('Circuit breaker dalam state OPEN. Keycloak tidak tersedia.')
                        ->danger()
                        ->duration(5000)
                        ->send();

                    return;
                }

                $nip = $data['nip'];

                // Validasi NIP ada di database dengan status aktif
                $pegawai = Pegawai::query()
                    ->where('nip', $nip)
                    ->where('status_pegawai', 'aktif')
                    ->first();

                if (! $pegawai) {
                    Notification::make()
                        ->title('Sync Gagal')
                        ->body("Pegawai dengan NIP {$nip} tidak ditemukan atau tidak aktif.")
                        ->danger()
                        ->duration(5000)
                        ->send();

                    return;
                }

                $syncService = app(KeycloakSyncServiceInterface::class);
                $result = $syncService->syncPegawai($pegawai);

                $this->sendSyncResultNotification($result, 'Single Sync');
            });
    }

    /**
     * Action untuk reset circuit breaker ke state CLOSED.
     */
    private function getResetCircuitBreakerAction(): Action
    {
        return Action::make('resetCircuitBreaker')
            ->label('Reset Circuit Breaker')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reset Circuit Breaker')
            ->modalDescription('Apakah Anda yakin ingin mereset circuit breaker ke state CLOSED? Ini akan mengizinkan semua request ke Keycloak.')
            ->modalSubmitActionLabel('Ya, Reset')
            ->action(function (): void {
                $circuitBreaker = app(CircuitBreakerInterface::class);
                $circuitBreaker->reset();

                Notification::make()
                    ->title('Circuit Breaker Direset')
                    ->body('Circuit breaker berhasil direset ke state CLOSED.')
                    ->success()
                    ->duration(5000)
                    ->send();
            });
    }

    /**
     * Kirim notification dengan hasil sync operation.
     *
     * Req 15.3: Menampilkan result counts dalam 5 detik.
     */
    private function sendSyncResultNotification(SyncResult $result, string $operationType): void
    {
        if ($result->success) {
            Notification::make()
                ->title("{$operationType} Berhasil")
                ->body(
                    "Created: {$result->created} | Updated: {$result->updated} | ".
                    "Skipped: {$result->skipped} | Conflicts: {$result->conflicts} | ".
                    "Errors: {$result->errors}"
                )
                ->success()
                ->duration(5000)
                ->send();
        } else {
            $errorSummary = $result->errors > 0
                ? " ({$result->errors} error)"
                : '';

            Notification::make()
                ->title("{$operationType} Selesai dengan Error{$errorSummary}")
                ->body(
                    "Created: {$result->created} | Updated: {$result->updated} | ".
                    "Skipped: {$result->skipped} | Conflicts: {$result->conflicts} | ".
                    "Errors: {$result->errors}"
                )
                ->warning()
                ->duration(5000)
                ->send();
        }
    }
}
