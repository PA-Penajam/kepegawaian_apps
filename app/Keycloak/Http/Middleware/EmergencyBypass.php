<?php

namespace App\Keycloak\Http\Middleware;

use App\Keycloak\Contracts\CircuitBreakerInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk emergency bypass saat Keycloak tidak tersedia.
 *
 * Mengizinkan akses ke route emergency/* ketika circuit breaker
 * dalam state OPEN, memungkinkan admin mengakses panel darurat
 * tanpa memerlukan token Keycloak yang valid.
 *
 * Req 10.8: Invalidasi session setelah 30-menit timeout.
 */
class EmergencyBypass
{
    public function __construct(
        private readonly CircuitBreakerInterface $circuitBreaker,
    ) {}

    /**
     * Handle incoming request dan izinkan emergency routes saat circuit open.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Jika request ke emergency/* dan circuit breaker open → izinkan akses
        if ($request->is('emergency/*') && $this->circuitBreaker->isOpen()) {
            return $next($request);
        }

        // Jika circuit open dan bukan emergency route → cek emergency session
        if ($this->circuitBreaker->isOpen() && $this->hasEmergencySession()) {
            // Req 10.8: Invalidasi session jika sudah timeout
            if ($this->isEmergencySessionExpired()) {
                $this->invalidateEmergencySession($request);

                return redirect()->route('emergency.login.form')
                    ->with('warning', 'Sesi emergency telah berakhir. Silakan login ulang.');
            }

            return $next($request);
        }

        // Normal flow: lanjutkan ke middleware berikutnya
        return $next($request);
    }

    /**
     * Cek apakah user memiliki emergency session (authenticated).
     */
    private function hasEmergencySession(): bool
    {
        $emergencySession = session('keycloak.emergency');

        return $emergencySession !== null
            && ($emergencySession['authenticated'] ?? false) === true;
    }

    /**
     * Cek apakah emergency session sudah melewati timeout.
     *
     * Req 10.8: Session expires setelah 30 menit.
     */
    private function isEmergencySessionExpired(): bool
    {
        $emergencySession = session('keycloak.emergency');
        $expiresAt = $emergencySession['expires_at'] ?? null;

        if ($expiresAt === null) {
            return true;
        }

        return now()->isAfter($expiresAt);
    }

    /**
     * Invalidasi emergency session dan regenerate session.
     *
     * Req 10.8: Hapus data emergency dan redirect ke login page.
     */
    private function invalidateEmergencySession(Request $request): void
    {
        session()->forget('keycloak.emergency');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
