<?php

namespace App\Services\Sso\Services;

use App\Services\Sso\Contracts\SsoTokenStorageInterface;
use App\Services\Sso\DataTransferObjects\SsoTokenResult;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;

/**
 * Implementasi penyimpanan token SSO PA Penajam di Laravel session.
 *
 * Menyimpan access token, refresh token (terenkripsi), dan
 * expiry timestamp untuk mendukung proactive token refresh.
 */
class SsoTokenStorage implements SsoTokenStorageInterface
{
    /** Key session untuk menyimpan data token SSO */
    public const string SESSION_KEY = 'sso.tokens';

    /**
     * {@inheritDoc}
     */
    public function storeTokens(SsoTokenResult $tokens): void
    {
        Session::put(self::SESSION_KEY, [
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken !== null ? Crypt::encryptString($tokens->refreshToken) : null,
            'expires_at' => Carbon::now()->addSeconds($tokens->expiresIn)->toIso8601String(),
            'token_type' => $tokens->tokenType,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessToken(): ?string
    {
        return Session::get(self::SESSION_KEY.'.access_token');
    }

    /**
     * {@inheritDoc}
     */
    public function getRefreshToken(): ?string
    {
        $encrypted = Session::get(self::SESSION_KEY.'.refresh_token');

        if ($encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAccessTokenExpiry(): ?CarbonInterface
    {
        $expiresAt = Session::get(self::SESSION_KEY.'.expires_at');

        if ($expiresAt === null) {
            return null;
        }

        return Carbon::parse($expiresAt);
    }

    /**
     * {@inheritDoc}
     */
    public function rotateTokens(SsoTokenResult $newTokens): void
    {
        $currentRefreshToken = $this->getRefreshToken();
        $refreshTokenToStore = $newTokens->refreshToken ?? $currentRefreshToken;

        Session::put(self::SESSION_KEY, [
            'access_token' => $newTokens->accessToken,
            'refresh_token' => $refreshTokenToStore !== null ? Crypt::encryptString($refreshTokenToStore) : null,
            'expires_at' => Carbon::now()->addSeconds($newTokens->expiresIn)->toIso8601String(),
            'token_type' => $newTokens->tokenType,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function clearTokens(): void
    {
        Session::forget('sso.tokens');
        Session::forget('sso.user');
        Session::forget('sso.oauth_state');
    }

    /**
     * {@inheritDoc}
     */
    public function isTokenValid(): bool
    {
        $accessToken = $this->getAccessToken();

        if ($accessToken === null) {
            return false;
        }

        $expiry = $this->getAccessTokenExpiry();

        if ($expiry === null) {
            return false;
        }

        return $expiry->isFuture();
    }

    /**
     * {@inheritDoc}
     */
    public function hasTokens(): bool
    {
        return $this->getAccessToken() !== null;
    }
}
