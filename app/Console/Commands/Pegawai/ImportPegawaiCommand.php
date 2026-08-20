<?php

namespace App\Console\Commands\Pegawai;

use App\Imports\PegawaiImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportPegawaiCommand extends Command
{
    protected $signature = 'pegawai:import {file : Jalur file Excel (.xlsx / .csv) yang akan diimport}';

    protected $description = 'Import data Pegawai dari file Excel template';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");

            return self::FAILURE;
        }

        $this->info("Memulai import data pegawai dari: {$filePath}...");

        $import = new PegawaiImport;
        Excel::import($import, $filePath);

        $this->newLine();
        $this->info('✅ Proses import selesai.');
        $this->table(
            ['Status', 'Jumlah'],
            [
                ['Data Baru (Imported)', "<fg=green>{$import->importedCount}</>"],
                ['Data Diperbarui (Updated)', "<fg=yellow>{$import->updatedCount}</>"],
                ['Data Dilewati (Skipped)', "<fg=gray>{$import->skippedCount}</>"],
                ['Error / Gagal', count($import->errors) > 0 ? '<fg=red>'.count($import->errors).'</>' : '<fg=green>0</>'],
            ]
        );

        if (! empty($import->errors)) {
            $this->newLine();
            $this->warn('Detail Baris Error:');
            foreach ($import->errors as $err) {
                $this->line("  • Baris {$err['row']} (NIP: {$err['nip']}): {$err['error']}");
            }
        }

        return self::SUCCESS;
    }
}
