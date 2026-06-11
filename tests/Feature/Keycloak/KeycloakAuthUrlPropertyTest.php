<?php

/**
 * Property-Based Tests untuk Authorization URL KeycloakClient.
 *
 * Menguji properti universal dari authorization URL generation:
 * - Property 3: Authorization URL Completeness (Req 1.3)
 */

use App\Keycloak\Services\KeycloakClient;
use App\Keycloak\Services\PkceGenerator;

beforeEach(function () {
    // Konfigurasi Keycloak untuk testing
    config([
        'keycloak.base_url' => 'http://keycloak.test:8080',
        'keycloak.realm' => 'kepegawaian',
        'keycloak.client.id' => 'kepegawaian-apps',
        'keycloak.scopes' => ['openid', 'profile', 'email', 'roles'],
    ]);

    $this->pkceGenerator = new PkceGenerator;
    $this->client = new KeycloakClient($this->pkceGenerator);
});

/**
 * Menghasilkan redirect URI acak untuk property testing.
 */
function generateRandomRedirectUri(): string
{
    $schemes = ['http', 'https'];
    $scheme = $schemes[array_rand($schemes)];

    $hosts = [
        'app.test',
        'localhost',
        'my-app.example.com',
        'staging.kepegawaian.go.id',
        '192.168.1.100',
        'internal-service.local',
    ];
    $host = $hosts[array_rand($hosts)];

    $paths = [
        '/callback',
        '/auth/callback',
        '/keycloak/callback',
        '/oauth2/redirect',
        '/api/v1/auth/callback',
        '/sso/complete',
    ];
    $path = $paths[array_rand($paths)];

    $ports = ['', ':3000', ':8000', ':8080', ':443'];
    $port = $ports[array_rand($ports)];

    return "{$scheme}://{$host}{$port}{$path}";
}

// ============================================================
// Property 3: Authorization URL Completeness
// **Validates: Requirement 1.3**
// ============================================================

describe('Property 3: Authorization URL Completeness', function () {
    test('URL selalu mengandung client_id yang sesuai dengan konfigurasi', function () {
        // Untuk semua authorization URL yang dihasilkan,
        // parameter client_id harus selalu sesuai dengan config.
        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $parsedUrl = parse_url($result->url);
            parse_str($parsedUrl['query'], $params);

            expect($params)->toHaveKey('client_id')
                ->and($params['client_id'])->toBe('kepegawaian-apps');
        }
    });

    test('URL selalu mengandung response_type=code', function () {
        // Untuk semua authorization URL yang dihasilkan,
        // parameter response_type harus selalu bernilai 'code'.
        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $parsedUrl = parse_url($result->url);
            parse_str($parsedUrl['query'], $params);

            expect($params)->toHaveKey('response_type')
                ->and($params['response_type'])->toBe('code');
        }
    });

    test('URL selalu mengandung scope yang menyertakan openid', function () {
        // Untuk semua authorization URL yang dihasilkan,
        // parameter scope harus selalu menyertakan 'openid'.
        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $parsedUrl = parse_url($result->url);
            parse_str($parsedUrl['query'], $params);

            expect($params)->toHaveKey('scope');

            $scopes = explode(' ', $params['scope']);
            expect($scopes)->toContain('openid');
        }
    });

    test('URL selalu mengandung redirect_uri yang sesuai dengan input', function () {
        // Untuk semua authorization URL yang dihasilkan,
        // parameter redirect_uri harus selalu sesuai dengan input yang diberikan.
        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $parsedUrl = parse_url($result->url);
            parse_str($parsedUrl['query'], $params);

            expect($params)->toHaveKey('redirect_uri')
                ->and($params['redirect_uri'])->toBe($redirectUri);
        }
    });

    test('URL selalu mengandung state parameter (non-empty, 64 karakter hex)', function () {
        // Untuk semua authorization URL yang dihasilkan,
        // parameter state harus non-empty dan berformat 64 karakter hex.
        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $parsedUrl = parse_url($result->url);
            parse_str($parsedUrl['query'], $params);

            expect($params)->toHaveKey('state')
                ->and($params['state'])->not->toBeEmpty()
                ->and($params['state'])->toHaveLength(64)
                ->and($params['state'])->toMatch('/^[a-f0-9]{64}$/');
        }
    });

    test('URL selalu mengandung code_challenge yang valid (base64url)', function () {
        // Untuk semua authorization URL yang dihasilkan,
        // parameter code_challenge harus valid base64url encoded.
        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $parsedUrl = parse_url($result->url);
            parse_str($parsedUrl['query'], $params);

            expect($params)->toHaveKey('code_challenge')
                ->and($params['code_challenge'])->not->toBeEmpty()
                ->and($params['code_challenge'])->toMatch('/^[A-Za-z0-9\-_]+$/');
        }
    });

    test('URL selalu mengandung code_challenge_method=S256', function () {
        // Untuk semua authorization URL yang dihasilkan,
        // parameter code_challenge_method harus selalu 'S256'.
        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $parsedUrl = parse_url($result->url);
            parse_str($parsedUrl['query'], $params);

            expect($params)->toHaveKey('code_challenge_method')
                ->and($params['code_challenge_method'])->toBe('S256');
        }
    });

    test('URL selalu mengarah ke endpoint otorisasi realm Keycloak yang benar', function () {
        // Untuk semua authorization URL yang dihasilkan,
        // URL harus mengarah ke endpoint otorisasi Keycloak realm yang dikonfigurasi.
        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $parsedUrl = parse_url($result->url);

            expect($parsedUrl['scheme'])->toBe('http')
                ->and($parsedUrl['host'])->toBe('keycloak.test')
                ->and($parsedUrl['port'])->toBe(8080)
                ->and($parsedUrl['path'])->toBe('/realms/kepegawaian/protocol/openid-connect/auth');
        }
    });

    test('setiap URL yang dihasilkan memiliki state unik (proteksi CSRF)', function () {
        // Untuk semua kumpulan authorization URL yang dihasilkan,
        // setiap state harus unik untuk mencegah CSRF replay attacks.
        $states = [];

        for ($i = 0; $i < 100; $i++) {
            $redirectUri = generateRandomRedirectUri();
            $result = $this->client->buildAuthorizationUrl($redirectUri);

            $states[] = $result->state;
        }

        // Semua state harus unik
        $uniqueStates = array_unique($states);
        expect(count($uniqueStates))->toBe(100);
    });
});
