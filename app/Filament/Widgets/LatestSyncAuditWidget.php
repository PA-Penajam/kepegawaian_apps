<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\KeycloakSyncAuditResource;
use App\Keycloak\Models\KeycloakSyncAudit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Widget tabel 5 riwayat sinkronisasi audit Keycloak terbaru.
 */
class LatestSyncAuditWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Riwayat Audit Sinkronisasi Terkini';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                KeycloakSyncAudit::query()->latest('created_at')->limit(5)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Tipe Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'create' => 'success',
                        'update' => 'info',
                        'conflict' => 'warning',
                        'sync_failure' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('nip')
                    ->label('NIP Pegawai')
                    ->searchable(),

                Tables\Columns\TextColumn::make('conflict_type')
                    ->label('Tipe Konflik')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'data_mismatch' => 'warning',
                        'status_conflict' => 'danger',
                        'role_override' => 'info',
                        'identifier_change' => 'purple',
                        default => 'gray',
                    })
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('resolved_by')
                    ->label('Diselesaikan Oleh'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->url(fn (KeycloakSyncAudit $record): string => KeycloakSyncAuditResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
