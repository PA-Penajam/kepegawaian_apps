<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'slug' => ['required', 'alpha_dash', Rule::unique('iam_roles', 'slug')->where('iam_application_id', $aplikasi->id)],
                'keterangan' => 'nullable|string',
                'permission_ids' => 'array',
                // Scope permission_ids hanya ke permissions milik aplikasi ini
                'permission_ids.*' => ['exists:iam_permissions,id', Rule::exists('iam_permissions', 'id')->where('iam_application_id', $aplikasi->id)],
            ]);

            // Ambil permission_ids sebelum create (bukan field tabel iam_roles)
            $permissionIds = $data['permission_ids'] ?? [];
            unset($data['permission_ids']);

            $role = $aplikasi->roles()->create($data);
            if (! empty($permissionIds)) {
                $role->permissions()->sync($permissionIds);
            }

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Role berhasil ditambahkan.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menambahkan role. Silakan coba lagi.');
        }
    }

    public function update(Request $request, IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah');

        // Validasi IDOR: pastikan role milik aplikasi yang dimaksud
        abort_unless($role->iam_application_id === $aplikasi->id, 404);

        try {
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'keterangan' => 'nullable|string',
                'permission_ids' => 'array',
                // Scope permission_ids hanya ke permissions milik aplikasi ini
                'permission_ids.*' => ['exists:iam_permissions,id', Rule::exists('iam_permissions', 'id')->where('iam_application_id', $aplikasi->id)],
            ]);

            // Ambil permission_ids sebelum update (bukan field tabel iam_roles)
            $permissionIds = $data['permission_ids'] ?? [];
            unset($data['permission_ids']);

            $role->update($data);
            $role->permissions()->sync($permissionIds);

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Role berhasil diperbarui.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat memperbarui role. Silakan coba lagi.');
        }
    }

    public function destroy(IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat dihapus');

        // Validasi IDOR: pastikan role milik aplikasi yang dimaksud
        abort_unless($role->iam_application_id === $aplikasi->id, 404);

        try {
            $role->delete();

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Role berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menghapus role. Silakan coba lagi.');
        }
    }
}
