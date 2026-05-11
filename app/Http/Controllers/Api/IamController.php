<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IamValidateResource;
use App\Models\IamSsoCode;
use App\Services\IamAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IamController extends Controller
{
    public function __construct(private readonly IamAuthorizationService $iamAuth) {}

    public function validate(Request $request): JsonResponse
    {
        $user = $request->user();
        $app = $request->attributes->get('iam_app'); // diinjek oleh VerifyIamSignature via attributes
        $token = $user->currentAccessToken();

        return response()->json([
            'user' => (new IamValidateResource($user))->resolve(),
            'roles' => $this->iamAuth->getUserRoles($user->id, $app->id),
            'permissions' => $this->iamAuth->getUserPermissions($user->id, $app->id),
            'token_expires_at' => $token && $token->expires_at ? $token->expires_at->timestamp : null,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $user = $request->user();
        $app = $request->attributes->get('iam_app');
        $permission = $request->query('permission', '');

        $allowed = in_array(
            $permission,
            $this->iamAuth->getUserPermissions($user->id, $app->id),
            true
        );

        return response()->json(['allowed' => $allowed, 'permission' => $permission]);
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
            $ssoCode = IamSsoCode::where('code', $request->code)->first();
            $user = $ssoCode->user;
            $ttlHours = (int) config('iam.token_ttl_hours', 8);

            // Scope token per aplikasi — bukan ['*'] yang terlalu luas
            $token = $user->createToken(
                "sso:{$app->slug}",
                ["app:{$app->slug}"],
                now()->addHours($ttlHours)
            );

            return response()->json([
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at->timestamp,
            ]);
        });
    }
}
