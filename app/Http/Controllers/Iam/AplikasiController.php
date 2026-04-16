<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class AplikasiController extends Controller
{
    public function index(): Response
    {
        $aplikasi = IamApplication::withCount('roles')
            ->latest()
            ->get()
            ->map(function ($app) {
                $app->api_key_display = $this->maskApiKey($app->api_key);
                unset($app->api_key);
                return $app;
            });

        return inertia('iam/aplikasi/index', compact('aplikasi'));
    }

    public function show(IamApplication $aplikasi): Response
    {
        $aplikasi->load(['roles.permissions', 'permissions']);

        // Mask api_key — tampilkan 4 karakter pertama dan 8 terakhir saja
        $aplikasiArray = $aplikasi->toArray();
        $aplikasiArray['api_key_display'] = $this->maskApiKey($aplikasi->api_key);
        unset($aplikasiArray['api_key']);

        return inertia('iam/aplikasi/show', [
            'aplikasi' => $aplikasiArray,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'slug' => 'required|string|unique:iam_applications,slug|alpha_dash',
            'url' => 'required|url',
            'deskripsi' => 'nullable|string',
        ]);

        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app = IamApplication::create([
            ...$data,
            'api_key' => $key,
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
            'nama' => 'required|string|max:100',
            'url' => 'required|url',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $aplikasi->update($data);

        Cache::forget("iam_app:{$aplikasi->slug}");

        return back();
    }

    public function destroy(IamApplication $aplikasi): RedirectResponse
    {
        if ($aplikasi->is_system) {
            abort(403, 'Aplikasi sistem tidak dapat dihapus');
        }

        Cache::forget("iam_app:{$aplikasi->slug}");
        $aplikasi->delete();

        return redirect()->route('iam.aplikasi.index');
    }

    /**
     * Regenerate api_key dan api_secret untuk aplikasi.
     * Field api_key dan api_secret_hash tidak mass-assignable (security),
     * jadi harus di-set secara manual.
     */
    public function regenerateKey(IamApplication $aplikasi): RedirectResponse
    {
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        // Set field sensitif secara manual karena tidak fillable
        $aplikasi->api_key = $key;
        $aplikasi->api_secret_hash = $hash;
        $aplikasi->save();

        return back()->with('api_secret_once', $secret);
    }

    /**
     * Mask API key untuk ditampilkan ke frontend.
     * Tampilkan 4 karakter pertama dan 8 terakhir, sisanya asterisk.
     */
    private function maskApiKey(string $apiKey): string
    {
        $length = strlen($apiKey);

        if ($length <= 12) {
            // Jika key terlalu pendek, return semua asterisk
            return str_repeat('*', $length);
        }

        $prefix = substr($apiKey, 0, 4);
        $suffix = substr($apiKey, -8);
        $maskedLength = $length - 12; // 4 prefix + 8 suffix

        return $prefix . str_repeat('*', $maskedLength) . $suffix;
    }
}
