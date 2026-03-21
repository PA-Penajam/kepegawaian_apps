<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AplikasiController extends Controller
{
    public function index(): Response
    {
        $aplikasi = IamApplication::withCount('roles')->latest()->get();
        return inertia('iam/aplikasi/index', compact('aplikasi'));
    }

    public function show(IamApplication $aplikasi): Response
    {
        $aplikasi->load(['roles.permissions', 'permissions']);
        return inertia('iam/aplikasi/show', compact('aplikasi'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama'      => 'required|string|max:100',
            'slug'      => 'required|string|unique:iam_applications,slug|alpha_dash',
            'url'       => 'required|url',
            'deskripsi' => 'nullable|string',
        ]);

        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app = IamApplication::create([
            ...$data,
            'api_key'         => $key,
            'api_secret_hash' => $hash,
        ]);

        return redirect()
            ->route('iam.aplikasi.show', $app)
            ->with('api_secret_once', $secret);
    }

    public function update(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        if ($aplikasi->is_system) {
            abort(403, 'Aplikasi sistem tidak dapat diubah');
        }

        $data = $request->validate([
            'nama'      => 'required|string|max:100',
            'url'       => 'required|url',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $aplikasi->update($data);
        return back();
    }

    public function destroy(IamApplication $aplikasi): RedirectResponse
    {
        if ($aplikasi->is_system) {
            abort(403, 'Aplikasi sistem tidak dapat dihapus');
        }

        $aplikasi->delete();
        return redirect()->route('iam.aplikasi.index');
    }

    public function regenerateKey(IamApplication $aplikasi): RedirectResponse
    {
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $aplikasi->update(['api_key' => $key, 'api_secret_hash' => $hash]);

        return back()->with('api_secret_once', $secret);
    }
}
