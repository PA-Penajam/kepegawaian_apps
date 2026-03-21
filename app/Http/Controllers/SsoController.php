<?php

namespace App\Http\Controllers;

use App\Models\IamApplication;
use App\Models\IamSsoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SsoController extends Controller
{
    public function login(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $validated = $request->validate([
                'app'      => 'required|string',
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

        return $this->generateCodeAndRedirect($request->user()->id, $app, $validated['redirect']);
    }

    /** Dipanggil setelah login berhasil jika ada SSO session */
    public function callback(Request $request): RedirectResponse
    {
        $appSlug  = session()->pull('sso_app');
        $redirect = session()->pull('sso_redirect');

        if (! $appSlug || ! $redirect) {
            return redirect()->route('dashboard');
        }

        $app = IamApplication::where('slug', $appSlug)->where('is_active', true)->first();

        if (! $app) {
            return redirect()->route('dashboard');
        }

        return $this->generateCodeAndRedirect($request->user()->id, $app, $redirect);
    }

    private function generateCodeAndRedirect(int $userId, IamApplication $app, string $redirectUrl): RedirectResponse
    {
        // Validasi domain whitelist — redirect harus dimulai dengan app.url terdaftar
        if (! str_starts_with($redirectUrl, $app->url)) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }

        $ttl  = (int) config('iam.sso_code_ttl_seconds', 60);
        $code = Str::random(64);

        IamSsoCode::create([
            'code'       => $code,
            'user_id'    => $userId,
            'app_slug'   => $app->slug,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        $separator = str_contains($redirectUrl, '?') ? '&' : '?';

        return redirect($redirectUrl . $separator . 'code=' . $code);
    }
}
