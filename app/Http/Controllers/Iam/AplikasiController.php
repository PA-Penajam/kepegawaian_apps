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

    public function show(IamApplication $aplikasi, \App\Services\Iam\IamPermissionAuditor $auditor): Response
    {
        $aplikasi->load(['roles.permissions', 'permissions']);

        // Mask api_key — tampilkan 4 karakter pertama dan 8 terakhir saja
        $aplikasiArray = $aplikasi->toArray();
        $aplikasiArray['api_key_display'] = $this->maskApiKey($aplikasi->api_key);
        unset($aplikasiArray['api_key']);

        $nonCanonicalCount = $auditor->findNonCanonical()
            ->filter(fn ($p) => $p['app'] === $aplikasi->slug)
            ->count();

        return inertia('iam/aplikasi/show', [
            'aplikasi' => $aplikasiArray,
            'permission_audit' => [
                'non_canonical_count' => $nonCanonicalCount,
            ],
        ]);
    }

    public function edit(IamApplication $aplikasi): Response
    {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat diubah');

        return inertia('iam/aplikasi/edit', [
            'aplikasi' => $aplikasi->only(['id', 'nama', 'slug', 'url', 'deskripsi', 'is_active']),
        ]);
    }

    public function store(
        StoreAplikasiRequest $request,
        \App\Services\Iam\IamSecretService $secretService,
    ): RedirectResponse {
        $app = IamApplication::create($request->validated());
        $plaintext = $secretService->generateAndStore($app, $request);

        return redirect()
            ->route('iam.aplikasi.show', $app)
            ->with('api_secret_once', $plaintext);
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
     * jadi harus di-set secara manual via service.
     */
    public function regenerateKey(
        \Illuminate\Http\Request $request,
        IamApplication $aplikasi,
        \App\Services\Iam\IamSecretService $secretService,
    ): RedirectResponse {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat diregenerasi');

        $plaintext = $secretService->regenerate($aplikasi, $request);

        return back()->with('api_secret_once', $plaintext);
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
