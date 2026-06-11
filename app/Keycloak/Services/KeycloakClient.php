<?php

namespace App\Keycloak\Services;

use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\DataTransferObjects\AuthorizationRequest;
use App\Keycloak\DataTransferObjects\IdTokenClaims;
use App\Keycloak\DataTransferObjects\TokenResult;
use App\Keycloak\Exceptions\KeycloakException;
use Illuminate\Support\Facades\Http;
use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;

/**
 * Implementasi KeycloakClient menggunakan jumbojett/openid-connect-php.
 *
 * Menangani seluruh komunikasi OIDC dengan Keycloak server termasuk
 * authorization URL generation, token exchange, refresh, validasi JWT,
 * logout, dan silent SSO check.
 */
class KeycloakClient implements KeycloakClientInterface
{
    public function __construct(
        private PkceGenerator $pkceGenerator,
    ) {}

    /**
     * Membangun authorization URL untuk redirect ke Keycloak login page.
     *
     * Menghasilkan URL dengan PKCE params, state CSRF, dan semua
     * parameter yang diperlukan untuk OIDC Authorization Code flow.
     */
    public function buildAuthorizationUrl(string $redirectUri): AuthorizationRequest
    {
        return $this->buildAuthUrl($redirectUri);
    }

    /**
     * Menukar authorization code dengan token set menggunakan code_verifier.
     *
     * POST ke token endpoint Keycloak dengan grant_type=authorization_code,
     * code, redirect_uri, code_verifier, client_id, dan client_secret.
     *
     * @throws KeycloakException Jika exchange gagal atau response invalid
     */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): TokenResult
    {
        $tokenEndpoint = $this->getTokenEndpoint();
        $timeout = config('keycloak.tokens.request_timeout_seconds', 5);

        $response = Http::asForm()
            ->timeout($timeout)
            ->post($tokenEndpoint, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $codeVerifier,
                'client_id' => config('keycloak.client.id'),
                'client_secret' => config('keycloak.client.secret'),
            ]);

        if ($response->failed()) {
            $errorDescription = $response->json('error_description', 'Token exchange gagal');

            throw new KeycloakException(
                "Gagal exchange authorization code: {$errorDescription}",
                KeycloakException::CODE_EXCHANGE_FAILED,
            );
        }

        $data = $response->json();

        return new TokenResult(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'],
            idToken: $data['id_token'] ?? '',
            expiresIn: $data['expires_in'],
            refreshExpiresIn: $data['refresh_expires_in'] ?? 0,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }

    /**
     * Refresh access token menggunakan refresh token.
     *
     * POST ke token endpoint dengan grant_type=refresh_token untuk
     * mendapatkan token set baru tanpa interaksi pengguna.
     *
     * @throws KeycloakException Jika refresh gagal
     */
    public function refreshToken(string $refreshToken): TokenResult
    {
        $tokenEndpoint = $this->getTokenEndpoint();
        $timeout = config('keycloak.tokens.request_timeout_seconds', 5);

        $response = Http::asForm()
            ->timeout($timeout)
            ->post($tokenEndpoint, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => config('keycloak.client.id'),
                'client_secret' => config('keycloak.client.secret'),
            ]);

        if ($response->failed()) {
            $errorDescription = $response->json('error_description', 'Token refresh gagal');

            throw new KeycloakException(
                "Gagal refresh token: {$errorDescription}",
                KeycloakException::REFRESH_FAILED,
            );
        }

        $data = $response->json();

        return new TokenResult(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'],
            idToken: $data['id_token'] ?? '',
            expiresIn: $data['expires_in'],
            refreshExpiresIn: $data['refresh_expires_in'] ?? 0,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }

    /**
     * Validasi dan decode ID token JWT.
     *
     * Memverifikasi signature menggunakan JWKS public key dari Keycloak,
     * memvalidasi issuer matches realm URL, memvalidasi expiry,
     * dan mengekstrak claims.
     *
     * @throws KeycloakException Jika signature invalid atau claims tidak valid
     */
    public function validateIdToken(string $idToken): IdTokenClaims
    {
        $oidcClient = $this->createOidcClient();

        try {
            // Verifikasi signature JWT menggunakan JWKS endpoint
            $oidcClient->verifySignatures($idToken);
        } catch (OpenIDConnectClientException $e) {
            throw new KeycloakException(
                "Gagal validasi signature ID token: {$e->getMessage()}",
                KeycloakException::INVALID_TOKEN,
                $e,
            );
        }

        // Decode payload JWT
        $payload = $this->decodeJwtPayload($idToken);

        // Validasi issuer
        $expectedIssuer = $this->getRealmUrl();
        if (($payload->iss ?? '') !== $expectedIssuer) {
            throw new KeycloakException(
                "Issuer tidak valid. Expected: {$expectedIssuer}, got: ".($payload->iss ?? 'null'),
                KeycloakException::INVALID_TOKEN,
            );
        }

        // Validasi expiry
        if (($payload->exp ?? 0) < time()) {
            throw new KeycloakException(
                'ID token sudah expired',
                KeycloakException::TOKEN_EXPIRED,
            );
        }

        // Ekstrak roles dan permissions dari realm_access dan resource_access
        $roles = $this->extractRoles($payload);
        $permissions = $this->extractPermissions($payload);

        return new IdTokenClaims(
            sub: $payload->sub ?? '',
            nip: $payload->preferred_username ?? $payload->nip ?? '',
            email: $payload->email ?? '',
            name: $payload->name ?? '',
            roles: $roles,
            permissions: $permissions,
            exp: $payload->exp ?? 0,
            iat: $payload->iat ?? 0,
            iss: $payload->iss ?? '',
        );
    }

    /**
     * Logout user dari Keycloak session.
     *
     * POST ke end-session endpoint menggunakan refresh token
     * untuk menginvalidasi server-side session di Keycloak.
     *
     * @throws KeycloakException Jika logout gagal
     */
    public function logout(string $refreshToken): void
    {
        $endSessionEndpoint = $this->getEndSessionEndpoint();
        $timeout = config('keycloak.tokens.request_timeout_seconds', 5);

        $response = Http::asForm()
            ->timeout($timeout)
            ->post($endSessionEndpoint, [
                'client_id' => config('keycloak.client.id'),
                'client_secret' => config('keycloak.client.secret'),
                'refresh_token' => $refreshToken,
            ]);

        if ($response->failed()) {
            throw new KeycloakException(
                'Gagal logout dari Keycloak: '.$response->json('error_description', 'Unknown error'),
                KeycloakException::LOGOUT_FAILED,
            );
        }
    }

    /**
     * Silent SSO check dengan prompt=none.
     *
     * Membangun authorization URL dengan parameter prompt=none untuk
     * mengecek apakah user sudah memiliki active session di Keycloak
     * tanpa menampilkan login page.
     */
    public function silentCheck(string $redirectUri): AuthorizationRequest
    {
        return $this->buildAuthUrl($redirectUri, promptNone: true);
    }

    /**
     * Membangun authorization URL dengan parameter OIDC yang lengkap.
     *
     * @param  bool  $promptNone  Jika true, tambahkan prompt=none untuk silent check
     */
    private function buildAuthUrl(string $redirectUri, bool $promptNone = false): AuthorizationRequest
    {
        // Generate PKCE pair
        $pkce = $this->pkceGenerator->generate();

        // Generate state CSRF (32 random bytes, hex encoded = 64 karakter)
        $state = bin2hex(random_bytes(32));

        // Bangun query parameters
        $params = [
            'client_id' => config('keycloak.client.id'),
            'response_type' => 'code',
            'scope' => implode(' ', config('keycloak.scopes', ['openid', 'profile', 'email'])),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => $pkce->method,
        ];

        if ($promptNone) {
            $params['prompt'] = 'none';
        }

        // Bangun authorization URL
        $authorizationEndpoint = $this->getAuthorizationEndpoint();
        $url = $authorizationEndpoint.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return new AuthorizationRequest(
            url: $url,
            state: $state,
            pkce: $pkce,
        );
    }

    /**
     * Membuat instance OpenIDConnectClient yang dikonfigurasi untuk validasi JWT.
     */
    private function createOidcClient(): OpenIDConnectClient
    {
        $providerUrl = $this->getRealmUrl();
        $timeout = config('keycloak.tokens.request_timeout_seconds', 5);

        $client = new OpenIDConnectClient(
            provider_url: $providerUrl,
            client_id: config('keycloak.client.id'),
            client_secret: config('keycloak.client.secret'),
        );

        $client->setTimeout($timeout);

        return $client;
    }

    /**
     * Decode payload (section 1) dari JWT token.
     */
    private function decodeJwtPayload(string $jwt): object
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new KeycloakException(
                'Format JWT tidak valid: harus memiliki 3 bagian',
                KeycloakException::INVALID_TOKEN,
            );
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), false);

        if (! $payload || ! is_object($payload)) {
            throw new KeycloakException(
                'Gagal decode payload JWT',
                KeycloakException::INVALID_TOKEN,
            );
        }

        return $payload;
    }

    /**
     * Ekstrak roles dari JWT payload (realm_access.roles).
     *
     * @return array<int, string>
     */
    private function extractRoles(object $payload): array
    {
        $roles = [];

        // Realm roles
        if (isset($payload->realm_access->roles) && is_array($payload->realm_access->roles)) {
            $roles = array_merge($roles, $payload->realm_access->roles);
        }

        return $roles;
    }

    /**
     * Ekstrak permissions dari JWT payload (resource_access).
     *
     * @return array<int, string>
     */
    private function extractPermissions(object $payload): array
    {
        $permissions = [];

        // Resource access permissions (client-specific roles)
        $clientId = config('keycloak.client.id');

        if (isset($payload->resource_access->$clientId->roles) && is_array($payload->resource_access->$clientId->roles)) {
            $permissions = array_merge($permissions, $payload->resource_access->$clientId->roles);
        }

        // Custom permissions claim jika ada
        if (isset($payload->permissions) && is_array($payload->permissions)) {
            $permissions = array_merge($permissions, $payload->permissions);
        }

        return array_unique($permissions);
    }

    /**
     * Mendapatkan base URL realm Keycloak.
     */
    private function getRealmUrl(): string
    {
        $baseUrl = rtrim(config('keycloak.base_url'), '/');
        $realm = config('keycloak.realm');

        return "{$baseUrl}/realms/{$realm}";
    }

    /**
     * Mendapatkan authorization endpoint URL.
     */
    private function getAuthorizationEndpoint(): string
    {
        return $this->getRealmUrl().'/protocol/openid-connect/auth';
    }

    /**
     * Mendapatkan token endpoint URL.
     */
    private function getTokenEndpoint(): string
    {
        return $this->getRealmUrl().'/protocol/openid-connect/token';
    }

    /**
     * Mendapatkan end-session endpoint URL.
     */
    private function getEndSessionEndpoint(): string
    {
        return $this->getRealmUrl().'/protocol/openid-connect/logout';
    }
}
