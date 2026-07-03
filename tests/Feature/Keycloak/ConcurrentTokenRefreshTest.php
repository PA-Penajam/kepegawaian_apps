<?php

/**
 * Tests untuk Concurrent Token Refresh Protection.
 *
 * Memverifikasi bahwa hanya satu refresh request yang dikirim ke Keycloak
 * ketika multiple concurrent requests mendeteksi token dalam refresh threshold.
 *
 * **Validates: Requirement 4.6**
 */

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\DataTransferObjects\TokenResult;
use App\Keycloak\Http\Middleware\KeycloakTokenRefresh;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Membuat TokenResult acak.
 */
function createTokenResultForConcurrency(): TokenResult
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
 * Membuat request ke non-excluded path.
 */
function createConcurrencyRequest(): Request
{
    return Request::create('http://localhost/dashboard/index', 'GET');
}

/**
 * Closure $next standar yang mengembalikan response 200.
 */
function concurrencyNextMiddleware(): Closure
{
    return fn (Request $request) => new Response('OK', 200);
}

describe('Concurrent Token Refresh Protection (Requirement 4.6)', function () {

    beforeEach(function () {
        Carbon::setTestNow('2024-06-15 10:00:00');
        Cache::flush();
    });

    afterEach(function () {
        Carbon::setTestNow();
        Mockery::close();
    });

    test('hanya satu refresh request dikirim ke Keycloak ketika lock diperoleh', function () {
        // Simulasikan token yang perlu di-refresh
        session()->flush();
        session()->put('keycloak.tokens', [
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);

        $refreshCallCount = 0;
        $newTokens = createTokenResultForConcurrency();

        $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $tokenStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        $tokenStorage->shouldReceive('getRefreshToken')
            ->andReturn(Str::random(64));
        $tokenStorage->shouldReceive('rotateTokens')
            ->once()
            ->with(Mockery::type(TokenResult::class));

        $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
        $keycloakClient->shouldReceive('refreshToken')
            ->once()
            ->andReturnUsing(function () use (&$refreshCallCount, $newTokens) {
                $refreshCallCount++;

                return $newTokens;
            });

        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('call')
            ->once()
            ->andReturnUsing(fn (callable $op) => $op());

        $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
        $request = createConcurrencyRequest();

        $response = $middleware->handle($request, concurrencyNextMiddleware());

        expect($response->getStatusCode())->toBe(200);
        expect($refreshCallCount)->toBe(1);
    });

    test('request kedua skip refresh jika token sudah di-refresh oleh request pertama (re-check setelah lock)', function () {
        // Simulasikan skenario dimana setelah mendapat lock, token sudah di-refresh.
        // Re-check di dalam lock melihat expiry sudah jauh, skip refresh.
        session()->flush();
        session()->put('keycloak.tokens', [
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);

        // Simulasi: pertama expiry dekat, setelah lock didapat expiry sudah jauh
        $callIndex = 0;
        $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $tokenStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturnUsing(function () use (&$callIndex) {
                $callIndex++;
                if ($callIndex === 1) {
                    // Panggilan pertama (sebelum lock): token dekat expiry
                    return now()->addSeconds(30);
                }

                // Panggilan kedua (di dalam lock re-check): token sudah di-refresh
                return now()->addSeconds(300);
            });

        // refreshToken dan rotateTokens TIDAK BOLEH dipanggil
        $tokenStorage->shouldNotReceive('getRefreshToken');
        $tokenStorage->shouldNotReceive('rotateTokens');

        $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
        $keycloakClient->shouldNotReceive('refreshToken');

        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldNotReceive('call');

        $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
        $request = createConcurrencyRequest();

        $response = $middleware->handle($request, concurrencyNextMiddleware());

        // Request tetap dilanjutkan tanpa melakukan refresh
        expect($response->getStatusCode())->toBe(200);
    });

    test('lock key unik per session sehingga user berbeda tidak saling blocking', function () {
        // Verifikasi bahwa lock key berbasis session ID
        session()->flush();
        session()->put('keycloak.tokens', [
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);

        $newTokens = createTokenResultForConcurrency();

        $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $tokenStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        $tokenStorage->shouldReceive('getRefreshToken')
            ->andReturn(Str::random(64));
        $tokenStorage->shouldReceive('rotateTokens')
            ->with(Mockery::type(TokenResult::class));

        $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
        $keycloakClient->shouldReceive('refreshToken')
            ->andReturn($newTokens);

        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('call')
            ->andReturnUsing(fn (callable $op) => $op());

        $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);

        // Lock dari session lain (berbeda key) tidak mengganggu
        $otherSessionLockKey = 'keycloak_token_refresh:other-session-id-12345';
        $otherLock = Cache::lock($otherSessionLockKey, 10);
        $otherLock->get();

        // Request tetap berhasil karena lock key per-session
        $response = $middleware->handle(createConcurrencyRequest(), concurrencyNextMiddleware());

        expect($response->getStatusCode())->toBe(200);

        $otherLock->release();
    });

    test('lock dilepas setelah refresh selesai sehingga request berikutnya tidak terblokir', function () {
        session()->flush();
        session()->put('keycloak.tokens', [
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);

        $newTokens = createTokenResultForConcurrency();

        $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $tokenStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        $tokenStorage->shouldReceive('getRefreshToken')
            ->andReturn(Str::random(64));
        $tokenStorage->shouldReceive('rotateTokens')
            ->with(Mockery::type(TokenResult::class));

        $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
        $keycloakClient->shouldReceive('refreshToken')
            ->andReturn($newTokens);

        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('call')
            ->andReturnUsing(fn (callable $op) => $op());

        $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);

        // Request pertama — acquire dan release lock
        $middleware->handle(createConcurrencyRequest(), concurrencyNextMiddleware());

        // Setelah request selesai, lock harus sudah dilepas
        $sessionId = session()->getId();
        $lockKey = "keycloak_token_refresh:{$sessionId}";
        $checkLock = Cache::lock($lockKey, 10);

        // Lock harus bisa di-acquire (artinya sudah dilepas oleh middleware)
        expect($checkLock->get())->toBeTrue();

        $checkLock->release();
    });

    test('jika LockTimeoutException terjadi dan token masih valid, request dilanjutkan', function () {
        // Simulasikan LockTimeoutException dengan meng-mock Cache facade
        session()->flush();
        session()->put('keycloak.tokens', [
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);

        $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $tokenStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        // Token masih valid
        $tokenStorage->shouldReceive('isTokenValid')
            ->andReturn(true);

        $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);

        // Mock Cache facade untuk memaksa LockTimeoutException
        Cache::shouldReceive('lock')
            ->andReturnUsing(function () {
                $mockLock = Mockery::mock(Lock::class);
                $mockLock->shouldReceive('block')
                    ->andThrow(new LockTimeoutException);

                return $mockLock;
            });

        $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
        $response = $middleware->handle(createConcurrencyRequest(), concurrencyNextMiddleware());

        // Token masih valid → request dilanjutkan
        expect($response->getStatusCode())->toBe(200);
    });

    test('jika LockTimeoutException terjadi dan token sudah expired, user di-logout', function () {
        session()->flush();
        session()->put('keycloak.tokens', [
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);

        $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $tokenStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        // Token sudah expired
        $tokenStorage->shouldReceive('isTokenValid')
            ->andReturn(false);
        $tokenStorage->shouldReceive('clearTokens');

        $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);

        // Mock Cache facade untuk memaksa LockTimeoutException
        Cache::shouldReceive('lock')
            ->andReturnUsing(function () {
                $mockLock = Mockery::mock(Lock::class);
                $mockLock->shouldReceive('block')
                    ->andThrow(new LockTimeoutException);

                return $mockLock;
            });

        $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
        $response = $middleware->handle(createConcurrencyRequest(), concurrencyNextMiddleware());

        // User di-redirect ke login karena token expired
        expect($response->getStatusCode())->toBe(302);
    });

    test('refresh failure di dalam lock menyebabkan logout', function () {
        session()->flush();
        session()->put('keycloak.tokens', [
            'access_token' => Str::random(64),
            'refresh_token' => Str::random(64),
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);

        $tokenStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $tokenStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        $tokenStorage->shouldReceive('getRefreshToken')
            ->andReturn(Str::random(64));
        $tokenStorage->shouldReceive('isTokenValid')
            ->andReturn(false);
        $tokenStorage->shouldReceive('clearTokens');

        $keycloakClient = Mockery::mock(KeycloakClientInterface::class);
        $keycloakClient->shouldReceive('refreshToken')
            ->andThrow(new RuntimeException('Token revoked'));

        $circuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $circuitBreaker->shouldReceive('call')
            ->andReturnUsing(fn (callable $op) => $op());

        $middleware = new KeycloakTokenRefresh($tokenStorage, $keycloakClient, $circuitBreaker);
        $response = $middleware->handle(createConcurrencyRequest(), concurrencyNextMiddleware());

        // Refresh gagal + token tidak valid → redirect ke login
        expect($response->getStatusCode())->toBe(302);
    });
});
