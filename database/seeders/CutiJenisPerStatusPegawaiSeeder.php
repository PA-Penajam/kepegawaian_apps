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
