<?php

namespace App\Http\Controllers\Api\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cuti\PengajuanResource;
use App\Models\Cuti\CutiPengajuan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller API untuk data pengajuan cuti.
 */
class PengajuanController extends Controller
{
    /**
     * Menampilkan daftar pengajuan cuti dengan filter dan paginasi.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CutiPengajuan::query()
            ->with(['pegawai', 'jenisCuti'])
            ->when($request->input('state'), fn ($q, $state) => $q->where('state', $state))
            ->when($request->input('pegawai_nip'), fn ($q, $nip) => $q->where('pegawai_nip', $nip))
            ->when($request->input('tahun'), fn ($q, $tahun) => $q->whereYear('tanggal_mulai', $tahun))
            ->latest('created_at');

        return PengajuanResource::collection($query->paginate(15));
    }

    /**
     * Menampilkan detail pengajuan cuti beserta relasi terkait.
     */
    public function show(string $id): PengajuanResource
    {
        $pengajuan = CutiPengajuan::with([
            'pegawai',
            'lampiran',
            'approvalSteps.aktor',
            'stateHistory',
            'jenisCuti',
        ])->findOrFail($id);

        return new PengajuanResource($pengajuan);
    }
}
