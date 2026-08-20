<?php

use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Exports\PegawaiTemplateExport;
use App\Imports\PegawaiImport;
use App\Models\Pegawai;
use Database\Seeders\RefJabatanSeeder;
use Database\Seeders\RefPangkatSeeder;
use Database\Seeders\RefUnitKerjaSeeder;
use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed([
        RefJabatanSeeder::class,
        RefUnitKerjaSeeder::class,
        RefPangkatSeeder::class,
    ]);
});

test('dapat membuat template excel pegawai via artisan command', function () {
    $tempFile = storage_path('framework/testing/test_template.xlsx');
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }

    $exitCode = Artisan::call('pegawai:make-template', ['path' => $tempFile]);

    expect($exitCode)->toBe(0)
        ->and(file_exists($tempFile))->toBeTrue()
        ->and(filesize($tempFile))->toBeGreaterThan(1000);

    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

test('dapat mengimpor data pegawai dari template excel', function () {
    $tempFile = storage_path('framework/testing/test_import.xlsx');
    $content = Excel::raw(new PegawaiTemplateExport, ExcelFormat::XLSX);
    file_put_contents($tempFile, $content);

    $import = new PegawaiImport;
    Excel::import($import, $tempFile);

    expect($import->errors)->toBeEmpty()
        ->and($import->importedCount + $import->updatedCount)->toBeGreaterThanOrEqual(2);

    $pegawai = Pegawai::withoutGlobalScopes()->where('nip', '199107132020121003')->first();
    expect($pegawai)->not->toBeNull()
        ->and($pegawai->nama_lengkap)->toBe('Ahmad Fauzi, S.Kom.')
        ->and($pegawai->tanggal_lahir->toDateString())->toBe('1991-07-13')
        ->and($pegawai->jenis_kelamin)->toBe(JenisKelamin::LakiLaki)
        ->and($pegawai->status_kepegawaian)->toBe(StatusKepegawaian::PNS)
        ->and($pegawai->status_pegawai)->toBe(StatusPegawai::Aktif);

    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});
