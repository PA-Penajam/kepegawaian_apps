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
use Throwable;

class GenerateSuratPengantarPdf
{
    public function __construct(private readonly NomorSuratService $nomorSuratService) {}

    public function handle(object $usulan, object $pejabatPenandatangan): object
    {
        return $this->handleWithinFinalTransaction($usulan, $pejabatPenandatangan);
    }

    /**
     * Merender draf PDF surat pengantar KP memakai nomor resmi yang sudah terbit dari SiSukma.
     */
    public function renderForExistingNumber(object $usulan, object $pejabatPenandatangan, string $nomorSurat): UsulanKpPdf
    {
        $tanggalSurat = CarbonImmutable::now();
        $path = "usulan-kp/surat-pengantar/{$usulan->id}.pdf";
        $absolutePath = Storage::disk('local')->path($path);

        File::ensureDirectoryExists(dirname($absolutePath));

        $usulan->loadMissing([
            'pegawai.pangkat',
            'pegawai.jabatan',
            'pegawai.unitKerja',
            'pangkatAsal',
            'pangkatTujuan',
            'checklistSubmission.items.item',
        ]);

        if (method_exists($pejabatPenandatangan, 'loadMissing')) {
            $pejabatPenandatangan->loadMissing('jabatan');
        }

        Pdf::view('pdf.kenaikan-pangkat.surat-pengantar', [
            'usulan' => $usulan,
            'pegawai' => $usulan->pegawai,
            'nomorSurat' => $nomorSurat,
            'tanggalSurat' => $tanggalSurat,
            'pejabatPenandatangan' => $pejabatPenandatangan,
            'namaSatker' => config('sikep.nama_satker', config('app.name', 'Sistem Kepegawaian')),
            'alamatSatker' => config('sikep.alamat_satker', 'Alamat satuan kerja'),
        ])
            ->withBrowsershot(function (Browsershot $browsershot): void {
                $browsershot->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']);
            })
            ->save($absolutePath);

        return UsulanKpPdf::query()->updateOrCreate(
            [
                'usulan_kenaikan_pangkat_id' => $usulan->id,
                'jenis_pdf' => 'surat_pengantar',
            ],
            [
                'nomor_surat' => $nomorSurat,
                'file_path' => $path,
                'generated_by' => $pejabatPenandatangan->id,
                'generated_at' => $tanggalSurat,
            ],
        );
    }

    /**
     * Merender PDF di luar transaksi DB, lalu mengonfirmasi reservasi nomor surat di transaksi akhir.
     *
     * @param  (callable(): void)|null  $beforeConfirm
     * @param  (callable(UsulanKpPdf): void)|null  $afterPdfCreated
     */
    public function handleWithinFinalTransaction(
        object $usulan,
        object $pejabatPenandatangan,
        ?callable $beforeConfirm = null,
        ?callable $afterPdfCreated = null,
    ): object {
        $tanggalSurat = CarbonImmutable::now();
        $reservation = $this->nomorSuratService->reserve('KP.01.1', $tanggalSurat->month, $tanggalSurat->year);
        $path = "usulan-kp/surat-pengantar/{$usulan->id}.pdf";
        $absolutePath = Storage::disk('local')->path($path);

        try {
            File::ensureDirectoryExists(dirname($absolutePath));

            $usulan->loadMissing([
                'pegawai.pangkat',
                'pegawai.jabatan',
                'pegawai.unitKerja',
                'pangkatAsal',
                'pangkatTujuan',
                'checklistSubmission.items.item',
            ]);

            if (method_exists($pejabatPenandatangan, 'loadMissing')) {
                $pejabatPenandatangan->loadMissing('jabatan');
            }

            Pdf::view('pdf.kenaikan-pangkat.surat-pengantar', [
                'usulan' => $usulan,
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

            return DB::transaction(function () use ($usulan, $pejabatPenandatangan, $reservation, $path, $tanggalSurat, $beforeConfirm, $afterPdfCreated): object {
                if ($beforeConfirm !== null) {
                    $beforeConfirm();
                }

                $this->nomorSuratService->confirm((string) $reservation->id);

                $pdf = UsulanKpPdf::query()->create([
                    'usulan_kenaikan_pangkat_id' => $usulan->id,
                    'jenis_pdf' => 'surat_pengantar',
                    'nomor_surat' => $reservation->nomor_lengkap,
                    'file_path' => $path,
                    'generated_by' => $pejabatPenandatangan->id,
                    'generated_at' => $tanggalSurat,
                ]);

                if ($afterPdfCreated !== null) {
                    $afterPdfCreated($pdf);
                }

                return $pdf;
            });
        } catch (Throwable $e) {
            try {
                $this->nomorSuratService->release((string) $reservation->id);
            } catch (\DomainException) {
                // Reservasi sudah dikonfirmasi atau dilepas sebelumnya.
            }

            $stillReferenced = UsulanKpPdf::query()
                ->where('usulan_kenaikan_pangkat_id', $usulan->id)
                ->where('file_path', $path)
                ->exists();

            if (! $stillReferenced && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }
}
