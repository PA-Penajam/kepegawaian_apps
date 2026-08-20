<?php

namespace App\Providers;

use App\Services\Sso\Contracts\SsoClientInterface;
use App\Services\Sso\Contracts\SsoTokenStorageInterface;
use App\Services\Sso\Services\PkceGenerator;
use App\Services\Sso\Services\SsoClient;
use App\Services\Sso\Services\SsoTokenStorage;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class SsoServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(SsoTokenStorageInterface::class, SsoTokenStorage::class);
        $this->app->singleton(PkceGenerator::class, PkceGenerator::class);

        $this->app->singleton(SsoClientInterface::class, function (Application $app): SsoClient {
            return new SsoClient(
                pkceGenerator: $app->make(PkceGenerator::class),
                baseUrl: (string) config('sso.base_url', 'http://localhost:8000'),
                clientId: (string) config('sso.client_id', 'kepegawaian-apps'),
                clientSecret: config('sso.client_secret'),
                timeout: (int) config('sso.tokens.timeout', 5),
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
