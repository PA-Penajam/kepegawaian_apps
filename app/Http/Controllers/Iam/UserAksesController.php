<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StoreUserAksesRequest;
use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Support\SqlLike;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class UserAksesController extends Controller
{
    public function index(): Response
    {
        $rawSearch = request('search');
        $search = is_string($rawSearch) && trim($rawSearch) !== '' ? $rawSearch : null;

        $users = Pegawai::query()
            ->withCount(['iamRoles' => fn ($query) => $query->whereHas('application')])
            ->with(['iamRoles' => fn ($query) => $query->whereHas('application')->with('application')])
            ->when($search !== null, fn ($query) => SqlLike::whereContains($query, ['nama_lengkap', 'nip'], $search))
            ->orderBy('nama_lengkap')
            ->paginate(20)
            ->withQueryString();

        return inertia('iam/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Pegawai $user): Response
    {
        $akses = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($query) => $query->whereHas('application'))
            ->with(['role.application', 'role.permissions', 'assignedByUser'])
            ->get();
        $availableApps = IamApplication::where('is_active', true)
            ->with('roles')
            ->get();

        return inertia('iam/users/akses', compact('user', 'akses', 'availableApps'));
    }

    public function store(StoreUserAksesRequest $request, Pegawai $user): RedirectResponse
    {
        $roleId = (string) $request->validated('iam_role_id');

        IamUserRole::firstOrCreate(
            ['user_id' => $user->id, 'iam_role_id' => $roleId],
            ['assigned_at' => now(), 'assigned_by' => $request->user()->id]
        );

        $role = IamRole::with('application')->find($roleId);
        if ($role?->application?->slug) {
            Cache::forget("iam_app:{$role->application->slug}");
        }

        return back();
    }

    public function destroy(Pegawai $user, IamRole $role): RedirectResponse
    {
        IamUserRole::where('user_id', $user->id)->where('iam_role_id', $role->id)->delete();

        $role->loadMissing('application');
        if ($role->application?->slug) {
            Cache::forget("iam_app:{$role->application->slug}");
        }

        return back();
    }
}
