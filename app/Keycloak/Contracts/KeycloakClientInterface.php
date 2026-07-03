<?php

namespace App\Keycloak\Contracts;

use App\Keycloak\DataTransferObjects\AuthorizationRequest;
use App\Keycloak\DataTransferObjects\IdTokenClaims;
use App\Keycloak\DataTransferObjects\TokenResult;

/**
 * Interface untuk komunikasi OIDC dengan Keycloak server.
 *
 * Mengelola seluruh protocol communication termasuk authorization URL generation,
 * token exchange, refresh, validasi JWT, logout, dan silent SSO check.
 */
interface KeycloakClientInterface
{
    /**
     * Membangun authorization URL untuk redirect ke Keycloak login page.
     *
     * Menghasilkan URL lengkap dengan PKCE params (code_challenge, code_challenge_method),
     * state parameter untuk CSRF protection, dan scope yang dikonfigurasi.
     */
    public function buildAuthorizationUrl(string $redirectUri): AuthorizationRequest;

    /**
     * Menukar authorization code dengan token set (access, refresh, id).
     *
     * Mengirim code beserta code_verifier ke token endpoint Keycloak
     * untuk membuktikan kepemilikan authorization code (PKCE verification).
     */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenResult;

    /**
     * Refresh access token menggunakan refresh token.
     *
     * Mengirim refresh token ke token endpoint untuk mendapatkan
     * token set baru tanpa interaksi pengguna.
     */
    public function refreshToken(string $refreshToken): TokenResult;

    /**
     * Validasi dan decode ID token (JWT signature verification).
     *
     * Memverifikasi signature menggunakan public key dari JWKS endpoint,
     * memvalidasi issuer, expiry, dan mengekstrak claims.
     */
    public function validateIdToken(string $idToken): IdTokenClaims;

    /**
     * Logout user dari Keycloak session.
     *
     * Mengirim request ke end-session endpoint untuk
     * menginvalidasi server-side session di Keycloak.
     */
    public function logout(string $refreshToken): void;

    /**
     * Silent SSO check (prompt=none).
     *
     * Membangun authorization URL dengan prompt=none untuk mengecek
     * apakah user sudah memiliki active session di Keycloak.
     */
    public function silentCheck(string $redirectUri): AuthorizationRequest;
}
