<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IamValidateResource;
use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\IamUserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IamController extends Controller
{
    public function validate(Request $request): JsonResponse
    {
        $user    = $request->user();
        $app     = $request->get('_iam_app'); // diinjek oleh VerifyIamSignature

        $userRoles = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $app->id))
            ->with('role.permissions')
            ->get();

        $roles       = $userRoles->map(fn ($ur) => $ur->role->slug)->values()->all();
        $permissions = $userRoles
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()->values()->all();

        $token = $user->currentAccessToken();

        return response()->json([
            'user'             => (new IamValidateResource($user))->resolve(),
            'roles'            => $roles,
            'permissions'      => $permissions,
            'token_expires_at' => $token && $token->expires_at ? $token->expires_at->timestamp : null,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $user       = $request->user();
        $app        = $request->get('_iam_app');
        $permission = $request->query('permission', '');

        $userPermissions = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $app->id))
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()->values()->all();

        return response()->json([
            'allowed'    => in_array($permission, $userPermissions, true),
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

        $ssoCode = IamSsoCode::where('code', $request->code)->first();

        if (! $ssoCode || ! $ssoCode->isValid()) {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }

        // Tandai code sebagai sudah dipakai
        $ssoCode->update(['used_at' => now()]);

        // Generate Sanctum token untuk user
        $user     = $ssoCode->user;
        $ttlHours = (int) config('iam.token_ttl_hours', 8);
        $token    = $user->createToken('sso', ['*'], now()->addHours($ttlHours));

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at->timestamp,
        ]);
    }
}
