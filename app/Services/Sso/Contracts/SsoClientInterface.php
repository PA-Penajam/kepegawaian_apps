<?php

namespace App\Services\Sso\Contracts;

use App\Services\Sso\DataTransferObjects\SsoAuthorizationRequest;
use App\Services\Sso\DataTransferObjects\SsoTokenResult;
use App\Services\Sso\DataTransferObjects\SsoUserInfo;
use App\Services\Sso\Exceptions\SsoException;

interface SsoClientInterface
{
    /**
     * Membangun Authorization Request URL lengkap dengan PKCE pair dan state CSRF.
     *
     * @param  string  $redirectUri  URI callback setelah user berhasil login di SSO
     * @param  array<int, string>  $scopes  Daftar scope OAuth2 yang diminta
     *
     * @throws SsoException
     */
    public function buildAuthorizationUrl(string $redirectUri, array $scopes = []): SsoAuthorizationRequest;

    /**
     * Menukar authorization code + code verifier menjadi set token OAuth2 (Access Token, Refresh Token).
     *
     * @param  string  $code  Authorization code yang diterima dari SSO
     * @param  string  $codeVerifier  PKCE code_verifier yang disimpan pada session saat request otorisasi
     * @param  string  $redirectUri  URI callback yang sama persis saat inisiasi otorisasi
     *
     * @throws SsoException
     */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): SsoTokenResult;

    /**
     * Memperbarui access token yang telah expired menggunakan refresh token.
     *
     * @param  string  $refreshToken  Refresh token yang valid
     *
     * @throws SsoException
     */
    public function refreshToken(string $refreshToken): SsoTokenResult;

    /**
     * Mengambil profil identitas pengguna terautentikasi dari endpoint /api/user.
     *
     * @param  string  $accessToken  Bearer access token yang valid
     *
     * @throws SsoException
     */
    public function getUserInfo(string $accessToken): SsoUserInfo;

    /**
     * Mengakhiri sesi SSO atau mencabut refresh token (opsional).
     *
     * @param  string|null  $refreshToken  Refresh token yang akan dicabut
     */
    public function logout(?string $refreshToken = null): void;
}
