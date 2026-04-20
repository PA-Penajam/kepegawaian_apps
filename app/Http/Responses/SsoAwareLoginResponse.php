<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class SsoAwareLoginResponse implements LoginResponseContract
{
    /**
     * Jika ada SSO session aktif (dari /sso/login), teruskan ke /sso/callback
     * agar alur SSO kepegawaian identik dengan aplikasi lain.
     * Jika tidak, gunakan redirect()->intended() seperti default Fortify.
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        if (session()->has('sso_app')) {
            return redirect()->route('sso.callback');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
