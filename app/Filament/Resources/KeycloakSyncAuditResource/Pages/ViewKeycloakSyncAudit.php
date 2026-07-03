<?php

namespace App\Filament\Resources\KeycloakSyncAuditResource\Pages;

use App\Filament\Resources\KeycloakSyncAuditResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Halaman detail view untuk Sync Audit Log.
 * Menampilkan pegawai_snapshot dan keycloak_snapshot secara detail.
 */
class ViewKeycloakSyncAudit extends ViewRecord
{
    protected static string $resource = KeycloakSyncAuditResource::class;
}
