<?php

namespace App\Http\Controllers\Api\UsulanKenaikanPangkat;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsulanKenaikanPangkat\UsulanKenaikanPangkatResource;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller API untuk usulan kenaikan pangkat.
 */
class UsulanKenaikanPangkatApiController extends Controller
{
    /**
     * Menampilkan daftar usulan kenaikan pangkat dengan filter dan paginasi.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = UsulanKenaikanPangkat::query()
            ->with(['pegawai', 'pangkatAsal', 'pangkatTujuan'])
            ->when($request->input('state'), fn ($query, string $state) => $query->where('state', $state))
            ->when($request->input('pegawai_id'), fn ($query, string $pegawaiId) => $query->where('pegawai_id', $pegawaiId))
            ->when($request->input('periode_usul_bulan'), fn ($query, string $bulan) => $query->where('periode_usul_bulan', (int) $bulan))
            ->when($request->input('periode_usul_tahun'), fn ($query, string $tahun) => $query->where('periode_usul_tahun', (int) $tahun))
            ->latest('created_at');

        return UsulanKenaikanPangkatResource::collection($query->paginate(15));
    }

    /**
     * Menampilkan detail usulan kenaikan pangkat.
     */
    public function show(UsulanKenaikanPangkat $usulan): UsulanKenaikanPangkatResource
    {
        return new UsulanKenaikanPangkatResource($usulan->load([
            'pegawai',
            'pangkatAsal',
            'pangkatTujuan',
            'approvalSteps',
            'stateHistory',
            'approverHistory',
            'lampiran',
            'pdfs',
            'checklistSubmission.items',
        ]));
    }

    /**
     * Menampilkan statistik usulan kenaikan pangkat per periode dan state.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = UsulanKenaikanPangkat::query()
            ->when($request->input('state'), fn ($query, string $state) => $query->where('state', $state))
            ->when($request->input('periode_usul_bulan'), fn ($query, string $bulan) => $query->where('periode_usul_bulan', (int) $bulan))
            ->when($request->input('periode_usul_tahun'), fn ($query, string $tahun) => $query->where('periode_usul_tahun', (int) $tahun));

        $perState = (clone $query)
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state')
            ->map(fn (int $count): int => $count);

        return response()->json([
            'total' => (clone $query)->count(),
            'per_state' => $perState,
            'periode' => $this->periodeFromRequest($request),
        ]);
    }

    /**
     * @return array{bulan: int|null, tahun: int|null}
     */
    private function periodeFromRequest(Request $request): array
    {
        return [
            'bulan' => $request->filled('periode_usul_bulan') ? (int) $request->input('periode_usul_bulan') : null,
            'tahun' => $request->filled('periode_usul_tahun') ? (int) $request->input('periode_usul_tahun') : null,
        ];
    }
}
