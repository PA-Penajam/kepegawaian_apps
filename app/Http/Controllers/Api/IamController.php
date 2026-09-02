<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IamValidateResource;
use App\Services\IamAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
