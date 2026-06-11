<?php

namespace App\Keycloak\Http\Middleware;

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\Exceptions\KeycloakCircuitOpenException;
use App\Keycloak\Exceptions\KeycloakException;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk proactive token refresh sebelum access token expired.
 *
 * Memastikan access token selalu segar dengan melakukan refresh
 * 60 detik sebelum expiry. Menggunakan Cache::lock() per-session
 * untuk mencegah multiple concurrent refresh requests ke Keycloak.
 * Hanya satu request yang melakukan refresh, request lain menunggu hasilnya.
 */
class KeycloakTokenRefresh
{
    /**
     * Durasi maksimum lock ditahan (detik).
     */
    private const LOCK_TTL_SECONDS = 10;

    /**
     * Waktu maksimum menunggu lock yang sedang ditahan (detik).
     */
    private const LOCK_WAIT_SECONDS = 3;

    public function __construct(
        private readonly KeycloakTokenStorageInterface $tokenStorage,
        private readonly KeycloakClientInterface $keycloakClient,
        private readonly CircuitBreakerInterface $circuitBreaker,
    ) {}

    /**
     * Handle incoming request dan lakukan proactive token refresh jika diperlukan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip untuk paths yang tidak memerlukan autentikasi
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Cek apakah ada session Keycloak yang valid
        if (! session()->has('keycloak.tokens')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi tidak ditemukan. Silakan login kembali.',
                    'error' => 'session_missing',
                ], 401);
            }

            return redirect()->route('keycloak.login');
        }

        $expiry = $this->tokenStorage->getAccessTokenExpiry();
        $threshold = config('keycloak.tokens.refresh_before_seconds', 60);

        // Proactive refresh: jika token akan expired dalam threshold detik
        if ($expiry && now()->diffInSeconds($expiry, false) < $threshold) {
            return $this->refreshWithLock($request, $next);
        }

        return $next($request);
    }

    /**
     * Lakukan token refresh dengan lock per-session untuk mencegah concurrent refresh.
     *
     * Hanya satu request yang mengirim refresh ke Keycloak.
     * Request lain menunggu lock selesai, lalu gunakan token yang sudah di-refresh.
     *
     * Graceful degradation:
     * - Req 4.3: Refresh gagal (expired/revoked) → clear tokens, logout, redirect ke login
     * - Req 4.4: Keycloak unavailable + token masih valid → lanjutkan dengan token existing
     * - Req 4.5: Keycloak unavailable + token expired → logout + redirect ke login
     */
    private function refreshWithLock(Request $request, Closure $next): Response
    {
        $lockKey = $this->buildLockKey();
        $lock = Cache::lock($lockKey, self::LOCK_TTL_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS, function () use ($request) {
                // Re-check setelah mendapat lock: mungkin request lain sudah refresh
                $expiry = $this->tokenStorage->getAccessTokenExpiry();
                $threshold = config('keycloak.tokens.refresh_before_seconds', 60);

                if ($expiry && now()->diffInSeconds($expiry, false) >= $threshold) {
                    // Token sudah di-refresh oleh request lain, tidak perlu refresh lagi
                    return;
                }

                $this->performRefresh($request);
            });
        } catch (LockTimeoutException) {
            // Tidak bisa mendapat lock dalam waktu tunggu.
            // Cek apakah token masih valid (mungkin sudah di-refresh oleh holder lock)
            if ($this->tokenStorage->isTokenValid()) {
                Log::debug('Keycloak token refresh: lock timeout, token masih valid — lanjutkan dalam degraded mode');

                return $next($request);
            }

            Log::warning('Keycloak token refresh: lock timeout + token expired — paksa logout');

            return $this->forceLogout($request);
        } catch (KeycloakCircuitOpenException $e) {
            // Req 4.4 & 4.5: Keycloak unavailable via circuit breaker
            if ($this->tokenStorage->isTokenValid()) {
                // Req 4.4: Token masih valid → degraded mode, lanjutkan request
                Log::info('Keycloak unavailable (circuit open), token masih valid — lanjutkan dalam degraded mode', [
                    'circuit_message' => $e->getMessage(),
                ]);

                return $next($request);
            }

            // Req 4.5: Token expired + Keycloak tidak tersedia → paksa logout
            Log::warning('Keycloak unavailable (circuit open) + token expired — paksa logout', [
                'circuit_message' => $e->getMessage(),
            ]);

            return $this->forceLogout($request);
        } catch (KeycloakException $e) {
            // Req 4.3: Refresh gagal (token revoked/expired di server Keycloak)
            Log::warning('Keycloak token refresh gagal — paksa logout', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return $this->forceLogout($request);
        } catch (\Throwable $e) {
            // Error tidak dikenal saat refresh
            Log::error('Keycloak token refresh: unexpected error', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            // Cek apakah token masih bisa dipakai
            if ($this->tokenStorage->isTokenValid()) {
                return $next($request);
            }

            return $this->forceLogout($request);
        }

        return $next($request);
    }

    /**
     * Eksekusi refresh token ke Keycloak via circuit breaker.
     */
    private function performRefresh(Request $request): void
    {
        $refreshToken = $this->tokenStorage->getRefreshToken();

        if ($refreshToken === null) {
            throw new \RuntimeException('Refresh token tidak tersedia');
        }

        $newTokens = $this->circuitBreaker->call(
            fn () => $this->keycloakClient->refreshToken($refreshToken)
        );

        // Rotate: simpan token baru secara atomic
        $this->tokenStorage->rotateTokens($newTokens);
    }

    /**
     * Buat lock key unik per-session untuk isolasi antar user.
     */
    private function buildLockKey(): string
    {
        return 'keycloak_token_refresh:'.session()->getId();
    }

    /**
     * Tentukan apakah request harus di-skip dari token refresh.
     *
     * Skip paths: keycloak/*, emergency/*, _health
     */
    private function shouldSkip(Request $request): bool
    {
        return $request->is('keycloak/*', 'emergency/*', '_health');
    }

    /**
     * Paksa logout user: clear tokens, invalidate session, redirect ke login.
     *
     * Req 4.3: Token expired + refresh gagal → clear tokens, invalidate session, redirect ke login.
     * Req 4.5: Keycloak unavailable + token expired → logout + redirect ke login.
     */
    private function forceLogout(Request $request): Response
    {
        $this->tokenStorage->clearTokens();
        session()->invalidate();
        session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sesi telah berakhir. Silakan login kembali.',
                'error' => 'session_expired',
            ], 401);
        }

        return redirect()->route('keycloak.login')
            ->with('error', 'Sesi telah berakhir. Silakan login kembali.');
    }
}
