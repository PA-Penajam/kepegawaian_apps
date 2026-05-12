<?php

namespace App\Http\Middleware;

use App\Models\IamApplication;
use App\Services\IamAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyIamPermission
{
    public function __construct(private readonly IamAuthorizationService $iamAuth) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated'], 401)
                : redirect()->to(
                    route('sso.login', [
                        'app' => config('iam.app_slug', 'kepegawaian'),
                        'redirect' => $request->url(),
                    ])
                );
        }

        $appSlug = config('iam.app_slug', 'kepegawaian');
        $kepegawaian = Cache::remember("iam_app:{$appSlug}", 3600,
            fn () => IamApplication::where('slug', $appSlug)->first()
        );

        if (! $kepegawaian) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (empty($permissions)) {
            $userRoles = $this->iamAuth->getUserRoles($user->id, $kepegawaian->id);
            abort_if(empty($userRoles), Response::HTTP_FORBIDDEN);

            return $next($request);
        }

        $userPermissions = $this->iamAuth->getUserPermissions($user->id, $kepegawaian->id);

        // Deteksi mode: 'any:perm1,perm2' = OR logic, default = AND logic
        $mode = 'all';
        $resolvedPermissions = [];

        foreach ($permissions as $permission) {
            if (str_starts_with($permission, 'any:')) {
                $mode = 'any';
                $resolvedPermissions = array_merge(
                    $resolvedPermissions,
                    array_map('trim', explode(',', substr($permission, 4)))
                );
            } else {
                $resolvedPermissions = array_merge(
                    $resolvedPermissions,
                    array_map('trim', explode(',', $permission))
                );
            }
        }

        $resolvedPermissions = array_filter($resolvedPermissions);

        if ($mode === 'any') {
            $hasAny = false;
            foreach ($resolvedPermissions as $perm) {
                if (in_array($perm, $userPermissions, true)) {
                    $hasAny = true;
                    break;
                }
            }
            abort_unless($hasAny, Response::HTTP_FORBIDDEN);
        } else {
            foreach ($resolvedPermissions as $perm) {
                abort_unless(in_array($perm, $userPermissions, true), Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
