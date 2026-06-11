<?php

use App\Keycloak\DataTransferObjects\AuthorizationRequest;
use App\Keycloak\DataTransferObjects\PkcePair;
use App\Keycloak\DataTransferObjects\TokenResult;
use App\Keycloak\Exceptions\KeycloakException;
use App\Keycloak\Services\KeycloakClient;
use App\Keycloak\Services\PkceGenerator;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Konfigurasi Keycloak untuk testing
    config([
        'keycloak.base_url' => 'http://keycloak.test:8080',
        'keycloak.realm' => 'kepegawaian',
        'keycloak.client.id' => 'kepegawaian-apps',
        'keycloak.client.secret' => 'test-secret',
        'keycloak.scopes' => ['openid', 'profile', 'email', 'roles'],
        'keycloak.tokens.request_timeout_seconds' => 5,
    ]);

    $this->pkceGenerator = new PkceGenerator;
    $this->client = new KeycloakClient($this->pkceGenerator);
});

// === buildAuthorizationUrl Tests ===

describe('buildAuthorizationUrl', function () {
    test('mengembalikan AuthorizationRequest', function () {
        $result = $this->client->buildAuthorizationUrl('http://app.test/callback');

        expect($result)->toBeInstanceOf(AuthorizationRequest::class);
    });

    test('menghasilkan URL dengan semua parameter OIDC yang diperlukan', function () {
        $result = $this->client->buildAuthorizationUrl('http://app.test/callback');

        $parsedUrl = parse_url($result->url);
        parse_str($parsedUrl['query'], $params);

        expect($parsedUrl['scheme'])->toBe('http')
            ->and($parsedUrl['host'])->toBe('keycloak.test')
            ->and($parsedUrl['path'])->toBe('/realms/kepegawaian/protocol/openid-connect/auth')
            ->and($params['client_id'])->toBe('kepegawaian-apps')
            ->and($params['response_type'])->toBe('code')
            ->and($params['scope'])->toContain('openid')
            ->and($params['redirect_uri'])->toBe('http://app.test/callback')
            ->and($params['state'])->toBe($result->state)
            ->and($params['code_challenge'])->toBe($result->pkce->challenge)
            ->and($params['code_challenge_method'])->toBe('S256');
    });

    test('menghasilkan state 64 karakter hex', function () {
        $result = $this->client->buildAuthorizationUrl('http://app.test/callback');

        // 32 bytes hex encoded = 64 karakter
        expect($result->state)
            ->toHaveLength(64)
            ->toMatch('/^[a-f0-9]+$/');
    });

    test('menghasilkan PKCE pair yang valid', function () {
        $result = $this->client->buildAuthorizationUrl('http://app.test/callback');

        expect($result->pkce)->toBeInstanceOf(PkcePair::class)
            ->and($result->pkce->method)->toBe('S256')
            ->and($result->pkce->verifier)->toMatch('/^[A-Za-z0-9\-_]+$/')
            ->and($result->pkce->challenge)->toMatch('/^[A-Za-z0-9\-_]+$/');
    });

    test('tidak menyertakan prompt parameter', function () {
        $result = $this->client->buildAuthorizationUrl('http://app.test/callback');

        $parsedUrl = parse_url($result->url);
        parse_str($parsedUrl['query'], $params);

        expect($params)->not->toHaveKey('prompt');
    });

    test('setiap panggilan menghasilkan state yang unik', function () {
        $states = collect(range(1, 5))
            ->map(fn () => $this->client->buildAuthorizationUrl('http://app.test/callback'))
            ->pluck('state')
            ->unique();

        expect($states)->toHaveCount(5);
    });
});

// === silentCheck Tests ===

describe('silentCheck', function () {
    test('menyertakan prompt=none parameter', function () {
        $result = $this->client->silentCheck('http://app.test/callback');

        $parsedUrl = parse_url($result->url);
        parse_str($parsedUrl['query'], $params);

        expect($params['prompt'])->toBe('none');
    });

    test('mengembalikan AuthorizationRequest dengan PKCE', function () {
        $result = $this->client->silentCheck('http://app.test/callback');

        expect($result)->toBeInstanceOf(AuthorizationRequest::class)
            ->and($result->pkce)->toBeInstanceOf(PkcePair::class)
            ->and($result->state)->toHaveLength(64);
    });
});

// === exchangeCode Tests ===

describe('exchangeCode', function () {
    test('mengembalikan TokenResult pada response sukses', function () {
        Http::fake([
            'keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-456',
                'id_token' => 'id-token-789',
                'expires_in' => 300,
                'refresh_expires_in' => 1800,
                'token_type' => 'Bearer',
            ]),
        ]);

        $result = $this->client->exchangeCode('auth-code', 'code-verifier', 'http://app.test/callback');

        expect($result)->toBeInstanceOf(TokenResult::class)
            ->and($result->accessToken)->toBe('access-token-123')
            ->and($result->refreshToken)->toBe('refresh-token-456')
            ->and($result->idToken)->toBe('id-token-789')
            ->and($result->expiresIn)->toBe(300)
            ->and($result->refreshExpiresIn)->toBe(1800)
            ->and($result->tokenType)->toBe('Bearer');
    });

    test('mengirim parameter yang benar ke token endpoint', function () {
        Http::fake([
            'keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/token' => Http::response([
                'access_token' => 'at',
                'refresh_token' => 'rt',
                'id_token' => 'it',
                'expires_in' => 300,
                'refresh_expires_in' => 1800,
            ]),
        ]);

        $this->client->exchangeCode('my-code', 'my-verifier', 'http://app.test/cb');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'my-code'
                && $request['code_verifier'] === 'my-verifier'
                && $request['redirect_uri'] === 'http://app.test/cb'
                && $request['client_id'] === 'kepegawaian-apps'
                && $request['client_secret'] === 'test-secret';
        });
    });

    test('melempar KeycloakException pada response gagal', function () {
        Http::fake([
            'keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Code not valid',
            ], 400),
        ]);

        $this->client->exchangeCode('invalid-code', 'verifier', 'http://app.test/callback');
    })->throws(KeycloakException::class, 'Gagal exchange authorization code: Code not valid');
});

// === refreshToken Tests ===

describe('refreshToken', function () {
    test('mengembalikan TokenResult pada response sukses', function () {
        Http::fake([
            'keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'id_token' => 'new-id-token',
                'expires_in' => 300,
                'refresh_expires_in' => 1800,
                'token_type' => 'Bearer',
            ]),
        ]);

        $result = $this->client->refreshToken('old-refresh-token');

        expect($result)->toBeInstanceOf(TokenResult::class)
            ->and($result->accessToken)->toBe('new-access-token')
            ->and($result->refreshToken)->toBe('new-refresh-token');
    });

    test('mengirim parameter grant_type=refresh_token', function () {
        Http::fake([
            'keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/token' => Http::response([
                'access_token' => 'at',
                'refresh_token' => 'rt',
                'id_token' => 'it',
                'expires_in' => 300,
            ]),
        ]);

        $this->client->refreshToken('my-refresh-token');

        Http::assertSent(function ($request) {
            return $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'my-refresh-token'
                && $request['client_id'] === 'kepegawaian-apps'
                && $request['client_secret'] === 'test-secret';
        });
    });

    test('melempar KeycloakException pada response gagal', function () {
        Http::fake([
            'keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token is not active',
            ], 400),
        ]);

        $this->client->refreshToken('expired-refresh-token');
    })->throws(KeycloakException::class, 'Gagal refresh token: Token is not active');
});

// === logout Tests ===

describe('logout', function () {
    test('berhasil mengirim request ke end-session endpoint', function () {
        Http::fake([
            'keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/logout' => Http::response(null, 204),
        ]);

        $this->client->logout('refresh-token-to-revoke');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/logout'
                && $request['client_id'] === 'kepegawaian-apps'
                && $request['client_secret'] === 'test-secret'
                && $request['refresh_token'] === 'refresh-token-to-revoke';
        });
    });

    test('melempar KeycloakException pada response gagal', function () {
        Http::fake([
            'keycloak.test:8080/realms/kepegawaian/protocol/openid-connect/logout' => Http::response([
                'error' => 'invalid_token',
                'error_description' => 'Token invalid',
            ], 400),
        ]);

        $this->client->logout('invalid-refresh-token');
    })->throws(KeycloakException::class, 'Gagal logout dari Keycloak');
});

// === validateIdToken Tests ===

describe('validateIdToken', function () {
    test('melempar exception pada JWT format invalid (kurang dari 3 bagian)', function () {
        $this->client->validateIdToken('invalid.jwt');
    })->throws(KeycloakException::class);

    test('melempar exception pada payload yang tidak bisa di-decode', function () {
        // JWT dengan payload yang bukan JSON valid
        $header = strtr(rtrim(base64_encode('{"alg":"RS256","typ":"JWT"}'), '='), '+/', '-_');
        $payload = strtr(rtrim(base64_encode('not-json'), '='), '+/', '-_');
        $signature = strtr(rtrim(base64_encode('sig'), '='), '+/', '-_');

        $this->client->validateIdToken("{$header}.{$payload}.{$signature}");
    })->throws(KeycloakException::class);
});
