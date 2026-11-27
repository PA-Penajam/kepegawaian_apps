<?php

namespace App\Actions\Cuti;

use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanPdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Merender PDF draf bernomor Surat Izin Cuti ke penyimpanan lokal dan mencatat metadata checksum.
 */
class GenerateSuratIzinCutiPdf
{
    /**
     * Merender draf PDF Surat Izin Cuti menggunakan nomor resmi dari SiSukma.
     */
    public function handle(CutiPengajuan $pengajuan, string $nomorSurat, string $penandatangan = 'ketua'): CutiPengajuanPdf
    {
        $pengajuan->loadMissing([
            'pegawai.pangkat',
            'pegawai.jabatan',
            'pegawai.unitKerja',
            'jenisCuti',
            'pejabatBerwenangCurrent.jabatan',
        ]);

        $tanggalSurat = CarbonImmutable::now();
        $relativePath = "cuti/surat-izin/{$pengajuan->id}.pdf";
        $absolutePath = Storage::disk('local')->path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));

        $jabatanPenandatangan = match ($penandatangan) {
            'wakil_ketua' => 'Wakil Ketua Pengadilan Agama Penajam',
            'panitera' => 'Panitera Pengadilan Agama Penajam',
            'sekretaris' => 'Sekretaris Pengadilan Agama Penajam',
            default => 'Ketua Pengadilan Agama Penajam',
        };

        Pdf::view('pdf.cuti.surat-izin', [
            'pengajuan' => $pengajuan,
            'nomorSurat' => $nomorSurat,
            'tanggalSurat' => $tanggalSurat,
            'jabatanPenandatangan' => $jabatanPenandatangan,
            'pejabatPenandatangan' => $pengajuan->pejabatBerwenangCurrent,
            'namaSatker' => config('sikep.nama_satker', 'Pengadilan Agama Penajam'),
            'alamatSatker' => config('sikep.alamat_satker', 'Jalan Provinsi KM 9, Penajam'),
        ])
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $browsershot->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']);
            })
            ->save($absolutePath);

        $bytes = File::get($absolutePath);
        $checksum = hash('sha256', $bytes);
        $sizeBytes = strlen($bytes);

        return CutiPengajuanPdf::query()->updateOrCreate(
            [
                'pengajuan_id' => $pengajuan->id,
                'path_file' => $relativePath,
            ],
            [
                'checksum_sha256' => $checksum,
                'size_bytes' => $sizeBytes,
                'generated_at' => $tanggalSurat,
            ],
        );
    }
}
