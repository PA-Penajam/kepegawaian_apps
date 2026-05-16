<?php

namespace App\Http\Controllers;

use App\Models\IamApplication;
use App\Models\IamSsoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SsoController extends Controller
{
    public function login(Request $request): RedirectResponse|JsonResponse|Response
    {
        try {
            $validated = $request->validate([
                'app' => 'required|string',
                'redirect' => 'required|url',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $app = IamApplication::where('slug', $validated['app'])->where('is_active', true)->first();

        if (! $app) {
            abort(404, 'Aplikasi tidak ditemukan');
        }

        if (! $request->user()) {
            session(['sso_app' => $validated['app'], 'sso_redirect' => $validated['redirect']]);

            return redirect()->route('login');
        }

        return $this->generateCodeAndRedirect($request, $request->user()->id, $app, $validated['redirect']);
    }

    /** Dipanggil setelah login berhasil jika ada SSO session */
    public function callback(Request $request): RedirectResponse|Response
    {
        $appSlug = session()->pull('sso_app');
        $redirect = session()->pull('sso_redirect');

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

        return $this->generateCodeAndRedirect($request, $request->user()->id, $app, $redirect);
    }

    private function generateCodeAndRedirect(Request $request, string $userId, IamApplication $app, string $redirectUrl): RedirectResponse|Response
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

        // Inertia request → Inertia::location() (409 + X-Inertia-Location header)
        // Browser navigation biasa → redirect()->away() (302 redirect)
        if ($request->header('X-Inertia')) {
            return Inertia::location($destination);
        }

        return redirect()->away($destination);
    }
}
