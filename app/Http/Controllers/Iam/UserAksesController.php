<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StoreUserAksesRequest;
use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class UserAksesController extends Controller
{
    public function index(): Response
    {
        $search = request('search');

        $users = Pegawai::query()
            ->withCount('iamRoles')
            ->with('iamRoles.application')
            ->when($search, fn ($query, $search) => $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            }))
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
            ->with(['role.application', 'role.permissions', 'assignedByUser'])
            ->get();
        $availableApps = IamApplication::where('is_active', true)
            ->with('roles')
            ->get();

        return inertia('iam/users/akses', compact('user', 'akses', 'availableApps'));
    }

    public function store(StoreUserAksesRequest $request, Pegawai $user): RedirectResponse
    {
        IamUserRole::firstOrCreate(
            ['user_id' => $user->id, 'iam_role_id' => $request->validated('iam_role_id')],
            ['assigned_at' => now(), 'assigned_by' => $request->user()->id]
        );

        Cache::flush();

        return back();
    }

    public function destroy(Pegawai $user, IamRole $role): RedirectResponse
    {
        IamUserRole::where('user_id', $user->id)->where('iam_role_id', $role->id)->delete();

        Cache::flush();

        return back();
    }
}
