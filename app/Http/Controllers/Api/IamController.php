<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IamValidateResource;
use App\Models\IamSsoCode;
use App\Models\IamUserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IamController extends Controller
{
    public function validate(Request $request): JsonResponse
    {
        $user = $request->user();
        $app = $request->attributes->get('iam_app'); // diinjek oleh VerifyIamSignature via attributes

        $userRoles = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $app->id))
            ->with('role.permissions')
            ->get();

        $roles = $userRoles->map(fn ($ur) => $ur->role->slug)->values()->all();
        $permissions = $userRoles
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()->values()->all();

        $token = $user->currentAccessToken();

        return response()->json([
            'user' => (new IamValidateResource($user))->resolve(),
            'roles' => $roles,
            'permissions' => $permissions,
            'token_expires_at' => $token && $token->expires_at ? $token->expires_at->timestamp : null,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $user = $request->user();
        $app = $request->attributes->get('iam_app');
        $permission = $request->query('permission', '');

        $userPermissions = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $app->id))
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()->values()->all();

        return response()->json([
            'allowed' => in_array($permission, $userPermissions, true),
            'permission' => $permission,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token invalidated']);
    }

    public function exchangeCode(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:64']);

        $app = $request->attributes->get('iam_app');

        return DB::transaction(function () use ($request, $app): JsonResponse {
            // Atomic: update hanya jika code valid, milik app yang benar, belum dipakai, belum expired
            $affected = IamSsoCode::where('code', $request->code)
                ->where('app_slug', $app->slug)        // cegah cross-app token theft
                ->whereNull('used_at')                 // belum digunakan
                ->where('expires_at', '>', now())      // belum expired
                ->update(['used_at' => now()]);

            if ($affected === 0) {
                return response()->json(['message' => 'Invalid or expired code'], 400);
            }

            // Ambil ssoCode setelah atomic update (dalam transaksi yang sama)
            $ssoCode  = IamSsoCode::where('code', $request->code)->first();
            $user     = $ssoCode->user;
            $ttlHours = (int) config('iam.token_ttl_hours', 8);

            // Scope token per aplikasi — bukan ['*'] yang terlalu luas
            $token = $user->createToken(
                "sso:{$app->slug}",
                ["app:{$app->slug}"],
                now()->addHours($ttlHours)
            );

            return response()->json([
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at->timestamp,
            ]);
        });
    }
}
