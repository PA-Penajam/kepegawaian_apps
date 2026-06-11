<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KeycloakSyncAuditResource\Pages;
use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Models\KeycloakSyncAudit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Filament resource untuk menampilkan dan mengelola log audit sinkronisasi Keycloak.
 */
class KeycloakSyncAuditResource extends Resource
{
    protected static ?string $model = KeycloakSyncAudit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Sync Audit Log';

    protected static ?string $modelLabel = 'Sync Audit';

    protected static ?string $pluralModelLabel = 'Sync Audit Logs';

    protected static ?string $navigationGroup = 'Keycloak';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('event_type')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('nip')
                    ->required()
                    ->maxLength(18),
                Forms\Components\TextInput::make('conflict_type')
                    ->maxLength(50),
                Forms\Components\TextInput::make('resolved_by')
                    ->required()
                    ->maxLength(50),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'create' => 'success',
                        'update' => 'info',
                        'conflict' => 'warning',
                        'sync_failure' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable(),
                Tables\Columns\TextColumn::make('conflict_type')
                    ->label('Conflict Type')
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
                    ->label('Resolved By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(50)
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'create' => 'Create',
                        'update' => 'Update',
                        'conflict' => 'Conflict',
                        'sync_failure' => 'Sync Failure',
                    ]),
                Tables\Filters\SelectFilter::make('conflict_type')
                    ->label('Conflict Type')
                    ->options(
                        collect(ConflictType::cases())
                            ->mapWithKeys(fn (ConflictType $type) => [$type->value => ucwords(str_replace('_', ' ', $type->value))])
                            ->toArray()
                    ),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Umum')
                    ->schema([
                        Infolists\Components\TextEntry::make('event_type')
                            ->label('Event Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'create' => 'success',
                                'update' => 'info',
                                'conflict' => 'warning',
                                'sync_failure' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('nip')
                            ->label('NIP'),
                        Infolists\Components\TextEntry::make('conflict_type')
                            ->label('Conflict Type')
                            ->badge()
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('resolved_by')
                            ->label('Resolved By'),
                        Infolists\Components\TextEntry::make('caused_by_nip')
                            ->label('Caused By NIP')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y H:i:s'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Pegawai Snapshot')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('pegawai_snapshot')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Infolists\Components\Section::make('Keycloak Snapshot')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('keycloak_snapshot')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Infolists\Components\Section::make('Resolution')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('resolution')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(fn (KeycloakSyncAudit $record): bool => ! empty($record->resolution)),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKeycloakSyncAudits::route('/'),
            'view' => Pages\ViewKeycloakSyncAudit::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
