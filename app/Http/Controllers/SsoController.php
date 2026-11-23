<?php

namespace App\Http\Controllers;

use App\Enums\StatusPegawai;
use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\Pegawai;
use App\Services\Iam\IamSsoStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SsoController extends Controller
{
    public function __construct(private readonly IamSsoStateStore $ssoStateStore) {}

    public function login(Request $request): RedirectResponse|JsonResponse|Response
    {
        try {
            $validated = $request->validate([
                'app' => 'required|string',
                'redirect' => 'required|url:http,https',
                'state' => 'nullable|string|max:128',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        $app = IamApplication::where('slug', $validated['app'])->where('is_active', true)->first();

        if (! $app) {
            abort(404, 'Aplikasi tidak ditemukan');
        }

        // Validasi skema, host, dan port redirect sebelum menyimpan state atau mengalihkan tamu
        $this->assertAllowedRedirectUrl((string) $app->url, $validated['redirect']);

        // Simpan state dari client app untuk di-pass balik setelah login
        $state = $validated['state'] ?? null;

        $user = $request->user();

        if (! $user) {
            $this->ssoStateStore->put($validated['app'], $validated['redirect'], $state);

            return redirect()->route('auth.sso.login');
        }

        if (! $this->isActivePegawai($user)) {
            return $this->logoutAndRedirectInactive($request);
        }

        return $this->generateCodeAndRedirect($request, $user->id, $app, $validated['redirect'], $state);
    }

    /**
     * Dipanggil setelah login berhasil jika ada SSO session.
     *
     * State diambil dari cache (dengan fallback ke session lama)
     * sehingga tahan terhadap session regeneration oleh Fortify.
     */
    public function callback(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        if ($user !== null && ! $this->isActivePegawai($user)) {
            return $this->logoutAndRedirectInactive($request);
        }

        [$appSlug, $redirect, $state] = $this->ssoStateStore->pull();

        if (! $appSlug || ! $redirect) {
            Log::info('SSO callback tanpa state — redirect ke dashboard kepegawaian.', [
                'user_id' => $request->user()?->id,
            ]);

            return redirect()->route('dashboard')
                ->with('warning', 'SSO redirect gagal: session SSO tidak ditemukan. Silakan coba login ulang dari aplikasi tujuan.');
        }

        // Kepegawaian adalah SSO provider-nya sendiri — skip code generation
        // karena session Fortify sudah terbuat. Validasi redirect sebelum mengarahkan.
        $selfSlug = config('iam.app_slug', 'kepegawaian');
        if ($appSlug === $selfSlug) {
            $selfApp = IamApplication::where('slug', $selfSlug)->where('is_active', true)->first();
            $allowedBaseUrl = $selfApp?->url ?: (string) config('app.url');
            $this->assertAllowedRedirectUrl($allowedBaseUrl, $redirect);

            return redirect()->away($redirect);
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

    private function generateCodeAndRedirect(Request $request, string $userId, IamApplication $app, string $redirectUrl, ?string $state = null): RedirectResponse|Response
    {
        // Validasi ketat skema, host, dan port sebagai defense-in-depth
        $this->assertAllowedRedirectUrl((string) $app->url, $redirectUrl);

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

    /**
     * Memastikan redirect URL memiliki skema (http/https), host, dan port efektif
     * yang cocok persis dengan URL dasar aplikasi terdaftar serta bebas userinfo/kontrol.
     */
    private function assertAllowedRedirectUrl(string $allowedBaseUrl, string $redirectUrl): void
    {
        if (preg_match('/[\x00-\x1F\x7F\s]/', $redirectUrl) === 1) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://([^/?\#]+)#', $redirectUrl, $authorityMatch) === 1) {
            if (str_contains($authorityMatch[1], '\\') || str_contains($authorityMatch[1], '@')) {
                abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
            }
        }

        $allowedParts = parse_url(trim($allowedBaseUrl));
        $targetParts = parse_url($redirectUrl);

        if (! is_array($allowedParts) || ! is_array($targetParts)) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }

        if (isset($targetParts['user']) || isset($targetParts['pass'])) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }

        $allowedScheme = strtolower((string) ($allowedParts['scheme'] ?? ''));
        $targetScheme = strtolower((string) ($targetParts['scheme'] ?? ''));

        if (! in_array($allowedScheme, ['http', 'https'], true) || $targetScheme !== $allowedScheme) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }

        $allowedHost = strtolower((string) ($allowedParts['host'] ?? ''));
        $targetHost = strtolower((string) ($targetParts['host'] ?? ''));

        if ($allowedHost === '' || $targetHost === '' || str_contains($targetHost, '\\') || $targetHost !== $allowedHost) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }

        $allowedPort = $allowedParts['port'] ?? ($allowedScheme === 'https' ? 443 : 80);
        $targetPort = $targetParts['port'] ?? ($targetScheme === 'https' ? 443 : 80);

        if ((int) $targetPort !== (int) $allowedPort) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }
    }

    /**
     * Memastikan pengguna yang terautentikasi adalah pegawai aktif dan belum di-soft-delete.
     */
    private function isActivePegawai(mixed $user): bool
    {
        return $user instanceof Pegawai
            && $user->status_pegawai === StatusPegawai::Aktif
            && ! $user->trashed();
    }

    /**
     * Mengeluarkan sesi lokal pegawai nonaktif/terhapus dan mengarahkannya ke halaman login.
     */
    private function logoutAndRedirectInactive(Request $request): RedirectResponse|Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Akun pegawai tidak aktif.',
                'error' => 'account_inactive',
            ], 401);
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        return redirect()->route('login')
            ->with('error', 'Akun pegawai Anda tidak aktif atau telah dihapus. Silakan hubungi administrator kepegawaian.');
    }
}
