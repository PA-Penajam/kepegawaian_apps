<?php

/**
 * Property-Based Tests untuk Keycloak Middleware Stack.
 *
 * Menguji properti universal dari middleware:
 * - Property 9: Proactive Token Refresh Trigger (Req 4.1)
 * - Property 22: Permission Enforcement (Req 13.3, 13.4)
 * - Property 23: Middleware Path Exclusion (Req 13.2)
 */

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\DataTransferObjects\TokenResult;
use App\Keycloak\Http\Middleware\KeycloakTokenRefresh;
use App\Keycloak\Http\Middleware\VerifyKeycloakPermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Membuat mock Request dengan path tertentu.
 */
function createRequestWithPath(string $path): Request
{
    $request = Request::create("http://localhost/{$path}", 'GET');

    return $request;
}

/**
 * Membuat TokenResult acak untuk testing.
 */
function createRandomTokenResultForMiddleware(): TokenResult
{
    return new TokenResult(
        accessToken: Str::random(64),
        refreshToken: Str::random(64),
        idToken: Str::random(64),
        expiresIn: random_int(300, 3600),
        refreshExpiresIn: random_int(1800, 86400),
    );
}

/**
 * Closure $next standar yang mengembalikan response 200.
 */
function nextMiddleware(): Closure
{
    return fn (Request $request) => new Response('OK', 200);
}

/**
 * Menghasilkan path acak yang BUKAN excluded path.
 */
function generateNonExcludedPath(): string
{
    $prefixes = ['admin', 'dashboard', 'api', 'pegawai', 'cuti', 'laporan', 'users', 'settings'];
    $segments = ['index', 'create', 'edit', 'show', 'list', 'detail', 'update'];

    $prefix = $prefixes[array_rand($prefixes)];
    $segment = $segments[array_rand($segments)];

    return "{$prefix}/{$segment}/".random_int(1, 9999);
}

/**
 * Menghasilkan path excluded secara acak.
 */
function generateExcludedPath(): string
{
    $excludedPaths = [
        'keycloak/login',
        'keycloak/callback',
        'keycloak/logout',
        'keycloak/'.Str::random(8),
        'emergency/login',
        'emergency/panel',
        'emergency/'.Str::random(8),
        '_health',
    ];

    return $excludedPaths[array_rand($excludedPaths)];
}

/**
 * Menghasilkan permission string acak.
 */
function generateRandomPermission(): string
{
    $resources = ['cuti', 'pegawai', 'laporan', 'jabatan', 'golongan', 'unit', 'gaji'];
    $actions = ['view', 'create', 'update', 'delete', 'approve', 'export'];

    $resource = $resources[array_rand($resources)];
    $action = $actions[array_rand($actions)];

    return "{$resource}.{$action}";
}

// ============================================================
// Property 9: Proactive Token Refresh Trigger
// **Validates: Requirement 4.1**
// ============================================================

describe('Property 9: Proactive Token Refresh Trigger', function () {
    test('token dalam 60 detik sebelum expiry SELALU di-refresh', function () {
        // Untuk semua access token yang berada dalam 60 detik sebelum expiry,
        // middleware HARUS selalu mencoba melakukan refresh.
        for ($i = 0; $i < 50; $i++) {
            Carbon::setTestNow('2024-06-15 10:00:00');
            session()->flush();

            // Atur session agar memiliki token Keycloak
            session()->put('keycloak.tokens', [
                'access_token' => Str::random(64),
                'refresh_token' => Str::random(64),
                'expires_at' => now()->addSeconds(random_int(1, 59))->toIso8601String(),
            ]);

            $refreshWasCalled = false;
            $newTokens = createRandomTokenResultForMiddleware();

            $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
            $tokenStorage->shouldReceive('getAccessTokenExpiry')
                ->andReturn(now()->addSeconds(random_int(1, 59)));
            $tokenStorage->shouldReceive('getRefreshToken')
                ->andReturn(Str::random(64));
            $tokenStorage->shouldReceive('rotateTokens')
                ->once()
                ->with(Mockery::type(TokenResult::class))
                ->andReturnUsing(function () use (&$refreshWasCalled) {
                    $refreshWasCalled = true;
                });

            $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
            $keycloakClient->shouldReceive('refreshToken')
                ->once()
                ->andReturn($newTokens);

            $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
            $circuitBreaker->shouldReceive('call')
                ->once()
                ->andReturnUsing(fn (callable $op) => $op());

            $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
            $request = createRequestWithPath(generateNonExcludedPath());

            $middleware->handle($request, nextMiddleware());

            expect($refreshWasCalled)->toBeTrue();

            Mockery::close();
            Carbon::setTestNow();
        }
    });

    test('token TIDAK dalam 60 detik sebelum expiry TIDAK PERNAH di-refresh', function () {
        // Untuk semua access token yang masih jauh dari expiry (>= 60 detik),
        // middleware TIDAK BOLEH melakukan refresh.
        for ($i = 0; $i < 50; $i++) {
            Carbon::setTestNow('2024-06-15 10:00:00');
            session()->flush();

            session()->put('keycloak.tokens', [
                'access_token' => Str::random(64),
                'refresh_token' => Str::random(64),
                'expires_at' => now()->addSeconds(random_int(61, 3600))->toIso8601String(),
            ]);

            $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
            $tokenStorage->shouldReceive('getAccessTokenExpiry')
                ->andReturn(now()->addSeconds(random_int(61, 3600)));
            $tokenStorage->shouldNotReceive('rotateTokens');

            $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
            $keycloakClient->shouldNotReceive('refreshToken');

            $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
            $circuitBreaker->shouldNotReceive('call');

            $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
            $request = createRequestWithPath(generateNonExcludedPath());

            $response = $middleware->handle($request, nextMiddleware());

            // Request harus dilanjutkan tanpa refresh
            expect($response->getStatusCode())->toBe(200);

            Mockery::close();
            Carbon::setTestNow();
        }
    });

    test('setelah refresh berhasil, token baru SELALU disimpan via rotateTokens', function () {
        // Untuk semua refresh yang berhasil, middleware HARUS menyimpan
        // token baru melalui rotateTokens().
        for ($i = 0; $i < 50; $i++) {
            Carbon::setTestNow('2024-06-15 10:00:00');
            session()->flush();

            session()->put('keycloak.tokens', [
                'access_token' => Str::random(64),
                'refresh_token' => Str::random(64),
                'expires_at' => now()->addSeconds(random_int(1, 59))->toIso8601String(),
            ]);

            $newTokens = createRandomTokenResultForMiddleware();
            $storedTokens = null;

            $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
            $tokenStorage->shouldReceive('getAccessTokenExpiry')
                ->andReturn(now()->addSeconds(random_int(1, 59)));
            $tokenStorage->shouldReceive('getRefreshToken')
                ->andReturn(Str::random(64));
            $tokenStorage->shouldReceive('rotateTokens')
                ->once()
                ->andReturnUsing(function (TokenResult $tokens) use (&$storedTokens) {
                    $storedTokens = $tokens;
                });

            $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
            $keycloakClient->shouldReceive('refreshToken')
                ->once()
                ->andReturn($newTokens);

            $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
            $circuitBreaker->shouldReceive('call')
                ->once()
                ->andReturnUsing(fn (callable $op) => $op());

            $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
            $request = createRequestWithPath(generateNonExcludedPath());

            $middleware->handle($request, nextMiddleware());

            // Verifikasi token yang disimpan adalah token baru yang dikembalikan dari Keycloak
            expect($storedTokens)->not->toBeNull();
            expect($storedTokens->accessToken)->toBe($newTokens->accessToken);
            expect($storedTokens->refreshToken)->toBe($newTokens->refreshToken);

            Mockery::close();
            Carbon::setTestNow();
        }
    });
});

// ============================================================
// Property 22: Permission Enforcement
// **Validates: Requirements 13.3, 13.4**
// ============================================================

describe('Property 22: Permission Enforcement', function () {
    test('user TANPA permission yang diperlukan SELALU mendapat 403', function () {
        // Untuk semua permission check, jika session permissions TIDAK
        // mengandung permission yang diperlukan, response HARUS 403.
        for ($i = 0; $i < 50; $i++) {
            session()->flush();

            // Buat permission yang diperlukan (tidak ada di session)
            $requiredPermission = generateRandomPermission();

            // Buat session permissions yang TIDAK mengandung required permission
            $sessionPermissions = [];
            for ($j = 0; $j < random_int(1, 10); $j++) {
                $perm = generateRandomPermission();
                // Pastikan permission yang digenerate berbeda dari required
                while ($perm === $requiredPermission) {
                    $perm = generateRandomPermission();
                }
                $sessionPermissions[] = $perm;
            }

            session()->put('keycloak.tokens', ['access_token' => Str::random(64)]);
            session()->put('keycloak.permissions', $sessionPermissions);

            $middleware = new VerifyKeycloakPermission;
            $request = createRequestWithPath(generateNonExcludedPath());

            $responseCode = null;
            try {
                $middleware->handle($request, nextMiddleware(), $requiredPermission);
            } catch (HttpException $e) {
                $responseCode = $e->getStatusCode();
            }

            expect($responseCode)->toBe(403);
        }
    });

    test('user DENGAN permission yang diperlukan SELALU diizinkan lewat', function () {
        // Untuk semua permission check, jika session permissions MENGANDUNG
        // permission yang diperlukan, request HARUS dilanjutkan.
        for ($i = 0; $i < 50; $i++) {
            session()->flush();

            $requiredPermission = generateRandomPermission();

            // Buat session permissions yang MENGANDUNG required permission
            $sessionPermissions = [$requiredPermission];
            for ($j = 0; $j < random_int(0, 10); $j++) {
                $sessionPermissions[] = generateRandomPermission();
            }
            // Shuffle agar posisi permission acak
            shuffle($sessionPermissions);

            session()->put('keycloak.tokens', ['access_token' => Str::random(64)]);
            session()->put('keycloak.permissions', $sessionPermissions);

            $middleware = new VerifyKeycloakPermission;
            $request = createRequestWithPath(generateNonExcludedPath());

            $response = $middleware->handle($request, nextMiddleware(), $requiredPermission);

            expect($response->getStatusCode())->toBe(200);
        }
    });

    test('session TANPA permissions array SELALU redirect ke login', function () {
        // Untuk semua session yang tidak memiliki keycloak.permissions,
        // middleware HARUS redirect ke login page.
        for ($i = 0; $i < 50; $i++) {
            session()->flush();

            $requiredPermission = generateRandomPermission();

            // Session tanpa permissions (dan tanpa tokens)
            // Middleware cek keycloak.tokens dan keycloak.permissions

            $middleware = new VerifyKeycloakPermission;
            $request = createRequestWithPath(generateNonExcludedPath());

            $response = $middleware->handle($request, nextMiddleware(), $requiredPermission);

            // Response harus redirect (302) ke login
            expect($response->getStatusCode())->toBe(302);
            expect($response->headers->get('Location'))->toContain('keycloak');
        }
    });
});

// ============================================================
// Property 23: Middleware Path Exclusion
// **Validates: Requirement 13.2**
// ============================================================

describe('Property 23: Middleware Path Exclusion', function () {
    test('request ke excluded paths TIDAK PERNAH menjalani token refresh', function () {
        // Untuk semua request ke paths yang di-exclude (keycloak/*, emergency/*, _health),
        // middleware HARUS skip tanpa melakukan token refresh.
        for ($i = 0; $i < 50; $i++) {
            session()->flush();

            $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
            $tokenStorage->shouldNotReceive('getAccessTokenExpiry');
            $tokenStorage->shouldNotReceive('getRefreshToken');
            $tokenStorage->shouldNotReceive('rotateTokens');

            $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
            $keycloakClient->shouldNotReceive('refreshToken');

            $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
            $circuitBreaker->shouldNotReceive('call');

            $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
            $request = createRequestWithPath(generateExcludedPath());

            $response = $middleware->handle($request, nextMiddleware());

            // Request harus langsung dilanjutkan tanpa proses apapun
            expect($response->getStatusCode())->toBe(200);
            expect($response->getContent())->toBe('OK');

            Mockery::close();
        }
    });

    test('request ke non-excluded paths SELALU diproses oleh middleware', function () {
        // Untuk semua request ke paths yang BUKAN excluded,
        // middleware HARUS memproses request (cek expiry, dll).
        for ($i = 0; $i < 50; $i++) {
            Carbon::setTestNow('2024-06-15 10:00:00');
            session()->flush();

            session()->put('keycloak.tokens', [
                'access_token' => Str::random(64),
                'refresh_token' => Str::random(64),
                'expires_at' => now()->addSeconds(random_int(300, 3600))->toIso8601String(),
            ]);

            $expiryChecked = false;

            $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
            $tokenStorage->shouldReceive('getAccessTokenExpiry')
                ->atLeast()
                ->once()
                ->andReturnUsing(function () use (&$expiryChecked) {
                    $expiryChecked = true;

                    // Token masih jauh dari expiry → tidak perlu refresh
                    return now()->addSeconds(random_int(300, 3600));
                });

            $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
            $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);

            $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
            $request = createRequestWithPath(generateNonExcludedPath());

            $response = $middleware->handle($request, nextMiddleware());

            // Middleware harus memeriksa token expiry (artinya request diproses)
            expect($expiryChecked)->toBeTrue();
            expect($response->getStatusCode())->toBe(200);

            Mockery::close();
            Carbon::setTestNow();
        }
    });
});
