<?php

namespace App\Keycloak\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk verifikasi permission berbasis Keycloak session.
 *
 * Memverifikasi bahwa session permissions array berisi permission
 * yang diperlukan untuk mengakses route tertentu. Mengembalikan
 * 403 Forbidden jika user tidak memiliki izin yang diperlukan.
 */
class VerifyKeycloakPermission
{
    /**
     * Handle incoming request dan verifikasi permission dari session Keycloak.
     *
     * @param  string  $permission  Permission string yang diperlukan untuk route ini
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Cek apakah ada session Keycloak yang valid
        if (! session()->has('keycloak.tokens') || ! session()->has('keycloak.permissions')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            return redirect()->route('keycloak.login');
        }

        // Ambil permissions dari session Keycloak
        $permissions = session('keycloak.permissions', []);

        // Cek apakah user memiliki permission yang diperlukan
        if (! in_array($permission, $permissions, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
