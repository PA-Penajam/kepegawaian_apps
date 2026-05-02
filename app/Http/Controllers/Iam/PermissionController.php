<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        try {
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'slug' => ['required', 'string', Rule::unique('iam_permissions', 'slug')->where('iam_application_id', $aplikasi->id)],
                'group' => 'nullable|string|max:50',
                'keterangan' => 'nullable|string',
            ]);
            $aplikasi->permissions()->create($data);

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Permission berhasil ditambahkan.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menambahkan permission. Silakan coba lagi.');
        }
    }

    public function update(Request $request, IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

        try {
            $data = $request->validate([
                'nama' => 'required|string|max:100',
                'group' => 'nullable|string|max:50',
                'keterangan' => 'nullable|string',
            ]);
            $permission->update($data);

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Permission berhasil diperbarui.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat memperbarui permission. Silakan coba lagi.');
        }
    }

    public function destroy(IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

        try {
            $permission->delete();

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Permission berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menghapus permission. Silakan coba lagi.');
        }
    }
}
