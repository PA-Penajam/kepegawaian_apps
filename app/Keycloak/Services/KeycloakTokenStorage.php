<?php

namespace App\Keycloak\Services;

use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\DataTransferObjects\TokenResult;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;

/**
 * Implementasi penyimpanan token Keycloak di Laravel encrypted session.
 *
 * Menyimpan access token, refresh token (terenkripsi), dan
 * expiry timestamp untuk mendukung proactive token refresh.
 */
class KeycloakTokenStorage implements KeycloakTokenStorageInterface
{
    /**
     * Key session untuk menyimpan data token Keycloak.
     */
    private const string SESSION_KEY = 'keycloak.tokens';

    /**
     * {@inheritDoc}
     *
     * Access token disimpan apa adanya, refresh token dienkripsi
     * menggunakan Crypt::encryptString() sebelum disimpan ke session.
     * Expiry dihitung dari waktu saat ini ditambah expiresIn (detik).
     */
    public function storeTokens(TokenResult $tokens): void
    {
        Session::put(self::SESSION_KEY, [
            'access_token' => $tokens->accessToken,
            'refresh_token' => Crypt::encryptString($tokens->refreshToken),
            'expires_at' => Carbon::now()->addSeconds($tokens->expiresIn)->toIso8601String(),
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
     *
     * Mendekripsi refresh token yang tersimpan sebelum dikembalikan.
     */
    public function getRefreshToken(): ?string
    {
        $encrypted = Session::get(self::SESSION_KEY.'.refresh_token');

        if ($encrypted === null) {
            return null;
        }

        return Crypt::decryptString($encrypted);
    }

    /**
     * {@inheritDoc}
     *
     * Mengembalikan Carbon instance dari timestamp ISO8601 yang tersimpan.
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
     *
     * Melakukan atomic replacement: mengganti seluruh data token
     * dalam satu operasi session put untuk memastikan konsistensi.
     */
    public function rotateTokens(TokenResult $newTokens): void
    {
        Session::put(self::SESSION_KEY, [
            'access_token' => $newTokens->accessToken,
            'refresh_token' => Crypt::encryptString($newTokens->refreshToken),
            'expires_at' => Carbon::now()->addSeconds($newTokens->expiresIn)->toIso8601String(),
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * Menghapus seluruh data Keycloak dari session termasuk
     * tokens, user claims, permissions, dan roles.
     */
    public function clearTokens(): void
    {
        Session::forget('keycloak.tokens');
        Session::forget('keycloak.user');
        Session::forget('keycloak.permissions');
        Session::forget('keycloak.roles');
        Session::forget('keycloak.oauth_state');
    }

    /**
     * {@inheritDoc}
     *
     * Token dianggap valid jika access token ada dan
     * waktu expiry belum terlewati.
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
}
