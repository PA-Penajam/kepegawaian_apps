<?php

namespace App\Providers;

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\ConflictResolutionInterface;
use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\Contracts\KeycloakSyncServiceInterface;
use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\Services\ConflictResolution;
use App\Keycloak\Services\KeycloakCircuitBreaker;
use App\Keycloak\Services\KeycloakClient;
use App\Keycloak\Services\KeycloakSyncService;
use App\Keycloak\Services\KeycloakTokenStorage;
use App\Keycloak\Services\SyncAuditLogger;
use Illuminate\Support\ServiceProvider;

class KeycloakServiceProvider extends ServiceProvider
{
    /**
     * Register Keycloak service bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/keycloak.php',
            'keycloak',
        );

        $this->app->singleton(CircuitBreakerInterface::class, KeycloakCircuitBreaker::class);
        $this->app->singleton(KeycloakTokenStorageInterface::class, KeycloakTokenStorage::class);
        $this->app->singleton(KeycloakClientInterface::class, KeycloakClient::class);
        $this->app->singleton(KeycloakSyncServiceInterface::class, KeycloakSyncService::class);
        $this->app->singleton(ConflictResolutionInterface::class, ConflictResolution::class);
        $this->app->singleton(SyncAuditLogger::class);
    }

    /**
     * Bootstrap Keycloak services.
     */
    public function boot(): void
    {
        //
    }
}
