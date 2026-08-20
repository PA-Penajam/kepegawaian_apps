<?php

namespace App\Console\Commands\Pegawai;

use App\Exports\PegawaiTemplateExport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class GeneratePegawaiTemplateCommand extends Command
{
    protected $signature = 'pegawai:make-template {path? : Jalur penyimpanan file Excel (default: template_import_pegawai.xlsx)}';

    protected $description = 'Generate file template Excel untuk import data awal Pegawai';

    public function handle(): int
    {
        $path = $this->argument('path') ?? base_path('template_import_pegawai.xlsx');

        $this->info("Menghasilkan template Excel ke: {$path}...");

        $content = Excel::raw(new PegawaiTemplateExport, ExcelFormat::XLSX);
        file_put_contents($path, $content);

        $this->info('✅ Template Excel berhasil dibuat.');
        $this->line("File siap diisi di: <fg=green>{$path}</>");

        return self::SUCCESS;
    }
}
