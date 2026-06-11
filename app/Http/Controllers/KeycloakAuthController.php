<?php

namespace App\Http\Controllers;

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\Exceptions\KeycloakException;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk autentikasi OIDC via Keycloak.
 *
 * Menangani login (redirect ke Keycloak), callback (token exchange
 * dan verifikasi NIP), dan logout (end-session + cleanup lokal).
 */
class KeycloakAuthController extends Controller
{
    public function __construct(
        private KeycloakClientInterface $keycloakClient,
        private KeycloakTokenStorageInterface $tokenStorage,
    ) {}

    /**
     * Redirect pengguna ke Keycloak authorization endpoint.
     *
     * Generate PKCE pair dan state CSRF, simpan di session
     * dengan expiry 10 menit, lalu redirect ke Keycloak login page.
     */
    public function login(Request $request): RedirectResponse
    {
        // Bangun authorization URL (generate PKCE + state secara internal)
        $authRequest = $this->keycloakClient->buildAuthorizationUrl(route('keycloak.callback'));

        // Simpan state + PKCE di session dengan expiry 10 menit
        session()->put('keycloak.oauth_state', [
            'state' => $authRequest->state,
            'code_verifier' => $authRequest->pkce->verifier,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]);

        // Redirect ke Keycloak authorization endpoint
        return redirect()->away($authRequest->url);
    }

    /**
     * Handle callback dari Keycloak setelah autentikasi berhasil.
     *
     * Validasi state CSRF, exchange authorization code untuk token,
     * validasi ID token, verifikasi NIP di Pegawai table, simpan
     * session data, dan login user.
     */
    public function callback(Request $request): RedirectResponse
    {
        // Periksa error dari Keycloak (misal: access_denied)
        if ($request->query('error')) {
            $errorDescription = $request->query('error_description', 'Autentikasi ditolak oleh Keycloak');

            return redirect()->route('keycloak.login')
                ->with('error', $errorDescription);
        }

        // Ambil oauth_state dari session (sekaligus hapus untuk cegah replay)
        $oauthState = session()->pull('keycloak.oauth_state');

        // Req 1.5: Validasi state menggunakan constant-time comparison
        // Jika tidak cocok atau expired → abort 403 + redirect ke login
        if (! $oauthState || ! hash_equals($oauthState['state'], $request->query('state', ''))) {
            Log::warning('Keycloak callback: invalid state parameter', [
                'ip' => $request->ip(),
                'has_state' => $oauthState !== null,
            ]);

            // Clear sisa OAuth state dari session untuk mencegah replay
            session()->forget('keycloak.oauth_state');

            abort(403, 'Invalid state parameter');
        }

        // Req 1.5: Periksa apakah state sudah expired (max 10 menit)
        if (now()->isAfter(Carbon::parse($oauthState['expires_at']))) {
            Log::warning('Keycloak callback: OAuth state expired', [
                'ip' => $request->ip(),
                'expired_at' => $oauthState['expires_at'],
            ]);

            abort(403, 'OAuth state expired');
        }

        // Req 1.8: Exchange authorization code untuk token set
        // Gagal → reject + redirect ke login
        try {
            $tokenResult = $this->keycloakClient->exchangeCode(
                $request->query('code', ''),
                $oauthState['code_verifier'],
                route('keycloak.callback'),
            );
        } catch (KeycloakException $e) {
            Log::error('Keycloak token exchange gagal', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('keycloak.login')
                ->with('error', 'Autentikasi gagal: tidak dapat memperoleh token. Silakan coba login kembali.');
        }

        // Req 1.9: Validasi ID token (signature, issuer, expiry)
        // Gagal → discard token set + 401 + redirect ke login
        try {
            $claims = $this->keycloakClient->validateIdToken($tokenResult->idToken);
        } catch (KeycloakException $e) {
            Log::error('Keycloak ID token validasi gagal', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'ip' => $request->ip(),
            ]);

            // Discard token set — jangan simpan token yang tidak valid
            return redirect()->route('keycloak.login')
                ->with('error', 'Token identitas tidak valid. Silakan coba login kembali.');
        }

        // Validasi format NIP (harus 18 digit angka)
        if (! preg_match('/^\d{18}$/', $claims->nip)) {
            Log::warning('Keycloak callback: NIP format tidak valid', [
                'nip' => $claims->nip,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('keycloak.login')
                ->with('error', 'Token berisi informasi identitas yang tidak valid.');
        }

        // Req 2.4: Verifikasi NIP terdaftar di tabel Pegawai
        $pegawai = Pegawai::where('nip', $claims->nip)->first();

        if (! $pegawai) {
            Log::warning('Keycloak callback: NIP tidak terdaftar', [
                'nip' => $claims->nip,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('keycloak.login')
                ->with('error', 'NIP tidak terdaftar dalam sistem kepegawaian');
        }

        // Verifikasi status Pegawai aktif
        if ($pegawai->status_pegawai !== StatusPegawai::Aktif) {
            Log::warning('Keycloak callback: Pegawai tidak aktif', [
                'nip' => $claims->nip,
                'status' => $pegawai->status_pegawai->value ?? 'unknown',
                'ip' => $request->ip(),
            ]);

            return redirect()->route('keycloak.login')
                ->with('error', 'Akun Pegawai tidak aktif. Hubungi administrator untuk informasi lebih lanjut.');
        }

        // Simpan token (encrypted) di session
        $this->tokenStorage->storeTokens($tokenResult);

        // Simpan user claims di session
        session()->put('keycloak.user', [
            'sub' => $claims->sub,
            'nip' => $claims->nip,
            'email' => $claims->email,
            'name' => $claims->name,
        ]);

        // Simpan permissions dan roles di session
        session()->put('keycloak.permissions', $claims->permissions);
        session()->put('keycloak.roles', $claims->roles);

        // Regenerate session ID untuk mencegah session fixation
        session()->regenerate();

        // Login user ke Laravel auth system
        Auth::login($pegawai);

        return redirect()->intended('/dashboard');
    }

    /**
     * Logout pengguna dari Keycloak dan aplikasi lokal.
     *
     * Invoke end-session endpoint di Keycloak, clear token,
     * invalidate session, dan regenerate CSRF token.
     */
    public function logout(Request $request): RedirectResponse
    {
        // Coba invoke end-session endpoint di Keycloak
        try {
            $refreshToken = $this->tokenStorage->getRefreshToken();

            if ($refreshToken) {
                $this->keycloakClient->logout($refreshToken);
            }
        } catch (\Throwable $e) {
            // Req 3.5: lanjutkan cleanup lokal meski Keycloak tidak tersedia
            Log::warning('Keycloak end-session gagal, lanjutkan cleanup lokal', [
                'error' => $e->getMessage(),
            ]);
        }

        // Hapus semua token dari session
        $this->tokenStorage->clearTokens();

        // Logout dari Laravel auth, invalidate session, regenerate CSRF
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
