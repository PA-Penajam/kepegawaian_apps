<?php

namespace App\Services\Iam;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class IamSsoStateStore
{
    private const string CACHE_PREFIX = 'sso_state:';

    private const int TTL_MINUTES = 15;

    /**
     * Simpan tujuan aplikasi konsumen selama pengguna login melalui SSO eksternal.
     */
    public function put(string $appSlug, string $redirectUrl, ?string $oauthState = null): void
    {
        $stateKey = Str::random(40);

        Cache::put(
            self::CACHE_PREFIX.$stateKey,
            ['app' => $appSlug, 'redirect' => $redirectUrl, 'state' => $oauthState],
            now()->addMinutes(self::TTL_MINUTES),
        );

        session([
            'sso_state_key' => $stateKey,
            'sso_app' => $appSlug,
            'sso_redirect' => $redirectUrl,
            'sso_state' => $oauthState,
        ]);
    }

    /**
     * Tentukan apakah ada alur IdP konsumen yang harus dilanjutkan.
     */
    public function hasPending(): bool
    {
        return session()->has('sso_state_key') || session()->has('sso_app');
    }

    /**
     * Ambil dan hapus state tujuan aplikasi konsumen.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    public function pull(): array
    {
        $stateKey = session()->pull('sso_state_key');

        if ($stateKey) {
            $state = Cache::pull(self::CACHE_PREFIX.$stateKey);

            if ($state && ! empty($state['app']) && ! empty($state['redirect'])) {
                session()->forget(['sso_app', 'sso_redirect', 'sso_state']);

                return [$state['app'], $state['redirect'], $state['state'] ?? null];
            }
        }

        return [
            session()->pull('sso_app'),
            session()->pull('sso_redirect'),
            session()->pull('sso_state'),
        ];
    }
}
