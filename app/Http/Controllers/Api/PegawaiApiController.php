<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexPegawaiRequest;
use App\Http\Resources\PegawaiApiResource;
use App\Models\Pegawai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller untuk API Pegawai yang dikonsumsi oleh attendance-qr-system.
 *
 * Endpoint:
 * - GET /api/v1/pegawai/{nip} - Single lookup by NIP (18 digit)
 * - GET /api/v1/pegawai - Batch lookup (nip[]) atau search (search + status)
 *
 * Security: auth:sanctum + verify.hmac middleware
 */
class PegawaiApiController extends Controller
{
    /**
     * Lookup single pegawai berdasarkan NIP.
     *
     * @param  string  $nip  NIP 18 digit
     */
    public function show(string $nip): JsonResponse
    {
        $pegawai = Pegawai::with(['jabatan', 'unitKerja', 'pangkat'])
            ->where('nip', $nip)
            ->first();

        if (! $pegawai) {
            return response()->json([
                'message' => 'Pegawai tidak ditemukan',
                'errors' => ['nip' => ['NIP tidak terdaftar']],
            ], 404);
        }

        return response()->json(['data' => new PegawaiApiResource($pegawai)]);
    }

    /**
     * Batch lookup atau search pegawai.
     *
     * Mode:
     * 1. Batch: nip[] parameter (max 50 NIP)
     * 2. Search: search + status parameter (status default ke 'aktif')
     *
     * Priority: nip[] diprioritaskan jika search juga dikirim.
     */
    public function index(IndexPegawaiRequest $request): JsonResponse
    {
        // Batch lookup by NIPs diprioritaskan jika nip[] ada
        if ($request->has('nip')) {
            return $this->batchByNips($request);
        }

        // Search mode
        return $this->search($request);
    }

    /**
     * Batch lookup pegawai berdasarkan array NIPs.
     */
    private function batchByNips(IndexPegawaiRequest $request): JsonResponse
    {
        $nips = $request->validated('nip');

        $pegawaiList = Pegawai::with(['jabatan', 'unitKerja', 'pangkat'])
            ->whereIn('nip', $nips)
            ->get();

        $foundNips = $pegawaiList->pluck('nip')->toArray();
        $notFoundNips = array_values(array_diff($nips, $foundNips));

        return response()->json([
            'data' => PegawaiApiResource::collection($pegawaiList),
            'not_found' => $notFoundNips,
        ]);
    }

    /**
     * Search pegawai berdasarkan nama dengan filter status.
     */
    private function search(Request $request): JsonResponse
    {
        $result = Pegawai::with(['jabatan', 'unitKerja', 'pangkat'])
            ->when($request->input('status') === 'aktif', fn ($q) => $q->aktif())
            ->when(
                $request->input('search'),
                fn ($q, $search) => $q->where('nama_lengkap', 'like', "%{$search}%")
            )
            ->paginate(20);

        return response()->json([
            'data' => PegawaiApiResource::collection($result),
            'meta' => [
                'current_page' => $result->currentPage(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
            ],
        ]);
    }
}
