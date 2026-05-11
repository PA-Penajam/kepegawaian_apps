<?php

namespace App\Http\Controllers\Referensi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referensi\StoreRefRoleRequest;
use App\Http\Requests\Referensi\UpdateRefRoleRequest;
use App\Models\IamApplication;
use App\Models\IamPermission as RefPermission;
use App\Models\IamRole as RefRole;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RefRoleController extends Controller
{
    private function getAppId(): string
    {
        return IamApplication::where('slug', 'kepegawaian')->value('id') ?? '';
    }

    public function index(): Response
    {
        $this->authorize('viewAny', RefRole::class);

        $appId = $this->getAppId();

        $roles = RefRole::query()
            ->where('iam_application_id', $appId)
            ->with(['permissions:id'])
            ->withCount(['permissions', 'pegawai'])
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $permissions = RefPermission::query()
            ->where('iam_application_id', $appId)
            ->orderBy('group')
            ->orderBy('nama')
            ->get(['id', 'nama', 'group', 'keterangan']);

        return Inertia::render('referensi/roles/index', [
            'roles' => $roles,
            'filters' => request()->only(['search']),
            'permissions' => $permissions,
        ]);
    }

    public function store(StoreRefRoleRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['nama', 'keterangan']);
        $data['iam_application_id'] = $this->getAppId();
        $data['slug'] = Str::slug($data['nama']);

        $role = RefRole::query()->create($data);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions', []));
        }

        return redirect()
            ->route('referensi.roles.index')
            ->with('success', 'Role berhasil ditambahkan.');
    }

    public function edit(RefRole $role): Response
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        $appId = $this->getAppId();

        $permissions = RefPermission::query()
            ->where('iam_application_id', $appId)
            ->orderBy('group')
            ->orderBy('nama')
            ->get(['id', 'nama', 'group', 'keterangan']);

        return Inertia::render('referensi/roles/edit', [
            'role' => $role,
            'permissions' => $permissions,
            'pegawaiList' => Pegawai::query()
                ->select('id', 'nama_lengkap', 'nip')
                ->when(request('search_pegawai'), fn ($q, $s) => $q
                    ->where('nama_lengkap', 'like', "%{$s}%")
                    ->orWhere('nip', 'like', "%{$s}%"))
                ->orderBy('nama_lengkap')
                ->paginate(15, ['*'], 'pegawai_page')
                ->withQueryString(),
            'assignedPegawaiIds' => $role->pegawai()->pluck('pegawai.id'),
        ]);
    }

    public function update(UpdateRefRoleRequest $request, RefRole $role): RedirectResponse
    {
        $data = $request->safe()->only(['nama', 'keterangan']);
        $data['slug'] = Str::slug($data['nama']);

        $role->update($data);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions', []));
        }

        if ($request->has('pegawai_ids')) {
            $role->pegawai()->sync($request->input('pegawai_ids', []));
        }

        return redirect()
            ->route('referensi.roles.index')
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(RefRole $role): RedirectResponse
    {
        if ($role->is_system) {
            return redirect()
                ->route('referensi.roles.index')
                ->with('error', 'Role sistem tidak dapat dihapus.');
        }

        $this->authorize('delete', $role);

        if ($role->pegawai()->exists()) {
            return redirect()
                ->route('referensi.roles.index')
                ->with('error', 'Role masih memiliki pegawai yang di-assign. Pindahkan pegawai ke role lain terlebih dahulu.');
        }

        $role->delete();

        return redirect()
            ->route('referensi.roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }
}
