<?php

use App\Actions\UsulanKenaikanPangkat\GenerateSuratPengantarPdf;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    Pdf::swap(new class extends FakePdfBuilder
    {
        public function save(string $path): static
        {
            parent::save($path);

            // Create a dummy file so Storage assertions pass
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, '%PDF-1.4 fake');

            return $this;
        }
    });
});

function makeKpUsulan(): UsulanKenaikanPangkat
{
    $pangkatAsal = RefPangkat::factory()->create(['nama' => 'Penata Muda', 'golongan' => 'III', 'ruang' => 'a']);
    $pangkatTujuan = RefPangkat::factory()->create(['nama' => 'Penata Muda Tk. I', 'golongan' => 'III', 'ruang' => 'b']);
    $pegawai = Pegawai::factory()->create(['ref_pangkat_id' => $pangkatAsal->id]);

    return UsulanKenaikanPangkat::query()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_asal_id' => $pangkatAsal->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
        'tmt_pangkat_asal' => '2022-04-01',
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
        'created_by' => null,
    ]);
}

it('generate surat pengantar PDF dan menyimpan record metadata', function () {
    $usulan = makeKpUsulan();
    $pejabat = Pegawai::factory()->create([
        'nama_lengkap' => 'Drs. Pejabat Penandatangan',
        'nip' => '197001011990031001',
    ]);

    $pdf = app(GenerateSuratPengantarPdf::class)->handle($usulan, $pejabat);

    expect(Storage::disk('local')->exists("usulan-kp/surat-pengantar/{$usulan->id}.pdf"))->toBeTrue();

    expect($pdf->jenis_pdf)->toBe('surat_pengantar')
        ->and($pdf->usulan_kenaikan_pangkat_id)->toBe($usulan->id)
        ->and($pdf->nomor_surat)->toContain('/KP.01.1/')
        ->and($pdf->file_path)->toBe("usulan-kp/surat-pengantar/{$usulan->id}.pdf");

    expect(DB::table('usulan_kp_pdf')
        ->where('usulan_kenaikan_pangkat_id', $usulan->id)
        ->where('jenis_pdf', 'surat_pengantar')
        ->where('file_path', "usulan-kp/surat-pengantar/{$usulan->id}.pdf")
        ->exists())->toBeTrue()
        ->and(DB::table('nomor_surat_reservations')
            ->where('nomor_lengkap', $pdf->nomor_surat)
            ->where('status', 'confirmed')
            ->exists())->toBeTrue();
});

it('menghasilkan nomor surat berbeda untuk dua usulan berbeda', function () {
    $pejabat = Pegawai::factory()->create();
    $pdfPertama = app(GenerateSuratPengantarPdf::class)->handle(makeKpUsulan(), $pejabat);
    $pdfKedua = app(GenerateSuratPengantarPdf::class)->handle(makeKpUsulan(), $pejabat);

    expect($pdfPertama->nomor_surat)->not->toBe($pdfKedua->nomor_surat);

    expect(DB::table('nomor_surat_sequences')
        ->where('klasifikasi', 'KP.01.1')
        ->where('tahun', now()->year)
        ->where('next_number', 3)
        ->exists())->toBeTrue();
});
