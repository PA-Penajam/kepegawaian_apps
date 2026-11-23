<?php

use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Exports\PegawaiTemplateExport;
use App\Imports\PegawaiImport;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Models\RiwayatJabatan;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Database\Seeders\RefJabatanSeeder;
use Database\Seeders\RefPangkatSeeder;
use Database\Seeders\RefUnitKerjaSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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

    $pegawai = Pegawai::withoutGlobalScopes()->where('nip', '199001012020121001')->first();
    expect($pegawai)->not->toBeNull()
        ->and($pegawai->nama_lengkap)->toBe('Ahmad Fauzi, S.Kom.')
        ->and($pegawai->tanggal_lahir->toDateString())->toBe('1990-01-01')
        ->and($pegawai->jenis_kelamin)->toBe(JenisKelamin::LakiLaki)
        ->and($pegawai->status_kepegawaian)->toBe(StatusKepegawaian::PNS)
        ->and($pegawai->status_pegawai)->toBe(StatusPegawai::Aktif)
        ->and($pegawai->password)->not->toBeNull()
        ->and(Hash::isHashed((string) $pegawai->password))->toBeTrue()
        ->and(Hash::check('Password123!', (string) $pegawai->password))->toBeFalse();

    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
});

test('re-import me-restore pegawai soft-deleted, menjaga tmt/tanggal_sk riwayat aktif, dan mem-parse !Ym akurat pada tanggal 31', function () {
    Carbon::setTestNow('2026-03-31');

    $nip = '199304152020021011';
    $pegawai = Pegawai::factory()->create([
        'nip' => $nip,
        'nama_lengkap' => 'ASN Lama',
    ]);
    $pangkatLama = RefPangkat::query()->firstOrFail();
    $jabatanLama = RefJabatan::query()->firstOrFail();
    $unitLama = RefUnitKerja::query()->firstOrFail();

    $riwayatPangkat = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pangkatLama->id,
        'no_sk' => 'SK-ASLI-PANGKAT',
        'tanggal_sk' => '2021-06-10',
        'tmt' => '2021-07-01',
        'is_aktif' => true,
    ]);
    $riwayatJabatan = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_jabatan_id' => $jabatanLama->id,
        'ref_unit_kerja_id' => $unitLama->id,
        'no_sk' => 'SK-ASLI-JABATAN',
        'tanggal_sk' => '2022-08-15',
        'tmt' => '2022-09-01',
        'is_aktif' => true,
    ]);

    $pegawai->delete();
    expect(Pegawai::query()->find($pegawai->id))->toBeNull();

    $csvPath = storage_path('framework/testing/test_reimport.csv');
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, ['NIP (18 Digit)', 'Nama Lengkap (beserta gelar)', 'Jabatan', 'Unit Kerja', 'Golongan / Pangkat', 'Email (Opsional)', 'No WhatsApp (Opsional)']);
    fputcsv($handle, [$nip, 'ASN Pulih', $jabatanLama->nama, $unitLama->nama, $pangkatLama->kode, 'pulih@example.test', '081234567890']);
    fclose($handle);

    $this->artisan('pegawai:import', ['file' => $csvPath])->assertSuccessful();

    $restored = Pegawai::query()->where('nip', $nip)->firstOrFail();
    expect($restored->trashed())->toBeFalse()
        ->and($restored->nama_lengkap)->toBe('ASN Pulih')
        ->and($restored->tanggal_masuk?->toDateString())->toBe('2020-02-01')
        ->and($riwayatPangkat->refresh()->no_sk)->toBe('SK-ASLI-PANGKAT')
        ->and($riwayatPangkat->tmt?->toDateString())->toBe('2021-07-01')
        ->and($riwayatJabatan->refresh()->no_sk)->toBe('SK-ASLI-JABATAN')
        ->and($riwayatJabatan->tmt?->toDateString())->toBe('2022-09-01');

    if (file_exists($csvPath)) {
        unlink($csvPath);
    }
    Carbon::setTestNow();
});

test('pegawai:import menolak ekstensi selain xlsx dan csv', function () {
    $xlsPath = storage_path('framework/testing/invalid_format.xls');
    file_put_contents($xlsPath, 'dummy xls content');

    $this->artisan('pegawai:import', ['file' => $xlsPath])
        ->assertFailed();

    if (file_exists($xlsPath)) {
        unlink($xlsPath);
    }
});

test('pegawai:import menolak tanggal kalender tidak valid pada NIP tanpa rollover diam-diam', function () {
    $csvPath = storage_path('framework/testing/test_invalid_nip_dates.csv');
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, ['NIP (18 Digit)', 'Nama Lengkap (beserta gelar)', 'Jabatan', 'Unit Kerja', 'Golongan / Pangkat', 'Email (Opsional)', 'No WhatsApp (Opsional)']);
    // 19900230 = 30 Februari (tidak valid), 202013 = bulan ke-13 (tidak valid)
    fputcsv($handle, ['199002302020131001', 'ASN Tanggal Invalid', '', '', '', '', '']);
    fclose($handle);

    $import = new PegawaiImport;
    Excel::import($import, $csvPath);

    expect($import->errors)->toHaveCount(1)
        ->and($import->errors[0]['nip'])->toBe('199002302020131001')
        ->and(Pegawai::withoutGlobalScopes()->where('nip', '199002302020131001')->exists())->toBeFalse();

    if (file_exists($csvPath)) {
        unlink($csvPath);
    }
});

test('re-import NIP existing dengan kolom unit_kerja, jabatan, dan golongan kosong menjaga relasi referensi dan riwayat aktif', function () {
    $nip = '199105152019031002';
    $pangkat = RefPangkat::query()->firstOrFail();
    $jabatan = RefJabatan::query()->firstOrFail();
    $unitKerja = RefUnitKerja::query()->firstOrFail();

    $pegawai = Pegawai::factory()->create([
        'nip' => $nip,
        'nama_lengkap' => 'ASN Awal',
        'ref_jabatan_id' => $jabatan->id,
        'ref_unit_kerja_id' => $unitKerja->id,
        'ref_pangkat_id' => $pangkat->id,
    ]);

    $riwayatJabatan = RiwayatJabatan::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_jabatan_id' => $jabatan->id,
        'ref_unit_kerja_id' => $unitKerja->id,
        'is_aktif' => true,
    ]);

    $riwayatPangkat = RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pangkat->id,
        'is_aktif' => true,
    ]);

    $csvPath = storage_path('framework/testing/test_reimport_empty_refs.csv');
    $handle = fopen($csvPath, 'w');
    fputcsv($handle, ['NIP (18 Digit)', 'Nama Lengkap (beserta gelar)', 'Jabatan', 'Unit Kerja', 'Golongan / Pangkat', 'Email (Opsional)', 'No WhatsApp (Opsional)']);
    fputcsv($handle, [$nip, 'ASN Diperbarui', '', '', '', '', '']);
    fclose($handle);

    $import = new PegawaiImport;
    Excel::import($import, $csvPath);

    $pegawai->refresh();
    $riwayatJabatan->refresh();
    $riwayatPangkat->refresh();

    expect($import->errors)->toBeEmpty()
        ->and($pegawai->nama_lengkap)->toBe('ASN Diperbarui')
        ->and($pegawai->ref_unit_kerja_id)->toBe($unitKerja->id)
        ->and($pegawai->ref_jabatan_id)->toBe($jabatan->id)
        ->and($pegawai->ref_pangkat_id)->toBe($pangkat->id)
        ->and($riwayatJabatan->ref_unit_kerja_id)->toBe($unitKerja->id)
        ->and($riwayatJabatan->ref_jabatan_id)->toBe($jabatan->id)
        ->and($riwayatPangkat->ref_pangkat_id)->toBe($pangkat->id);

    if (file_exists($csvPath)) {
        unlink($csvPath);
    }
});
