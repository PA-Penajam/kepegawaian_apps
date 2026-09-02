<?php

namespace App\Http\Middleware;

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware untuk memvalidasi status Pegawai sebelum proses autentikasi via Fortify.
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
        if (! in_array($request->route()?->getName(), ['login', 'login.store'])) {
            return $next($request);
        }

        $nip = null;

        if ($request->isMethod('POST')) {
            $nip = $request->input('nip');
        }

        if (! $nip) {
            return $next($request);
        }

        $pegawai = Pegawai::where('nip', $nip)->first();

        if (! $pegawai) {
            return $next($request);
        }

        if ($pegawai->status_pegawai !== StatusPegawai::Aktif) {
            $errorMessage = 'Akun Pegawai tidak aktif ('.$pegawai->status_pegawai->label().'). Hubungi administrator untuk informasi lebih lanjut.';

            return redirect()->back()
                ->withInput($request->only('nip', 'remember'))
                ->withErrors([
                    'nip' => $errorMessage,
                ]);
        }

        return $next($request);
    }
}
