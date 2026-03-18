<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PegawaiApiResource;
use App\Models\Pegawai;
use Illuminate\Http\JsonResponse;

/**
 * Controller untuk API Pegawai yang dikonsumsi oleh attendance-qr-system.
 *
 * Endpoint:
 * - GET /api/v1/pegawai/{nip} - Single lookup
 * - GET /api/v1/pegawai - Batch lookup (nip[]) atau search (search + status)
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
        $pegawai = Pegawai::with(['jabatan', 'unitKerja'])
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
     * Dipanggil via index() di routes.
     */
    public function index(): JsonResponse
    {
        // Akan diimplementasikan di Task 4
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
