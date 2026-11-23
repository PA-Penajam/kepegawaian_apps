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
        $state = $this->scalarString($request, 'state');
        $pegawaiId = $this->scalarString($request, 'pegawai_id');
        $bulan = $this->scalarInt($request, 'periode_usul_bulan');
        $tahun = $this->scalarInt($request, 'periode_usul_tahun');

        $query = UsulanKenaikanPangkat::query()
            ->with(['pegawai', 'pangkatAsal', 'pangkatTujuan'])
            ->when($state !== null, fn ($query) => $query->where('state', $state))
            ->when($pegawaiId !== null, fn ($query) => $query->where('pegawai_id', $pegawaiId))
            ->when($bulan !== null, fn ($query) => $query->where('periode_usul_bulan', $bulan))
            ->when($tahun !== null, fn ($query) => $query->where('periode_usul_tahun', $tahun))
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
        $state = $this->scalarString($request, 'state');
        $bulan = $this->scalarInt($request, 'periode_usul_bulan');
        $tahun = $this->scalarInt($request, 'periode_usul_tahun');

        $query = UsulanKenaikanPangkat::query()
            ->when($state !== null, fn ($query) => $query->where('state', $state))
            ->when($bulan !== null, fn ($query) => $query->where('periode_usul_bulan', $bulan))
            ->when($tahun !== null, fn ($query) => $query->where('periode_usul_tahun', $tahun));

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
            'bulan' => $this->scalarInt($request, 'periode_usul_bulan'),
            'tahun' => $this->scalarInt($request, 'periode_usul_tahun'),
        ];
    }

    /**
     * Mengambil parameter string skalar dengan aman untuk mencegah error input array.
     */
    private function scalarString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Mengambil parameter integer skalar dengan aman untuk mencegah error input array.
     */
    private function scalarInt(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $raw = trim((string) $value);

        return $raw !== '' && is_numeric($raw) ? (int) $raw : null;
    }
}
