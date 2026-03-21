<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'slug' => ['required', 'string', Rule::unique('iam_permissions', 'slug')->where('iam_application_id', $aplikasi->id)],
            'group' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);
        $aplikasi->permissions()->create($data);

        return back();
    }

    public function update(Request $request, IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'group' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);
        $permission->update($data);

        return back();
    }

    public function destroy(IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

        $permission->delete();

        return back();
    }
}
