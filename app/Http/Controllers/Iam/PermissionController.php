<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StorePermissionRequest;
use App\Http\Requests\Iam\UpdatePermissionRequest;
use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class PermissionController extends Controller
{
    public function store(StorePermissionRequest $request, IamApplication $aplikasi): RedirectResponse
    {
        try {
            $aplikasi->permissions()->create($request->validated());

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Permission berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menambahkan permission. Silakan coba lagi.');
        }
    }

    public function update(UpdatePermissionRequest $request, IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

        try {
            $permission->update($request->validated());

            Cache::forget("iam_app:{$aplikasi->slug}");

            return back()->with('success', 'Permission berhasil diperbarui.');
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

    public function migrateSlug(
        IamApplication $aplikasi,
        IamPermission $permission,
        \App\Services\Iam\IamPermissionAuditor $auditor,
    ): RedirectResponse {
        // Validasi IDOR: pastikan permission milik aplikasi yang dimaksud
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

        $suggested = $auditor->suggestCanonical($permission->slug);
        if (! $suggested) {
            return back()->withErrors([
                'slug' => 'Tidak ada saran canonical untuk slug ini. Edit manual via tabel permission.',
            ]);
        }

        if (! $auditor->isValidSlug($suggested)) {
            return back()->withErrors([
                'slug' => 'Saran canonical tidak valid menurut konvensi. Edit manual.',
            ]);
        }

        // Cek uniqueness dalam aplikasi
        $exists = IamPermission::where('iam_application_id', $aplikasi->id)
            ->where('slug', $suggested)
            ->where('id', '!=', $permission->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'slug' => "Slug '{$suggested}' sudah ada di aplikasi ini. Resolusi konflik manual diperlukan.",
            ]);
        }

        $before = $permission->slug;
        $permission->update([
            'slug'  => $suggested,
            'group' => explode('.', $suggested)[0],
        ]);

        activity('iam.permission')
            ->causedBy(auth()->user())
            ->performedOn($permission)
            ->withProperties(['before' => $before, 'after' => $suggested])
            ->log('slug-migrated');

        Cache::forget("iam_app:{$aplikasi->slug}");

        return back()->with('success', "Slug dimigrasi: {$before} → {$suggested}");
    }
}
