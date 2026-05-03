<?php

namespace Database\Seeders;

use App\Models\Cuti\CutiJenisPerStatusPegawai;
use Illuminate\Database\Seeder;

class CutiJenisPerStatusPegawaiSeeder extends Seeder
{
    public function run(): void
    {
        $mapping = [
            ['jenis_cuti_kode' => 'CT', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => 12, 'catatan' => null],
            ['jenis_cuti_kode' => 'CT', 'status_kepegawaian' => 'PPPK', 'boleh' => true, 'hak_per_tahun' => 12, 'catatan' => null],
            ['jenis_cuti_kode' => 'CS_TIER1', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => null],
            ['jenis_cuti_kode' => 'CS_TIER1', 'status_kepegawaian' => 'PPPK', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => null],
            ['jenis_cuti_kode' => 'CS_TIER2', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Wajib melampirkan surat keterangan dokter'],
            ['jenis_cuti_kode' => 'CS_TIER2', 'status_kepegawaian' => 'PPPK', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Wajib melampirkan surat keterangan dokter'],
            ['jenis_cuti_kode' => 'CAP', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => null],
            ['jenis_cuti_kode' => 'CAP', 'status_kepegawaian' => 'PPPK', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => null],
            ['jenis_cuti_kode' => 'CB', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Minimal masa kerja 5 tahun terus-menerus'],
            ['jenis_cuti_kode' => 'CB', 'status_kepegawaian' => 'PPPK', 'boleh' => false, 'hak_per_tahun' => null, 'catatan' => 'PPPK tidak berhak cuti besar sesuai PP 11/2017'],
            ['jenis_cuti_kode' => 'CM', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Khusus PNS wanita, untuk anak 1-3'],
            ['jenis_cuti_kode' => 'CM', 'status_kepegawaian' => 'PPPK', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Khusus PPPK wanita, untuk anak 1-3'],
            ['jenis_cuti_kode' => 'CLTN', 'status_kepegawaian' => 'PNS', 'boleh' => true, 'hak_per_tahun' => null, 'catatan' => 'Minimal masa kerja 5 tahun, tanpa gaji'],
            ['jenis_cuti_kode' => 'CLTN', 'status_kepegawaian' => 'PPPK', 'boleh' => false, 'hak_per_tahun' => null, 'catatan' => 'PPPK tidak berhak CLTN sesuai PP 11/2017'],
        ];

        foreach ($mapping as $data) {
            CutiJenisPerStatusPegawai::firstOrCreate(
                [
                    'jenis_cuti_kode' => $data['jenis_cuti_kode'],
                    'status_kepegawaian' => $data['status_kepegawaian'],
                ],
                $data,
            );
        }
    }
}
