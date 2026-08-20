<?php

namespace App\Http\Controllers\Auth;

use App\Enums\StatusPegawai;
use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Services\Sso\Contracts\SsoClientInterface;
use App\Services\Sso\Contracts\SsoTokenStorageInterface;
use App\Services\Sso\Exceptions\SsoException;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk autentikasi SSO PA Penajam via OAuth2 Authorization Code Grant + PKCE.
 *
 * Menangani alur login (redirect ke server SSO), callback (penukaran code,
 * pengambilan profil identitas, verifikasi NIP Pegawai), dan logout.
 */
class SsoAuthController extends Controller
{
    public function __construct(
        private SsoClientInterface $ssoClient,
        private SsoTokenStorageInterface $tokenStorage,
    ) {}

    /**
     * Mengalihkan pengguna ke endpoint otorisasi SSO PA Penajam.
     */
    public function login(Request $request): RedirectResponse
    {
        $redirectUri = config('sso.redirect_uri') ?: route('auth.sso.callback');
        $authRequest = $this->ssoClient->buildAuthorizationUrl($redirectUri);

        $stateExpiryMinutes = (int) config('sso.tokens.state_expiry_minutes', 10);

        session()->put('sso.oauth_state', [
            'state' => $authRequest->state,
            'code_verifier' => $authRequest->pkce->verifier,
            'expires_at' => now()->addMinutes($stateExpiryMinutes)->toIso8601String(),
        ]);

        return redirect()->away($authRequest->url);
    }

    /**
     * Menangani callback dari SSO PA Penajam setelah proses otorisasi.
     */
    public function callback(Request $request): RedirectResponse
    {
        // 1. Cek parameter error dari SSO
        if ($request->filled('error')) {
            $errorDescription = $request->query('error_description', 'Autentikasi ditolak atau dibatalkan oleh SSO.');

            Log::warning('SSO callback received error', [
                'error' => $request->query('error'),
                'description' => $errorDescription,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with('error', $errorDescription);
        }

        // 2. Validasi CSRF state parameter (constant-time check)
        $oauthState = session()->pull('sso.oauth_state');

        if (! $oauthState || ! hash_equals($oauthState['state'], (string) $request->query('state', ''))) {
            Log::warning('SSO callback: Invalid or missing CSRF state parameter', [
                'ip' => $request->ip(),
                'has_stored_state' => $oauthState !== null,
            ]);

            session()->forget('sso.oauth_state');
            abort(403, 'State parameter tidak valid.');
        }

        // 3. Validasi waktu kedaluwarsa state
        if (! isset($oauthState['expires_at']) || now()->isAfter(Carbon::parse($oauthState['expires_at']))) {
            Log::warning('SSO callback: OAuth state expired', [
                'ip' => $request->ip(),
                'expired_at' => $oauthState['expires_at'] ?? null,
            ]);

            abort(403, 'Sesi permintaan otorisasi telah kedaluwarsa.');
        }

        $code = (string) $request->query('code', '');
        $redirectUri = config('sso.redirect_uri') ?: route('auth.sso.callback');

        // 4. Tukar authorization code dengan access token & refresh token
        try {
            $tokenResult = $this->ssoClient->exchangeCode($code, $oauthState['code_verifier'], $redirectUri);
        } catch (SsoException $e) {
            Log::error('SSO token exchange failed', [
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Autentikasi gagal: tidak dapat memperoleh token dari SSO. Silakan coba login kembali.'
            );
        }

        // 5. Ambil data profil identitas pengguna dari /api/user
        try {
            $userInfo = $this->ssoClient->getUserInfo($tokenResult->accessToken);
        } catch (SsoException $e) {
            Log::error('SSO getUserInfo failed', [
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Gagal mengambil informasi profil dari SSO. Silakan coba login kembali.'
            );
        }

        $nip = $userInfo->nip ?? $userInfo->sub;

        // 6. Validasi format NIP (18 digit numerik)
        if (! $nip || ! preg_match('/^\d{18}$/', $nip)) {
            Log::warning('SSO callback: format NIP tidak valid', [
                'nip' => $nip,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Akun SSO tidak memiliki format NIP yang valid (18 digit).'
            );
        }

        // 7. Pencocokan identitas dengan model Pegawai di SIMPEG
        $pegawai = Pegawai::query()->where('nip', $nip)->first();

        if (! $pegawai) {
            Log::warning('SSO callback: NIP tidak terdaftar di SIMPEG', [
                'nip' => $nip,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with(
                'error',
                "NIP {$nip} tidak terdaftar dalam sistem kepegawaian (SIMPEG)."
            );
        }

        // 8. Verifikasi status keaktifan pegawai
        if ($pegawai->status_pegawai !== StatusPegawai::Aktif) {
            Log::warning('SSO callback: Pegawai nonaktif mencoba login', [
                'nip' => $nip,
                'status' => $pegawai->status_pegawai->value ?? 'unknown',
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Akun Pegawai tidak aktif. Silakan hubungi administrator kepegawaian.'
            );
        }

        // 9. Simpan token ke storage & profil pengguna di session
        $this->tokenStorage->storeTokens($tokenResult);

        session()->put('sso.user', [
            'sub' => $userInfo->sub,
            'nip' => $userInfo->nip,
            'name' => $userInfo->name,
            'email' => $userInfo->email,
            'department' => $userInfo->department,
            'position' => $userInfo->position,
            'security_level' => $userInfo->securityLevel,
        ]);

        // 10. Regenerasi session ID untuk keamanan dan autentikasi user
        session()->regenerate();
        Auth::login($pegawai);

        return redirect()->intended('/dashboard');
    }

    /**
     * Melakukan proses logout pengguna dari sesi SSO dan aplikasi lokal.
     */
    public function logout(Request $request): RedirectResponse
    {
        try {
            $refreshToken = $this->tokenStorage->getRefreshToken();
            if ($refreshToken) {
                $this->ssoClient->logout($refreshToken);
            }
        } catch (\Throwable $e) {
            Log::warning('SSO logout API failed, continuing local cleanup', [
                'error' => $e->getMessage(),
            ]);
        }

        $this->tokenStorage->clearTokens();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
