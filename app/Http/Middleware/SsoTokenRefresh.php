<?php

namespace App\Http\Middleware;

use App\Services\Sso\Contracts\SsoClientInterface;
use App\Services\Sso\Contracts\SsoTokenStorageInterface;
use App\Services\Sso\Exceptions\SsoException;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk proactive token refresh sebelum access token SSO expired.
 *
 * Menggunakan Cache::lock() per-session untuk mencegah concurrent refresh ke SSO Server.
 */
class SsoTokenRefresh
{
    private const int LOCK_TTL_SECONDS = 10;

    private const int LOCK_WAIT_SECONDS = 3;

    public function __construct(
        private readonly SsoTokenStorageInterface $tokenStorage,
        private readonly SsoClientInterface $ssoClient,
    ) {}

    /**
     * Handle incoming request dan lakukan proactive token refresh jika diperlukan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        if (! session()->has('sso.tokens')) {
            return $next($request);
        }

        $expiry = $this->tokenStorage->getAccessTokenExpiry();
        $threshold = (int) config('sso.tokens.refresh_before_seconds', 60);

        if ($expiry && now()->diffInSeconds($expiry, false) < $threshold) {
            return $this->refreshWithLock($request, $next);
        }

        return $next($request);
    }

    /**
     * Lakukan token refresh dengan lock per-session.
     */
    private function refreshWithLock(Request $request, Closure $next): Response
    {
        $lockKey = 'sso_token_refresh:'.session()->getId();
        $lock = Cache::lock($lockKey, self::LOCK_TTL_SECONDS);

        try {
            $lock->block(self::LOCK_WAIT_SECONDS, function () {
                $expiry = $this->tokenStorage->getAccessTokenExpiry();
                $threshold = (int) config('sso.tokens.refresh_before_seconds', 60);

                if ($expiry && now()->diffInSeconds($expiry, false) >= $threshold) {
                    return;
                }

                $this->performRefresh();
            });
        } catch (LockTimeoutException) {
            if ($this->tokenStorage->isTokenValid()) {
                return $next($request);
            }

            return $this->forceLogout($request);
        } catch (SsoException $e) {
            Log::warning('SSO token refresh failed', ['error' => $e->getMessage()]);

            if ($this->tokenStorage->isTokenValid()) {
                return $next($request);
            }

            return $this->forceLogout($request);
        } catch (\Throwable $e) {
            Log::error('SSO token refresh unexpected error', ['error' => $e->getMessage()]);

            if ($this->tokenStorage->isTokenValid()) {
                return $next($request);
            }

            return $this->forceLogout($request);
        }

        return $next($request);
    }

    private function performRefresh(): void
    {
        $refreshToken = $this->tokenStorage->getRefreshToken();

        if ($refreshToken === null) {
            return;
        }

        $newTokens = $this->ssoClient->refreshToken($refreshToken);
        $this->tokenStorage->rotateTokens($newTokens);
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is('auth/sso/*', 'sso/*', 'login', 'logout', '_health', 'up');
    }

    private function forceLogout(Request $request): Response
    {
        $this->tokenStorage->clearTokens();
        session()->invalidate();
        session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sesi login telah kedaluwarsa. Silakan login kembali.',
                'error' => 'session_expired',
            ], 401);
        }

        return redirect()->route('login')->with('error', 'Sesi login telah kedaluwarsa. Silakan login kembali.');
    }
}
