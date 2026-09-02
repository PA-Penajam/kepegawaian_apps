<?php

namespace App\Services\Sso\Services;

use App\Services\Sso\Contracts\SsoClientInterface;
use App\Services\Sso\DataTransferObjects\SsoAuthorizationRequest;
use App\Services\Sso\DataTransferObjects\SsoTokenResult;
use App\Services\Sso\DataTransferObjects\SsoUserInfo;
use App\Services\Sso\Exceptions\SsoException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoClient implements SsoClientInterface
{
    public function __construct(
        private PkceGenerator $pkceGenerator,
        private string $baseUrl,
        private string $clientId,
        private ?string $clientSecret = null,
        private int $timeout = 5,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * {@inheritDoc}
     */
    public function buildAuthorizationUrl(string $redirectUri, array $scopes = []): SsoAuthorizationRequest
    {
        $pkce = $this->pkceGenerator->generate();
        $state = $this->generateState();

        $defaultScopes = (array) config('sso.scopes', []);
        $effectiveScopes = ! empty($scopes) ? $scopes : $defaultScopes;
        $scopeString = implode(' ', array_filter($effectiveScopes));

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
            'code_challenge' => $pkce->challenge,
            'code_challenge_method' => $pkce->method,
        ];

        if (! empty($scopeString)) {
            $params['scope'] = $scopeString;
        }

        $url = $this->baseUrl.'/oauth/authorize?'.http_build_query($params);

        return new SsoAuthorizationRequest(
            url: $url,
            state: $state,
            pkce: $pkce,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri): SsoTokenResult
    {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ];

        if (! empty($this->clientSecret)) {
            $params['client_secret'] = $this->clientSecret;
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->baseUrl.'/oauth/token', $params);

            if (! $response->successful()) {
                Log::error('SSO token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new SsoException(
                    'Gagal menukar authorization code ke SSO: '.($response->json('message') ?? $response->json('error_description') ?? $response->body()),
                    SsoException::CODE_EXCHANGE_FAILED,
                );
            }

            return SsoTokenResult::fromArray($response->json());
        } catch (ConnectionException $e) {
            Log::error('SSO connection error during token exchange', ['error' => $e->getMessage()]);

            throw new SsoException(
                'Server SSO PA Penajam tidak dapat dihubungi: '.$e->getMessage(),
                SsoException::SSO_UNREACHABLE,
                $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function refreshToken(string $refreshToken): SsoTokenResult
    {
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
        ];

        if (! empty($this->clientSecret)) {
            $params['client_secret'] = $this->clientSecret;
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->baseUrl.'/oauth/token', $params);

            if (! $response->successful()) {
                Log::warning('SSO refresh token failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new SsoException(
                    'Gagal memperbarui token SSO: '.($response->json('message') ?? $response->json('error_description') ?? $response->body()),
                    SsoException::REFRESH_TOKEN_FAILED,
                );
            }

            return SsoTokenResult::fromArray($response->json());
        } catch (ConnectionException $e) {
            Log::error('SSO connection error during refresh token', ['error' => $e->getMessage()]);

            throw new SsoException(
                'Server SSO PA Penajam tidak dapat dihubungi: '.$e->getMessage(),
                SsoException::SSO_UNREACHABLE,
                $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getUserInfo(string $accessToken): SsoUserInfo
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout($this->timeout)
                ->acceptJson()
                ->get($this->baseUrl.'/api/user');

            if (! $response->successful()) {
                Log::error('SSO getUserInfo failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                throw new SsoException(
                    'Gagal mengambil data profil dari SSO: '.($response->json('message') ?? $response->body()),
                    SsoException::USER_INFO_FAILED,
                );
            }

            $payload = $response->json('data') ?? $response->json();

            return SsoUserInfo::fromArray((array) $payload);
        } catch (ConnectionException $e) {
            Log::error('SSO connection error during getUserInfo', ['error' => $e->getMessage()]);

            throw new SsoException(
                'Server SSO PA Penajam tidak dapat dihubungi: '.$e->getMessage(),
                SsoException::SSO_UNREACHABLE,
                $e,
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function logout(?string $refreshToken = null): void
    {
        // SSO Passport tidak mewajibkan back-channel logout, namun kita siapkan graceful handler
        Log::info('SSO client logout executed locally.');
    }

    /**
     * Generate random state string untuk pencegahan CSRF.
     */
    private function generateState(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Exception $e) {
            throw new SsoException(
                'Gagal menghasilkan CSRF state: '.$e->getMessage(),
                SsoException::CSPRNG_UNAVAILABLE,
                $e,
            );
        }
    }
}
