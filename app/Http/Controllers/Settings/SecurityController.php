<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Tampilkan halaman keamanan akun.
     *
     * Autentikasi (password & MFA) dikelola sepenuhnya oleh SSO PA Penajam,
     * sehingga halaman ini hanya menampilkan informasi, bukan form pengelolaan.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/security', [
            'ssoManaged' => true,
        ]);
    }
}
