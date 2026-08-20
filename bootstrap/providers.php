<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\KeycloakServiceProvider;
use App\Providers\SsoServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    KeycloakServiceProvider::class,
    SsoServiceProvider::class,
];
