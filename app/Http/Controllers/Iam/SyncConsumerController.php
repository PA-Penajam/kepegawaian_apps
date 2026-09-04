<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StoreSyncConsumerRequest;
use App\Http\Requests\Iam\UpdateSyncConsumerRequest;
use App\Models\Pegawai;
use App\Models\PegawaiSyncPull;
use App\Models\SyncConsumer;
use App\Services\Iam\SyncConsumerCredentialService;
use App\Services\Sync\SyncConnectionTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Halaman Klien Sinkronisasi — pengelolaan konsumen API sync pegawai.
 *
 * Scope v1:
 * - Daftar konsumen + kesehatan pull terakhir (read)
 * - Uji koneksi per konsumen (simulasi signature + hit endpoint)
 * - CRUD konsumen + penerbitan/revoke Sanctum token
 * - Dokumentasi endpoint & contoh signature
 */
class SyncConsumerController extends Controller
{
    public function index(): Response
    {
        $consumers = SyncConsumer::query()
            ->withCount('pulls')
            ->orderBy('nama')
            ->get();

        $recentPulls = PegawaiSyncPull::query()
            ->with('consumer')
            ->latest('pulled_at')
            ->limit(20)
            ->get();

        return inertia('iam/sinkronisasi/index', [
            'konsumen' => $consumers,
            'recentPulls' => $recentPulls,
            'stats' => [
                'total_konsumen' => SyncConsumer::count(),
                'aktif' => SyncConsumer::active()->count(),
                'pull_24h' => PegawaiSyncPull::where('pulled_at', '>=', now()->subHours(24))->count(),
                'pegawai_total' => Pegawai::count(),
            ],
        ]);
    }

    public function store(
        StoreSyncConsumerRequest $request,
        SyncConsumerCredentialService $credentials,
    ): RedirectResponse {
        $consumer = SyncConsumer::create($request->validated());

        // Sekalian terbitkan token + HMAC secret per konsumen agar langsung
        // siap dipakai. Keduanya hanya ditampilkan SEKALI lewat flash
        // (seperti pola client-credentials sso-papenajam).
        $hmacSecret = $credentials->ensureHmacSecret($consumer);
        $token = $credentials->issueToken($consumer);

        return back()->with([
            'sync_token_once' => [
                'consumer_id' => $consumer->id,
                'consumer_slug' => $consumer->slug,
                'plaintext' => $token['plaintext'],
                'expires_at' => $token['expires_at'] ?? null,
                'hmac_secret' => $hmacSecret,
            ],
            'success' => "Konsumen {$consumer->nama} ditambahkan dan kredensial diterbitkan.",
        ]);
    }

    public function update(
        UpdateSyncConsumerRequest $request,
        SyncConsumer $konsumen,
    ): RedirectResponse {
        $konsumen->update($request->validated());

        return back()->with('success', "Konsumen {$konsumen->nama} diperbarui.");
    }

    public function destroy(SyncConsumer $konsumen): RedirectResponse
    {
        $konsumen->delete();

        return back()->with('success', "Konsumen {$konsumen->nama} dihapus.");
    }

    /**
     * Uji koneksi: simulasikan request bertanda tangan ke endpoint sync sendiri
     * menggunakan kredensial konsumen, lalu catat hasilnya pada konsumen.
     */
    public function testConnection(
        Request $request,
        SyncConsumer $konsumen,
    ): JsonResponse {
        try {
            $result = app(SyncConnectionTester::class)->test($konsumen);

            $konsumen->update([
                'last_connection_test_at' => now(),
                'last_connection_test_status' => $result['success'] ? 'success' : 'failed',
                'last_connection_test_message' => $result['message'],
            ]);

            $request->session()->flash('test_connection', $result);

            return response()->json($result);
        } catch (\Throwable $e) {
            $konsumen->update([
                'last_connection_test_at' => now(),
                'last_connection_test_status' => 'failed',
                'last_connection_test_message' => $e->getMessage(),
            ]);

            $request->session()->flash('test_connection', [
                'success' => false,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Terbitkan token baru (revoke token lama) untuk konsumen.
     */
    public function regenerateToken(
        SyncConsumer $konsumen,
        SyncConsumerCredentialService $credentials,
    ): RedirectResponse {
        $token = $credentials->regenerateToken($konsumen);

        return back()->with([
            'sync_token_once' => [
                'consumer_id' => $konsumen->id,
                'consumer_slug' => $konsumen->slug,
                'plaintext' => $token['plaintext'],
                'expires_at' => $token['expires_at'] ?? null,
                // String kosong = secret tidak diputar pada aksi ini.
                'hmac_secret' => '',
            ],
            'success' => "Token API untuk {$konsumen->nama} diperbarui. HMAC secret tidak berubah.",
        ]);
    }

    /**
     * Putar HMAC secret per konsumen (secret lama langsung tidak berlaku).
     * Token Sanctum tidak tersentuh.
     */
    public function regenerateSecret(
        SyncConsumer $konsumen,
        SyncConsumerCredentialService $credentials,
    ): RedirectResponse {
        $secret = $credentials->regenerateHmacSecret($konsumen);

        return back()->with([
            'sync_token_once' => [
                'consumer_id' => $konsumen->id,
                'consumer_slug' => $konsumen->slug,
                // String kosong = token tidak diputar pada aksi ini.
                'plaintext' => '',
                'expires_at' => null,
                'hmac_secret' => $secret,
            ],
            'success' => "HMAC secret untuk {$konsumen->nama} diperbarui. Token tidak berubah.",
        ]);
    }

    /**
     * Revoke token aktif konsumen tanpa menghapus konsumen.
     */
    public function revokeToken(
        SyncConsumer $konsumen,
        SyncConsumerCredentialService $credentials,
    ): RedirectResponse {
        $credentials->revokeToken($konsumen);

        return back()->with('success', "Token API untuk {$konsumen->nama} dicabut.");
    }
}
