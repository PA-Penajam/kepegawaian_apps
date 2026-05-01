<?php

namespace App\Http\Controllers\Api\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cuti\SaldoResource;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Services\Cuti\SaldoLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Controller API untuk data saldo cuti pegawai.
 */
class SaldoController extends Controller
{
    public function __construct(
        private SaldoLedgerService $saldoService,
    ) {}

    /**
     * Menampilkan ringkasan saldo cuti per jenis per tahun aktif.
     */
    public function show(string $nip): AnonymousResourceCollection
    {
        $alokasiList = CutiAlokasiTahunan::where('pegawai_nip', $nip)
            ->orderBy('tahun_hak', 'desc')
            ->get();

        $saldoSummary = $alokasiList->map(fn (CutiAlokasiTahunan $a) => [
            'jenis_cuti_kode' => $a->jenis_cuti_kode,
            'tahun_hak' => $a->tahun_hak,
            'hak_awal' => $a->hak_awal,
            'saldo_tersedia' => $this->saldoService->saldoBucket($nip, $a->jenis_cuti_kode, $a->tahun_hak),
        ]);

        return SaldoResource::collection($saldoSummary);
    }

    /**
     * Menampilkan riwayat ledger saldo cuti dengan paginasi.
     */
    public function ledger(Request $request, string $nip): AnonymousResourceCollection
    {
        $query = CutiSaldoLedger::where('pegawai_nip', $nip)
            ->when($request->input('tahun_hak'), fn ($q, $tahun) => $q->where('tahun_hak', $tahun))
            ->latest('created_at');

        return JsonResource::collection($query->paginate(25));
    }
}
