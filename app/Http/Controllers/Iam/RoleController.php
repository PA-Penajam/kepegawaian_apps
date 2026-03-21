<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'slug' => ['required', 'alpha_dash', Rule::unique('iam_roles', 'slug')->where('iam_application_id', $aplikasi->id)],
            'keterangan' => 'nullable|string',
            'permission_ids' => 'array',
            // Scope permission_ids hanya ke permissions milik aplikasi ini
            'permission_ids.*' => ['exists:iam_permissions,id', Rule::exists('iam_permissions', 'id')->where('iam_application_id', $aplikasi->id)],
        ]);

        $role = $aplikasi->roles()->create($data);
        if (! empty($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        return back();
    }

    public function update(Request $request, IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah');

        // Validasi IDOR: pastikan role milik aplikasi yang dimaksud
        abort_unless($role->iam_application_id === $aplikasi->id, 404);

        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string',
            'permission_ids' => 'array',
            // Scope permission_ids hanya ke permissions milik aplikasi ini
            'permission_ids.*' => ['exists:iam_permissions,id', Rule::exists('iam_permissions', 'id')->where('iam_application_id', $aplikasi->id)],
        ]);
        $role->update($data);
        $role->permissions()->sync($data['permission_ids'] ?? []);

        return back();
    }

    public function destroy(IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat dihapus');

        // Validasi IDOR: pastikan role milik aplikasi yang dimaksud
        abort_unless($role->iam_application_id === $aplikasi->id, 404);

        $role->delete();

        return back();
    }
}
