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

    protected static ?string $navigationLabel = 'Emergency Login Log';

    protected static ?string $navigationGroup = 'Keycloak';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Emergency Login Log';

    protected static ?string $pluralModelLabel = 'Emergency Login Logs';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(50)
                    ->tooltip(fn (KeycloakEmergencyLoginLog $record): ?string => $record->user_agent)
                    ->searchable(),

                Tables\Columns\TextColumn::make('logged_in_at')
                    ->label('Logged In At')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('logged_out_at')
                    ->label('Logged Out At')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->placeholder('-'),
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
