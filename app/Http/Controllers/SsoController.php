<?php

namespace App\Http\Controllers;

use App\Models\IamApplication;
use App\Models\IamSsoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SsoController extends Controller
{
    public function login(Request $request): Response
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
            session([
                'sso_app' => $validated['app'],
                'sso_redirect' => $validated['redirect'],
                'sso_state' => $state,
            ]);

            return redirect()->route('login');
        }

        return $this->generateCodeAndRedirect($request, $request->user()->id, $app, $validated['redirect'], $state);
    }

    /** Dipanggil setelah login berhasil jika ada SSO session */
    public function callback(Request $request): Response
    {
        $appSlug = session()->pull('sso_app');
        $redirect = session()->pull('sso_redirect');
        $state = session()->pull('sso_state');

        if (! $appSlug || ! $redirect) {
            return redirect()->route('dashboard');
        }

        // Kepegawaian adalah SSO provider-nya sendiri — skip code generation
        // karena session Fortify sudah terbuat. Redirect langsung ke URL tujuan.
        $selfSlug = config('iam.app_slug', 'kepegawaian');
        if ($appSlug === $selfSlug) {
            return redirect($redirect);
        }

        $app = IamApplication::where('slug', $appSlug)->where('is_active', true)->first();

        if (! $app) {
            return redirect()->route('dashboard');
        }

        return $this->generateCodeAndRedirect($request, $request->user()->id, $app, $redirect, $state);
    }

    private function generateCodeAndRedirect(Request $request, string $userId, IamApplication $app, string $redirectUrl, ?string $state = null): Response
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
            $destination .= '&state=' . urlencode($state);
        }

        // Inertia request → Inertia::location() (409 + X-Inertia-Location header)
        // Browser navigation biasa → redirect()->away() (302 redirect)
        if ($request->header('X-Inertia')) {
            return Inertia::location($destination);
        }

        return redirect()->away($destination);
    }
}
