<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class UserAksesController extends Controller
{
    public function index(): Response
    {
        $users = User::with('iamRoles.role.application')->paginate(20);
        return inertia('iam/users/index', compact('users'));
    }

    public function show(User $user): Response
    {
        $akses = IamUserRole::where('user_id', $user->id)
            ->with(['role.application', 'role.permissions', 'assignedByUser'])
            ->get();
        $availableApps = IamApplication::where('is_active', true)
            ->with('roles')
            ->get();
        return inertia('iam/users/akses', compact('user', 'akses', 'availableApps'));
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['iam_role_id' => 'required|exists:iam_roles,id']);

        IamUserRole::firstOrCreate(
            ['user_id' => $user->id, 'iam_role_id' => $data['iam_role_id']],
            ['assigned_at' => now(), 'assigned_by' => $request->user()->id]
        );
        return back();
    }

    public function destroy(User $user, IamRole $role): RedirectResponse
    {
        IamUserRole::where('user_id', $user->id)->where('iam_role_id', $role->id)->delete();
        return back();
    }
}
