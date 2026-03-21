<?php

namespace App\Services;

use App\Models\IamUserRole;

class IamAuthorizationService
{
    /**
     * Ambil semua permission slug untuk user pada aplikasi tertentu.
     * Mengganti duplikasi logika yang sama di IamController (2x) dan VerifyIamPermission.
     *
     * @param  string  $applicationId  ULID dari IamApplication
     * @return string[]
     */
    public function getUserPermissions(string $userId, string $applicationId): array
    {
        return IamUserRole::where('user_id', $userId)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $applicationId))
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ambil semua role slug untuk user pada aplikasi tertentu.
     *
     * @param  string  $applicationId  ULID dari IamApplication
     * @return string[]
     */
    public function getUserRoles(string $userId, string $applicationId): array
    {
        return IamUserRole::where('user_id', $userId)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $applicationId))
            ->with('role')
            ->get()
            ->map(fn ($ur) => $ur->role->slug)
            ->values()
            ->all();
    }
}
