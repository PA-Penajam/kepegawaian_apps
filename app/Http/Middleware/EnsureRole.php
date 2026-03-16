<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                abort(Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->route('login');
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => strtolower(trim($role)))
            ->filter()
            ->values()
            ->all();

        // Multi-role: cek apakah pegawai punya salah satu role yang diizinkan
        $userRoles = $user->roles->pluck('nama')->map(fn ($r) => strtolower($r))->toArray();
        $hasRole = count(array_intersect($userRoles, $allowedRoles)) > 0;

        if (! $hasRole) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
