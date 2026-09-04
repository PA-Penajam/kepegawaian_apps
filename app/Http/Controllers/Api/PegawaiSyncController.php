<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PegawaiApiResource;
use App\Models\Pegawai;
use App\Services\Sync\SyncPullRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller penerbit sinkronisasi data pegawai untuk aplikasi client
 * (mis. wfa-task melalui SyncPegawaiService::importAll).
 *
 * Endpoint:
 * - GET /api/v1/pegawai/sync - Ekspor penuh terpaginasi dengan meta lengkap
 *
 * Kontrak respons diselaraskan dengan kebutuhan client:
 * - data: array PegawaiApiResource (nip, nama, jabatan, email, unit_kerja, status_pegawai, ...)
 * - meta: current_page, last_page, per_page, total (wajib lengkap agar loop
 *   paginasi client berhenti tepat di halaman terakhir)
 * - synced_at: cursor ISO-8601 untuk pull delta berikutnya via ?since=
 *
 * Catatan perilaku:
 * - Urutan deterministik berdasarkan id (ULID) agar walk antar halaman
 *   tidak duplikat dan tidak ada yang terlewat.
 * - Pegawai yang di-soft-delete tidak ikut ekspor; penonaktifan di sisi
 *   client mengandalkan mekanisme batch not_found (lihat syncAll wfa-task).
 * - Filter ?since= membandingkan updated_at (inklusif, >=) sehingga client
 *   cukup upsert berdasarkan NIP bila ada duplikat di batas cursor.
 *
 * Security: auth:sanctum + verify.hmac middleware (sama seperti endpoint pegawai lain).
 */
class PegawaiSyncController extends Controller
{
    /**
     * Ekspor data pegawai untuk sinkronisasi penuh maupun delta.
     */
    public function index(Request $request, SyncPullRecorder $recorder): JsonResponse
    {
        $startedAt = microtime(true);

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:500',
            'since' => 'sometimes|nullable|date',
        ]);

        $perPage = $validated['per_page'] ?? 100;
        $page = $validated['page'] ?? 1;

        $result = Pegawai::with(['jabatan', 'unitKerja', 'pangkat'])
            ->when(
                $request->filled('since'),
                fn ($query) => $query->where('updated_at', '>=', $validated['since'])
            )
            ->orderBy('id')
            ->paginate($perPage);

        $recorder->record(
            $request,
            rows: $result->count(),
            page: $page,
            perPage: $perPage,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );

        return response()->json([
            'data' => PegawaiApiResource::collection($result),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
