<?php

namespace App\Http\Middleware;

use App\Models\IamApplication;
use App\Models\IamUserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIamPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login');
        }

        $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
        if (! $kepegawaian) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // Kumpulkan semua permissions user untuk aplikasi kepegawaian
        $userPermissions = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $kepegawaian->id))
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();

        // Jika tidak ada permission yang diminta, cukup cek user punya role di app ini
        if (empty($permissions)) {
            if (empty($userPermissions)) {
                abort(Response::HTTP_FORBIDDEN);
            }
            return $next($request);
        }

        // Cek semua permission yang diminta terpenuhi
        foreach ($permissions as $permission) {
            if (! in_array($permission, $userPermissions, true)) {
                abort(Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
