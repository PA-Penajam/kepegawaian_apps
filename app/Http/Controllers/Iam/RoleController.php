<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        $data = $request->validate([
            'nama'           => 'required|string|max:100',
            'slug'           => 'required|string|alpha_dash',
            'keterangan'     => 'nullable|string',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:iam_permissions,id',
        ]);

        $role = $aplikasi->roles()->create($data);
        if (!empty($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        return back();
    }

    public function update(Request $request, IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah');
        $data = $request->validate([
            'nama'           => 'required|string|max:100',
            'keterangan'     => 'nullable|string',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:iam_permissions,id',
        ]);
        $role->update($data);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        return back();
    }

    public function destroy(IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat dihapus');
        $role->delete();
        return back();
    }
}
