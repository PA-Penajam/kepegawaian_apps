<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'slug'       => 'required|string',
            'group'      => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);
        $aplikasi->permissions()->create($data);
        return back();
    }

    public function update(Request $request, IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'group'      => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);
        $permission->update($data);
        return back();
    }

    public function destroy(IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        $permission->delete();
        return back();
    }
}
