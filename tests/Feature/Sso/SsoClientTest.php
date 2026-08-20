<?php

use App\Services\Sso\DataTransferObjects\PkcePair;
use App\Services\Sso\Exceptions\SsoException;
use App\Services\Sso\Services\PkceGenerator;
use App\Services\Sso\Services\SsoClient;
use Illuminate\Support\Facades\Http;

describe('SsoClient', function () {
    beforeEach(function () {
        $this->pkceGenerator = new PkceGenerator;
        $this->baseUrl = 'https://sso.pa-penajam.go.id';
        $this->clientId = 'kepegawaian-apps';
        $this->clientSecret = 'test-secret';

        $this->client = new SsoClient(
            pkceGenerator: $this->pkceGenerator,
            baseUrl: $this->baseUrl,
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            timeout: 5,
        );
    });

    test('buildAuthorizationUrl membentuk URL otorisasi OAuth2 dengan PKCE dan CSRF state', function () {
        $redirectUri = 'https://kepegawaian.pa-penajam.go.id/auth/sso/callback';
        $request = $this->client->buildAuthorizationUrl($redirectUri);

        expect($request->url)->toContain('https://sso.pa-penajam.go.id/oauth/authorize');
        expect($request->url)->toContain('client_id=kepegawaian-apps');
        expect($request->url)->toContain('response_type=code');
        expect($request->url)->toContain('code_challenge_method=S256');
        expect($request->url)->toContain('code_challenge='.$request->pkce->challenge);
        expect($request->url)->toContain('state='.$request->state);
        expect($request->state)->toBeString()->not->toBeEmpty();
        expect($request->pkce)->toBeInstanceOf(PkcePair::class);
    });

    test('exchangeCode berhasil menukar authorization code menjadi SsoTokenResult', function () {
        Http::fake([
            'https://sso.pa-penajam.go.id/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'mock-access-token',
                'refresh_token' => 'mock-refresh-token',
            ], 200),
        ]);

        $tokens = $this->client->exchangeCode(
            code: 'mock-code',
            codeVerifier: 'mock-verifier',
            redirectUri: 'https://kepegawaian.pa-penajam.go.id/auth/sso/callback',
        );

        expect($tokens->accessToken)->toBe('mock-access-token');
        expect($tokens->refreshToken)->toBe('mock-refresh-token');
        expect($tokens->expiresIn)->toBe(3600);
        expect($tokens->tokenType)->toBe('Bearer');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sso.pa-penajam.go.id/oauth/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['client_id'] === 'kepegawaian-apps'
                && $request['client_secret'] === 'test-secret'
                && $request['code'] === 'mock-code'
                && $request['code_verifier'] === 'mock-verifier';
        });
    });

    test('exchangeCode melempar SsoException saat SSO merespons error', function () {
        Http::fake([
            'https://sso.pa-penajam.go.id/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Authorization code has expired',
            ], 400),
        ]);

        expect(fn () => $this->client->exchangeCode(
            code: 'expired-code',
            codeVerifier: 'mock-verifier',
            redirectUri: 'https://kepegawaian.pa-penajam.go.id/auth/sso/callback',
        ))->toThrow(SsoException::class, 'Gagal menukar authorization code ke SSO');
    });

    test('refreshToken berhasil memperbarui access token', function () {
        Http::fake([
            'https://sso.pa-penajam.go.id/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 7200,
                'access_token' => 'new-mock-access-token',
                'refresh_token' => 'new-mock-refresh-token',
            ], 200),
        ]);

        $tokens = $this->client->refreshToken('current-refresh-token');

        expect($tokens->accessToken)->toBe('new-mock-access-token');
        expect($tokens->refreshToken)->toBe('new-mock-refresh-token');
        expect($tokens->expiresIn)->toBe(7200);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sso.pa-penajam.go.id/oauth/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'current-refresh-token'
                && $request['client_id'] === 'kepegawaian-apps'
                && $request['client_secret'] === 'test-secret';
        });
    });

    test('getUserInfo berhasil mengambil data profil dan NIP pengguna', function () {
        Http::fake([
            'https://sso.pa-penajam.go.id/api/user' => Http::response([
                'data' => [
                    'sub' => '199501012020121001',
                    'nip' => '199501012020121001',
                    'name' => 'Ahmad Fauzi, S.Kom.',
                    'email' => 'ahmad.fauzi@pa-penajam.go.id',
                    'department' => 'Kepaniteraan',
                    'position' => 'Pranata Komputer Ahli Pertama',
                    'security_level' => 'standard',
                ],
            ], 200),
        ]);

        $userInfo = $this->client->getUserInfo('mock-access-token');

        expect($userInfo->nip)->toBe('199501012020121001');
        expect($userInfo->sub)->toBe('199501012020121001');
        expect($userInfo->name)->toBe('Ahmad Fauzi, S.Kom.');
        expect($userInfo->email)->toBe('ahmad.fauzi@pa-penajam.go.id');
        expect($userInfo->department)->toBe('Kepaniteraan');
        expect($userInfo->position)->toBe('Pranata Komputer Ahli Pertama');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sso.pa-penajam.go.id/api/user'
                && $request->hasHeader('Authorization', 'Bearer mock-access-token');
        });
    });

    test('getUserInfo melempar SsoException saat endpoint userinfo mengembalikan error', function () {
        Http::fake([
            'https://sso.pa-penajam.go.id/api/user' => Http::response([
                'message' => 'Unauthenticated.',
            ], 401),
        ]);

        expect(fn () => $this->client->getUserInfo('invalid-token'))
            ->toThrow(SsoException::class, 'Gagal mengambil data profil dari SSO');
    });
});
