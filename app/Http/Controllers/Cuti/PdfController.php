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

        Gate::authorize('viewOwn', $pengajuan);

        return Pdf::view('pdf.cuti.pengajuan', ['p' => $pengajuan])
            ->name("cuti-{$pengajuan->nomor_pengajuan}.pdf")
            ->download();
    }
}
