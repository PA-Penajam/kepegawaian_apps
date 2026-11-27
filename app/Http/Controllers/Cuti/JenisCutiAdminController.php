<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\UpdatePenandatanganJenisCutiRequest;
use App\Models\Cuti\CutiJenisMaster;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class JenisCutiAdminController extends Controller
{
    /**
     * Menampilkan daftar jenis cuti dan pejabat penandatangan surat resminya.
     */
    public function index(): Response
    {
        $jenisList = CutiJenisMaster::query()
            ->orderBy('kode')
            ->get([
                'kode',
                'nama',
                'saldo_driven',
                'butuh_lampiran',
                'boleh_dicabut_setelah_disetujui',
                'penandatangan_surat',
                'aktif',
            ]);

        return Inertia::render('cuti/admin/jenis', [
            'jenisList' => $jenisList,
            'signerOptions' => [
                ['value' => 'ketua', 'label' => 'Ketua (KPA)'],
                ['value' => 'wakil_ketua', 'label' => 'Wakil Ketua (KPA)'],
                ['value' => 'panitera', 'label' => 'Panitera (PAN.PA)'],
                ['value' => 'sekretaris', 'label' => 'Sekretaris (SEK.PA)'],
            ],
        ]);
    }

    /**
     * Memperbarui pejabat penandatangan surat resmi untuk satu jenis cuti.
     */
    public function update(UpdatePenandatanganJenisCutiRequest $request, string $kode): RedirectResponse
    {
        $jenis = CutiJenisMaster::query()->findOrFail($kode);
        $jenis->update([
            'penandatangan_surat' => $request->validated('penandatangan_surat'),
        ]);

        return back()->with('success', "Penandatangan surat untuk {$jenis->nama} berhasil diperbarui.");
    }
}
