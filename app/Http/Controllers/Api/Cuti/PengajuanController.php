<?php

namespace App\Http\Controllers\Api\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cuti\PengajuanResource;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Controller API untuk data pengajuan cuti.
 */
class PengajuanController extends Controller
{
    /**
     * Menampilkan daftar pengajuan cuti dengan filter dan paginasi sesuai izin akses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user instanceof Pegawai, 403);

        $canViewAll = $user->hasPermission('cuti.pengajuan.view-all');
        $canViewTeam = $user->hasPermission('cuti.pengajuan.view-team');
        $canViewOwn = $user->hasPermission('cuti.pengajuan.view-own');

        if (! $canViewAll && ! $canViewTeam && ! $canViewOwn) {
            abort(403);
        }

        $rawState = $request->input('state');
        $state = is_string($rawState) && trim($rawState) !== '' ? trim($rawState) : null;
        $rawNip = $request->input('pegawai_nip');
        $pegawaiNip = is_string($rawNip) && trim($rawNip) !== '' ? trim($rawNip) : null;
        $rawTahun = $request->input('tahun');
        $tahun = is_scalar($rawTahun) && is_numeric((string) $rawTahun) ? (int) $rawTahun : null;

        $query = CutiPengajuan::query()
            ->with(['pegawai', 'jenisCuti'])
            ->when(! $canViewAll, function ($q) use ($user, $canViewOwn, $canViewTeam): void {
                $q->where(function ($scope) use ($user, $canViewOwn, $canViewTeam): void {
                    if ($canViewOwn) {
                        $scope->orWhere('pegawai_nip', $user->nip);
                    }

                    if ($canViewTeam) {
                        $scope->orWhere('atasan_langsung_current_nip', $user->nip)
                            ->orWhere('pejabat_berwenang_current_nip', $user->nip);
                    }
                });
            })
            ->when($state !== null, fn ($q) => $q->where('state', $state))
            ->when($pegawaiNip !== null, fn ($q) => $q->where('pegawai_nip', $pegawaiNip))
            ->when($tahun !== null, fn ($q) => $q->whereYear('tanggal_mulai', $tahun))
            ->latest('created_at');

        return PengajuanResource::collection($query->paginate(15));
    }

    /**
     * Menampilkan detail pengajuan cuti beserta relasi terkait.
     */
    public function show(Request $request, string $id): PengajuanResource
    {
        $user = $request->user();
        abort_unless($user instanceof Pegawai, 403);

        $pengajuan = CutiPengajuan::with([
            'pegawai',
            'lampiran',
            'approvalSteps.aktor',
            'stateHistory',
            'jenisCuti',
        ])->findOrFail($id);

        abort_unless(Gate::any(['viewOwn', 'viewTeam', 'viewAll'], $pengajuan), 403);

        return new PengajuanResource($pengajuan);
    }
}
