<?php

namespace App\Http\Controllers;

use App\Models\IamApplication;
use App\Models\IamSsoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SsoController extends Controller
{
    /**
     * Prefix untuk cache key SSO state.
     * Digunakan bersama state token agar state aman dari session regeneration.
     */
    private const SSO_CACHE_PREFIX = 'sso_state:';

    /**
     * Durasi SSO state dalam cache (menit).
     * Cukup lama untuk menutupi proses login + 2FA.
     */
    private const SSO_STATE_TTL_MINUTES = 15;

    public function login(Request $request): RedirectResponse|JsonResponse|Response
    {
        try {
            $validated = $request->validate([
                'app' => 'required|string',
                'redirect' => 'required|url',
                'state' => 'nullable|string|max:128',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $app = IamApplication::where('slug', $validated['app'])->where('is_active', true)->first();

        if (! $app) {
            abort(404, 'Aplikasi tidak ditemukan');
        }

        // Simpan state dari client app untuk di-pass balik setelah login
        $state = $validated['state'] ?? null;

        if (! $request->user()) {
            $this->storeSsoState($validated['app'], $validated['redirect'], $state);

            return redirect()->route('login');
        }

        return $this->generateCodeAndRedirect($request, $request->user()->id, $app, $validated['redirect'], $state);
    }

    /**
     * Dipanggil setelah login berhasil jika ada SSO session.
     *
     * State diambil dari cache (dengan fallback ke session lama)
     * sehingga tahan terhadap session regeneration oleh Fortify.
     */
    public function callback(Request $request): RedirectResponse|Response
    {
        [$appSlug, $redirect, $state] = $this->pullSsoState();

        if (! $appSlug || ! $redirect) {
            Log::info('SSO callback tanpa state — redirect ke dashboard kepegawaian.', [
                'user_id' => $request->user()?->id,
            ]);

            return redirect()->route('dashboard')
                ->with('warning', 'SSO redirect gagal: session SSO tidak ditemukan. Silakan coba login ulang dari aplikasi tujuan.');
        }

        // Kepegawaian adalah SSO provider-nya sendiri — skip code generation
        // karena session Fortify sudah terbuat. Redirect langsung ke URL tujuan.
        $selfSlug = config('iam.app_slug', 'kepegawaian');
        if ($appSlug === $selfSlug) {
            return redirect($redirect);
        }

        $app = IamApplication::where('slug', $appSlug)->where('is_active', true)->first();

        if (! $app) {
            Log::warning('SSO callback: aplikasi tidak ditemukan atau tidak aktif.', [
                'app_slug' => $appSlug,
                'user_id' => $request->user()?->id,
            ]);

            return redirect()->route('dashboard')
                ->with('warning', "Aplikasi '{$appSlug}' tidak ditemukan atau tidak aktif.");
        }

        return $this->generateCodeAndRedirect($request, $request->user()->id, $app, $redirect, $state);
    }

    /**
     * Simpan SSO state ke cache DAN session.
     *
     * Cache digunakan sebagai primary storage karena tahan terhadap
     * session regeneration oleh Fortify. Session key (`sso_state_key`)
     * menyimpan pointer ke cache entry. Fallback ke session langsung
     * (`sso_app`, `sso_redirect`) sebagai safety net.
     */
    private function storeSsoState(string $appSlug, string $redirectUrl, ?string $oauthState = null): void
    {
        $stateKey = Str::random(40);

        // Primary: simpan di cache (kebal session regeneration)
        Cache::put(
            self::SSO_CACHE_PREFIX.$stateKey,
            ['app' => $appSlug, 'redirect' => $redirectUrl, 'state' => $oauthState],
            now()->addMinutes(self::SSO_STATE_TTL_MINUTES),
        );

        // Simpan key pointer di session
        session(['sso_state_key' => $stateKey]);

        // Fallback: simpan juga langsung di session (jika cache down)
        session(['sso_app' => $appSlug, 'sso_redirect' => $redirectUrl, 'sso_state' => $oauthState]);
    }

    /**
     * Ambil dan hapus SSO state.
     *
     * Coba dari cache terlebih dahulu (via state key di session),
     * fallback ke session langsung jika cache miss.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} [$appSlug, $redirectUrl, $oauthState]
     */
    private function pullSsoState(): array
    {
        // Coba primary: cache-backed state
        $stateKey = session()->pull('sso_state_key');
        if ($stateKey) {
            $state = Cache::pull(self::SSO_CACHE_PREFIX.$stateKey);
            if ($state && ! empty($state['app']) && ! empty($state['redirect'])) {
                // Bersihkan fallback session keys jika masih ada
                session()->forget(['sso_app', 'sso_redirect', 'sso_state']);

                return [$state['app'], $state['redirect'], $state['state'] ?? null];
            }
        }

        // Fallback: session langsung (backward compatibility)
        $appSlug = session()->pull('sso_app');
        $redirect = session()->pull('sso_redirect');
        $oauthState = session()->pull('sso_state');

        return [$appSlug, $redirect, $oauthState];
    }

    private function generateCodeAndRedirect(Request $request, string $userId, IamApplication $app, string $redirectUrl, ?string $state = null): RedirectResponse|Response
    {
        // Validasi host: redirect harus ke domain yang sama persis dengan app terdaftar
        $appHost = parse_url($app->url, PHP_URL_HOST);
        $redirectHost = parse_url($redirectUrl, PHP_URL_HOST);

        if (! $appHost || ! $redirectHost || $appHost !== $redirectHost) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }

        $ttl = (int) config('iam.sso_code_ttl_seconds', 60);
        $code = Str::random(64);

        IamSsoCode::create([
            'code' => $code,
            'user_id' => $userId,
            'app_slug' => $app->slug,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        $separator = str_contains($redirectUrl, '?') ? '&' : '?';
        $destination = $redirectUrl.$separator.'code='.$code;

        // Pass state back to client app for CSRF validation
        if ($state) {
            $destination .= '&state='.urlencode($state);
        }

        // Inertia request → Inertia::location() (409 + X-Inertia-Location header)
        // Browser navigation biasa → redirect()->away() (302 redirect)
        if ($request->header('X-Inertia')) {
            return Inertia::location($destination);
        }

        return redirect()->away($destination);
    }
}
