<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
use App\Models\Cuti\CutiPengajuan;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class PdfController extends Controller
{
    /**
     * Unduh PDF formulir pengajuan cuti.
     */
    public function show(string $id): PdfBuilder
    {
        $pengajuan = CutiPengajuan::with([
            'pegawai.jabatan',
            'pegawai.unitKerja',
            'jenisCuti',
            'saldoLedger',
            'atasanLangsungCurrent',
            'pejabatBerwenangCurrent',
        ])->findOrFail($id);

        abort_unless(Gate::any(['viewOwn', 'viewTeam', 'viewAll'], $pengajuan), 403);

        $safeNomor = str_replace(['/', '\\'], '-', (string) ($pengajuan->nomor_pengajuan ?? $pengajuan->id));

        return Pdf::view('pdf.cuti.pengajuan', ['p' => $pengajuan])
            ->name("cuti-{$safeNomor}.pdf")
            ->download();
    }
}
