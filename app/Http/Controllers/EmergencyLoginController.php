<?php

namespace App\Http\Controllers;

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Models\KeycloakEmergencyLoginLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller untuk emergency bypass login.
 *
 * Menyediakan akses admin darurat saat Keycloak tidak tersedia
 * (circuit breaker OPEN). Mengautentikasi menggunakan kredensial
 * yang dikonfigurasi di environment dengan rate limiting.
 *
 * Requirement 10.1-10.8: Emergency bypass authentication
 */
class EmergencyLoginController extends Controller
{
    public function __construct(
        private readonly CircuitBreakerInterface $circuitBreaker,
    ) {}

    /**
     * Tampilkan form login emergency.
     *
     * Hanya tersedia saat circuit breaker OPEN dan
     * emergency access diaktifkan di konfigurasi.
     * (Req 10.1, 10.2, 10.3)
     */
    public function showLoginForm(Request $request): View|RedirectResponse|Response
    {
        // Req 10.2: Redirect ke login normal jika circuit NOT open
        if (! $this->circuitBreaker->isOpen()) {
            return redirect()->route('keycloak.login')
                ->with('info', 'Keycloak tersedia, silakan gunakan login normal.');
        }

        // Req 10.3: Return 503 jika emergency disabled
        if (! config('keycloak.emergency.enabled')) {
            abort(503, 'Emergency access tidak diaktifkan.');
        }

        return view('auth.emergency-login');
    }

    /**
     * Proses kredensial login emergency.
     *
     * Validasi kredensial dengan constant-time comparison untuk username
     * dan Hash::check untuk password (Req 10.5).
     * Buat session terbatas dengan 30-menit timeout (Req 10.4).
     */
    public function login(Request $request): RedirectResponse|Response
    {
        // Req 10.2: Redirect ke login normal jika circuit NOT open
        if (! $this->circuitBreaker->isOpen()) {
            return redirect()->route('keycloak.login')
                ->with('info', 'Keycloak tersedia, silakan gunakan login normal.');
        }

        // Req 10.3: Return 503 jika emergency disabled
        if (! config('keycloak.emergency.enabled')) {
            abort(503, 'Emergency access tidak diaktifkan.');
        }

        // Req 10.7: Rate limiting max 5 attempts per IP dalam 15 menit
        $rateLimitKey = 'emergency_login:'.$request->ip();
        $maxAttempts = config('keycloak.emergency.rate_limit_max_attempts', 5);
        $decayMinutes = config('keycloak.emergency.rate_limit_decay_minutes', 15);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            // Log attempt yang di-rate-limit
            $this->logAttempt($request, 'rate_limited');

            return redirect()->route('emergency.login.form')
                ->withErrors([
                    'credentials' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
                ]);
        }

        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $inputUsername = $request->input('username');
        $inputPassword = $request->input('password');

        $configUsername = config('keycloak.emergency.username', '');
        $configPasswordHash = config('keycloak.emergency.password', '');

        // Req 10.5: Constant-time comparison untuk username, Hash::check untuk password
        $usernameValid = hash_equals((string) $configUsername, (string) $inputUsername);

        try {
            $passwordValid = Hash::check((string) $inputPassword, (string) $configPasswordHash);
        } catch (\RuntimeException) {
            // Password hash di config tidak valid (bukan bcrypt/argon) → credential gagal
            $passwordValid = false;
        }

        if (! $usernameValid || ! $passwordValid) {
            // Hit rate limiter
            RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

            // Req 10.6: Log gagal ke keycloak_emergency_login_log
            $this->logAttempt($request, 'failure');

            return redirect()->route('emergency.login.form')
                ->withErrors(['credentials' => 'Kredensial emergency tidak valid.'])
                ->withInput(['username' => $inputUsername]);
        }

        // Req 10.4: Buat emergency session dengan timeout 30 menit
        $timeoutMinutes = config('keycloak.emergency.session_timeout_minutes', 30);

        session()->put('keycloak.emergency', [
            'authenticated' => true,
            'roles' => config('keycloak.emergency.allowed_roles', ['admin']),
            'expires_at' => now()->addMinutes($timeoutMinutes),
            'logged_in_at' => now(),
        ]);

        // Reset rate limiter setelah berhasil login
        RateLimiter::clear($rateLimitKey);

        // Req 10.6: Log sukses ke keycloak_emergency_login_log
        $this->logAttempt($request, 'success');

        // Regenerate session ID untuk mencegah session fixation
        session()->regenerate();

        return redirect()->route('dashboard');
    }

    /**
     * Log percobaan emergency login ke database.
     *
     * Menyimpan username dalam bentuk hashed untuk keamanan (Req 10.6).
     */
    private function logAttempt(Request $request, string $outcome): void
    {
        KeycloakEmergencyLoginLog::create([
            'username' => hash('sha256', (string) $request->input('username', '')),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'logged_in_at' => now(),
            'outcome' => $outcome,
        ]);
    }
}
