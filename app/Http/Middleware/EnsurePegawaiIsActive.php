<?php

namespace App\Http\Middleware;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Services\Sso\Contracts\SsoTokenStorageInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan pegawai yang sedang terautentikasi masih berstatus aktif dan belum dihapus.
 * Jika tidak, cabut sesi dan token lokal secara real-time.
 */
class EnsurePegawaiIsActive
{
    public function __construct(
        private readonly SsoTokenStorageInterface $tokenStorage,
    ) {}

    /**
     * Memeriksa status aktif pegawai pada setiap request masuk dan mengeluarkan
     * sesi lokal apabila pegawai tidak aktif atau telah dihapus.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('auth/sso/*', 'login', 'logout', '_health', 'up')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof Pegawai && ($user->status_pegawai !== StatusPegawai::Aktif || $user->trashed())) {
            $this->tokenStorage->clearTokens();
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

        return $next($request);
    }
}
