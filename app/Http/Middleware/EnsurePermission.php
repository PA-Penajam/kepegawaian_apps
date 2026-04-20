<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                abort(Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->to(
                route('sso.login', [
                    'app' => config('iam.app_slug', 'kepegawaian'),
                    'redirect' => $request->url(),
                ])
            );
        }

        $requiredPermissions = collect($permissions)
            ->flatMap(fn (string $perm) => explode(',', $perm))
            ->map(fn (string $perm) => trim($perm))
            ->filter()
            ->values()
            ->all();

        if (! $user->hasAnyPermission(...$requiredPermissions)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
