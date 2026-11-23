<?php

namespace App\Http\Controllers\Referensi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referensi\StoreRefRoleRequest;
use App\Http\Requests\Referensi\UpdateRefRoleRequest;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Support\SqlLike;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RefRoleController extends Controller
{
    private function appSlug(): string
    {
        return (string) config('iam.app_slug', 'kepegawaian');
    }

    private function getAppId(): string
    {
        return IamApplication::where('slug', $this->appSlug())->value('id') ?? '';
    }

    private function forgetAppCache(): void
    {
        Cache::forget('iam_app:'.$this->appSlug());
    }

    public function index(): Response
    {
        $this->authorize('viewAny', IamRole::class);

        $appId = $this->getAppId();
        $rawSearch = request('search');
        $search = is_string($rawSearch) && trim($rawSearch) !== '' ? $rawSearch : null;

        $roles = IamRole::query()
            ->where('iam_application_id', $appId)
            ->with(['permissions:id'])
            ->withCount(['permissions', 'users as pegawai_count'])
            ->when($search !== null, function ($query) use ($search): void {
                SqlLike::whereContains($query, ['nama', 'keterangan'], $search);
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $permissions = IamPermission::query()
            ->where('iam_application_id', $appId)
            ->orderBy('group')
            ->orderBy('nama')
            ->get(['id', 'nama', 'group', 'keterangan']);

        return Inertia::render('referensi/roles/index', [
            'roles' => $roles,
            'filters' => ['search' => $search],
            'permissions' => $permissions,
        ]);
    }

    public function store(StoreRefRoleRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['nama', 'keterangan']);
        $data['iam_application_id'] = $this->getAppId();
        $data['slug'] = Str::slug($data['nama']);

        $role = IamRole::query()->create($data);

        if ($request->has('permissions')) {
            $permissions = $request->validated('permissions');
            $role->permissions()->sync(is_array($permissions) ? $permissions : []);
        }

        $this->forgetAppCache();

        return redirect()
            ->route('referensi.roles.index')
            ->with('success', 'Role berhasil ditambahkan.');
    }

    public function edit(IamRole $role): Response
    {
        abort_unless($role->iam_application_id === $this->getAppId(), 404);

        $this->authorize('update', $role);

        $role->load('permissions');

        $appId = $this->getAppId();

        $permissions = IamPermission::query()
            ->where('iam_application_id', $appId)
            ->orderBy('group')
            ->orderBy('nama')
            ->get(['id', 'nama', 'group', 'keterangan']);

        $rawSearchPegawai = request('search_pegawai');
        $searchPegawai = is_string($rawSearchPegawai) && trim($rawSearchPegawai) !== '' ? $rawSearchPegawai : null;

        return Inertia::render('referensi/roles/edit', [
            'role' => $role,
            'permissions' => $permissions,
            'pegawaiList' => Pegawai::query()
                ->select('id', 'nama_lengkap', 'nip')
                ->when(
                    $searchPegawai !== null,
                    fn ($q) => SqlLike::whereContains($q, ['nama_lengkap', 'nip'], $searchPegawai)
                )
                ->orderBy('nama_lengkap')
                ->paginate(15, ['*'], 'pegawai_page')
                ->withQueryString(),
            'assignedPegawaiIds' => $role->users()->pluck('pegawai.id'),
        ]);
    }

    public function update(UpdateRefRoleRequest $request, IamRole $role): RedirectResponse
    {
        abort_unless($role->iam_application_id === $this->getAppId(), 404);

        $validated = $request->safe()->only(['nama', 'keterangan']);

        if ($role->is_system) {
            if ($validated['nama'] !== $role->nama) {
                return redirect()
                    ->route('referensi.roles.index')
                    ->withErrors(['nama' => 'Nama role sistem tidak dapat diubah.'])
                    ->with('error', 'Nama role sistem tidak dapat diubah.');
            }

            // Role sistem hanya mengizinkan perubahan keterangan, izin, dan penugasan pegawai
            $role->update([
                'keterangan' => $validated['keterangan'] ?? $role->keterangan,
            ]);
        } else {
            // Jangan pernah menimpa slug saat update agar integritas referensi terjaga
            $role->update($validated);
        }

        if ($request->has('permissions')) {
            $permissions = $request->validated('permissions');
            $role->permissions()->sync(is_array($permissions) ? $permissions : []);
        }

        if ($request->has('pegawai_ids')) {
            $pegawaiIds = $request->validated('pegawai_ids');
            $role->users()->sync(is_array($pegawaiIds) ? $pegawaiIds : []);
        }

        $this->forgetAppCache();

        return redirect()
            ->route('referensi.roles.index')
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(IamRole $role): RedirectResponse
    {
        abort_unless($role->iam_application_id === $this->getAppId(), 404);

        $this->authorize('delete', $role);

        if ($role->is_system) {
            return redirect()
                ->route('referensi.roles.index')
                ->with('error', 'Role sistem tidak dapat dihapus.');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('referensi.roles.index')
                ->with('error', 'Role masih memiliki pegawai yang di-assign. Pindahkan pegawai ke role lain terlebih dahulu.');
        }

        $role->delete();
        $this->forgetAppCache();

        return redirect()
            ->route('referensi.roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }
}
