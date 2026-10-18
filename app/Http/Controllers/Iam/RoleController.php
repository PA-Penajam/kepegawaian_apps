<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StoreRoleRequest;
use App\Http\Requests\Iam\UpdateRoleRequest;
use App\Models\IamApplication;
use App\Models\IamRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function store(StoreRoleRequest $request, IamApplication $aplikasi): RedirectResponse
    {
        try {
            $data = $request->validated();

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
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat menambahkan role. Silakan coba lagi.');
        }
    }

    public function update(UpdateRoleRequest $request, IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah');

        // Validasi IDOR: pastikan role milik aplikasi yang dimaksud
        abort_unless($role->iam_application_id === $aplikasi->id, 404);

        try {
            $data = $request->validated();

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
            report($e);

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
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat menghapus role. Silakan coba lagi.');
        }
    }
}
