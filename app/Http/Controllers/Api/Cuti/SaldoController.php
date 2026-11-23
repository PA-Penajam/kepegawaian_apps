<?php

namespace App\Http\Controllers\Api\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cuti\SaldoResource;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\Pegawai;
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
    public function show(Request $request, string $nip): AnonymousResourceCollection
    {
        $this->authorizeSaldoAccess($request, $nip);

        $alokasiList = CutiAlokasiTahunan::where('pegawai_nip', $nip)
            ->orderBy('tahun_hak', 'desc')
            ->get();

        // Hitung saldo seluruh bucket dalam satu query agregat (anti N+1)
        $buckets = $alokasiList->map(fn (CutiAlokasiTahunan $a): array => [
            'nip' => $nip,
            'jenis' => $a->jenis_cuti_kode,
            'tahun' => $a->tahun_hak,
        ])->all();
        $saldos = $this->saldoService->saldoBuckets($buckets);

        $saldoSummary = $alokasiList->map(fn (CutiAlokasiTahunan $a): array => [
            'jenis_cuti_kode' => $a->jenis_cuti_kode,
            'tahun_hak' => $a->tahun_hak,
            'hak_awal' => $a->hak_awal,
            'saldo_tersedia' => $saldos["{$nip}|{$a->jenis_cuti_kode}|{$a->tahun_hak}"] ?? 0,
        ]);

        return SaldoResource::collection($saldoSummary);
    }

    /**
     * Menampilkan riwayat ledger saldo cuti dengan paginasi.
     */
    public function ledger(Request $request, string $nip): AnonymousResourceCollection
    {
        $this->authorizeSaldoAccess($request, $nip);

        $rawTahunHak = $request->input('tahun_hak');
        $tahunHak = is_scalar($rawTahunHak) && is_numeric((string) $rawTahunHak) ? (int) $rawTahunHak : null;

        $query = CutiSaldoLedger::where('pegawai_nip', $nip)
            ->when($tahunHak !== null, fn ($q) => $q->where('tahun_hak', $tahunHak))
            ->latest('created_at');

        return JsonResource::collection($query->paginate(25));
    }

    /**
     * Memastikan pemanggil adalah Pegawai dan memiliki izin akses saldo yang sesuai.
     */
    private function authorizeSaldoAccess(Request $request, string $nip): void
    {
        $user = $request->user();
        abort_unless($user instanceof Pegawai, 403);

        if ($user->nip === $nip) {
            abort_unless($user->hasAnyPermission('cuti.saldo.view-own', 'cuti.saldo.view-all'), 403);

            return;
        }

        abort_unless($user->hasPermission('cuti.saldo.view-all'), 403);
    }
}
