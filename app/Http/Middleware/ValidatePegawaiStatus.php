<?php

namespace App\Http\Middleware;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware untuk memvalidasi status Pegawai sebelum proses autentikasi.
 *
 * Middleware ini memastikan hanya Pegawai dengan status "aktif" yang dapat login,
 * baik melalui Laravel Fortify maupun Keycloak SSO.
 *
 * Penting: return type harus `mixed` bukan `Response` karena class ini
 * dipakai di dua konteks:
 * - HTTP Middleware (return Symfony Response)
 * - Fortify Login Pipeline (return Responsable seperti SsoAwareLoginResponse)
 */
class ValidatePegawaiStatus
{
    /**
     * Handle request yang masuk.
     *
     * Validasi status_pegawai dari NIP yang diinput user, dan redirect ke halaman login
     * jika status bukan "aktif".
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // Skip validation jika route login tidak sesuai
        // Route login: GET /login (form), POST /login (submit)
        // SSO callback route: keycloak/callback
        if (! in_array($request->route()?->getName(), ['login', 'login.store', 'keycloak.callback'])) {
            return $next($request);
        }

        // Dapatkan NIP dari session atau request body
        $nip = null;

        // Untuk Keycloak callback - ambil NIP dari session
        if ($request->is('*/keycloak/*')) {
            $nip = session('keycloak.user.nip') ?? null;
        }

        // Untuk Fortify login POST - ambil NIP dari request
        if (! $nip && $request->isMethod('POST')) {
            $nip = $request->input('nip');
        }

        // Jika tidak ada NIP, lanjutkan proses
        if (! $nip) {
            return $next($request);
        }

        // Cari data Pegawai berdasarkan NIP
        $pegawai = Pegawai::where('nip', $nip)->first();

        // Jika Pegawai tidak ditemukan, lanjutkan ke auth
        if (! $pegawai) {
            return $next($request);
        }

        // Validasi status Pegawai harus aktif
        if ($pegawai->status_pegawai !== StatusPegawai::Aktif) {
            // Tampilkan pesan error yang informatif
            $errorMessage = 'Akun Pegawai tidak aktif ('.$pegawai->status_pegawai->label().'). Hubungi administrator untuk informasi lebih lanjut.';

            // Untuk Keycloak callback - redirect ke dashboard dengan warning message
            if ($request->is('*/keycloak/*')) {
                return redirect()->route('dashboard')
                    ->with('error', $errorMessage);
            }

            // Untuk Fortify login - redirect back dengan error message
            return redirect()->back()
                ->withInput($request->only('nip', 'remember'))
                ->withErrors([
                    'nip' => $errorMessage,
                ]);
        }

        // Status aktif, lanjutkan proses autentikasi
        return $next($request);
    }
}
