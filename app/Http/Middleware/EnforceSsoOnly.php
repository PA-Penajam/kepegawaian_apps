<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSsoOnly
{
    /**
     * Blokir endpoint login lokal sebelum pipeline autentikasi Fortify berjalan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->route()?->named('login.store')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Login lokal dinonaktifkan. Silakan masuk melalui SSO PA Penajam.',
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('login')->with(
                'error',
                'Login lokal dinonaktifkan. Silakan masuk melalui SSO PA Penajam.',
            );
        }

        if ($request->route()?->named(
            'password.confirm',
            'password.confirm.store',
            'password.confirmation',
        )) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
