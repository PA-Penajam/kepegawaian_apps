<?php

namespace App\Actions\UsulanKenaikanPangkat;

use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Services\NomorSurat\NomorSuratService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class GenerateSuratPengantarPdf
{
    public function __construct(private readonly NomorSuratService $nomorSuratService) {}

    public function handle(object $usulan, object $pejabatPenandatangan): object
    {
        return DB::transaction(function () use ($usulan, $pejabatPenandatangan): object {
            $tanggalSurat = CarbonImmutable::now();
            $reservation = $this->nomorSuratService->reserve('KP.01.1', $tanggalSurat->month, $tanggalSurat->year);
            $path = "usulan-kp/surat-pengantar/{$usulan->id}.pdf";
            $absolutePath = Storage::disk('local')->path($path);

            File::ensureDirectoryExists(dirname($absolutePath));

            Pdf::view('pdf.kenaikan-pangkat.surat-pengantar', [
                'usulan' => $usulan->loadMissing([
                    'pegawai.pangkat',
                    'pegawai.jabatan',
                    'pegawai.unitKerja',
                    'pangkatAsal',
                    'pangkatTujuan',
                    'checklistSubmission.items.templateItem',
                ]),
                'pegawai' => $usulan->pegawai,
                'nomorSurat' => $reservation->nomor_lengkap,
                'tanggalSurat' => $tanggalSurat,
                'pejabatPenandatangan' => $pejabatPenandatangan,
                'namaSatker' => config('app.name', 'Sistem Kepegawaian'),
                'alamatSatker' => config('sikep.alamat_satker', 'Alamat satuan kerja'),
            ])
                ->withBrowsershot(function (Browsershot $browsershot): void {
                    $browsershot->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']);
                })
                ->save($absolutePath);

            $this->nomorSuratService->confirm((string) $reservation->id);

            return UsulanKpPdf::query()->create([
                'usulan_kenaikan_pangkat_id' => $usulan->id,
                'jenis_pdf' => 'surat_pengantar',
                'nomor_surat' => $reservation->nomor_lengkap,
                'file_path' => $path,
                'generated_by' => $pejabatPenandatangan->id,
                'generated_at' => $tanggalSurat,
            ]);
        });
    }
}
