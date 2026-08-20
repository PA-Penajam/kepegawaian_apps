<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KeycloakEmergencyLoginLogResource\Pages;
use App\Keycloak\Models\KeycloakEmergencyLoginLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Filament resource untuk menampilkan log emergency login Keycloak.
 * Menampilkan data paginated (max 50/page) dengan kolom ip_address, user_agent,
 * logged_in_at, dan logged_out_at.
 */
class KeycloakEmergencyLoginLogResource extends Resource
{
    protected static ?string $model = KeycloakEmergencyLoginLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Log Login Darurat';

    protected static ?string $navigationGroup = 'Keamanan & Audit';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Login Darurat';

    protected static ?string $pluralModelLabel = 'Log Login Darurat';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily('mono')
                    ->icon('heroicon-m-globe-alt')
                    ->iconColor('primary')
                    ->copyable()
                    ->copyMessage('IP Address disalin')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('Perangkat / User Agent')
                    ->limit(60)
                    ->tooltip(fn (KeycloakEmergencyLoginLog $record): ?string => $record->user_agent)
                    ->icon('heroicon-m-computer-desktop')
                    ->searchable(),

                Tables\Columns\TextColumn::make('logged_in_at')
                    ->label('Waktu Masuk')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('logged_out_at')
                    ->label('Waktu Keluar')
                    ->dateTime('d M Y H:i:s')
                    ->sortable()
                    ->placeholder('Sesi Masih Aktif / Tidak Tercatat'),
            ])
            ->defaultSort('logged_in_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(50);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKeycloakEmergencyLoginLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
