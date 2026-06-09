<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class SsoAwareLoginResponse implements LoginResponseContract
{
    /**
     * Jika ada SSO state aktif (dari /sso/login), teruskan ke /sso/callback
     * agar alur SSO kepegawaian identik dengan aplikasi lain.
     * Jika tidak, gunakan redirect()->intended() seperti default Fortify.
     *
     * SSO state dicek dari dua sumber:
     * 1. Cache key pointer di session (primary — tahan session regeneration)
     * 2. Session langsung (fallback — backward compatibility)
     */
    public function toResponse($request)
    {
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['two_factor' => false]);
        }

        if ($this->hasSsoState()) {
            return redirect()->route('sso.callback');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }

    /**
     * Periksa apakah ada SSO state yang aktif.
     * Cek dari cache key pointer (primary) atau session langsung (fallback).
     */
    private function hasSsoState(): bool
    {
        // Primary: cek via cache key pointer di session
        if (session()->has('sso_state_key')) {
            return true;
        }

        // Fallback: cek session langsung
        return session()->has('sso_app');
    }
}
