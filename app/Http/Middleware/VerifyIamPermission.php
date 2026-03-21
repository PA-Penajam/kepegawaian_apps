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
                : redirect()->route('login');
        }

        $appSlug = config('iam.app_slug', 'kepegawaian');
        // Cache query IAM app (hasil statis, TTL 1 jam)
        $kepegawaian = Cache::remember("iam_app:{$appSlug}", 3600,
            fn () => IamApplication::where('slug', $appSlug)->first()
        );

        if (! $kepegawaian) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $userPermissions = $this->iamAuth->getUserPermissions($user->id, $kepegawaian->id);

        if (empty($permissions)) {
            abort_if(empty($userPermissions), Response::HTTP_FORBIDDEN);
            return $next($request);
        }

        foreach ($permissions as $permission) {
            abort_unless(in_array($permission, $userPermissions, true), Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
