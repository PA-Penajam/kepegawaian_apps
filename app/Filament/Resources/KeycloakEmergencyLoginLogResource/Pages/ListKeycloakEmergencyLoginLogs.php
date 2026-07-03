<?php

namespace App\Filament\Resources\KeycloakEmergencyLoginLogResource\Pages;

use App\Filament\Resources\KeycloakEmergencyLoginLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Halaman list untuk emergency login log dengan pagination max 50/page.
 */
class ListKeycloakEmergencyLoginLogs extends ListRecords
{
    protected static string $resource = KeycloakEmergencyLoginLogResource::class;
}
