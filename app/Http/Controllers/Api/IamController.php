<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusPegawai;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExchangeCodeRequest;
use App\Http\Resources\IamValidateResource;
use App\Models\IamSsoCode;
use App\Models\Pegawai;
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
        if (! $this->isPegawaiAktif($user)) {
            return response()->json(['message' => 'Akun pegawai tidak aktif atau telah dihapus.'], 403);
        }

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
        if (! $this->isPegawaiAktif($user)) {
            return response()->json(['message' => 'Akun pegawai tidak aktif atau telah dihapus.'], 403);
        }

        $app = $request->attributes->get('iam_app');
        $rawPermission = $request->query('permission');
        $permission = is_string($rawPermission) ? $rawPermission : '';

        $allowed = in_array(
            $permission,
            $this->iamAuth->getUserPermissions($user->id, $app->id),
            true
        );

        return response()->json(['allowed' => $allowed, 'permission' => $permission]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isPegawaiAktif($user)) {
            return response()->json(['message' => 'Akun pegawai tidak aktif atau telah dihapus.'], 403);
        }

        $app = $request->attributes->get('iam_app');
        $token = $user->currentAccessToken();

        if (! $app || ! $token || ! $token->can("app:{$app->slug}")) {
            return response()->json(['message' => 'Token tidak memiliki akses untuk aplikasi ini.'], 403);
        }

        $token->delete();

        return response()->json(['message' => 'Token invalidated']);
    }

    public function exchangeCode(ExchangeCodeRequest $request): JsonResponse
    {
        $app = $request->attributes->get('iam_app');

        return DB::transaction(function () use ($request, $app): JsonResponse {
            $ssoCode = IamSsoCode::query()
                ->where('code', $request->code)
                ->where('app_slug', $app->slug)
                ->lockForUpdate()
                ->first();

            if (
                $ssoCode === null
                || $ssoCode->used_at !== null
                || $ssoCode->expires_at === null
                || $ssoCode->expires_at->lte(now())
            ) {
                return response()->json(['message' => 'Invalid or expired code'], 400);
            }

            $user = $ssoCode->user()->withTrashed()->first();

            if (! $this->isPegawaiAktif($user)) {
                return response()->json(['message' => 'Akun pegawai tidak aktif atau tidak terdaftar.'], 403);
            }

            $ssoCode->forceFill(['used_at' => now()])->save();
            $ttlHours = (int) config('iam.token_ttl_hours', 8);

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

    /**
     * Memastikan subjek autentikasi IAM merupakan pegawai yang masih aktif dan belum dihapus.
     */
    private function isPegawaiAktif(mixed $user): bool
    {
        return $user instanceof Pegawai
            && ! $user->trashed()
            && $user->status_pegawai === StatusPegawai::Aktif;
    }
}
