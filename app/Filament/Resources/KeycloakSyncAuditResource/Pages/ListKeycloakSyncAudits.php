<?php

namespace App\Filament\Resources\KeycloakSyncAuditResource\Pages;

use App\Filament\Resources\KeycloakSyncAuditResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Halaman list untuk Sync Audit Log dengan pagination max 50/page.
 */
class ListKeycloakSyncAudits extends ListRecords
{
    protected static string $resource = KeycloakSyncAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
