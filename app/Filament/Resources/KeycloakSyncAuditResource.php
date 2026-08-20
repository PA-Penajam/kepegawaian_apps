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

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Log Audit Sinkronisasi';

    protected static ?string $modelLabel = 'Audit Sinkronisasi';

    protected static ?string $pluralModelLabel = 'Log Audit Sinkronisasi';

    protected static ?string $navigationGroup = 'Integrasi Keycloak & SSO';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('event_type')
                    ->label('Tipe Event')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('nip')
                    ->label('NIP Pegawai')
                    ->required()
                    ->maxLength(18),
                Forms\Components\TextInput::make('conflict_type')
                    ->label('Tipe Konflik')
                    ->maxLength(50),
                Forms\Components\TextInput::make('resolved_by')
                    ->label('Diselesaikan Oleh')
                    ->required()
                    ->maxLength(50),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('nip')
                    ->label('NIP Pegawai')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('NIP berhasil disalin')
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
                    ->label('Diselesaikan Oleh')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Kejadian')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(50)
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Tipe Event')
                    ->options([
                        'create' => 'Pembuatan Akun (Create)',
                        'update' => 'Pembaruan Data (Update)',
                        'conflict' => 'Konflik Data (Conflict)',
                        'sync_failure' => 'Kegagalan Sync (Failure)',
                    ]),
                Tables\Filters\SelectFilter::make('conflict_type')
                    ->label('Tipe Konflik')
                    ->options(
                        collect(ConflictType::cases())
                            ->mapWithKeys(fn (ConflictType $type) => [$type->value => ucwords(str_replace('_', ' ', $type->value))])
                            ->toArray()
                    ),
                Tables\Filters\Filter::make('created_at')
                    ->label('Rentang Tanggal')
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
                Tables\Actions\ViewAction::make()
                    ->label('Lihat Snapshot'),
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
                            ->state(fn (KeycloakSyncAudit $record): array => self::flattenForKeyValue($record->pegawai_snapshot))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Infolists\Components\Section::make('Keycloak Snapshot')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('keycloak_snapshot')
                            ->label('')
                            ->state(fn (KeycloakSyncAudit $record): array => self::flattenForKeyValue($record->keycloak_snapshot))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Infolists\Components\Section::make('Resolution')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('resolution')
                            ->label('')
                            ->state(fn (KeycloakSyncAudit $record): array => self::flattenForKeyValue($record->resolution))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(fn (KeycloakSyncAudit $record): bool => ! empty($record->resolution)),
            ]);
    }

    /**
     * Meratakan data snapshot agar aman dirender oleh KeyValueEntry.
     *
     * KeyValueEntry hanya mendukung data key-value satu dimensi, sehingga
     * nilai bersarang (array/objek) dikonversi menjadi string JSON.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, scalar|null>
     */
    protected static function flattenForKeyValue(?array $data): array
    {
        return collect($data ?? [])
            ->map(fn ($value) => is_scalar($value) || $value === null
                ? $value
                : json_encode($value, JSON_UNESCAPED_UNICODE))
            ->all();
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
