<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StoreAplikasiRequest;
use App\Http\Requests\Iam\UpdateAplikasiRequest;
use App\Models\IamApplication;
use Illuminate\Http\RedirectResponse;
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

    public function edit(IamApplication $aplikasi): Response
    {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat diubah');

        return inertia('iam/aplikasi/edit', [
            'aplikasi' => $aplikasi->only(['id', 'nama', 'slug', 'url', 'deskripsi', 'is_active']),
        ]);
    }

    public function store(StoreAplikasiRequest $request): RedirectResponse
    {
        // Buat aplikasi dengan data validasi (api_key & api_secret_hash tidak fillable)
        $app = IamApplication::create($request->validated());

        // Generate & set credentials secara manual setelah create
        // (sama dengan approach di regenerateKey())
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();
        $app->api_key = $key;
        $app->api_secret_hash = $hash;
        $app->save();

        return redirect()
            ->route('iam.aplikasi.show', $app)
            ->with('api_secret_once', $secret);
    }

    public function update(UpdateAplikasiRequest $request, IamApplication $aplikasi): RedirectResponse
    {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat diubah');

        $aplikasi->update($request->validated());

        Cache::forget("iam_app:{$aplikasi->slug}");

        return back();
    }

    public function destroy(IamApplication $aplikasi): RedirectResponse
    {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat dihapus');

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

        return $prefix.str_repeat('*', $maskedLength).$suffix;
    }
}
